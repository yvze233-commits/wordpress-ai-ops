<?php

namespace Tests\Feature;

use App\Jobs\GenerateContentItemJob;
use App\Jobs\PublishWordPressDraftJob;
use App\Models\ContentItem;
use App\Models\TopicCandidate;
use App\Models\WordPressConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AdminReviewWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_queue_can_filter_all_operational_states_and_retry_failed_item(): void
    {
        Bus::fake();
        $manual = $this->item('needs_manual_review', 'manual-item');
        $failed = $this->item('retryable_failed', 'failed-item');
        $this->get('/admin/reviews?state=retryable_failed')->assertOk()->assertSee($failed->title)->assertDontSee($manual->title);
        $this->post("/admin/reviews/{$failed->id}/retry")->assertRedirect("/admin/reviews/{$failed->id}");
        Bus::assertDispatched(GenerateContentItemJob::class, fn (GenerateContentItemJob $job): bool => $job->contentItemId === $failed->id);
    }

    public function test_approved_item_can_queue_wordpress_draft_from_html_form(): void
    {
        Bus::fake();
        $item = $this->item('approved', 'approved-item');
        $connection = WordPressConnection::create(['name' => '站点', 'base_url' => 'https://example.test', 'username' => 'ops', 'application_password_encrypted' => 'secret']);
        $this->post("/admin/reviews/{$item->id}/write-draft", ['connection_id' => $connection->id])->assertRedirect("/admin/reviews/{$item->id}");
        Bus::assertDispatched(PublishWordPressDraftJob::class, fn (PublishWordPressDraftJob $job): bool => $job->contentItemId === $item->id && $job->connectionId === $connection->id);
    }

    private function item(string $state, string $key): ContentItem
    {
        $candidate = TopicCandidate::create(['source_type' => 'manual', 'source_key' => $key, 'title' => $key, 'normalized_title' => $key]);
        return ContentItem::create(['topic_candidate_id' => $candidate->id, 'title' => $key, 'state' => $state, 'idempotency_key' => $key, 'content_html' => '<p>正文</p>', 'review_result' => ['passed' => false, 'score' => 60], 'evidence_snapshot' => ['evidence' => []]]);
    }
}
