<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Content\ContentState;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\ContentBatch;
use App\Models\ContentItem;
use App\Models\ImageLibrary;
use App\Models\KnowledgeBase;
use App\Models\Skill;
use App\Models\TopicSource;
use App\Models\WordPressConnection;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): mixed
    {
        $counts = collect(ContentState::values())->mapWithKeys(fn (string $state): array => [$state => ContentItem::query()->where('state', $state)->count()])->all();
        $batch = ContentBatch::query()->whereDate('run_date', now()->toDateString())->first();
        $reviewItems = ContentItem::query()
            ->with('topicCandidate')
            ->whereIn('state', [ContentState::AWAITING_REVIEW, ContentState::NEEDS_MANUAL_REVIEW])
            ->latest('updated_at')
            ->limit(5)
            ->get();
        $knowledgeBases = KnowledgeBase::query()->where('enabled', true)->withCount('documents')->get();
        $imageLibraries = ImageLibrary::query()->where('enabled', true)->withCount('images')->get();
        $skills = Skill::query()->get(['enabled']);
        $data = [
            'daily_target' => (int) ($batch?->target_count ?? config('content-ops.topic_daily_target', 4)),
            'selected' => (int) ($batch?->completed_count ?? 0),
            'states' => $counts,
        ];
        $knowledgeCount = $knowledgeBases->count();
        $knowledgeDocuments = (int) $knowledgeBases->sum('documents_count');
        $imageLibraryCount = $imageLibraries->count();
        $imageCount = (int) $imageLibraries->sum('images_count');
        $skillCount = $skills->count();
        $enabledSkillCount = $skills->where('enabled', true)->count();

        $alerts = $this->alerts();
        $report = $this->dailyReport(now()->toDateString());
        $reportHistory = $this->reportHistory();

        if ($request->expectsJson()) {
            return response()->json(['data' => $data + compact('knowledgeCount', 'knowledgeDocuments', 'imageLibraryCount', 'imageCount', 'skillCount', 'enabledSkillCount', 'alerts', 'report', 'reportHistory')]);
        }

        return view('admin.dashboard', compact('data', 'batch', 'reviewItems', 'knowledgeCount', 'knowledgeDocuments', 'imageLibraryCount', 'imageCount', 'skillCount', 'enabledSkillCount', 'alerts', 'report', 'reportHistory'));
    }

    /**
     * In-app alert panel: production failures, stuck generation and WordPress connection health.
     *
     * @return list<array{level:string,title:string,detail:string}>
     */
    private function alerts(): array
    {
        $alerts = [];

        $failed = ContentItem::query()->whereIn('state', [ContentState::RETRYABLE_FAILED, ContentState::PERMANENTLY_FAILED])->count();
        if ($failed > 0) {
            $alerts[] = ['level' => 'red', 'title' => "有 {$failed} 篇文章生产失败", 'detail' => '可在批次页对「失败可重试」的文章点击重试。'];
        }

        $stuck = ContentItem::query()->where('state', ContentState::GENERATING)->where('updated_at', '<', now()->subMinutes(30))->count();
        if ($stuck > 0) {
            $alerts[] = ['level' => 'amber', 'title' => "有 {$stuck} 篇文章生成超过 30 分钟未完成", 'detail' => '可能是队列积压或 AI 服务超时，请检查队列与 AI 连接。'];
        }

        $connection = WordPressConnection::query()->oldest('id')->first();
        if ($connection === null) {
            $alerts[] = ['level' => 'amber', 'title' => '尚未配置 WordPress 连接', 'detail' => '请在系统配置中保存站点地址和用户名。'];
        } elseif (filled($connection->last_error)) {
            $alerts[] = ['level' => 'red', 'title' => 'WordPress 连接最近一次检测失败', 'detail' => (string) $connection->last_error];
        } elseif ($connection->last_health_checked_at === null) {
            $alerts[] = ['level' => 'amber', 'title' => 'WordPress 连接还没有测试过', 'detail' => '建议在系统配置中点击「测试 WordPress 连接」。'];
        }

        $failedSources = TopicSource::query()->where('enabled', true)->whereNotNull('last_error')->count();
        if ($failedSources > 0) {
            $alerts[] = ['level' => 'amber', 'title' => "有 {$failedSources} 个热点来源最近抓取失败", 'detail' => '请到热点来源页查看失败原因。'];
        }

        return $alerts;
    }

    /**
     * Daily production report derived from audit events.
     *
     * @return array{generated:int, approved:int, manual:int, drafted:int, failed:int}
     */
    private function dailyReport(string $date): array
    {
        $counts = AuditEvent::query()
            ->whereIn('event_type', ['content_generated', 'content_review_passed', 'content_review_manual', 'wordpress_draft_written', 'wordpress_post_published', 'content_generation_failed', 'content_review_failed'])
            ->whereDate('created_at', $date)
            ->selectRaw('event_type, count(*) as total')
            ->groupBy('event_type')
            ->pluck('total', 'event_type');

        return [
            'generated' => (int) ($counts['content_generated'] ?? 0),
            'approved' => (int) ($counts['content_review_passed'] ?? 0),
            'manual' => (int) ($counts['content_review_manual'] ?? 0),
            'drafted' => (int) ($counts['wordpress_draft_written'] ?? 0) + (int) ($counts['wordpress_post_published'] ?? 0),
            'failed' => (int) ($counts['content_generation_failed'] ?? 0) + (int) ($counts['content_review_failed'] ?? 0),
        ];
    }

    /** @return list<array{date:string,generated:int,approved:int,manual:int,drafted:int,failed:int}> */
    private function reportHistory(): array
    {
        $rows = [];
        for ($days = 6; $days >= 0; $days--) {
            $date = now()->subDays($days)->toDateString();
            $report = $this->dailyReport($date);
            $rows[] = ['date' => $date] + $report;
        }

        return $rows;
    }
}
