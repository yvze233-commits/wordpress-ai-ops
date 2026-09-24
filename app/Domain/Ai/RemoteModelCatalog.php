<?php

namespace App\Domain\Ai;

use App\Domain\Content\AiProviderCatalog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class RemoteModelCatalog
{
    /** @return array{provider:string,base_url:string,models:array<string,string>,source:string} */
    public function fetch(string $provider, string $baseUrl, string $apiKey): array
    {
        if (! array_key_exists($provider, AiProviderCatalog::all())) {
            throw new RemoteModelCatalogException('不支持的 AI 服务商。', 422, 'unsupported_provider');
        }
        if (trim($apiKey) === '') {
            throw new RemoteModelCatalogException('请先填写 API 密钥，或先保存该服务商配置。', 422, 'missing_api_key');
        }

        $baseUrl = rtrim(trim($baseUrl), '/');
        if ($baseUrl === '') {
            throw new RemoteModelCatalogException('请先填写接口 URL。', 422, 'missing_base_url');
        }

        try {
            $response = $this->request($provider, $baseUrl.'/models', $apiKey);
            if ($response->status() === 404 && str_ends_with($baseUrl, '/v1')) {
                $response = $this->request($provider, substr($baseUrl, 0, -3).'/models', $apiKey);
            }
        } catch (ConnectionException) {
            throw new RemoteModelCatalogException('无法连接服务商接口，请检查 URL、网络或代理设置。', 502, 'connection_failed');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new RemoteModelCatalogException('API 密钥无效，或没有读取模型列表的权限。', 422, 'authentication_failed');
        }
        if ($response->failed()) {
            throw new RemoteModelCatalogException('服务商模型接口返回 HTTP '.$response->status().'，未能获取模型。', 502, 'provider_error');
        }

        $models = $this->normalize($response->json());
        if ($models === []) {
            throw new RemoteModelCatalogException('接口响应中没有可用模型，请检查服务商 URL 和账号权限。', 502, 'invalid_model_response');
        }

        return ['provider' => $provider, 'base_url' => $baseUrl, 'models' => $models, 'source' => 'remote'];
    }

    private function request(string $provider, string $url, string $apiKey): \Illuminate\Http\Client\Response
    {
        $request = Http::acceptJson()->timeout(15)->connectTimeout(5);
        if ($provider === 'anthropic') {
            return $request->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])->get($url);
        }

        return $request->withToken($apiKey)->get($url);
    }

    /** @return array<string,string> */
    private function normalize(mixed $payload): array
    {
        $items = is_array($payload) && isset($payload['data']) && is_array($payload['data'])
            ? $payload['data']
            : (is_array($payload) && isset($payload['models']) && is_array($payload['models']) ? $payload['models'] : []);
        $models = [];
        foreach ($items as $item) {
            $id = is_string($item) ? trim($item) : (is_array($item) ? trim((string) ($item['id'] ?? $item['model'] ?? '')) : '');
            if ($id !== '') {
                $models[$id] = is_array($item) && filled($item['name'] ?? null) ? (string) $item['name'] : $id;
            }
        }

        return $models;
    }
}
