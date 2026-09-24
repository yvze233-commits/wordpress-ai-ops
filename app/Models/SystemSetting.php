<?php

namespace App\Models;

use App\Domain\Content\AiProviderCatalog;
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
            'ai_provider' => 'content-ops.ai_provider',
            'writing_ai_model' => 'content-ops.ai_default_model',
            'review_ai_model' => 'content-ops.review_ai_model',
            'topic_daily_target' => 'content-ops.topic_daily_target',
            'review_pass_threshold' => 'content-ops.review_pass_threshold',
            'wp_default_status' => 'content-ops.wp_default_status',
            'playwright_enabled' => 'content-ops.playwright_enabled',
        ];

        foreach ($map as $key => $configKey) {
            $value = self::value($key);
            if ($value !== null && $value !== '') {
                config([$configKey => $value]);
            }
        }
        $writingModel = self::value('writing_ai_model');
        if ($writingModel !== null && $writingModel !== '') {
            config(['content-ops.ai_default_model' => $writingModel, 'content-ops.writing_ai_model' => $writingModel]);
        }
        $apiKey = self::value('ai_api_key');
        $provider = (string) config('content-ops.ai_provider', 'openai');
        $providerConfig = (array) config("ai.providers.{$provider}", []);
        $baseUrl = self::value('ai_base_url');
        if (filled($baseUrl) && $providerConfig !== []) {
            config(["ai.providers.{$provider}.url" => $baseUrl]);
        }
        if (filled($apiKey) && $providerConfig !== []) {
            config(["ai.providers.{$provider}.key" => $apiKey, 'ai.default' => $provider]);
        }

        foreach (['writing', 'review'] as $task) {
            $connectionId = self::value("{$task}_ai_connection_id");
            $connection = $connectionId ? AiConnection::query()->find((int) $connectionId) : null;
            if ($connection === null || ! $connection->enabled) {
                continue;
            }

            $driver = AiProviderCatalog::driver($connection->provider);
            $providerConfig = (array) config("ai.providers.{$driver}", []);
            if ($providerConfig === []) {
                continue;
            }
            config(["ai.providers.{$driver}.key" => $connection->api_key_encrypted]);
            if (filled($connection->base_url)) {
                config(["ai.providers.{$driver}.url" => $connection->base_url]);
            }
            config([
                "content-ops.{$task}_ai_provider" => $driver,
                "content-ops.{$task}_ai_model" => self::value("{$task}_ai_model", config("content-ops.{$task}_ai_model")),
            ]);
        }
    }

    private static function isSecretKey(string $key): bool
    {
        return in_array($key, ['ai_api_key'], true);
    }

    private static function castValue(string $key, mixed $value): mixed
    {
        return match ($key) {
            'topic_daily_target', 'review_pass_threshold' => (int) $value,
            'playwright_enabled' => filter_var($value, FILTER_VALIDATE_BOOL),
            default => $value,
        };
    }
}
