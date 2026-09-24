<?php

namespace App\Domain\Content;

final class AiProviderCatalog
{
    /** @return array<string, array{label:string, driver:string, base_url:string, models:array<string, string>}> */
    public static function all(): array
    {
        return [
            'openai' => [
                'label' => 'GPT / OpenAI',
                'driver' => 'openai',
                'base_url' => 'https://api.openai.com/v1',
                'models' => ['gpt-4o-mini' => 'GPT-4o mini', 'gpt-4o' => 'GPT-4o', 'gpt-5-mini' => 'GPT-5 mini'],
            ],
            'deepseek' => [
                'label' => 'DeepSeek',
                'driver' => 'deepseek',
                'base_url' => 'https://api.deepseek.com/v1',
                'models' => ['deepseek-chat' => 'DeepSeek Chat', 'deepseek-reasoner' => 'DeepSeek Reasoner'],
            ],
            'doubao' => [
                'label' => '豆包',
                'driver' => 'openai-compatible',
                'base_url' => 'https://ark.cn-beijing.volces.com/api/v3',
                'models' => ['doubao-seed-1-6' => '豆包通用', 'doubao-seed-1-6-flash' => '豆包极速'],
            ],
            'anthropic' => [
                'label' => 'Claude / Anthropic',
                'driver' => 'anthropic',
                'base_url' => 'https://api.anthropic.com/v1',
                'models' => ['claude-sonnet-4-5' => 'Claude Sonnet', 'claude-haiku-4-5' => 'Claude Haiku'],
            ],
        ];
    }

    /** @return array<string, string> */
    public static function models(string $provider): array
    {
        return self::all()[$provider]['models'] ?? [];
    }

    public static function driver(string $provider): string
    {
        return self::all()[$provider]['driver'] ?? 'openai-compatible';
    }

    public static function baseUrl(string $provider): string
    {
        return self::all()[$provider]['base_url'] ?? '';
    }
}
