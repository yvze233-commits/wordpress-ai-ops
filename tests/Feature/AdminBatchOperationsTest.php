<?php

namespace Tests\Feature;

use App\Jobs\CreateDailyBatchJob;
use App\Jobs\GenerateContentItemJob;
use App\Models\ContentBatch;
use App\Models\ContentItem;
use App\Models\TopicCandidate;
use App\Models\TopicTitleCandidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AdminBatchOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_create_a_batch_and_queue_locked_items(): void
    {
        Bus::fake();
        $this->post('/admin/batches', ['run_date' => '2026-09-25', 'target_count' => 2])->assertRedirect('/admin/batches');
        Bus::assertDispatched(CreateDailyBatchJob::class);

        $batch = ContentBatch::create(['run_date' => '2026-09-26', 'target_count' => 1, 'status' => 'planned']);
        $candidate = TopicCandidate::create(['source_type' => 'manual', 'source_key' => 'one', 'title' => '测试选题', 'normalized_title' => '测试选题']);
        $item = ContentItem::create(['content_batch_id' => $batch->id, 'topic_candidate_id' => $candidate->id, 'title' => '测试选题', 'state' => 'locked', 'idempotency_key' => 'test-item']);

        $this->post("/admin/batches/{$batch->id}/run")->assertRedirect("/admin/batches/{$batch->id}");
        Bus::assertDispatched(GenerateContentItemJob::class, fn (GenerateContentItemJob $job): bool => $job->contentItemId === $item->id);
    }

    public function test_retry_only_queues_retryable_items(): void
    {
        Bus::fake();
        $batch = ContentBatch::create(['run_date' => '2026-09-27', 'target_count' => 1, 'status' => 'shortfall']);
        $candidate = TopicCandidate::create(['source_type' => 'manual', 'source_key' => 'two', 'title' => '失败选题', 'normalized_title' => '失败选题']);
        $item = ContentItem::create(['content_batch_id' => $batch->id, 'topic_candidate_id' => $candidate->id, 'title' => '失败选题', 'state' => 'retryable_failed', 'idempotency_key' => 'failed-item']);

        $this->post("/admin/batches/{$batch->id}/retry")->assertRedirect("/admin/batches/{$batch->id}");
        Bus::assertDispatched(GenerateContentItemJob::class, fn (GenerateContentItemJob $job): bool => $job->contentItemId === $item->id);
    }

    public function test_operator_can_select_a_title_candidate_for_the_matching_batch_item(): void
    {
        $batch = ContentBatch::create(['run_date' => '2026-09-28', 'target_count' => 1, 'status' => 'planned']);
        $candidate = TopicCandidate::create(['source_type' => 'rss', 'source_key' => 'three', 'title' => '热点原题', 'normalized_title' => '热点原题']);
        $item = ContentItem::create(['content_batch_id' => $batch->id, 'topic_candidate_id' => $candidate->id, 'title' => '热点原题', 'state' => 'locked', 'idempotency_key' => 'title-item']);
        $title = TopicTitleCandidate::create(['topic_candidate_id' => $candidate->id, 'title' => '热点原题：影响解读', 'normalized_title' => '热点原题:影响解读', 'score' => 92]);

        $this->post("/admin/batches/{$batch->id}/title-candidates/{$title->id}")->assertRedirect("/admin/batches/{$batch->id}");

        $this->assertSame('selected', $title->fresh()->status);
        $this->assertSame('热点原题：影响解读', $item->fresh()->title);
    }

    public function test_operator_can_open_batch_details_when_title_candidates_exist(): void
    {
        $batch = ContentBatch::create(['run_date' => '2026-09-29', 'target_count' => 1, 'status' => 'completed']);
        $candidate = TopicCandidate::create(['source_type' => 'rss', 'source_key' => 'details', 'title' => '详情页热点', 'normalized_title' => '详情页热点']);
        ContentItem::create(['content_batch_id' => $batch->id, 'topic_candidate_id' => $candidate->id, 'title' => '详情页热点', 'state' => 'locked', 'idempotency_key' => 'details-item']);
        TopicTitleCandidate::create(['topic_candidate_id' => $candidate->id, 'title' => '详情页热点：解读', 'normalized_title' => '详情页热点:解读', 'score' => 88]);

        $this->get("/admin/batches/{$batch->id}")
            ->assertOk()
            ->assertSee('详情页热点：解读');
    }
}
