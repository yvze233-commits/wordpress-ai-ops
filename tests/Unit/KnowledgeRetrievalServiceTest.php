<?php

namespace Tests\Unit;

use App\Domain\Knowledge\EvidenceSnapshot;
use App\Domain\Knowledge\KnowledgeRetrievalService;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class KnowledgeRetrievalServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reviewed_evidence_is_ranked_first_and_high_risk_unreviewed_is_excluded(): void
    {
        $base = KnowledgeBase::create(['name' => '产品知识']);
        $reviewed = KnowledgeDocument::create([
            'knowledge_base_id' => $base->id,
            'name' => '已审核资料',
            'review_status' => 'reviewed',
            'risk_level' => 'low',
            'content_hash' => hash('sha256', 'reviewed'),
            'content_length' => 30,
            'text_content' => '教育平台支持课程管理和学习数据分析。',
        ]);
        $unreviewedHighRisk = KnowledgeDocument::create([
            'knowledge_base_id' => $base->id,
            'name' => '待审高风险资料',
            'review_status' => 'pending',
            'risk_level' => 'high',
            'content_hash' => hash('sha256', 'high-risk'),
            'content_length' => 30,
            'text_content' => '教育平台支持课程管理和学习数据分析。',
        ]);
        $reviewedChunk = KnowledgeChunk::create([
            'knowledge_document_id' => $reviewed->id,
            'position' => 0,
            'content' => '教育平台支持课程管理和学习数据分析。',
            'content_hash' => hash('sha256', '教育平台支持课程管理和学习数据分析。'),
        ]);
        KnowledgeChunk::create([
            'knowledge_document_id' => $unreviewedHighRisk->id,
            'position' => 0,
            'content' => '教育平台支持课程管理和学习数据分析。',
            'content_hash' => hash('sha256', 'high-risk-chunk'),
        ]);

        $snapshot = app(KnowledgeRetrievalService::class)->retrieve('教育平台 课程管理', [$base->id], 5);

        $this->assertSame($reviewedChunk->id, $snapshot->evidence()[0]['chunk_id']);
        $this->assertCount(1, $snapshot->evidence());
        $this->assertTrue($snapshot->validate());
    }

    public function test_snapshot_validation_rejects_a_changed_chunk_hash(): void
    {
        $base = KnowledgeBase::create(['name' => '稳定性测试']);
        $document = KnowledgeDocument::create([
            'knowledge_base_id' => $base->id,
            'name' => '资料',
            'review_status' => 'reviewed',
            'risk_level' => 'low',
            'content_hash' => hash('sha256', 'body'),
            'content_length' => 4,
            'text_content' => 'body',
        ]);
        $chunk = KnowledgeChunk::create([
            'knowledge_document_id' => $document->id,
            'position' => 0,
            'content' => 'body',
            'content_hash' => hash('sha256', 'body'),
        ]);
        $snapshot = new EvidenceSnapshot([[
            'chunk_id' => $chunk->id,
            'document_id' => $document->id,
            'content_hash' => $chunk->content_hash,
            'content' => $chunk->content,
        ]]);

        $chunk->update(['content' => 'changed', 'content_hash' => hash('sha256', 'changed')]);

        $this->expectException(InvalidArgumentException::class);
        $snapshot->validate();
    }
}
