<?php

namespace Tests\Feature;

use App\Domain\Content\StructuredAiGateway;
use App\Models\AuditEvent;
use App\Models\ContentItem;
use App\Models\ContentRun;
use App\Models\TopicCandidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionConsoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_console_can_generate_review_and_cancel_items(): void
    {
        $item = $this->item('locked');
        $this->fakeGateway(['valid' => true, 'passed' => true]);

        $this->post('/admin/content-items/'.$item->id.'/generate')
            ->assertRedirect()
            ->assertSessionHas('status');
        $this->assertSame('awaiting_review', $item->fresh()->state);

        $this->post('/admin/content-items/'.$item->id.'/review')
            ->assertRedirect()
            ->assertSessionHas('status');
        $this->assertSame('approved', $item->fresh()->state);
        $this->assertNotNull(AuditEvent::query()->where('event_type', 'content_review_passed')->first());

        $pending = $this->item('locked');
        $this->post('/admin/content-items/'.$pending->id.'/cancel')
            ->assertRedirect()
            ->assertSessionHas('status');
        $this->assertSame('permanently_failed', $pending->fresh()->state);
        $this->assertNotNull(AuditEvent::query()->where('event_type', 'content_cancelled')->first());
    }

    public function test_retryable_failed_item_can_be_regenerated_from_console(): void
    {
        $item = $this->item('locked');
        $this->fakeGateway(['valid' => false]);
        (new \App\Jobs\GenerateContentItemJob($item->id))->handle();
        $this->assertSame('retryable_failed', $item->fresh()->state);

        $this->fakeGateway(['valid' => true, 'passed' => true]);
        $this->post('/admin/content-items/'.$item->id.'/generate')
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame('awaiting_review', $item->fresh()->state);
        $this->assertSame(2, ContentRun::query()->where('stage', 'generation')->count());
    }

    public function test_console_actions_reject_invalid_states_with_readable_errors(): void
    {
        $approved = $this->item('approved');
        $this->post('/admin/content-items/'.$approved->id.'/cancel')
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertSame('approved', $approved->fresh()->state);

        $locked = $this->item('locked');
        $this->post('/admin/content-items/'.$locked->id.'/review')
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_item_production_log_is_visible_on_the_detail_page(): void
    {
        $item = $this->item('locked');
        $this->fakeGateway(['valid' => true, 'passed' => true]);
        (new \App\Jobs\GenerateContentItemJob($item->id))->handle();
        (new \App\Jobs\ReviewContentItemJob($item->id))->handle();

        $this->get('/admin/reviews/'.$item->id)
            ->assertOk()
            ->assertSee('生产日志')
            ->assertSee('开始生成')
            ->assertSee('生成完成')
            ->assertSee('审核通过')
            ->assertSee('配图位置');
    }

    public function test_operator_can_edit_an_awaiting_article_and_rerun_review(): void
    {
        $item = $this->item('locked');
        $this->fakeGateway(['valid' => true, 'passed' => true]);
        (new \App\Jobs\GenerateContentItemJob($item->id))->handle();
        $item->refresh();
        $originalTitle = $item->title;

        $this->get('/admin/reviews/'.$item->id)
            ->assertOk()
            ->assertSee('编辑文章');

        $this->post('/admin/reviews/'.$item->id.'/update', [
            'title' => '人工修改后的标题',
            'excerpt' => $item->excerpt,
            'content_markdown' => "# 人工修改后的标题\n\n这是运营人员手动修订过的正文内容。",
            'rerun_review' => '1',
        ])->assertRedirect('/admin/reviews/'.$item->id)
            ->assertSessionHas('status');

        $fresh = $item->fresh();
        $this->assertSame('人工修改后的标题', $fresh->title);
        $this->assertNotSame($originalTitle, $fresh->title);
        $this->assertSame('approved', $fresh->state);
        $this->assertStringContainsString('手动修订过', $fresh->content_html);
        $this->assertNotNull(AuditEvent::query()->where('event_type', 'content_edited')->first());
    }

    public function test_manual_review_item_can_be_edited_back_to_ai_review(): void
    {
        $item = $this->item('needs_manual_review');
        $this->fakeGateway(['valid' => true, 'passed' => true]);

        $this->post('/admin/reviews/'.$item->id.'/update', [
            'title' => '人工复核编辑标题',
            'content_markdown' => "# 人工复核编辑标题\n\n修订后的正文。",
            'rerun_review' => '1',
        ])->assertRedirect('/admin/reviews/'.$item->id);

        $this->assertSame('approved', $item->fresh()->state);
    }

    public function test_edit_rejects_invalid_state_and_empty_markdown(): void
    {
        $approved = $this->item('approved');
        $this->post('/admin/reviews/'.$approved->id.'/update', [
            'title' => 'x',
            'content_markdown' => 'content',
        ])->assertRedirect()->assertSessionHas('error');

        $item = $this->item('awaiting_review');
        $this->post('/admin/reviews/'.$item->id.'/update', [
            'title' => '空内容标题',
            'content_markdown' => '   ',
        ])->assertSessionHasErrors();
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
                return [
                    'passed' => $this->options['passed'], 'score' => $this->options['passed'] ? 90 : 40, 'threshold' => 70,
                    'criterion_scores' => ['facts' => $this->options['passed'] ? 90 : 40],
                    'conflicts' => [], 'missing_evidence' => [], 'image_issues' => [],
                    'revision_instructions' => ['补充来源'],
                ];
            }
        });
    }
}
