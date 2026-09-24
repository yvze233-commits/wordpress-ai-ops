<?php

namespace Tests\Feature;

use App\Infrastructure\WordPress\BrowserFallbackPublisher;
use App\Models\ContentItem;
use App\Models\TopicCandidate;
use App\Models\WordPressConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrowserFallbackPublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_browser_fallback_is_explicitly_disabled_by_default(): void
    {
        config(['content-ops.playwright_enabled' => false]);
        $item = $this->item();
        $connection = WordPressConnection::create(['name' => 'WP', 'base_url' => 'https://wp.example.test', 'username' => 'admin', 'application_password_encrypted' => 'secret']);

        $result = (new BrowserFallbackPublisher)->publish($item, $connection);

        $this->assertSame('disabled', $result['status']);
    }

    private function item(): ContentItem
    {
        $candidate = TopicCandidate::create(['source_type' => 'rss', 'source_key' => 'browser-test', 'title' => '测试', 'normalized_title' => '测试']);

        return ContentItem::create(['topic_candidate_id' => $candidate->id, 'title' => '测试', 'state' => 'approved', 'idempotency_key' => 'browser-item']);
    }
}
