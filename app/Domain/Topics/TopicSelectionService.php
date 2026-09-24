<?php

namespace App\Domain\Topics;

use App\Domain\Content\IdempotencyKey;
use App\Models\ContentBatch;
use App\Models\ContentItem;
use App\Models\DailySelection;
use App\Models\TitleLibraryEntry;
use App\Models\TopicCandidate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class TopicSelectionService
{
    public function __construct(
        private readonly DuplicateDetector $duplicates = new DuplicateDetector,
        private readonly TopicLockService $locks = new TopicLockService,
    ) {}

    public function selectForBatch(ContentBatch $batch, int $limit): Collection
    {
        if ($limit <= 0) {
            return collect();
        }

        $this->syncTitleLibraryCandidates();
        $existingIds = DailySelection::query()
            ->where('content_batch_id', $batch->id)
            ->pluck('topic_candidate_id');
        $selected = collect();

        $candidates = TopicCandidate::query()
            ->where(function ($query): void {
                $query->where('status', 'candidate')
                    ->orWhere(function ($query): void {
                        $query->where('status', 'locked')->where('locked_until', '<', now());
                    });
            })
            ->whereNotIn('id', $existingIds)
            ->get()
            ->map(fn (TopicCandidate $candidate): array => [
                'candidate' => $candidate,
                'score' => ((int) $candidate->priority / 100) + (random_int(0, 1_000_000) / 1_000_000),
            ])
            ->sortByDesc('score')
            ->values();

        foreach ($candidates as $entry) {
            if ($selected->count() >= $limit) {
                break;
            }

            /** @var TopicCandidate $candidate */
            $candidate = $entry['candidate'];
            if ($this->duplicates->isDuplicate($candidate, $selected)) {
                continue;
            }

            $locked = $this->locks->acquire($candidate);
            if ($locked === null) {
                continue;
            }

            try {
                $this->persistSelection($batch, $locked, (float) $entry['score']);
                $selected->push($locked);
            } catch (\Throwable $exception) {
                $this->locks->release($locked);
                report($exception);
            }
        }

        return $selected;
    }

    public function select(ContentBatch $batch, int $limit): Collection
    {
        return $this->selectForBatch($batch, $limit);
    }

    private function persistSelection(ContentBatch $batch, TopicCandidate $candidate, float $score): void
    {
        DB::transaction(function () use ($batch, $candidate, $score): void {
            $titleEntry = $this->titleEntry($candidate);
            $contentItem = ContentItem::query()->firstOrCreate(
                ['idempotency_key' => IdempotencyKey::forTopic($candidate->id, 'batch:'.$batch->id, 0)],
                [
                    'content_batch_id' => $batch->id,
                    'topic_candidate_id' => $candidate->id,
                    'title' => $candidate->title,
                    'state' => 'locked',
                ],
            );

            DailySelection::query()->firstOrCreate(
                ['content_batch_id' => $batch->id, 'topic_candidate_id' => $candidate->id],
                [
                    'title_library_entry_id' => $titleEntry?->id,
                    'source' => $candidate->source_type,
                    'selection_score' => $score,
                    'locked_until' => $candidate->locked_until,
                    'result' => 'locked',
                ],
            );

            if ($titleEntry !== null) {
                $titleEntry->forceFill([
                    'content_item_id' => $contentItem->id,
                    'use_count' => $titleEntry->use_count + 1,
                    'last_used_at' => now(),
                ])->save();
            }
        });
    }

    private function syncTitleLibraryCandidates(): void
    {
        TitleLibraryEntry::query()
            ->where('enabled', true)
            ->get()
            ->each(function (TitleLibraryEntry $entry): void {
                TopicCandidate::query()->firstOrCreate(
                    ['source_type' => 'title_library', 'source_key' => 'title_library:'.$entry->id],
                    [
                        'title' => $entry->raw_title,
                        'normalized_title' => $entry->normalized_title,
                        'priority' => $entry->priority,
                        'status' => 'candidate',
                    ],
                );
            });
    }

    private function titleEntry(TopicCandidate $candidate): ?TitleLibraryEntry
    {
        if ($candidate->source_type !== 'title_library' || ! str_starts_with($candidate->source_key, 'title_library:')) {
            return null;
        }

        return TitleLibraryEntry::query()->find((int) substr($candidate->source_key, strlen('title_library:')));
    }
}
