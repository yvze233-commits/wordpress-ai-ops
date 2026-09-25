<?php

namespace Tests\Feature;

use App\Domain\Topics\TopicSourceException;
use App\Jobs\CollectTopicSourceJob;
use App\Models\TopicSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TopicCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_rss_items_are_upserted_as_candidates_and_raw_feed_items(): void
    {
        Http::fake([
            'https://example.test/feed.xml' => Http::response(<<<'XML'
                <?xml version="1.0"?>
                <rss version="2.0"><channel><item>
                    <guid>event-1</guid>
                    <title>最新教育政策</title>
                    <description>政策摘要</description>
                    <link>https://example.test/news/1/?utm_source=rss</link>
                    <pubDate>Wed, 24 Sep 2026 08:00:00 GMT</pubDate>
                </item></channel></rss>
                XML, 200),
        ]);

        $source = TopicSource::create([
            'name' => '示例 RSS',
            'type' => 'rss',
            'url' => 'https://example.test/feed.xml',
            'parser_config' => [],
            'trust_score' => 90,
            'enabled' => true,
        ]);

        $count = (new CollectTopicSourceJob($source->id))->handle();

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('topic_candidates', [
            'source_type' => 'rss',
            'source_key' => 'event-1',
            'normalized_title' => '最新教育政策',
            'source_url' => 'https://example.test/news/1',
        ]);
        $this->assertDatabaseHas('topic_feeds', [
            'topic_source_id' => $source->id,
            'source_key' => 'event-1',
            'normalized_url' => 'https://example.test/news/1',
        ]);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'topic_source_collected']);
    }

    public function test_source_preview_returns_first_five_parsed_items_without_writing_candidates(): void
    {
        Http::fake(['https://example.test/feed.xml' => Http::response(<<<'XML'
            <rss><channel><item><guid>preview-1</guid><title>热点一</title><link>https://example.test/1</link></item></channel></rss>
            XML, 200)]);
        $source = TopicSource::create(['name' => '预览源', 'type' => 'rss', 'url' => 'https://example.test/feed.xml']);

        $this->postJson("/admin/sources/{$source->id}/preview")
            ->assertOk()
            ->assertJsonPath('data.0.title', '热点一');
        $this->assertDatabaseCount('topic_candidates', 0);
        $this->assertDatabaseCount('topic_feeds', 0);
    }

    public function test_disabled_sources_exit_without_an_http_request(): void
    {
        Http::fake();
        $source = TopicSource::create([
            'name' => '停用来源',
            'type' => 'rss',
            'url' => 'https://example.test/disabled.xml',
            'enabled' => false,
        ]);

        $this->assertSame(0, (new CollectTopicSourceJob($source->id))->handle());
        Http::assertNothingSent();
        $this->assertDatabaseCount('topic_candidates', 0);
    }

    public function test_unauthorized_sources_are_paused_without_creating_candidates(): void
    {
        Http::fake(['https://example.test/private.xml' => Http::response('unauthorized', 401)]);
        $source = TopicSource::create([
            'name' => '需鉴权来源',
            'type' => 'rss',
            'url' => 'https://example.test/private.xml',
            'enabled' => true,
        ]);

        $this->assertSame(0, (new CollectTopicSourceJob($source->id))->handle());
        $this->assertSame('paused', $source->fresh()->status);
        $this->assertDatabaseCount('topic_candidates', 0);
    }

    public function test_malformed_feed_is_recorded_without_creating_candidates(): void
    {
        Http::fake(['https://example.test/broken.json' => Http::response('{broken', 200)]);
        $source = TopicSource::create([
            'name' => '损坏来源',
            'type' => 'json',
            'url' => 'https://example.test/broken.json',
            'enabled' => true,
        ]);

        $this->assertSame(0, (new CollectTopicSourceJob($source->id))->handle());
        $this->assertNotNull($source->fresh()->last_error);
        $this->assertDatabaseCount('topic_candidates', 0);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'topic_source_collection_failed']);
    }

    public function test_rate_limited_sources_remain_retryable(): void
    {
        Http::fake(['https://example.test/limited.xml' => Http::response('too many requests', 429)]);
        $source = TopicSource::create([
            'name' => '限流来源',
            'type' => 'rss',
            'url' => 'https://example.test/limited.xml',
            'enabled' => true,
        ]);

        $this->expectException(TopicSourceException::class);
        (new CollectTopicSourceJob($source->id))->handle();
    }

    public function test_json_sources_use_configured_paths(): void
    {
        Http::fake([
            'https://example.test/api/topics' => Http::response([
                'data' => [[
                    'uid' => 'json-1',
                    'headline' => 'JSON 热点',
                    'abstract' => '摘要',
                    'canonical' => 'https://example.test/json/1',
                    'time' => '2026-09-24T09:00:00+08:00',
                ]],
            ], 200),
        ]);
        $source = TopicSource::create([
            'name' => 'JSON 来源',
            'type' => 'json',
            'url' => 'https://example.test/api/topics',
            'parser_config' => [
                'items_path' => 'data',
                'source_key_path' => 'uid',
                'title_path' => 'headline',
                'summary_path' => 'abstract',
                'url_path' => 'canonical',
                'published_at_path' => 'time',
            ],
        ]);

        $this->assertSame(1, (new CollectTopicSourceJob($source->id))->handle());
        $this->assertDatabaseHas('topic_candidates', ['source_key' => 'json-1', 'source_type' => 'json']);
    }

    public function test_html_sources_use_allowlisted_selectors(): void
    {
        Http::fake([
            'https://example.test/topics' => Http::response(<<<'HTML'
                <html><body><article class="story">
                    <h2>HTML 热点</h2><a class="link" href="https://example.test/html/1">查看</a>
                    <p class="summary">HTML 摘要</p>
                </article></body></html>
                HTML, 200),
        ]);
        $source = TopicSource::create([
            'name' => 'HTML 来源',
            'type' => 'html',
            'url' => 'https://example.test/topics',
            'parser_config' => [
                'item_selector' => 'article.story',
                'title_selector' => 'h2',
                'url_selector' => 'a.link',
                'summary_selector' => 'p.summary',
            ],
        ]);

        $this->assertSame(1, (new CollectTopicSourceJob($source->id))->handle());
        $this->assertDatabaseHas('topic_candidates', [
            'source_key' => 'https://example.test/html/1',
            'source_type' => 'html',
        ]);
    }

    public function test_connection_timeouts_are_retryable(): void
    {
        Http::fake(['https://example.test/timeout.xml' => Http::failedConnection()]);
        $source = TopicSource::create([
            'name' => '超时来源',
            'type' => 'rss',
            'url' => 'https://example.test/timeout.xml',
        ]);

        try {
            (new CollectTopicSourceJob($source->id))->handle();
            $this->fail('Expected a retryable topic source exception.');
        } catch (TopicSourceException $exception) {
            $this->assertTrue($exception->retryable);
        }
    }
}
