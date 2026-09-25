<?php

namespace Tests\Feature;

use App\Models\ImageLibrary;
use App\Models\KnowledgeBase;
use App\Models\TitleLibraryEntry;
use App\Models\TopicSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAssetPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_asset_pages_expose_operational_forms_and_status_context(): void
    {
        KnowledgeBase::create(['name' => '品牌资料']);
        ImageLibrary::create(['name' => '案例图片']);
        TitleLibraryEntry::create(['raw_title' => 'AI 教育', 'normalized_title' => 'ai教育']);
        TopicSource::create(['name' => 'RSS', 'type' => 'rss', 'url' => 'https://example.test/feed.xml', 'status' => 'error', 'last_error' => 'HTTP 500']);

        $this->get('/admin/knowledge')->assertOk()->assertSee('新建知识库')->assertSee('上传资料');
        $this->get('/admin/images')->assertOk()->assertSee('新建图片库')->assertSee('批量上传');
        $this->get('/admin/titles')->assertOk()->assertSee('批量导入标题')->assertSee('AI 教育');
        $this->get('/admin/sources')->assertOk()->assertSee('立即抓取')->assertSee('HTTP 500');
    }
}
