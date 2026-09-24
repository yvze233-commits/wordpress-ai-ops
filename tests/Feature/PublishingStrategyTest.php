<?php

namespace Tests\Feature;

use App\Domain\Content\ContentState;
use App\Domain\Content\PublishScheduler;
use App\Domain\Content\StructuredAiGateway;
use App\Jobs\ReviewContentItemJob;
use App\Models\ContentItem;
use App\Models\TopicCandidate;
use App\Models\WordPressConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublishingStrategyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'content-ops.publish_window_start' => '08:00',
            'content-ops.publish_window_end' => '22:00',
            'content-ops.publish_daily_max' => 0,
        ]);
    }

    public function test_draft_only_mode_never_schedules_a_publish(): void
    {
        config(['content-ops.publish_mode' => 'draft_only']);
        $item = $this->item('awaiting_review');

        $this->post('/admin/reviews/'.$item->id.'/approve')->assertRedirect();

        $item->refresh();
        $this->assertSame(ContentState::APPROVED, $item->state);
        $this->assertNull($item->publish_status);
        $this->assertNull($item->scheduled_publish_at);
    }

    public function test_approval_publish_mode_schedules_a_slot_on_approval(): void
    {
        config(['content-ops.publish_mode' => 'approval_publish']);
        $item = $this->item('awaiting_review');

        $this->post('/admin/reviews/'.$item->id.'/approve')->assertRedirect();

        $item->refresh();
        $this->assertSame(ContentState::APPROVED, $item->state);
        $this->assertSame('publish', $item->publish_status);
        $this->assertNotNull($item->scheduled_publish_at);
        $this->assertNotNull(\App\Models\AuditEvent::query()->where('event_type', 'publish_scheduled')->first());
    }

    public function test_auto_publish_mode_schedules_when_ai_review_passes(): void
    {
        config(['content-ops.publish_mode' => 'auto_publish']);
        $item = $this->item('awaiting_review');
        $this->fakeGateway();

        (new ReviewContentItemJob($item->id))->handle();

        $item->refresh();
        $this->assertSame(ContentState::APPROVED, $item->state);
        $this->assertSame('publish', $item->publish_status);
        $this->assertNotNull($item->scheduled_publish_at);
    }

    public function test_scheduler_command_dispatches_only_due_items(): void
    {
        $candidate = TopicCandidate::create([
            'source_type' => 'rss', 'source_key' => uniqid('sched-'), 'title' => '标题', 'normalized_title' => '标题', 'status' => 'used',
        ]);
        $connection = WordPressConnection::create([
            'name' => 'Test WordPress', 'base_url' => 'https://wp.example.test', 'username' => 'admin',
            'application_password_encrypted' => 'secret-password', 'category_mapping' => [],
        ]);
        $common = ['topic_candidate_id' => $candidate->id, 'generation_meta' => ['category' => '教育', 'keywords' => ['AI']], 'content_html' => '<p>内容</p>'];
        $due = $this->item('approved', $common);
        $due->forceFill(['publish_status' => 'publish', 'scheduled_publish_at' => now()->subMinutes(5)])->save();
        $future = $this->item('approved', $common);
        $future->forceFill(['publish_status' => 'publish', 'scheduled_publish_at' => now()->addHours(2)])->save();

        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/categories?per_page=100')) {
                return Http::response([['id' => 4, 'name' => 'Education']]);
            }
            if (str_ends_with($request->url(), '/posts')) {
                return Http::response(['id' => 88, 'link' => 'https://wp.example.test/?p=88']);
            }

            return Http::response([]);
        });

        $this->artisan('content-ops:publish-due')->assertSuccessful();

        $due->refresh();
        $future->refresh();
        $this->assertSame(ContentState::PUBLISHED, $due->state);
        $this->assertSame(88, $due->wordpress_post_id);
        $this->assertSame(ContentState::APPROVED, $future->state);
    }

    public function test_scheduled_slot_falls_inside_the_publish_window(): void
    {
        $item = $this->item('approved');
        app(PublishScheduler::class)->schedule($item, 'publish');

        $item->refresh();
        $this->assertTrue($item->scheduled_publish_at->between(
            now()->startOfDay()->setTimeFromTimeString('08:00'),
            now()->endOfDay()->setTimeFromTimeString('22:00'),
        ));
    }

    public function test_daily_cap_rolls_the_slot_over_to_the_next_day(): void
    {
        config(['content-ops.publish_daily_max' => 1]);
        $published = $this->item(ContentState::WP_DRAFT_WRITTEN);
        $published->forceFill(['updated_at' => now()])->save();

        $item = $this->item('approved');
        app(PublishScheduler::class)->schedule($item, 'publish');

        $item->refresh();
        $this->assertTrue($item->scheduled_publish_at->isTomorrow());
    }

    public function test_publishing_strategy_is_saved_from_the_settings_page(): void
    {
        $this->post('/admin/settings', [
            'writing_ai_provider' => 'openai',
            'writing_ai_model' => 'gpt-4o-mini',
            'review_ai_provider' => 'openai',
            'review_ai_model' => 'gpt-4o-mini',
            'topic_daily_target' => 4,
            'review_pass_threshold' => 70,
            'publish_mode' => 'auto_publish',
            'publish_window_start' => '09:00',
            'publish_window_end' => '21:00',
            'publish_daily_max' => 3,
            'wp_default_status' => 'draft',
        ])->assertRedirect('/admin/settings');

        $this->assertSame('auto_publish', config('content-ops.publish_mode'));
        $this->assertSame('09:00', config('content-ops.publish_window_start'));
        $this->assertSame(3, config('content-ops.publish_daily_max'));

        $this->get('/admin/settings')
            ->assertOk()
            ->assertSee('全自动发布')
            ->assertSee('发布窗口');
    }

    public function test_dashboard_shows_alerts_and_daily_report(): void
    {
        $failed = $this->item('retryable_failed');
        $failed->auditEvents()->create(['event_type' => 'content_generation_failed', 'payload' => ['exception' => 'RuntimeException']]);
        $this->item('awaiting_review')->auditEvents()->create(['event_type' => 'content_generated', 'payload' => []]);

        $this->get('/admin')
            ->assertOk()
            ->assertSee('告警')
            ->assertSee('生产失败')
            ->assertSee('每日运行报表')
            ->assertSee('生成');

        $json = $this->getJson('/admin')->assertOk();
        $json->assertJsonPath('data.report.generated', 1);
        $json->assertJsonPath('data.report.failed', 1);
    }

    private function item(string $state, array $attributes = []): ContentItem
    {
        $candidate = TopicCandidate::create([
            'source_type' => 'title_library', 'source_key' => uniqid('candidate-', true),
            'title' => '发布策略测试标题', 'normalized_title' => uniqid('pub-'),
            'source_url' => 'https://example.test/news', 'status' => 'locked',
        ]);

        return ContentItem::create(array_merge([
            'topic_candidate_id' => $candidate->id,
            'title' => '发布策略测试标题',
            'state' => $state,
            'idempotency_key' => uniqid('item-', true),
            'content_html' => '<p>正文内容。</p>',
        ], $attributes));
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
                return [
                    'passed' => true, 'score' => 90, 'threshold' => 70,
                    'criterion_scores' => ['facts' => 90],
                    'conflicts' => [], 'missing_evidence' => [], 'image_issues' => [],
                    'revision_instructions' => [],
                ];
            }
        });
    }
}
