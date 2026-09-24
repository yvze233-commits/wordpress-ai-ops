<?php

namespace Tests\Feature;

use App\Jobs\PublishWordPressDraftJob;
use App\Models\ContentItem;
use App\Models\TopicCandidate;
use App\Models\WordPressConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminOperationsPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_and_library_endpoints_return_operational_data(): void
    {
        $this->getJson('/admin')->assertOk()->assertJsonPath('data.daily_target', 4);
        $this->getJson('/admin/sources')->assertOk()->assertJsonStructure(['data']);
        $this->getJson('/admin/titles')->assertOk()->assertJsonStructure(['data']);
        $this->getJson('/admin/knowledge')->assertOk()->assertJsonStructure(['data']);
        $this->getJson('/admin/images')->assertOk()->assertJsonStructure(['data']);
        $this->getJson('/admin/wordpress')->assertOk()->assertJsonStructure(['data']);
    }

    public function test_only_approved_content_can_queue_wordpress_draft_writing(): void
    {
        Queue::fake();
        $candidate = TopicCandidate::create(['source_type' => 'rss', 'source_key' => 'admin-test', 'title' => '审核文章', 'normalized_title' => '审核文章']);
        $item = ContentItem::create(['topic_candidate_id' => $candidate->id, 'title' => '审核文章', 'state' => 'awaiting_review', 'idempotency_key' => 'admin-item']);
        $connection = WordPressConnection::create(['name' => 'WP', 'base_url' => 'https://wp.example.test', 'username' => 'admin', 'application_password_encrypted' => 'secret']);

        $this->postJson("/admin/reviews/{$item->id}/write-draft", ['connection_id' => $connection->id])->assertConflict();
        $item->update(['state' => 'approved']);
        $this->postJson("/admin/reviews/{$item->id}/write-draft", ['connection_id' => $connection->id])->assertAccepted();
        Queue::assertPushed(PublishWordPressDraftJob::class, 1);
    }
}
