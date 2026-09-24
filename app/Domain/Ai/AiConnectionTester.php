<?php

namespace App\Domain\Ai;

use App\Models\SystemSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class AiConnectionTester
{
    public const TIMEOUT_SECONDS = 12;

    /**
     * Call GET {base_url}/models on the provider: the cheapest request that validates address, key and permissions
     * without generating any content.
     *
     * @return array{ok:bool, category:string, message:string, models:list<string>, checked_at:string}
     */
    public function test(string $provider): array
    {
        $result = $this->callModelsEndpoint($provider);

        SystemSetting::setValue("ai_test_result_{$provider}", json_encode([
            'ok' => $result['ok'],
            'category' => $result['category'],
            'message' => $result['message'],
        ], JSON_UNESCAPED_UNICODE));

        return $result;
    }

    /** @return array{ok:bool, category:string, message:string, models:list<string>, checked_at:string} */
    public function callModelsEndpoint(string $provider): array
    {
        $checkedAt = now()->toIso8601String();
        $definition = AiProviderCatalog::definition($provider);
        if ($definition === null) {
            return ['ok' => false, 'category' => 'unknown_provider', 'message' => '未知的 AI 服务商。', 'models' => [], 'checked_at' => $checkedAt];
        }

        $baseUrl = AiProviderCatalog::baseUrl($provider);
        $apiKey = AiProviderCatalog::apiKey($provider);

        if ($baseUrl === null || $baseUrl === '') {
            return ['ok' => false, 'category' => 'missing_url', 'message' => '未配置接口地址，请先填写 Base URL。', 'models' => [], 'checked_at' => $checkedAt];
        }
        if (! preg_match('#^https?://#i', $baseUrl)) {
            return ['ok' => false, 'category' => 'missing_url', 'message' => '接口地址必须以 http:// 或 https:// 开头。', 'models' => [], 'checked_at' => $checkedAt];
        }
        if (filled($apiKey) === false) {
            return ['ok' => false, 'category' => 'missing_key', 'message' => '未配置 API 密钥，请先保存密钥。', 'models' => [], 'checked_at' => $checkedAt];
        }

        try {
            $response = Http::acceptJson()
                ->withToken((string) $apiKey)
                ->timeout(self::TIMEOUT_SECONDS)
                ->connectTimeout(6)
                ->get(rtrim($baseUrl, '/').'/models');
        } catch (ConnectionException $exception) {
            return ['ok' => false, 'category' => 'network', 'message' => '网络错误：无法在 '.self::TIMEOUT_SECONDS.' 秒内连接到接口地址，请检查地址、网络或代理。'.$this->brief($exception->getMessage()), 'models' => [], 'checked_at' => $checkedAt];
        } catch (Throwable $exception) {
            return ['ok' => false, 'category' => 'network', 'message' => '请求失败：'.$this->brief($exception->getMessage()), 'models' => [], 'checked_at' => $checkedAt];
        }

        if ($response->status() === 401) {
            return ['ok' => false, 'category' => 'auth', 'message' => '认证失败（401）：API 密钥无效或已过期。', 'models' => [], 'checked_at' => $checkedAt];
        }
        if ($response->status() === 403) {
            return ['ok' => false, 'category' => 'forbidden', 'message' => '权限不足（403）：密钥有效但无权访问该接口，请检查密钥权限或分组。', 'models' => [], 'checked_at' => $checkedAt];
        }
        if ($response->status() === 404) {
            return ['ok' => false, 'category' => 'not_found', 'message' => '接口不存在（404）：Base URL 路径可能不正确，确认是否需要以 /v1 结尾。', 'models' => [], 'checked_at' => $checkedAt];
        }
        if ($response->status() >= 500) {
            return ['ok' => false, 'category' => 'server', 'message' => '服务商错误（'.$response->status().'）：上游服务暂时不可用，可稍后重试。'.$this->brief($response->body()), 'models' => [], 'checked_at' => $checkedAt];
        }
        if ($response->failed()) {
            return ['ok' => false, 'category' => 'client_error', 'message' => '请求被拒绝（'.$response->status().'）：'.$this->brief($response->body()), 'models' => [], 'checked_at' => $checkedAt];
        }

        $models = $this->extractModelIds($response->json());
        if ($models === null) {
            return ['ok' => false, 'category' => 'parse', 'message' => '接口返回格式无法识别（不是 OpenAI /models 结构）。连接可能正常，请手动填写模型 ID。', 'models' => [], 'checked_at' => $checkedAt];
        }

        return [
            'ok' => true,
            'category' => 'success',
            'message' => $models === []
                ? '连接成功，但服务未返回模型列表，请手动填写模型 ID。'
                : '连接成功，获取到 '.count($models).' 个模型。',
            'models' => $models,
            'checked_at' => $checkedAt,
        ];
    }

    /**
     * Persisted test result for display on the settings page.
     *
     * @return array{ok:bool, category:string, message:string, checked_at:?string}|null
     */
    public static function lastResult(string $provider): ?array
    {
        $raw = SystemSetting::value("ai_test_result_{$provider}");
        if (! filled($raw)) {
            return null;
        }
        $decoded = json_decode((string) $raw, true);
        if (! is_array($decoded)) {
            return null;
        }

        return [
            'ok' => (bool) ($decoded['ok'] ?? false),
            'category' => (string) ($decoded['category'] ?? 'unknown'),
            'message' => (string) ($decoded['message'] ?? ''),
            'checked_at' => isset($decoded['checked_at']) ? (string) $decoded['checked_at'] : null,
        ];
    }

    /**
     * @return list<string>|null null when the payload is not an OpenAI-style model list
     */
    private function extractModelIds(mixed $payload): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        $items = $payload['data'] ?? $payload['models'] ?? null;
        if (! is_array($items)) {
            return null;
        }

        $ids = [];
        foreach ($items as $item) {
            $id = is_array($item) ? ($item['id'] ?? $item['name'] ?? null) : $item;
            if (is_string($id) && trim($id) !== '') {
                $ids[] = trim($id);
            }
        }
        sort($ids, SORT_NATURAL | SORT_FLAG_CASE);

        return array_values(array_unique($ids));
    }

    private function brief(string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        return $text === '' ? '' : ' 详情：'.mb_substr($text, 0, 160);
    }
}
