<?php

namespace App\Models;

use App\Domain\Ai\AiProviderCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'is_secret'];

    protected function casts(): array
    {
        return ['is_secret' => 'boolean'];
    }

    public static function setValue(string $key, mixed $value): void
    {
        $secret = self::isSecretKey($key);
        $encoded = is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        self::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $secret ? Crypt::encryptString($encoded) : $encoded, 'is_secret' => $secret],
        );
    }

    public static function value(string $key, mixed $default = null): mixed
    {
        $setting = self::query()->where('key', $key)->first();
        if ($setting === null) {
            return $default;
        }

        $value = $setting->is_secret ? Crypt::decryptString((string) $setting->value) : $setting->value;
        return self::castValue($key, $value);
    }

    public static function configured(string $key): bool
    {
        return filled(self::value($key));
    }

    public static function applyToConfig(): void
    {
        $map = [
            'writing_ai_model' => 'content-ops.ai_default_model',
            'review_ai_model' => 'content-ops.review_ai_model',
            'writing_ai_provider' => 'content-ops.ai_default_provider',
            'review_ai_provider' => 'content-ops.review_ai_provider',
            'topic_daily_target' => 'content-ops.topic_daily_target',
            'review_pass_threshold' => 'content-ops.review_pass_threshold',
            'wp_default_status' => 'content-ops.wp_default_status',
            'playwright_enabled' => 'content-ops.playwright_enabled',
            'publish_mode' => 'content-ops.publish_mode',
            'publish_window_start' => 'content-ops.publish_window_start',
            'publish_window_end' => 'content-ops.publish_window_end',
            'publish_daily_max' => 'content-ops.publish_daily_max',
        ];

        foreach ($map as $key => $configKey) {
            $value = self::value($key);
            if ($value !== null && $value !== '') {
                config([$configKey => $value]);
            }
        }

        foreach (AiProviderCatalog::all() as $provider) {
            $id = $provider['id'];
            $apiKey = self::value(AiProviderCatalog::apiKeySettingKey($id));
            if ($id === 'openai' && ! filled($apiKey)) {
                $apiKey = self::value('ai_api_key');
            }
            if (filled($apiKey)) {
                config(["ai.providers.{$id}.key" => $apiKey]);
            }

            $baseUrl = self::value("ai_base_url_{$id}");
            if (filled($baseUrl)) {
                config(["ai.providers.{$id}.url" => rtrim((string) $baseUrl, '/')]);
            }
            if (! filled(config("ai.providers.{$id}.url")) && filled($provider['default_url'])) {
                config(["ai.providers.{$id}.url" => $provider['default_url']]);
            }
        }
    }

    private static function isSecretKey(string $key): bool
    {
        if (str_starts_with($key, 'ai_api_key_')) {
            return true;
        }

        return in_array($key, ['ai_api_key'], true);
    }

    private static function castValue(string $key, mixed $value): mixed
    {
        return match ($key) {
            'topic_daily_target', 'review_pass_threshold', 'publish_daily_max' => (int) $value,
            'playwright_enabled' => filter_var($value, FILTER_VALIDATE_BOOL),
            default => $value,
        };
    }
}
