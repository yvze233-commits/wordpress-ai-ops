<?php

namespace Tests\Unit;

use App\Domain\Content\ArticlePromptBuilder;
use App\Models\ContentItem;
use App\Models\TopicCandidate;
use Tests\TestCase;

class ArticlePromptBuilderTest extends TestCase
{
    public function test_generation_prompt_contains_immutable_inputs_and_boundary_instructions(): void
    {
        $candidate = new TopicCandidate(['title' => '人工智能教育', 'summary' => '一条摘要', 'source_url' => 'https://example.test/news', 'source_type' => 'rss']);
        $item = new ContentItem(['title' => '人工智能教育']);
        $item->setRelation('topicCandidate', $candidate);
        $prompt = (new ArticlePromptBuilder)->generation(
            $item,
            ['retrieved_at' => '2026-09-24T00:00:00Z', 'evidence' => [['chunk_id' => 1, 'content_hash' => 'abc', 'content' => '证据']]],
            [['image_id' => 2, 'role' => 'featured', 'paragraph_index' => null]],
            ['skill_id' => 3, 'version' => 2, 'raw_text' => '写作规则'],
        );

        $this->assertStringContainsString('人工智能教育', $prompt);
        $this->assertStringContainsString('证据', $prompt);
        $this->assertStringContainsString('image_placement_plan', $prompt);
        $this->assertStringContainsString('写作规则', $prompt);
        $this->assertStringContainsString('不要复制来源原文', $prompt);
    }

    public function test_structured_contracts_expose_all_required_fields(): void
    {
        $this->assertSame(
            ['title', 'slug', 'excerpt', 'content_markdown', 'keywords', 'category', 'source_links', 'claims'],
            ArticlePromptBuilder::generationSchema()['required'],
        );
        $this->assertSame(
            ['passed', 'score', 'threshold', 'criterion_scores', 'conflicts', 'missing_evidence', 'image_issues', 'revision_instructions'],
            ArticlePromptBuilder::reviewSchema()['required'],
        );
    }
}
