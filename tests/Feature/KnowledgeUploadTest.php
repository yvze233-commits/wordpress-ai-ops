<?php

namespace Tests\Feature;

use App\Domain\Knowledge\KnowledgeIngestionService;
use App\Models\KnowledgeBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class KnowledgeUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_markdown_text_is_normalized_chunked_and_deduplicated(): void
    {
        $base = KnowledgeBase::create(['name' => '运营资料']);
        $service = app(KnowledgeIngestionService::class);
        $text = "# 产品介绍\n\n教育平台支持课程管理。\n\n## 数据分析\n\n平台提供学习数据分析。";

        $first = $service->ingestText($base, $text, [
            'name' => '产品说明.md',
            'source_url' => 'https://example.test/product',
            'review_status' => 'reviewed',
        ]);
        $second = $service->ingestText($base, $text, ['name' => '副本.md']);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('reviewed', $first->review_status);
        $this->assertGreaterThanOrEqual(2, $first->chunks()->count());
        $this->assertSame(hash('sha256', $first->text_content), $first->content_hash);
    }

    public function test_executable_file_extensions_are_rejected(): void
    {
        $base = KnowledgeBase::create(['name' => '安全测试']);

        $this->expectException(InvalidArgumentException::class);
        app(KnowledgeIngestionService::class)->ingestText($base, 'rm -rf /', ['name' => 'danger.php']);
    }
}
