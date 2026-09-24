<?php

namespace App\Infrastructure\WordPress;

use App\Models\WordPressConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

final class WordPressRequestFactory
{
    public function baseUrl(WordPressConnection $connection): string
    {
        $url = rtrim(trim($connection->base_url), '/');
        if ($url === '') {
            throw new InvalidArgumentException('WordPress base URL is required.');
        }
        if (! preg_match('#^https?://#i', $url)) {
            throw new InvalidArgumentException('WordPress base URL must include its scheme.');
        }
        if (! app()->environment(['local', 'testing']) && ! str_starts_with(strtolower($url), 'https://')) {
            throw new InvalidArgumentException('WordPress connections must use HTTPS outside local/testing environments.');
        }

        $url = preg_replace('#/wp-json(?:/wp/v2)?$#i', '', $url) ?: $url;

        return $url.'/wp-json/wp/v2';
    }

    public function endpoint(WordPressConnection $connection, string $path = ''): string
    {
        return rtrim($this->baseUrl($connection), '/').'/'.ltrim($path, '/');
    }

    public function make(WordPressConnection $connection): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withBasicAuth((string) $connection->username, (string) $connection->application_password_encrypted)
            ->timeout((int) config('content-ops.wp_request_timeout', 30))
            ->connectTimeout(min(10, (int) config('content-ops.wp_request_timeout', 30)));
    }
}
