<?php

namespace Tests\Feature;

use App\Jobs\PublishWordPressDraftJob;
use App\Models\ContentItem;
use App\Models\TopicCandidate;
use App\Models\WordPressConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WordPressDraftPublishingTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_content_is_written_as_an_idempotent_draft(): void
    {
        $candidate = TopicCandidate::create([
            'source_type' => 'rss', 'source_key' => 'wp-test', 'title' => '标题', 'normalized_title' => '标题', 'status' => 'used',
        ]);
        $item = ContentItem::create([
            'topic_candidate_id' => $candidate->id, 'title' => '标题', 'slug' => 'title', 'excerpt' => '摘要',
            'content_html' => '<p>内容</p>', 'state' => 'approved', 'idempotency_key' => 'item-marker-1',
            'generation_meta' => ['category' => '教育', 'keywords' => ['AI']],
        ]);
        $connection = WordPressConnection::create([
            'name' => 'Test WordPress', 'base_url' => 'https://wp.example.test', 'username' => 'admin',
            'application_password_encrypted' => 'secret-password', 'category_mapping' => ['教育' => 'Education'],
        ]);

        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/categories?per_page=100')) {
                return Http::response([['id' => 4, 'name' => 'Education']]);
            }
            if (str_ends_with($request->url(), '/posts')) {
                return Http::response(['id' => 88, 'link' => 'https://wp.example.test/?p=88']);
            }
            if (str_ends_with($request->url(), '/posts/88')) {
                return Http::response(['id' => 88, 'link' => 'https://wp.example.test/?p=88']);
            }

            return Http::response([]);
        });

        (new PublishWordPressDraftJob($item->id, $connection->id))->handle();
        $this->assertSame('wp_draft_written', $item->fresh()->state);
        $this->assertSame(88, $item->fresh()->wordpress_post_id);

        (new PublishWordPressDraftJob($item->id, $connection->id))->handle();
        $this->assertGreaterThanOrEqual(2, Http::recorded()->filter(fn (array $record): bool => str_contains($record[0]->url(), '/posts'))->count());
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/posts/88') && ($request->data()['status'] ?? null) === 'draft');
    }
}
