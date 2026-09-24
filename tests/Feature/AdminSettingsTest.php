<?php

namespace Tests\Feature;

use App\Domain\Content\AiProviderCatalog;
use App\Models\AiConnection;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_exposes_two_role_cards_without_exposing_secrets(): void
    {
        AiConnection::create([
            'name' => 'DeepSeek 主账号',
            'provider' => 'deepseek',
            'base_url' => 'https://api.deepseek.com/v1',
            'api_key_encrypted' => 'secret-key',
        ]);

        $this->get('/admin/settings')
            ->assertOk()
            ->assertSee('生成 AI')
            ->assertSee('审核 AI')
            ->assertSee('获取模型')
            ->assertDontSee('secret-key')
            ->assertDontSee('<input name="writing_ai_model"');
    }

    public function test_operator_can_configure_a_provider_once_and_assign_different_connections_to_tasks(): void
    {
        $this->post('/admin/settings/connections', [
            'name' => 'GPT 内容账号',
            'provider' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'gpt-secret',
        ])->assertRedirect('/admin/settings');

        $gpt = AiConnection::query()->firstOrFail();
        $this->post('/admin/settings/connections', [
            'name' => 'DeepSeek 审核账号',
            'provider' => 'deepseek',
            'base_url' => 'https://api.deepseek.com/v1',
            'api_key' => 'deepseek-secret',
        ])->assertRedirect('/admin/settings');
        $deepseek = AiConnection::query()->where('provider', 'deepseek')->firstOrFail();

        $this->post('/admin/settings/routing', [
            'writing_connection_id' => $gpt->id,
            'writing_model' => 'gpt-4o-mini',
            'review_connection_id' => $deepseek->id,
            'review_model' => 'deepseek-chat',
        ])->assertRedirect('/admin/settings');

        $this->assertSame($gpt->id, (int) SystemSetting::value('writing_ai_connection_id'));
        $this->assertSame($deepseek->id, (int) SystemSetting::value('review_ai_connection_id'));
        $this->assertSame('gpt-4o-mini', config('content-ops.ai_default_model'));
        $this->assertSame('gpt-4o-mini', config('content-ops.writing_ai_model'));
        $this->assertSame('deepseek-chat', config('content-ops.review_ai_model'));
        $this->assertSame('openai', config('content-ops.ai_provider'));
        $this->assertSame('deepseek', config('content-ops.review_ai_provider'));
        $this->assertSame('gpt-secret', $gpt->fresh()->api_key_encrypted);
        $this->assertSame('deepseek-secret', $deepseek->fresh()->api_key_encrypted);
    }

    public function test_unknown_model_cannot_be_assigned_to_a_provider(): void
    {
        $connection = AiConnection::create(['name' => 'GPT', 'provider' => 'openai', 'api_key_encrypted' => 'secret']);

        $this->from('/admin/settings')->post('/admin/settings/routing', [
            'writing_connection_id' => $connection->id,
            'writing_model' => 'made-up-model',
            'review_connection_id' => $connection->id,
            'review_model' => array_key_first(AiProviderCatalog::models('openai')),
        ])->assertRedirect('/admin/settings')
            ->assertSessionHasErrors('writing_model');
    }

    public function test_role_cards_return_provider_models_and_save_generation_and_review_separately(): void
    {
        $this->get('/admin/settings/models?provider=openai')
            ->assertOk()
            ->assertJsonPath('provider', 'openai')
            ->assertJsonPath('models.gpt-4o-mini', 'GPT-4o mini');

        $this->post('/admin/settings/ai-role', [
            'role' => 'writing',
            'provider' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'gpt-secret',
            'model' => 'gpt-4o-mini',
        ])->assertRedirect('/admin/settings');

        $this->post('/admin/settings/ai-role', [
            'role' => 'review',
            'provider' => 'deepseek',
            'base_url' => 'https://api.deepseek.com/v1',
            'api_key' => 'deepseek-secret',
            'model' => 'deepseek-chat',
        ])->assertRedirect('/admin/settings');

        $this->assertSame('openai', AiConnection::find(SystemSetting::value('writing_ai_connection_id'))->provider);
        $this->assertSame('deepseek', AiConnection::find(SystemSetting::value('review_ai_connection_id'))->provider);
        $this->assertSame('gpt-4o-mini', SystemSetting::value('writing_ai_model'));
        $this->assertSame('deepseek-chat', SystemSetting::value('review_ai_model'));
    }

    public function test_writing_skill_can_be_selected_from_preset_catalog(): void
    {
        $this->get('/admin/settings')->assertOk()->assertSee('GEOFlow 印象文生成')->assertSee('GEOFlow 榜单文生成');

        $this->post('/admin/settings', [
            'topic_daily_target' => 4,
            'review_pass_threshold' => 70,
            'writing_skill_slug' => 'geoflow_ranking_article',
            'wp_default_status' => 'draft',
        ])->assertRedirect('/admin/settings');

        $this->assertSame('geoflow_ranking_article', SystemSetting::value('writing_skill_slug'));
    }
}
