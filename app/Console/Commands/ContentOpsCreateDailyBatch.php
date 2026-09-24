<?php

namespace App\Console\Commands;

use App\Jobs\CollectTopicSourceJob;
use App\Jobs\CreateDailyBatchJob;
use App\Jobs\GenerateContentItemJob;
use App\Models\TopicSource;
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

        $batch = (new CreateDailyBatchJob($this->argument('date') ?: now()->toDateString()))->handle();
        $items = $batch->contentItems()
            ->where('state', 'locked')
            ->whereDoesntHave('auditEvents', fn ($query) => $query->where('event_type', 'generation_queued'))
            ->get();
        foreach ($items as $item) {
            $item->auditEvents()->create(['event_type' => 'generation_queued', 'payload' => ['queued_at' => now()->toIso8601String()]]);
            GenerateContentItemJob::dispatch((int) $item->id);
        }

        $this->info("Batch {$batch->run_date->toDateString()} queued with {$batch->contentItems()->count()} item(s).");

        return self::SUCCESS;
    }
}
