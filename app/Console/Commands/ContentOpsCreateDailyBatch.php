<?php

namespace App\Console\Commands;

use App\Jobs\CollectTopicSourceJob;
use App\Jobs\CreateDailyBatchJob;
use App\Jobs\GenerateContentItemJob;
use App\Jobs\AggregateHotTopicsJob;
use App\Models\TopicSource;
use App\Models\ContentTask;
use Illuminate\Console\Command;

class ContentOpsCreateDailyBatch extends Command
{
    protected $signature = 'content-ops:create-daily-batch {date? : YYYY-MM-DD, defaults to today}';

    protected $description = 'Collect enabled sources, create the daily batch, and queue generation jobs.';

    public function handle(): int
    {
        foreach (TopicSource::query()->where('enabled', true)->where('status', '!=', 'paused')->pluck('id') as $sourceId) {
            CollectTopicSourceJob::dispatch((int) $sourceId);
        }

        $date = $this->argument('date') ?: now()->toDateString();
        $tasks = ContentTask::query()->where('enabled', true)->where('status', 'running')->get();
        if ($tasks->isEmpty()) {
            $tasks = collect([null]);
        }
        foreach ($tasks as $task) {
            if ($task !== null && $this->argument('date') === null && $task->schedule_time !== now()->format('H:i')) {
                continue;
            }
            if ($task !== null && ($task->settings['topic_source_mode'] ?? 'both') !== 'titles') {
                try {
                    AggregateHotTopicsJob::dispatchSync($task->id);
                } catch (\Throwable $exception) {
                    $this->warn("Hot topic aggregation failed for task {$task->id}: {$exception->getMessage()}");
                }
            }
            $batch = (new CreateDailyBatchJob($date, null, $task?->id))->handle();
            $items = $batch->contentItems()->where('state', 'locked')->whereDoesntHave('auditEvents', fn ($query) => $query->where('event_type', 'generation_queued'))->get();
            foreach ($items as $item) {
                $item->auditEvents()->create(['event_type' => 'generation_queued', 'payload' => ['queued_at' => now()->toIso8601String()]]);
                GenerateContentItemJob::dispatch((int) $item->id);
            }
            $this->info("Batch {$batch->run_date->toDateString()} queued with {$batch->contentItems()->count()} item(s).");
        }

        return self::SUCCESS;
    }
}
