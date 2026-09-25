<?php

namespace Tests\Unit;

use App\Domain\Topics\HotTopicAggregator;
use App\Models\ContentTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HotTopicAggregatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregator_filters_old_and_low_trust_items_and_deduplicates_urls(): void
    {
        config(['content-ops.hot_topic_sources' => [
            ['name' => '聚合源', 'type' => 'rss', 'url' => 'https://example.test/feed.xml', 'trust_score' => 80],
            ['name' => '低可信源', 'type' => 'rss', 'url' => 'https://example.test/low.xml', 'trust_score' => 20],
        ]]);
        Http::fake([
            'https://example.test/feed.xml' => Http::response(<<<'XML'
                <rss><channel>
                    <item><guid>new-1</guid><title>教育 AI 新政策</title><link>https://example.test/news/1</link><pubDate>2026-09-25T09:00:00+08:00</pubDate></item>
                    <item><guid>new-duplicate</guid><title>教育 AI 新政策重复</title><link>https://example.test/news/1?utm_source=rss</link><pubDate>2026-09-25T08:00:00+08:00</pubDate></item>
                    <item><guid>old-1</guid><title>旧政策</title><link>https://example.test/old</link><pubDate>2026-01-01T09:00:00+08:00</pubDate></item>
                </channel></rss>
                XML, 200),
            'https://example.test/low.xml' => Http::response('<rss><channel><item><title>低可信热点</title></item></channel></rss>', 200),
        ]);
        $task = ContentTask::create(['name' => '热点任务', 'writing_skill_slug' => 'geoflow_impression_article', 'topic_window_hours' => 72, 'min_source_trust' => 50, 'topic_keywords' => '教育,AI']);

        $items = app(HotTopicAggregator::class)->collect($task);

        $this->assertCount(1, $items);
        $this->assertSame('教育 AI 新政策', $items[0]['title']);
        $this->assertSame(80, $items[0]['trust_score']);
    }
}
