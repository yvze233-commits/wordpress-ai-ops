<?php

namespace App\Jobs;

use App\Domain\Content\ArticleGenerationService;
use App\Domain\Content\ContentState;
use App\Domain\Content\ContentStateTransition;
use App\Models\ContentItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateContentItemJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $contentItemId) {}

    public function handle(?ArticleGenerationService $generation = null): void
    {
        $generation ??= app(ArticleGenerationService::class);
        $item = ContentItem::query()->find($this->contentItemId);
        if ($item === null || ! in_array($item->state, [ContentState::LOCKED, ContentState::RETRYABLE_FAILED], true)) {
            return;
        }

        $attempt = ((int) $item->runs()->where('stage', 'generation')->max('attempt')) + 1;
        $run = $item->runs()->create(['stage' => 'generation', 'attempt' => max(1, $attempt), 'status' => 'running', 'payload' => []]);
        $this->transition($item, ContentState::GENERATING);
        $item->auditEvents()->create(['event_type' => 'content_generation_started', 'payload' => ['run_id' => $run->id, 'attempt' => $attempt]]);

        try {
            $generated = $generation->generate($item->fresh(['topicCandidate']));
            $run->forceFill(['status' => 'succeeded', 'payload' => ['state' => ContentState::AWAITING_REVIEW]])->save();
            $this->transition($generated, ContentState::AWAITING_REVIEW);
            $generated->auditEvents()->create(['event_type' => 'content_generated', 'payload' => ['run_id' => $run->id, 'prompt_hash' => $generated->generation_meta['prompt_hash'] ?? null]]);
        } catch (Throwable $exception) {
            $run->forceFill(['status' => 'failed', 'error_message' => $exception->getMessage(), 'payload' => ['exception' => $exception::class]])->save();
            $this->transition($item->fresh(), ContentState::RETRYABLE_FAILED);
            $item->fresh()->auditEvents()->create(['event_type' => 'content_generation_failed', 'payload' => ['run_id' => $run->id, 'exception' => $exception::class]]);
        }
    }

    private function transition(ContentItem $item, string $to): void
    {
        $from = (string) $item->state;
        if ($from === $to) {
            return;
        }
        ContentStateTransition::assertAllowed($from, $to);
        $item->forceFill(['state' => $to])->save();
    }
}
