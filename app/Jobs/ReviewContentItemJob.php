<?php

namespace App\Jobs;

use App\Domain\Content\ArticleGenerationService;
use App\Domain\Content\ArticleReviewService;
use App\Domain\Content\ContentState;
use App\Domain\Content\ContentStateTransition;
use App\Models\ContentItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ReviewContentItemJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $contentItemId) {}

    public function handle(?ArticleReviewService $review = null, ?ArticleGenerationService $generation = null): void
    {
        $review ??= app(ArticleReviewService::class);
        $generation ??= app(ArticleGenerationService::class);
        $item = ContentItem::query()->find($this->contentItemId);
        if ($item === null || $item->state !== ContentState::AWAITING_REVIEW) {
            return;
        }

        $attempt = ((int) $item->runs()->where('stage', 'review')->max('attempt')) + 1;
        $run = $item->runs()->create(['stage' => 'review', 'attempt' => max(1, $attempt), 'status' => 'running', 'payload' => []]);
        $item->auditEvents()->create(['event_type' => 'content_review_started', 'payload' => ['run_id' => $run->id, 'attempt' => $attempt]]);

        try {
            $revisionCount = (int) ($item->generation_meta['revision_count'] ?? 0);
            $maxRevisions = max(0, (int) config('content-ops.review_max_revisions', 0));
            $result = [];
            while (true) {
                $result = $review->review($item->fresh());
                $item->forceFill(['review_result' => $result])->save();
                if ($result['passed']) {
                    $run->forceFill(['status' => 'succeeded', 'payload' => ['passed' => true, 'score' => $result['score'], 'revisions' => $revisionCount]])->save();
                    $this->transition($item->fresh(), ContentState::APPROVED);
                    $item->fresh()->auditEvents()->create(['event_type' => 'content_review_passed', 'payload' => ['run_id' => $run->id, 'score' => $result['score'], 'revisions' => $revisionCount]]);
                    if (config('content-ops.publish_mode') === 'auto_publish') {
                        app(\App\Domain\Content\PublishScheduler::class)->schedule($item->fresh(), 'publish');
                    }

                    break;
                }

                $instructions = array_values(array_filter($result['revision_instructions'], 'is_string'));
                if ($instructions === [] || $revisionCount >= $maxRevisions) {
                    $run->forceFill(['status' => 'succeeded', 'payload' => ['passed' => false, 'score' => $result['score'], 'revisions' => $revisionCount]])->save();
                    $this->transition($item->fresh(), ContentState::NEEDS_MANUAL_REVIEW);
                    $item->fresh()->auditEvents()->create(['event_type' => 'content_review_manual', 'payload' => ['run_id' => $run->id, 'score' => $result['score'], 'revisions' => $revisionCount]]);

                    break;
                }

                $revisionCount++;
                $item->forceFill(['generation_meta' => [...($item->generation_meta ?? []), 'revision_count' => $revisionCount]])->save();
                $generation->revise($item->fresh(), $instructions);
            }
        } catch (Throwable $exception) {
            $run->forceFill(['status' => 'failed', 'error_message' => $exception->getMessage(), 'payload' => ['exception' => $exception::class]])->save();
            $this->transition($item->fresh(), ContentState::RETRYABLE_FAILED);
            $item->fresh()->auditEvents()->create(['event_type' => 'content_review_failed', 'payload' => ['run_id' => $run->id, 'exception' => $exception::class]]);
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
