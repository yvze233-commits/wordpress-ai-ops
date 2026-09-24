<?php

namespace Tests\Feature;

use App\Domain\Content\StructuredAiGateway;
use App\Jobs\GenerateContentItemJob;
use App\Jobs\ReviewContentItemJob;
use App\Models\ContentItem;
use App\Models\ContentRun;
use App\Models\TopicCandidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_generation_and_review_store_snapshots_and_approve_the_item(): void
    {
        $item = $this->item('locked');
        $this->fakeGateway(['valid' => true, 'passed' => true]);

        (new GenerateContentItemJob($item->id))->handle();
        $this->assertSame('awaiting_review', $item->fresh()->state);
        $this->assertSame('writing-rule-v1', $item->fresh()->writing_skill_snapshot['raw_text']);
        $this->assertNotEmpty($item->fresh()->evidence_snapshot);
        $this->assertNotEmpty($item->fresh()->content_html);

        (new ReviewContentItemJob($item->id))->handle();
        $fresh = $item->fresh();
        $this->assertSame('approved', $fresh->state);
        $this->assertTrue($fresh->review_result['passed']);
        $this->assertSame(2, $fresh->review_result['review_stages']);
        $this->assertArrayHasKey('pass_1', $fresh->review_result['criterion_scores']);
        $this->assertSame(2, ContentRun::query()->count());
    }

    public function test_review_conflict_routes_item_to_manual_review(): void
    {
        $item = $this->item('awaiting_review');
        $this->fakeGateway(['valid' => true, 'passed' => false, 'conflict' => true]);

        (new ReviewContentItemJob($item->id))->handle();

        $this->assertSame('needs_manual_review', $item->fresh()->state);
        $this->assertNotEmpty($item->fresh()->review_result['conflicts']);
    }

    public function test_invalid_generation_output_is_retryable_and_never_reviewed(): void
    {
        $item = $this->item('locked');
        $this->fakeGateway(['valid' => false]);

        (new GenerateContentItemJob($item->id))->handle();

        $this->assertSame('retryable_failed', $item->fresh()->state);
        $this->assertSame(0, ContentRun::query()->where('stage', 'review')->count());
        $this->assertSame('failed', ContentRun::query()->first()->status);
    }

    private function item(string $state): ContentItem
    {
        $candidate = TopicCandidate::create([
            'source_type' => 'title_library', 'source_key' => uniqid('candidate-', true),
            'title' => 'AI 教育新闻', 'normalized_title' => 'AI 教育新闻', 'summary' => '教育行业发生变化',
            'source_url' => 'https://example.test/news', 'status' => 'locked',
        ]);

        return ContentItem::create([
            'topic_candidate_id' => $candidate->id,
            'title' => $candidate->title,
            'state' => $state,
            'idempotency_key' => uniqid('item-', true),
            'evidence_snapshot' => ['retrieved_at' => now()->toIso8601String(), 'evidence' => []],
            'writing_skill_snapshot' => ['skill_id' => 1, 'version' => 1, 'raw_text' => 'writing-rule-v1'],
            'review_skill_snapshot' => [
                'strategy' => 'geoflow_two_pass',
                'pass_threshold' => 70,
                'stages' => [
                    ['skill_id' => 2, 'version' => 1, 'raw_text' => 'review-rule-v1'],
                    ['skill_id' => 3, 'version' => 1, 'raw_text' => 'review-rule-v2'],
                ],
            ],
        ]);
    }

    private function fakeGateway(array $options): void
    {
        $this->app->instance(StructuredAiGateway::class, new class($options) implements StructuredAiGateway
        {
            public function __construct(private array $options) {}

            public function generate(string $prompt, array $schema): array
            {
                if (! $this->options['valid']) {
                    return ['title' => 'incomplete'];
                }

                return [
                    'title' => '生成标题', 'slug' => 'generated-title', 'excerpt' => '摘要',
                    'content_markdown' => "# 生成标题\n\n这是一段有证据的内容。",
                    'keywords' => ['教育'], 'category' => '教育', 'source_links' => ['https://example.test/news'], 'claims' => ['事实'],
                ];
            }

            public function review(string $prompt, array $schema): array
            {
                $conflict = $this->options['conflict'] ?? false;

                return [
                    'passed' => $this->options['passed'], 'score' => $this->options['passed'] ? 90 : 40, 'threshold' => 70,
                    'criterion_scores' => ['facts' => $this->options['passed'] ? 90 : 40],
                    'conflicts' => $conflict ? ['证据冲突'] : [], 'missing_evidence' => [], 'image_issues' => [],
                    'revision_instructions' => ['补充来源'],
                ];
            }
        });
    }
}
