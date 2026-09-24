<?php

namespace Tests\Feature;

use App\Domain\Content\StructuredAiGateway;
use App\Models\ContentBatch;
use App\Models\TitleLibraryEntry;
use App\Models\TopicCandidate;
use App\Models\TopicSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TitleAndSourceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_titles_can_be_imported_from_a_csv_file_with_deduplication(): void
    {
        $csv = "标题,分类,优先级\n在线教育行业洞察,行业,90\nK12 机构转型指南,转型,60\n";
        $file = UploadedFile::fake()->createWithContent('titles.csv', $csv);

        $this->post('/admin/titles/import', ['import_file' => $file])
            ->assertRedirect('/admin/titles')
            ->assertSessionHas('status');

        $this->assertSame(2, TitleLibraryEntry::query()->count());
        $first = TitleLibraryEntry::query()->where('raw_title', '在线教育行业洞察')->first();
        $this->assertSame('行业', $first->category);
        $this->assertSame(90, $first->priority);
        $this->assertTrue($first->enabled);

        $second = UploadedFile::fake()->createWithContent('titles.csv', $csv);
        $this->post('/admin/titles/import', ['import_file' => $second])
            ->assertRedirect('/admin/titles')
            ->assertSessionHas('error');

        $this->assertSame(2, TitleLibraryEntry::query()->count());
    }

    public function test_pasted_titles_are_imported_one_per_line(): void
    {
        $this->post('/admin/titles/import', [
            'pasted_text' => "第一个足够长的标题\n第二个足够长的标题\n短\n",
        ])->assertRedirect('/admin/titles');

        $this->assertSame(2, TitleLibraryEntry::query()->count());
    }

    public function test_single_title_can_be_added_toggled_and_deleted(): void
    {
        $this->post('/admin/titles', ['title' => '成人英语市场分析', 'category' => '市场', 'priority' => 80])
            ->assertRedirect('/admin/titles')
            ->assertSessionHas('status');

        $title = TitleLibraryEntry::query()->where('raw_title', '成人英语市场分析')->firstOrFail();
        $this->assertSame('市场', $title->category);

        $this->post('/admin/titles/'.$title->id.'/toggle')->assertRedirect();
        $this->assertFalse($title->fresh()->enabled);

        $this->post('/admin/titles/'.$title->id.'/delete')->assertRedirect();
        $this->assertNull($title->fresh());
    }

    public function test_titles_page_shows_usage_stats(): void
    {
        TitleLibraryEntry::query()->create([
            'raw_title' => '已使用的标题样例',
            'normalized_title' => '已使用的标题样例',
            'use_count' => 2,
            'enabled' => false,
        ]);

        $this->get('/admin/titles')
            ->assertOk()
            ->assertSee('标题总数')
            ->assertSee('已使用')
            ->assertSee('批量导入')
            ->assertSee('已使用的标题样例');
    }

    public function test_sources_can_be_created_updated_and_paused(): void
    {
        $this->post('/admin/sources', [
            'name' => '教育新闻 RSS',
            'type' => 'rss',
            'url' => 'https://example.test/feed.xml',
            'trust_score' => 80,
        ])->assertRedirect('/admin/sources')
            ->assertSessionHas('status');

        $source = TopicSource::query()->where('name', '教育新闻 RSS')->firstOrFail();
        $this->assertSame(80, $source->trust_score);
        $this->assertTrue($source->enabled);
        $this->assertSame('active', $source->status);

        $this->post('/admin/sources/'.$source->id.'/update', [
            'name' => '教育新闻 RSS V2',
            'type' => 'rss',
            'url' => 'https://example.test/feed2.xml',
            'trust_score' => 65,
            'parser_config' => '{"item_selector":".item"}',
        ])->assertRedirect('/admin/sources');

        $source->refresh();
        $this->assertSame('教育新闻 RSS V2', $source->name);
        $this->assertSame(['item_selector' => '.item'], $source->parser_config);

        $this->post('/admin/sources/'.$source->id.'/pause')->assertRedirect();
        $this->assertSame('paused', $source->fresh()->status);

        $this->post('/admin/sources/'.$source->id.'/pause')->assertRedirect();
        $this->assertSame('active', $source->fresh()->status);

        $this->post('/admin/sources/'.$source->id.'/toggle')->assertRedirect();
        $this->assertFalse($source->fresh()->enabled);

        $this->post('/admin/sources/'.$source->id.'/delete')->assertRedirect();
        $this->assertNull($source->fresh());
    }

    public function test_invalid_parser_config_is_rejected(): void
    {
        $this->post('/admin/sources', [
            'name' => '坏配置来源',
            'type' => 'json',
            'url' => 'https://example.test/api',
            'parser_config' => '{not-json',
        ])->assertSessionHasErrors();

        $this->assertSame(0, TopicSource::query()->count());
    }

    public function test_source_test_endpoint_reports_parsed_titles_without_writing(): void
    {
        $source = TopicSource::create(['name' => '测试 RSS', 'type' => 'rss', 'url' => 'https://example.test/feed.xml']);

        Http::fake([
            'https://example.test/feed.xml' => Http::response(
                '<?xml version="1.0"?><rss version="2.0"><channel><item><guid>a</guid><title>测试标题一</title></item><item><guid>b</guid><title>测试标题二</title></item></channel></rss>',
                200,
            ),
        ]);

        $this->postJson('/admin/sources/'.$source->id.'/test')
            ->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.count', 2)
            ->assertJsonPath('data.samples.0', '测试标题一');

        $this->assertSame(0, TopicCandidate::query()->count());
        $this->assertNull($source->fresh()->last_fetched_at);
    }

    public function test_source_test_endpoint_reports_network_and_parse_failures(): void
    {
        $offline = TopicSource::create(['name' => '离线来源', 'type' => 'rss', 'url' => 'https://offline.test/feed.xml']);
        $broken = TopicSource::create(['name' => '坏来源', 'type' => 'json', 'url' => 'https://broken.test/api']);

        Http::fake([
            'https://offline.test/feed.xml' => Http::failedConnection(),
            'https://broken.test/api' => Http::response('not-json', 200),
        ]);

        $this->postJson('/admin/sources/'.$offline->id.'/test')
            ->assertStatus(422)
            ->assertJsonPath('data.ok', false)
            ->assertJsonPath('data.category', 'network');

        $this->postJson('/admin/sources/'.$broken->id.'/test')
            ->assertStatus(422)
            ->assertJsonPath('data.ok', false)
            ->assertJsonPath('data.category', 'parse');
    }

    public function test_source_collect_endpoint_runs_a_real_collection(): void
    {
        $source = TopicSource::create(['name' => '抓取来源', 'type' => 'rss', 'url' => 'https://example.test/feed.xml']);

        Http::fake([
            'https://example.test/feed.xml' => Http::response(
                '<?xml version="1.0"?><rss version="2.0"><channel><item><guid>n1</guid><title>抓取到的新闻标题</title></item></channel></rss>',
                200,
            ),
        ]);

        $this->post('/admin/sources/'.$source->id.'/collect')
            ->assertRedirect()
            ->assertSessionHas('status');

        $source->refresh();
        $this->assertSame(200, $source->last_http_status);
        $this->assertNotNull($source->last_fetched_at);
        $this->assertSame(1, $source->feeds()->count());
    }

    public function test_daily_batch_can_be_created_from_the_admin(): void
    {
        $this->fakeGateway();
        $source = TopicSource::create(['name' => '批次来源', 'type' => 'rss', 'url' => 'https://example.test/feed.xml', 'enabled' => true]);
        Http::fake([
            'https://example.test/feed.xml' => Http::response(
                '<?xml version="1.0"?><rss version="2.0"><channel><item><guid>b1</guid><title>批次新闻标题样例</title></item></channel></rss>',
                200,
            ),
        ]);
        TitleLibraryEntry::create(['raw_title' => '考研英语备考策略分享', 'normalized_title' => '考研英语备考策略分享']);
        TitleLibraryEntry::create(['raw_title' => '留学申请文书写作指南', 'normalized_title' => '留学申请文书写作指南']);

        $this->post('/admin/batches/create-daily')
            ->assertRedirect()
            ->assertSessionHas('status');

        $batch = ContentBatch::query()->whereDate('run_date', now()->toDateString())->firstOrFail();
        $this->assertSame(3, $batch->contentItems()->count());
        $this->assertSame(3, ContentBatch::query()->whereDate('run_date', now()->toDateString())->firstOrFail()->completed_count);

        $this->get('/admin/batches/'.$batch->id)
            ->assertOk()
            ->assertSee('批次文章')
            ->assertSee('生成标题');
    }

    public function test_daily_batch_without_materials_reports_shortfall(): void
    {
        $this->post('/admin/batches/create-daily')->assertRedirect();

        $batch = ContentBatch::query()->whereDate('run_date', now()->toDateString())->firstOrFail();
        $this->assertSame(0, $batch->contentItems()->count());
        $this->assertSame('shortfall', $batch->status);
    }

    private function fakeGateway(): void
    {
        $this->app->instance(StructuredAiGateway::class, new class implements StructuredAiGateway
        {
            public function generate(string $prompt, array $schema): array
            {
                return [
                    'title' => '生成标题', 'slug' => 'generated-title', 'excerpt' => '摘要',
                    'content_markdown' => "# 生成标题\n\n这是一段有证据的内容。",
                    'keywords' => ['教育'], 'category' => '教育', 'source_links' => ['https://example.test/news'], 'claims' => ['事实'],
                ];
            }

            public function review(string $prompt, array $schema): array
            {
                return ['passed' => true, 'score' => 90, 'threshold' => 70, 'criterion_scores' => ['facts' => 90], 'conflicts' => [], 'missing_evidence' => [], 'image_issues' => [], 'revision_instructions' => []];
            }
        });
    }
}
