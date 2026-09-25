<?php

namespace App\Domain\Topics;

use App\Domain\Content\ArticlePromptBuilder;
use App\Domain\Content\StructuredAiGateway;
use App\Models\ContentTask;
use App\Models\TopicCandidate;
use App\Models\TopicTitleCandidate;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class TopicTitleCandidateService
{
    public function __construct(
        private readonly StructuredAiGateway $ai,
        private readonly ArticlePromptBuilder $prompts,
    ) {}

    /** @return list<TopicTitleCandidate> */
    public function generate(TopicCandidate $candidate, ?ContentTask $task = null): array
    {
        $keywords = $this->keywords($task?->topic_keywords);
        $result = $this->ai->generate(
            $this->prompts->topicTitles([
                'title' => $candidate->title,
                'summary' => $candidate->summary,
                'source_url' => $candidate->source_url,
                'source_type' => $candidate->source_type,
                'event_fingerprint' => $candidate->event_fingerprint,
            ], $keywords),
            ArticlePromptBuilder::topicTitleSchema(),
        );

        $titles = $result['titles'] ?? null;
        if (! is_array($titles) || count($titles) < 3) {
            throw new InvalidArgumentException('AI title output must contain three candidates.');
        }

        $created = [];
        foreach (array_slice($titles, 0, 3) as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $title = trim((string) ($entry['title'] ?? ''));
            $normalized = (new TopicNormalizer)->title($title);
            if ($title === '' || mb_strlen($title, 'UTF-8') > 80 || $normalized === $candidate->normalized_title) {
                continue;
            }
            $model = TopicTitleCandidate::query()->updateOrCreate(
                ['topic_candidate_id' => $candidate->id, 'normalized_title' => $normalized],
                [
                    'title' => $title,
                    'rationale' => trim((string) ($entry['rationale'] ?? '')) ?: null,
                    'score' => max(0, min(100, (int) ($entry['score'] ?? 0))),
                    'status' => 'available',
                    'source_snapshot' => [
                        'title' => $candidate->title,
                        'summary' => $candidate->summary,
                        'source_url' => $candidate->source_url,
                        'captured_at' => now()->toIso8601String(),
                    ],
                ],
            );
            $created[] = $model;
        }

        if (count($created) === 0) {
            throw new InvalidArgumentException('AI title output did not contain usable candidates.');
        }

        return $created;
    }

    /** @return list<string> */
    private function keywords(?string $value): array
    {
        return array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            preg_split('/[,，\n]+/u', (string) $value) ?: [],
        )));
    }
}
