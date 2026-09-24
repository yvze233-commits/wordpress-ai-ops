<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_returns_runtime_configuration_without_exposing_secrets(): void
    {
        SystemSetting::setValue('ai_api_key', 'secret-key');
        SystemSetting::setValue('wordpress_application_password', 'secret-password');
        SystemSetting::setValue('topic_daily_target', 6);

        $this->get('/admin/settings')
            ->assertOk()
            ->assertSee('已配置')
            ->assertSee('6')
            ->assertDontSee('secret-key')
            ->assertDontSee('secret-password');
    }

    public function test_settings_page_lists_supported_providers(): void
    {
        $this->get('/admin/settings')
            ->assertOk()
            ->assertSee('DeepSeek')
            ->assertSee('豆包（火山方舟）')
            ->assertSee('中转站')
            ->assertSee('测试连接')
            ->assertSee('获取模型列表');
    }

    public function test_browser_can_save_settings_and_runtime_config_uses_database_value(): void
    {
        $this->post('/admin/settings', [
            'writing_ai_provider' => 'openai',
            'writing_ai_model' => 'gpt-5-mini',
            'review_ai_provider' => 'openai',
            'review_ai_model' => 'gpt-5-mini',
            'ai_api_key_openai' => 'new-secret',
            'topic_daily_target' => 5,
            'review_pass_threshold' => 82,
            'publish_mode' => 'draft_only',
            'publish_window_start' => '08:00',
            'publish_window_end' => '22:00',
            'publish_daily_max' => 0,
            'wp_default_status' => 'draft',
            'playwright_enabled' => '1',
        ])->assertRedirect('/admin/settings');

        $this->assertSame('gpt-5-mini', config('content-ops.ai_default_model'));
        $this->assertSame('gpt-5-mini', config('content-ops.review_ai_model'));
        $this->assertSame('openai', config('content-ops.ai_default_provider'));
        $this->assertSame(5, config('content-ops.topic_daily_target'));
        $this->assertSame(82, config('content-ops.review_pass_threshold'));
        $this->assertTrue(config('content-ops.playwright_enabled'));
        $this->assertSame('new-secret', SystemSetting::value('ai_api_key_openai'));
        $this->assertSame('new-secret', config('ai.providers.openai.key'));
    }

    public function test_provider_keys_and_base_urls_are_saved_encrypted_and_applied(): void
    {
        $this->post('/admin/settings', [
            'writing_ai_provider' => 'deepseek',
            'writing_ai_model' => 'deepseek-chat',
            'review_ai_provider' => 'relay',
            'review_ai_model' => 'gpt-4o-mini',
            'ai_api_key_deepseek' => 'sk-deepseek-secret',
            'ai_api_key_relay' => 'sk-relay-secret',
            'ai_base_url_relay' => 'https://relay.example.com/v1/',
            'topic_daily_target' => 4,
            'review_pass_threshold' => 70,
            'publish_mode' => 'draft_only',
            'publish_window_start' => '08:00',
            'publish_window_end' => '22:00',
            'publish_daily_max' => 0,
            'wp_default_status' => 'draft',
        ])->assertRedirect('/admin/settings');

        $this->assertSame('deepseek-chat', config('content-ops.ai_default_model'));
        $this->assertSame('deepseek', config('content-ops.ai_default_provider'));
        $this->assertSame('relay', config('content-ops.review_ai_provider'));

        $this->assertSame('sk-deepseek-secret', SystemSetting::value('ai_api_key_deepseek'));
        $this->assertSame('sk-relay-secret', SystemSetting::value('ai_api_key_relay'));
        $this->assertNotSame('sk-deepseek-secret', SystemSetting::query()->where('key', 'ai_api_key_deepseek')->value('value'));

        $this->assertSame('sk-deepseek-secret', config('ai.providers.deepseek.key'));
        $this->assertSame('sk-relay-secret', config('ai.providers.relay.key'));
        $this->assertSame('https://relay.example.com/v1', config('ai.providers.relay.url'));
    }

    public function test_legacy_shared_openai_key_still_applies_when_no_provider_key_saved(): void
    {
        SystemSetting::setValue('ai_api_key', 'legacy-key');
        SystemSetting::applyToConfig();

        $this->assertSame('legacy-key', config('ai.providers.openai.key'));
    }

    public function test_model_list_endpoint_returns_sorted_model_ids(): void
    {
        SystemSetting::setValue('ai_api_key_deepseek', 'sk-test');

        Http::fake([
            'https://api.deepseek.com/v1/models' => Http::response([
                'data' => [['id' => 'deepseek-reasoner'], ['id' => 'deepseek-chat']],
            ]),
        ]);

        $this->getJson('/admin/settings/ai/deepseek/models')
            ->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.models', ['deepseek-chat', 'deepseek-reasoner']);
    }

    public function test_model_list_endpoint_reports_auth_failure(): void
    {
        SystemSetting::setValue('ai_api_key_deepseek', 'sk-bad');

        Http::fake(['https://api.deepseek.com/v1/models' => Http::response(['error' => ['message' => 'Invalid key']], 401)]);

        $this->getJson('/admin/settings/ai/deepseek/models')
            ->assertStatus(422)
            ->assertJsonPath('data.ok', false)
            ->assertJsonPath('data.category', 'auth')
            ->assertJsonPath('data.message', fn (string $message) => str_contains($message, '认证失败'));
    }

    public function test_provider_test_endpoint_requires_a_key_before_calling_out(): void
    {
        Http::fake();

        $this->postJson('/admin/settings/ai/relay/test')
            ->assertStatus(422)
            ->assertJsonPath('data.category', 'missing_url');

        SystemSetting::setValue('ai_base_url_relay', 'https://relay.example.com/v1');
        $this->postJson('/admin/settings/ai/relay/test')
            ->assertStatus(422)
            ->assertJsonPath('data.category', 'missing_key');

        $this->postJson('/admin/settings/ai/unknown-provider/test')->assertStatus(404);
        Http::assertNothingSent();
    }

    public function test_provider_test_endpoint_persists_last_result(): void
    {
        SystemSetting::setValue('ai_api_key_doubao', 'sk-doubao');
        Http::fake(['https://ark.cn-beijing.volces.com/api/v3/models' => Http::response(['data' => [['id' => 'doubao-seed-1-6-250615']]])]);

        $this->postJson('/admin/settings/ai/doubao/test')
            ->assertOk()
            ->assertJsonPath('data.ok', true);

        $last = SystemSetting::value('ai_test_result_doubao');
        $this->assertNotNull($last);
        $this->assertTrue(json_decode((string) $last, true)['ok']);

        $this->get('/admin/settings')->assertOk()->assertSee('上次测试成功');
    }

    public function test_wordpress_test_endpoint_validates_saved_connection_and_records_health(): void
    {
        SystemSetting::setValue('wordpress_application_password', 'saved-app-password');
        $connection = \App\Models\WordPressConnection::query()->create([
            'name' => '主站点',
            'base_url' => 'https://example.test',
            'username' => 'ops',
            'application_password_encrypted' => 'saved-app-password',
            'default_post_status' => 'draft',
            'status' => 'inactive',
        ]);

        Http::fake([
            'https://example.test/wp-json/wp/v2/users/me*' => Http::response(['id' => 1, 'name' => 'ops']),
        ]);

        $this->postJson('/admin/settings/wordpress/test')
            ->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.message', fn (string $message) => str_contains($message, '连接成功'));

        $connection->refresh();
        $this->assertSame('active', $connection->status);
        $this->assertNotNull($connection->last_health_checked_at);
        $this->assertNull($connection->last_error);
    }

    public function test_wordpress_test_endpoint_reports_auth_failure_without_saving(): void
    {
        \App\Models\WordPressConnection::query()->create([
            'name' => '主站点',
            'base_url' => 'https://example.test',
            'username' => 'ops',
            'application_password_encrypted' => 'saved-app-password',
            'default_post_status' => 'draft',
            'status' => 'active',
        ]);

        Http::fake(['https://example.test/wp-json/wp/v2/users/me*' => Http::response(['code' => 'invalid_username_or_password'], 401)]);

        $this->postJson('/admin/settings/wordpress/test')
            ->assertStatus(422)
            ->assertJsonPath('data.ok', false)
            ->assertJsonPath('data.category', 'auth');

        $connection = \App\Models\WordPressConnection::query()->oldest('id')->first();
        $this->assertSame('active', $connection->status);
        $this->assertNotNull($connection->last_health_checked_at);
        $this->assertNotNull($connection->last_error);
    }
}
