<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Skills\SkillCatalog;
use App\Http\Controllers\Controller;
use App\Jobs\CreateDailyBatchJob;
use App\Models\ContentTask;
use App\Models\Skill;
use App\Models\WordPressConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;

class TaskController extends Controller
{
    public function index(Request $request): mixed
    {
        app(SkillCatalog::class)->seedBuiltIns();
        $tasks = ContentTask::query()->latest()->get();
        $writingSkills = Skill::query()->where('kind', 'writing')->where('enabled', true)->orderBy('name')->get();
        $reviewSkills = Skill::query()->where('kind', 'review')->where('enabled', true)->orderBy('name')->get();
        $wordpressConnections = WordPressConnection::query()->whereIn('status', ['active', 'healthy'])->orderBy('name')->get();
        return $request->expectsJson() ? response()->json(['data' => $tasks]) : view('admin.tasks.index', compact('tasks', 'writingSkills', 'reviewSkills', 'wordpressConnections'));
    }

    public function store(Request $request): mixed
    {
        $data = $request->validate($this->rules());
        $task = ContentTask::query()->create([...$this->taskData($data), 'settings' => $this->settings($request), 'status' => 'paused', 'enabled' => true]);
        return $request->expectsJson() ? response()->json(['data' => $task], 201) : redirect()->route('admin.tasks.index')->with('status', '任务已保存，当前为暂停状态。');
    }

    public function update(Request $request, ContentTask $task): mixed
    {
        $data = $request->validate($this->rules());
        $task->update([...$this->taskData($data), 'settings' => $this->settings($request)]);
        return $request->expectsJson() ? response()->json(['data' => $task->fresh()]) : redirect()->route('admin.tasks.index')->with('status', '任务配置已更新。');
    }

    public function start(Request $request, ContentTask $task): mixed
    {
        $task->update(['status' => 'running', 'enabled' => true]);
        return $this->redirectOrJson($request, '任务已启动。', $task);
    }

    public function pause(Request $request, ContentTask $task): mixed
    {
        $task->update(['status' => 'paused']);
        return $this->redirectOrJson($request, '任务已暂停，已在队列中的文章不会被删除。', $task);
    }

    public function run(Request $request, ContentTask $task): mixed
    {
        if ($task->status !== 'running') {
            return $this->redirectOrJson($request, '请先启动任务，再运行今日内容。', $task, 409);
        }
        Bus::dispatch(new CreateDailyBatchJob(now()->toDateString(), null, $task->id));
        return $this->redirectOrJson($request, '今日任务已加入队列。', $task, 202);
    }

    private function rules(): array
    {
        return ['name' => ['required', 'string', 'max:120'], 'daily_target' => ['required', 'integer', 'min:1', 'max:20'], 'writing_skill_slug' => ['required', 'exists:skills,slug'], 'review_skill_slug' => ['required', 'string', 'max:120'], 'review_pass_threshold' => ['required', 'integer', 'min:1', 'max:100'], 'publish_status' => ['required', 'in:draft,pending,publish'], 'schedule_time' => ['required', 'date_format:H:i'], 'topic_source_mode' => ['nullable', 'in:all,both,hot,titles,hot_only,title_only,热点和标题库,仅热点,仅标题库'], 'topic_window_hours' => ['nullable', 'integer', 'min:1', 'max:168'], 'topic_keywords' => ['nullable', 'string', 'max:500'], 'min_source_trust' => ['nullable', 'integer', 'min:0', 'max:100'], 'wordpress_connection_id' => ['nullable', 'exists:wordpress_connections,id'], 'require_images' => ['nullable', 'boolean'], 'require_source_links' => ['nullable', 'boolean']];
    }

    private function taskData(array $data): array
    {
        return [
            ...collect($data)->only(['name', 'daily_target', 'writing_skill_slug', 'review_skill_slug', 'review_pass_threshold', 'publish_status', 'schedule_time', 'topic_keywords', 'wordpress_connection_id'])->all(),
            'topic_window_hours' => (int) ($data['topic_window_hours'] ?? 72),
            'min_source_trust' => (int) ($data['min_source_trust'] ?? 50),
        ];
    }

    private function settings(Request $request): array
    {
        $mode = match ($request->string('topic_source_mode')->toString()) {
            'hot', 'hot_only', '仅热点' => 'hot',
            'titles', 'title_library', '仅标题库' => 'titles',
            default => 'both',
        };

        return ['topic_source_mode' => $mode, 'require_images' => $request->boolean('require_images'), 'require_source_links' => $request->boolean('require_source_links')];
    }

    private function redirectOrJson(Request $request, string $message, ContentTask $task, int $status = 200): mixed
    {
        return $request->expectsJson() ? response()->json(['message' => $message, 'data' => $task->fresh()], $status) : redirect()->route('admin.tasks.index')->with('status', $message);
    }
}
