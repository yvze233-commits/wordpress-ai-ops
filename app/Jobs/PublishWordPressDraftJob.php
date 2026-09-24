<?php

namespace App\Jobs;

use App\Domain\Content\ContentState;
use App\Domain\Content\ContentStateTransition;
use App\Infrastructure\WordPress\WordPressDraftPublisher;
use App\Models\ContentItem;
use App\Models\WordPressConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class PublishWordPressDraftJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $contentItemId, public readonly int $connectionId) {}

    public function handle(?WordPressDraftPublisher $publisher = null): void
    {
        $publisher ??= app(WordPressDraftPublisher::class);
        $item = ContentItem::query()->find($this->contentItemId);
        $connection = WordPressConnection::query()->find($this->connectionId);
        $lastRun = $item?->runs()->where('stage', 'wordpress_publish')->latest('id')->first();
        $retryingPublish = $item?->state === ContentState::RETRYABLE_FAILED && $lastRun !== null;
        if ($item === null || $connection === null || (! in_array($item->state, [ContentState::APPROVED, ContentState::WP_DRAFT_WRITTEN], true) && ! $retryingPublish)) {
            return;
        }
        if ($retryingPublish) {
            ContentStateTransition::assertAllowed((string) $item->state, ContentState::APPROVED);
            $item->forceFill(['state' => ContentState::APPROVED])->save();
        }
        $attempt = ((int) $item->runs()->where('stage', 'wordpress_publish')->max('attempt')) + 1;
        $run = $item->runs()->create(['stage' => 'wordpress_publish', 'attempt' => max(1, $attempt), 'status' => 'running', 'payload' => []]);
        $remoteStatus = in_array($item->publish_status, ['draft', 'pending', 'publish'], true) ? $item->publish_status : 'draft';
        try {
            $post = $publisher->publish($item->fresh(), $connection, $remoteStatus);
            $run->forceFill(['status' => 'succeeded', 'payload' => ['post_id' => $post['id'] ?? null, 'status' => $remoteStatus]])->save();
        } catch (Throwable $exception) {
            $run->forceFill(['status' => 'failed', 'error_message' => $exception->getMessage(), 'payload' => ['exception' => $exception::class]])->save();
            $fresh = $item->fresh();
            if (ContentStateTransition::canTransition((string) $fresh->state, ContentState::RETRYABLE_FAILED)) {
                $fresh->forceFill(['state' => ContentState::RETRYABLE_FAILED])->save();
            }
        }
    }
}
