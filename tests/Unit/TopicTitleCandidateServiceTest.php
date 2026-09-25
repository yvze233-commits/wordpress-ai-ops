<?php

namespace Tests\Unit;

use App\Domain\Content\StructuredAiGateway;
use App\Domain\Topics\TopicTitleCandidateService;
use App\Models\TopicCandidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopicTitleCandidateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_persists_three_fact_bound_title_candidates_and_is_idempotent(): void
    {
        $candidate = TopicCandidate::create([
            'source_type' => 'rss', 'source_key' => 'event-1', 'title' => '教育 AI 新政策',
            'normalized_title' => '教育 ai 新政策', 'summary' => '教育部门发布新政策', 'source_url' => 'https://example.test/1',
        ]);
        $this->app->instance(StructuredAiGateway::class, new class implements StructuredAiGateway {
            public function generate(string $prompt, array $schema): array
            {
                return ['titles' => [
                    ['title' => '教育 AI 新政策：学校如何准备', 'rationale' => '保留事件并提供实践角度', 'score' => 92],
                    ['title' => '教育 AI 新政策：核心变化一文读懂', 'rationale' => '突出政策变化', 'score' => 88],
                    ['title' => '教育 AI 新政策影响哪些学习场景', 'rationale' => '对应教育场景', 'score' => 84],
                ]];
            }

            public function review(string $prompt, array $schema): array
            {
                return [];
            }
        });

        $service = app(TopicTitleCandidateService::class);
        $first = $service->generate($candidate);
        $second = $service->generate($candidate);

        $this->assertCount(3, $first);
        $this->assertCount(3, $second);
        $this->assertDatabaseCount('topic_title_candidates', 3);
        $this->assertSame('available', $first[0]->fresh()->status);
        $this->assertSame('https://example.test/1', $first[0]->source_snapshot['source_url']);
    }
}
