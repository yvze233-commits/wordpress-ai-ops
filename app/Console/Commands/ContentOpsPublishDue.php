<?php

namespace App\Console\Commands;

use App\Domain\Content\ContentState;
use App\Jobs\PublishWordPressDraftJob;
use App\Models\ContentItem;
use App\Models\WordPressConnection;
use Illuminate\Console\Command;

class ContentOpsPublishDue extends Command
{
    protected $signature = 'content-ops:publish-due';

    protected $description = 'Dispatch due scheduled publishes (draft or publish per strategy) for approved content.';

    public function handle(): int
    {
        $connection = WordPressConnection::query()->oldest('id')->first();
        if ($connection === null) {
            $this->warn('No WordPress connection configured; nothing to publish.');

            return self::SUCCESS;
        }

        $due = ContentItem::query()
            ->where('state', ContentState::APPROVED)
            ->whereIn('publish_status', ['draft', 'pending', 'publish'])
            ->whereNotNull('scheduled_publish_at')
            ->where('scheduled_publish_at', '<=', now())
            ->get();

        foreach ($due as $item) {
            PublishWordPressDraftJob::dispatch((int) $item->id, (int) $connection->id);
            $this->info("Publishing queued for item #{$item->id} [{$item->title}] as {$item->publish_status}.");
        }

        if ($due->isEmpty()) {
            $this->info('No due publishes.');
        }

        return self::SUCCESS;
    }
}
