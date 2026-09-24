<?php

namespace Tests\Feature;

use App\Jobs\GenerateContentItemJob;
use App\Jobs\ReconcileContentRunJob;
use App\Models\ContentItem;
use App\Models\ContentRun;
use App\Models\TopicCandidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_command_is_idempotent_and_queues_generation_for_locked_items(): void
    {
        Queue::fake();
        foreach (range(1, 4) as $index) {
            TopicCandidate::create(['source_type' => 'rss', 'source_key' => 'schedule-'.$index, 'title' => '选题 '.$index, 'normalized_title' => '选题 '.$index]);
        }
        $this->artisan('content-ops:create-daily-batch', ['date' => '2026-09-24'])->assertSuccessful();
        $this->artisan('content-ops:create-daily-batch', ['date' => '2026-09-24'])->assertSuccessful();

        Queue::assertPushed(GenerateContentItemJob::class, 4);
        $this->assertDatabaseCount('content_batches', 1);
    }

    public function test_reconciliation_releases_stale_runs_to_retryable_state(): void
    {
        $candidate = TopicCandidate::create(['source_type' => 'rss', 'source_key' => 'stale', 'title' => '旧任务', 'normalized_title' => '旧任务']);
        $item = ContentItem::create(['topic_candidate_id' => $candidate->id, 'title' => '旧任务', 'state' => 'generating', 'idempotency_key' => 'stale-item']);
        $run = ContentRun::create(['content_item_id' => $item->id, 'stage' => 'generation', 'attempt' => 1, 'status' => 'running']);
        $run->forceFill(['updated_at' => now()->subHours(2)])->save();
        Queue::fake();

        $this->artisan('content-ops:reconcile-runs', ['--age' => 60])->assertSuccessful();
        Queue::assertPushed(ReconcileContentRunJob::class, 1);
    }
}
