<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Content\AiProviderCatalog;
use App\Domain\Ai\RemoteModelCatalog;
use App\Domain\Ai\RemoteModelCatalogException;
use App\Domain\Skills\SkillCatalog;
use App\Http\Controllers\Controller;
use App\Models\AiConnection;
use App\Models\Skill;
use App\Models\SystemSetting;
use App\Models\WordPressConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(Request $request): mixed
    {
        app(SkillCatalog::class)->seedBuiltIns();
        $connection = WordPressConnection::query()->oldest('id')->first();
        $aiConnections = AiConnection::query()->where('enabled', true)->orderBy('provider')->get();
        $writingSkills = Skill::query()->where('kind', 'writing')->where('enabled', true)->orderBy('name')->get();
        $settings = [
            'ai_provider' => config('content-ops.ai_provider'),
            'writing_ai_model' => config('content-ops.ai_default_model'),
            'review_ai_model' => config('content-ops.review_ai_model'),
            'topic_daily_target' => config('content-ops.topic_daily_target'),
            'review_pass_threshold' => config('content-ops.review_pass_threshold'),
            'writing_skill_slug' => SystemSetting::value('writing_skill_slug', SkillCatalog::DEFAULT_WRITING_SLUG),
            'wp_default_status' => config('content-ops.wp_default_status'),
            'playwright_enabled' => (bool) config('content-ops.playwright_enabled'),
            'ai_api_key_configured' => SystemSetting::configured('ai_api_key'),
            'ai_base_url' => SystemSetting::value('ai_base_url', config('ai.providers.'.config('content-ops.ai_provider', 'openai').'.url')),
        ];
        $assignments = [
            'writing' => ['connection_id' => SystemSetting::value('writing_ai_connection_id'), 'model' => SystemSetting::value('writing_ai_model', config('content-ops.ai_default_model'))],
            'review' => ['connection_id' => SystemSetting::value('review_ai_connection_id'), 'model' => SystemSetting::value('review_ai_model', config('content-ops.review_ai_model'))],
        ];
        $roleConfigs = [];
        foreach (['writing', 'review'] as $role) {
            $roleConnection = $assignments[$role]['connection_id']
                ? $aiConnections->firstWhere('id', (int) $assignments[$role]['connection_id'])
                : null;
            $roleConfigs[$role] = [
                'provider' => $roleConnection?->provider ?? 'openai',
                'base_url' => $roleConnection?->base_url ?? AiProviderCatalog::baseUrl('openai'),
                'model' => $assignments[$role]['model'],
                'configured' => $roleConnection !== null,
                'connection_id' => $roleConnection?->id,
            ];
        }

        if ($request->expectsJson()) {
            return response()->json(['data' => compact('settings', 'connection', 'aiConnections', 'assignments', 'roleConfigs', 'writingSkills')]);
        }

        return view('admin.settings.index', ['settings' => $settings, 'connection' => $connection, 'aiConnections' => $aiConnections, 'assignments' => $assignments, 'roleConfigs' => $roleConfigs, 'writingSkills' => $writingSkills, 'providerCatalog' => AiProviderCatalog::all()]);
    }

    public function models(Request $request, RemoteModelCatalog $catalog): JsonResponse
    {
        if (! $request->isMethod('post')) {
            return response()->json(['message' => '获取模型需要提交当前卡片的 API 密钥，不能使用预设列表代替。'], 405);
        }

        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:'.implode(',', array_keys(AiProviderCatalog::all()))],
            'base_url' => ['required', 'url', 'max:500'],
            'api_key' => ['nullable', 'string', 'max:500'],
        ]);
        $connection = AiConnection::query()->where('provider', $validated['provider'])->where('enabled', true)->first();
        $apiKey = filled($validated['api_key'] ?? null) ? (string) $validated['api_key'] : (string) ($connection?->api_key_encrypted ?? '');

        try {
            return response()->json($catalog->fetch($validated['provider'], $validated['base_url'], $apiKey));
        } catch (RemoteModelCatalogException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'code' => $exception->reason], $exception->statusCode);
        }
    }

    public function saveRole(Request $request, RemoteModelCatalog $catalog): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'in:writing,review'],
            'provider' => ['required', 'string', 'in:'.implode(',', array_keys(AiProviderCatalog::all()))],
            'base_url' => ['required', 'url', 'max:500'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'model' => ['required', 'string'],
        ]);

        $aiConnection = AiConnection::query()->where('provider', $validated['provider'])->first();
        $apiKey = filled($validated['api_key'] ?? null) ? (string) $validated['api_key'] : (string) ($aiConnection?->api_key_encrypted ?? '');
        try {
            $remoteModels = $catalog->fetch($validated['provider'], $validated['base_url'], $apiKey)['models'];
        } catch (RemoteModelCatalogException $exception) {
            return back()->withErrors(['api_key' => $exception->getMessage()])->withInput();
        }
        if (! array_key_exists($validated['model'], $remoteModels)) {
            return back()->withErrors(['model' => '所选模型不在该服务商当前返回的模型列表中，请重新获取模型。'])->withInput();
        }

        $aiConnection ??= new AiConnection;
        $aiConnection->fill([
            'name' => AiProviderCatalog::all()[$validated['provider']]['label'],
            'provider' => $validated['provider'],
            'base_url' => rtrim($validated['base_url'], '/'),
            'enabled' => true,
            'status' => 'active',
            'last_error' => null,
        ]);
        if (filled($validated['api_key'] ?? null)) {
            $aiConnection->api_key_encrypted = $validated['api_key'];
        }
        $aiConnection->save();

        $role = $validated['role'];
        SystemSetting::setValue("{$role}_ai_connection_id", $aiConnection->id);
        SystemSetting::setValue("{$role}_ai_model", $validated['model']);
        SystemSetting::applyToConfig();

        return redirect()->route('admin.settings.index')->with('status', ($role === 'writing' ? '生成 AI' : '审核 AI').' 配置已保存。');
    }

    public function storeConnection(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'provider' => ['required', 'string', 'in:'.implode(',', array_keys(AiProviderCatalog::all()))],
            'base_url' => ['nullable', 'url', 'max:500'],
            'api_key' => ['nullable', 'string', 'max:500'],
        ]);
        $existing = AiConnection::query()->where('provider', $validated['provider'])->first();
        if ($existing === null && blank($validated['api_key'] ?? null)) {
            return back()->withErrors(['api_key' => '新建服务商连接必须填写 API 密钥。'])->withInput();
        }
        $catalog = AiProviderCatalog::all()[$validated['provider']];
        $aiConnection = $existing ?? new AiConnection;
        $aiConnection->fill([
            'name' => $validated['name'],
            'provider' => $validated['provider'],
            'base_url' => $validated['base_url'] ?: $catalog['base_url'],
            'enabled' => true,
            'status' => 'active',
        ]);
        if (filled($validated['api_key'] ?? null)) {
            $aiConnection->api_key_encrypted = $validated['api_key'];
        }
        $aiConnection->save();

        return redirect()->route('admin.settings.index')->with('status', $existing ? 'AI 服务商连接已更新。' : 'AI 服务商连接已添加。');
    }

    public function updateRouting(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'writing_connection_id' => ['required', 'integer', 'exists:ai_connections,id'],
            'writing_model' => ['required', 'string'],
            'review_connection_id' => ['required', 'integer', 'exists:ai_connections,id'],
            'review_model' => ['required', 'string'],
        ]);
        foreach (['writing', 'review'] as $task) {
            $connection = AiConnection::query()->findOrFail((int) $validated["{$task}_connection_id"]);
            if (! array_key_exists($validated["{$task}_model"], AiProviderCatalog::models($connection->provider))) {
                return back()->withErrors(["{$task}_model" => '所选模型不属于当前服务商，请重新选择。'])->withInput();
            }
            SystemSetting::setValue("{$task}_ai_connection_id", $connection->id);
            SystemSetting::setValue("{$task}_ai_model", $validated["{$task}_model"]);
        }
        SystemSetting::applyToConfig();

        return redirect()->route('admin.settings.index')->with('status', 'AI 任务分配已保存。');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'topic_daily_target' => ['required', 'integer', 'min:1', 'max:20'],
            'review_pass_threshold' => ['required', 'integer', 'min:1', 'max:100'],
            'writing_skill_slug' => ['required', 'string', 'exists:skills,slug'],
            'wp_default_status' => ['required', 'in:draft,publish,pending'],
            'playwright_enabled' => ['nullable', 'boolean'],
            'wp_name' => ['nullable', 'string', 'max:120'],
            'wp_base_url' => ['nullable', 'url', 'max:500'],
            'wp_username' => ['nullable', 'string', 'max:120'],
            'wp_application_password' => ['nullable', 'string', 'max:500'],
        ]);

        foreach (['topic_daily_target', 'review_pass_threshold', 'wp_default_status'] as $key) {
            SystemSetting::setValue($key, $validated[$key]);
        }
        $writingSkill = Skill::query()->where('slug', $validated['writing_skill_slug'])->where('kind', 'writing')->where('enabled', true)->first();
        if ($writingSkill === null) {
            return back()->withErrors(['writing_skill_slug' => '请选择启用中的写作 Skill。'])->withInput();
        }
        SystemSetting::setValue('writing_skill_slug', $writingSkill->slug);
        SystemSetting::setValue('playwright_enabled', $request->boolean('playwright_enabled'));
        SystemSetting::applyToConfig();

        if (filled($validated['wp_base_url'] ?? null) && filled($validated['wp_username'] ?? null)) {
            $connection = WordPressConnection::query()->oldest('id')->first() ?? new WordPressConnection;
            $connection->fill([
                'name' => $validated['wp_name'] ?: '主 WordPress 站点',
                'base_url' => rtrim($validated['wp_base_url'], '/'),
                'username' => $validated['wp_username'],
                'default_post_status' => $validated['wp_default_status'],
                'status' => 'active',
            ]);
            if (filled($validated['wp_application_password'] ?? null)) {
                $connection->application_password_encrypted = $validated['wp_application_password'];
            }
            $connection->save();
        }

        return redirect()->route('admin.settings.index')->with('status', '配置已保存，后续任务会使用新设置。');
    }
}
