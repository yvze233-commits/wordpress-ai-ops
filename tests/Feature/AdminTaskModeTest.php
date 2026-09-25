<?php

namespace Tests\Feature;

use App\Jobs\CreateDailyBatchJob;
use App\Models\ContentBatch;
use App\Models\ContentTask;
use App\Models\Skill;
use App\Models\TitleLibraryEntry;
use App\Models\TopicCandidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AdminTaskModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_create_task_with_daily_target_and_selected_skills(): void
    {
        $this->get('/admin/tasks')->assertOk()->assertSee('任务模式')->assertSee('每日文章数量');
        $this->post('/admin/tasks', [
            'name' => '每日品牌内容', 'daily_target' => 4,
            'writing_skill_slug' => 'geoflow_ranking_article', 'review_skill_slug' => 'geoflow_two_pass',
            'review_pass_threshold' => 75, 'publish_status' => 'draft', 'schedule_time' => '09:30',
        ])->assertRedirect('/admin/tasks');

        $task = ContentTask::query()->firstOrFail();
        $this->assertSame('geoflow_ranking_article', $task->writing_skill_slug);
        $this->assertSame(4, $task->daily_target);
        $this->assertSame('paused', $task->status);
    }

    public function test_operator_can_select_direct_publish_status(): void
    {
        $this->get('/admin/tasks')->assertOk();
        $this->post('/admin/tasks', [
            'name' => '直接发布任务', 'daily_target' => 1,
            'writing_skill_slug' => 'geoflow_impression_article', 'review_skill_slug' => 'geoflow_two_pass',
            'review_pass_threshold' => 80, 'publish_status' => 'publish', 'schedule_time' => '09:30',
        ])->assertRedirect('/admin/tasks');

        $this->assertSame('publish', ContentTask::query()->latest('id')->value('publish_status'));
    }

    public function test_operator_can_configure_hot_topic_strategy_and_wordpress_site(): void
    {
        $connection = \App\Models\WordPressConnection::create(['name' => '主站', 'base_url' => 'https://wp.example.test', 'username' => 'admin', 'application_password_encrypted' => 'secret', 'status' => 'healthy']);
        $this->get('/admin/tasks')->assertOk();
        $this->post('/admin/tasks', [
            'name' => '时讯任务', 'daily_target' => 4, 'writing_skill_slug' => 'geoflow_impression_article',
            'review_skill_slug' => 'geoflow_two_pass', 'review_pass_threshold' => 70, 'publish_status' => 'draft',
            'schedule_time' => '09:00', 'topic_source_mode' => 'hot', 'topic_window_hours' => 24,
            'topic_keywords' => '教育,人工智能', 'min_source_trust' => 75, 'wordpress_connection_id' => $connection->id,
        ])->assertRedirect('/admin/tasks');

        $task = ContentTask::query()->latest('id')->firstOrFail();
        $this->assertSame(24, $task->topic_window_hours);
        $this->assertSame('教育,人工智能', $task->topic_keywords);
        $this->assertSame(75, $task->min_source_trust);
        $this->assertSame($connection->id, $task->wordpress_connection_id);
    }

    public function test_operator_can_start_pause_and_run_a_task(): void
    {
        Bus::fake();
        $task = ContentTask::create(['name' => '自动任务', 'daily_target' => 3, 'writing_skill_slug' => 'geoflow_impression_article', 'review_skill_slug' => 'geoflow_two_pass', 'review_pass_threshold' => 70, 'publish_status' => 'draft', 'status' => 'paused', 'enabled' => true]);

        $this->post("/admin/tasks/{$task->id}/start")->assertRedirect('/admin/tasks');
        $this->assertSame('running', $task->fresh()->status);
        $this->post("/admin/tasks/{$task->id}/run")->assertRedirect('/admin/tasks');
        Bus::assertDispatched(CreateDailyBatchJob::class, fn (CreateDailyBatchJob $job): bool => $job->taskId === $task->id);
        $this->post("/admin/tasks/{$task->id}/pause")->assertRedirect('/admin/tasks');
        $this->assertSame('paused', $task->fresh()->status);
    }

    public function test_daily_batch_keeps_task_configuration_on_the_batch_and_content_items(): void
    {
        $task = ContentTask::create(['name' => '榜单任务', 'daily_target' => 1, 'writing_skill_slug' => 'geoflow_ranking_article', 'review_skill_slug' => 'geoflow_two_pass', 'review_pass_threshold' => 80, 'publish_status' => 'draft', 'status' => 'running', 'enabled' => true]);
        $batch = (new CreateDailyBatchJob('2026-09-30', null, $task->id))->handle();
        $this->assertSame($task->id, $batch->fresh()->content_task_id);
    }

    public function test_task_source_mode_only_selects_the_configured_source(): void
    {
        $task = ContentTask::create([
            'name' => '仅热点任务', 'daily_target' => 2, 'writing_skill_slug' => 'geoflow_impression_article',
            'review_skill_slug' => 'geoflow_two_pass', 'status' => 'running',
            'settings' => ['topic_source_mode' => 'hot_only'],
        ]);
        TopicCandidate::create(['source_type' => 'rss', 'source_key' => 'hot-1', 'title' => '热点选题', 'normalized_title' => '热点选题']);
        TopicCandidate::create(['source_type' => 'manual', 'source_key' => 'manual-1', 'title' => '人工选题', 'normalized_title' => '人工选题']);
        $title = TitleLibraryEntry::create(['raw_title' => '标题库选题', 'normalized_title' => '标题库选题', 'enabled' => true]);

        $batch = (new CreateDailyBatchJob('2026-10-01', null, $task->id))->handle();

        $this->assertSame(['rss'], $batch->contentItems()->with('topicCandidate')->get()->pluck('topicCandidate.source_type')->all());
        $this->assertNotNull($title->fresh());
    }
}
