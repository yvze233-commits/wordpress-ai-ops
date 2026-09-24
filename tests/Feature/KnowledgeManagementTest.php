<?php

namespace Tests\Feature;

use App\Models\KnowledgeBase;
use App\Models\KnowledgeDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

class KnowledgeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_create_view_update_toggle_and_delete_a_knowledge_base(): void
    {
        $this->post('/admin/knowledge', ['name' => '产品资料', 'description' => '官方产品介绍'])
            ->assertRedirect('/admin/knowledge');

        $base = KnowledgeBase::query()->where('name', '产品资料')->firstOrFail();
        $this->assertTrue($base->enabled);

        $this->get('/admin/knowledge')
            ->assertOk()
            ->assertSee('产品资料')
            ->assertSee('新建知识库');

        $this->get('/admin/knowledge/'.$base->id)
            ->assertOk()
            ->assertSee('上传文档')
            ->assertSee('检索测试');

        $this->post('/admin/knowledge/'.$base->id.'/update', ['name' => '产品资料 V2', 'description' => '更新后的说明', 'enabled' => '1'])
            ->assertRedirect('/admin/knowledge/'.$base->id);
        $base->refresh();
        $this->assertSame('产品资料 V2', $base->name);

        $this->post('/admin/knowledge/'.$base->id.'/toggle')->assertRedirect();
        $base->refresh();
        $this->assertFalse($base->enabled);

        $this->post('/admin/knowledge/'.$base->id.'/delete')->assertRedirect('/admin/knowledge');
        $this->assertNull($base->fresh());
    }

    public function test_operator_can_upload_files_and_pasted_text_to_a_base(): void
    {
        $base = KnowledgeBase::create(['name' => '上传测试']);

        $markdown = UploadedFile::fake()->createWithContent('intro.md', "# 功能\n\n平台支持自动写作与审核。");
        $textFile = UploadedFile::fake()->createWithContent('notes.txt', '这是一段纯文本资料。');

        $this->post('/admin/knowledge/'.$base->id.'/documents', [
            'documents' => [$markdown, $textFile],
            'pasted_text' => "## 手动整理\n\n运营粘贴的补充资料。",
            'pasted_name' => '补充资料',
            'review_status' => 'reviewed',
            'risk_level' => 'low',
        ])->assertRedirect('/admin/knowledge/'.$base->id);

        $this->assertSame(3, $base->documents()->count());
        $reviewed = $base->documents()->where('name', 'intro.md')->first();
        $this->assertSame('reviewed', $reviewed->review_status);
        $this->assertGreaterThanOrEqual(1, $reviewed->chunks()->count());
        $this->assertSame('markdown', $reviewed->source_type);
    }

    public function test_docx_files_are_parsed_into_text(): void
    {
        $base = KnowledgeBase::create(['name' => 'Docx 测试']);
        $path = tempnam(sys_get_temp_dir(), 'docx');

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>来自 Word 文档的第一段。</w:t></w:r></w:p><w:p><w:r><w:t>第二段包含产品说明。</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();

        $docx = UploadedFile::fake()->createWithContent('manual.docx', (string) file_get_contents($path));
        unlink($path);

        $this->post('/admin/knowledge/'.$base->id.'/documents', ['documents' => [$docx]])
            ->assertRedirect('/admin/knowledge/'.$base->id);

        $document = $base->documents()->firstOrFail();
        $this->assertSame('word', $document->source_type);
        $this->assertStringContainsString('第一段', $document->text_content);
    }

    public function test_unsupported_files_report_a_readable_error(): void
    {
        $base = KnowledgeBase::create(['name' => '格式测试']);

        $pdf = UploadedFile::fake()->createWithContent('paper.pdf', '%PDF-1.4 fake');

        $response = $this->post('/admin/knowledge/'.$base->id.'/documents', ['documents' => [$pdf]]);
        $response->assertSessionHasErrors();

        $this->assertSame(0, $base->documents()->count());
    }

    public function test_excluded_documents_are_not_retrieved(): void
    {
        $base = KnowledgeBase::create(['name' => '检索测试库']);
        $service = app(\App\Domain\Knowledge\KnowledgeIngestionService::class);

        $keep = $service->ingestText($base, "# 平台功能\n\n平台支持自动生成文章并审核。", ['review_status' => 'reviewed']);
        $drop = $service->ingestText($base, "# 内部信息\n\n内部折扣码 SECRET2024。", ['review_status' => 'reviewed']);

        $snapshot = app(\App\Domain\Knowledge\KnowledgeRetrievalService::class)->retrieve('平台功能', [$base->id]);
        $this->assertSame([$keep->id], array_unique(array_column($snapshot->evidence(), 'document_id')));

        $drop->update(['review_status' => 'excluded']);
        $snapshot = app(\App\Domain\Knowledge\KnowledgeRetrievalService::class)->retrieve('折扣码 SECRET2024', [$base->id]);
        $this->assertSame([], array_column($snapshot->evidence(), 'document_id'));
    }

    public function test_document_review_status_can_be_changed_from_the_admin(): void
    {
        $base = KnowledgeBase::create(['name' => '审核测试']);
        $document = $base->documents()->create([
            'name' => '资料.md',
            'source_type' => 'markdown',
            'review_status' => 'pending',
            'risk_level' => 'low',
            'content_hash' => hash('sha256', 'content'),
            'content_length' => 7,
            'text_content' => 'content',
        ]);

        $this->post('/admin/knowledge/'.$base->id.'/documents/'.$document->id.'/update', [
            'review_status' => 'excluded',
            'risk_level' => 'high',
        ])->assertRedirect('/admin/knowledge/'.$base->id);

        $document->refresh();
        $this->assertSame('excluded', $document->review_status);
        $this->assertSame('high', $document->risk_level);
    }

    public function test_retrieval_test_endpoint_returns_ranked_evidence(): void
    {
        $base = KnowledgeBase::create(['name' => '接口测试']);
        app(\App\Domain\Knowledge\KnowledgeIngestionService::class)->ingestText(
            $base,
            "# 定价\n\n专业版每月 199 元，含 100 篇文章额度。",
            ['review_status' => 'reviewed'],
        );

        $this->postJson('/admin/knowledge/'.$base->id.'/test-retrieval', ['query' => '专业版 价格'])
            ->assertOk()
            ->assertJsonPath('data.evidence_count', 1)
            ->assertJsonPath('data.evidence.0.source_name', fn (?string $name) => $name !== null);
    }

    public function test_document_delete_removes_chunks_and_document(): void
    {
        $base = KnowledgeBase::create(['name' => '删除测试']);
        app(\App\Domain\Knowledge\KnowledgeIngestionService::class)->ingestText($base, "# 标题\n\n内容正文。", ['name' => '待删除.md']);

        $document = $base->documents()->firstOrFail();
        $this->post('/admin/knowledge/'.$base->id.'/documents/'.$document->id.'/delete')
            ->assertRedirect('/admin/knowledge/'.$base->id);

        $this->assertSame(0, KnowledgeDocument::query()->count());
    }
}
