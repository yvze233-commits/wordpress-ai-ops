<?php

namespace Tests\Feature;

use App\Jobs\CollectTopicSourceJob;
use App\Models\ImageLibrary;
use App\Models\KnowledgeBase;
use App\Models\TitleLibraryEntry;
use App\Models\TopicSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminAssetManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_create_and_toggle_a_knowledge_base_and_ingest_a_markdown_file(): void
    {
        $this->post('/admin/knowledge', ['name' => '品牌资料', 'description' => '核心资料'])
            ->assertRedirect('/admin/knowledge');

        $base = KnowledgeBase::query()->firstOrFail();
        $this->post("/admin/knowledge/{$base->id}/documents", [
            'file' => UploadedFile::fake()->createWithContent('facts.md', "# 品牌\n\n多次元教育提供教育服务。"),
        ])->assertRedirect('/admin/knowledge');

        $this->post("/admin/knowledge/{$base->id}/toggle")->assertRedirect('/admin/knowledge');
        $this->assertFalse($base->fresh()->enabled);
        $this->assertDatabaseHas('knowledge_documents', ['knowledge_base_id' => $base->id, 'name' => 'facts.md']);
    }

    public function test_operator_can_create_library_upload_image_and_toggle_it(): void
    {
        $this->post('/admin/images', ['name' => '案例图片'])->assertRedirect('/admin/images');
        $library = ImageLibrary::query()->firstOrFail();

        $this->post("/admin/images/{$library->id}/upload", [
            'image' => UploadedFile::fake()->image('campus.jpg', 20, 20),
            'alt_text' => '校园案例',
            'notes' => '适合校园场景',
            'keywords' => '校园,教育',
        ])->assertRedirect('/admin/images');

        $this->post("/admin/images/{$library->id}/toggle")->assertRedirect('/admin/images');
        $this->assertFalse($library->fresh()->enabled);
        $this->assertDatabaseHas('library_images', ['image_library_id' => $library->id, 'alt_text' => '校园案例']);
    }

    public function test_operator_can_bulk_import_titles_without_duplicates_and_toggle_entry(): void
    {
        $this->post('/admin/titles/import', ['titles' => "教育数字化趋势\n教育数字化趋势\nAI 教学实践", 'category' => '热点'])
            ->assertRedirect('/admin/titles');

        $this->assertSame(2, TitleLibraryEntry::query()->count());
        $title = TitleLibraryEntry::query()->firstOrFail();
        $this->post("/admin/titles/{$title->id}/toggle")->assertRedirect('/admin/titles');
        $this->assertFalse($title->fresh()->enabled);
    }

    public function test_operator_can_create_source_and_queue_a_fetch(): void
    {
        Bus::fake();
        $this->post('/admin/sources', [
            'name' => '教育资讯', 'type' => 'rss', 'url' => 'https://example.test/feed.xml', 'trust_score' => 80,
        ])->assertRedirect('/admin/sources');

        $source = TopicSource::query()->firstOrFail();
        $this->post("/admin/sources/{$source->id}/fetch")->assertRedirect('/admin/sources');
        Bus::assertDispatched(CollectTopicSourceJob::class, fn (CollectTopicSourceJob $job): bool => $job->topicSourceId === $source->id);
        $this->assertSame('fetching', $source->fresh()->status);
    }

    public function test_sync_fetch_runs_immediately_and_returns_source_to_active(): void
    {
        config(['queue.default' => 'sync']);
        Http::fake([
            'https://example.test/feed.xml' => Http::response('<rss><channel><item><guid>sync-1</guid><title>同步热点</title></item></channel></rss>', 200),
        ]);
        $this->post('/admin/sources', ['name' => '同步来源', 'type' => 'rss', 'url' => 'https://example.test/feed.xml'])->assertRedirect('/admin/sources');
        $source = TopicSource::query()->firstOrFail();

        $this->post("/admin/sources/{$source->id}/fetch")->assertRedirect('/admin/sources');

        $this->assertSame('active', $source->fresh()->status);
        $this->assertSame(1, $source->fresh()->feeds()->count());
    }
}
