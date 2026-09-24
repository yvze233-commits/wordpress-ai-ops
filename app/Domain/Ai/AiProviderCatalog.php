<?php

namespace App\Domain\Ai;

use Illuminate\Support\Facades\Config;

final class AiProviderCatalog
{
    public const WRITING_PROVIDER_KEY = 'content-ops.ai_default_provider';

    public const REVIEW_PROVIDER_KEY = 'content-ops.review_ai_provider';

    /** @return list<array{id:string,label:string,driver:string,default_url:?string,url_editable:bool,models_url:string,hint:string}> */
    public static function all(): array
    {
        return [
            [
                'id' => 'openai',
                'label' => 'OpenAI / GPT',
                'driver' => 'openai',
                'default_url' => 'https://api.openai.com/v1',
                'url_editable' => true,
                'models_url' => 'https://api.openai.com/v1/models',
                'hint' => '官方 OpenAI 接口，也支持填写兼容地址覆盖默认。',
            ],
            [
                'id' => 'deepseek',
                'label' => 'DeepSeek',
                'driver' => 'deepseek',
                'default_url' => 'https://api.deepseek.com/v1',
                'url_editable' => true,
                'models_url' => 'https://api.deepseek.com/v1/models',
                'hint' => 'DeepSeek 官方接口，常用模型 deepseek-chat、deepseek-reasoner。',
            ],
            [
                'id' => 'doubao',
                'label' => '豆包（火山方舟）',
                'driver' => 'openai-compatible',
                'default_url' => 'https://ark.cn-beijing.volces.com/api/v3',
                'url_editable' => true,
                'models_url' => 'https://ark.cn-beijing.volces.com/api/v3/models',
                'hint' => '火山方舟 OpenAI 兼容接口，模型 ID 形如 doubao-seed-1-6-250615，也可填写 ep- 推理接入点。',
            ],
            [
                'id' => 'relay',
                'label' => '中转站（自定义 OpenAI 兼容）',
                'driver' => 'openai-compatible',
                'default_url' => null,
                'url_editable' => true,
                'models_url' => null,
                'hint' => '适用于 one-api / new-api 等 OpenAI 兼容中转，需填写完整 Base URL（以 /v1 结尾）。',
            ],
        ];
    }

    public static function definition(string $provider): ?array
    {
        foreach (self::all() as $definition) {
            if ($definition['id'] === $provider) {
                return $definition;
            }
        }

        return null;
    }

    public static function isValidProvider(string $provider): bool
    {
        return self::definition($provider) !== null;
    }

    public static function label(string $provider): string
    {
        return self::definition($provider)['label'] ?? $provider;
    }

    /**
     * Resolve the effective base URL for a provider: saved setting first, then .env/config, then catalog default.
     */
    public static function baseUrl(string $provider): ?string
    {
        $saved = trim((string) \App\Models\SystemSetting::value("ai_base_url_{$provider}", ''));
        if ($saved !== '') {
            return rtrim($saved, '/');
        }

        $configured = trim((string) Config::get("ai.providers.{$provider}.url", ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        return self::definition($provider)['default_url'] ?? null;
    }

    public static function apiKeySettingKey(string $provider): string
    {
        return "ai_api_key_{$provider}";
    }

    /**
     * The runtime API key: per-provider setting first, then the legacy shared ai_api_key for OpenAI.
     */
    public static function apiKey(string $provider): ?string
    {
        $key = \App\Models\SystemSetting::value(self::apiKeySettingKey($provider));
        if (filled($key)) {
            return (string) $key;
        }

        if ($provider === 'openai') {
            $legacy = \App\Models\SystemSetting::value('ai_api_key');
            if (filled($legacy)) {
                return (string) $legacy;
            }
        }

        return filled(Config::get("ai.providers.{$provider}.key")) ? (string) Config::get("ai.providers.{$provider}.key") : null;
    }
}
