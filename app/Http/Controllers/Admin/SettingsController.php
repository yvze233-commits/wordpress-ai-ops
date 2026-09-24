<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Content\AiProviderCatalog;
use App\Http\Controllers\Controller;
use App\Models\AiConnection;
use App\Models\SystemSetting;
use App\Models\WordPressConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(Request $request): mixed
    {
        $connection = WordPressConnection::query()->oldest('id')->first();
        $aiConnections = AiConnection::query()->where('enabled', true)->orderBy('provider')->get();
        $settings = [
            'ai_provider' => config('content-ops.ai_provider'),
            'writing_ai_model' => config('content-ops.ai_default_model'),
            'review_ai_model' => config('content-ops.review_ai_model'),
            'topic_daily_target' => config('content-ops.topic_daily_target'),
            'review_pass_threshold' => config('content-ops.review_pass_threshold'),
            'wp_default_status' => config('content-ops.wp_default_status'),
            'playwright_enabled' => (bool) config('content-ops.playwright_enabled'),
            'ai_api_key_configured' => SystemSetting::configured('ai_api_key'),
            'ai_base_url' => SystemSetting::value('ai_base_url', config('ai.providers.'.config('content-ops.ai_provider', 'openai').'.url')),
        ];
        $assignments = [
            'writing' => ['connection_id' => SystemSetting::value('writing_ai_connection_id'), 'model' => SystemSetting::value('writing_ai_model', config('content-ops.ai_default_model'))],
            'review' => ['connection_id' => SystemSetting::value('review_ai_connection_id'), 'model' => SystemSetting::value('review_ai_model', config('content-ops.review_ai_model'))],
        ];

        if ($request->expectsJson()) {
            return response()->json(['data' => compact('settings', 'connection', 'aiConnections', 'assignments')]);
        }

        return view('admin.settings.index', ['settings' => $settings, 'connection' => $connection, 'aiConnections' => $aiConnections, 'assignments' => $assignments, 'providerCatalog' => AiProviderCatalog::all()]);
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
