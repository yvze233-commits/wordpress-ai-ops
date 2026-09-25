<?php

namespace App\Jobs;

use App\Domain\Topics\TopicSelectionService;
use App\Models\ContentBatch;
use App\Models\ContentTask;
use App\Domain\Skills\SkillCatalog;
use App\Jobs\GenerateTopicTitleCandidatesJob;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Queue\SerializesModels;

class CreateDailyBatchJob implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly string $runDate, public readonly ?int $targetCount = null, public readonly ?int $taskId = null) {}

    public function handle(?TopicSelectionService $selection = null): ContentBatch
    {
        $selection ??= app(TopicSelectionService::class);
        $runDate = Carbon::parse($this->runDate)->toDateString();
        $task = $this->taskId ? ContentTask::query()->findOrFail($this->taskId) : null;
        if ($task !== null && (! $task->enabled || $task->status !== 'running')) {
            return ContentBatch::query()->firstOrCreate(['run_date' => $runDate, 'content_task_id' => $task->id], ['target_count' => $task->daily_target, 'status' => 'paused']);
        }
        $batch = ContentBatch::query()->whereDate('run_date', $runDate)->when($task, fn ($query) => $query->where('content_task_id', $task->id))->first();
        if ($batch === null) {
            try {
                $batch = ContentBatch::query()->create([
                    'run_date' => $runDate,
                    'content_task_id' => $task?->id,
                    'target_count' => $this->targetCount ?? $task?->daily_target ?? (int) config('content-ops.topic_daily_target', 4),
                    'status' => 'planned',
                ]);
            } catch (QueryException $exception) {
                if (! str_contains(strtolower($exception->getMessage()), 'unique')) {
                    throw $exception;
                }

                $batch = ContentBatch::query()->whereDate('run_date', $runDate)->when($task, fn ($query) => $query->where('content_task_id', $task->id))->firstOrFail();
            }
        }
        $existing = $batch->dailySelections()->count();
        $target = (int) $batch->target_count;

        if ($existing < $target) {
            $selectedTopics = $selection->selectForBatch($batch, $target - $existing);
            if ($task !== null) {
                foreach ($selectedTopics as $topic) {
                    if (in_array($topic->source_type, ['rss', 'atom', 'json', 'json_api', 'api', 'html'], true) && filled($topic->source_url)) {
                        GenerateTopicTitleCandidatesJob::dispatch($topic->id, $task->id);
                    }
                }
            }
        }

        if ($task !== null) {
            $snapshots = app(SkillCatalog::class)->snapshotsForConfiguration($task->writing_skill_slug, $task->review_skill_slug);
            $requirements = [
                'require_images' => (bool) ($task->settings['require_images'] ?? false),
                'require_source_links' => (bool) ($task->settings['require_source_links'] ?? false),
            ];
            $batch->contentItems()->get()->each(function ($item) use ($snapshots, $task, $requirements): void {
                $item->forceFill([
                    'writing_skill_snapshot' => $snapshots['writing'],
                    'review_skill_snapshot' => [...$snapshots['review'], 'pass_threshold' => $task->review_pass_threshold, 'requirements' => $requirements],
                ])->save();
            });
        }

        $selected = $batch->dailySelections()->count();
        $batch->forceFill([
            'completed_count' => $selected,
            'status' => $selected >= $target ? 'completed' : 'shortfall',
        ])->save();

        return $batch->fresh();
    }
}
