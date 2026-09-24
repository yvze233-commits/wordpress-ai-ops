<?php

namespace App\Domain\Topics;

use Illuminate\Support\Str;

final class TopicNormalizer
{
    public static function normalizeTitle(?string $title): string
    {
        return (new self)->title($title);
    }

    public static function normalizeUrl(?string $url): ?string
    {
        return (new self)->url($url);
    }

    public function title(?string $title): string
    {
        $title = trim((string) $title);
        $title = strtr($title, [
            '，' => ',', '。' => '.', '！' => '!', '？' => '?', '：' => ':',
            '；' => ';', '（' => '(', '）' => ')', '【' => '[', '】' => ']',
            '［' => '[', '］' => ']', '、' => ',', '“' => '"', '”' => '"',
            '‘' => "'", '’' => "'",
        ]);

        $title = (string) preg_replace('/\s+/u', ' ', $title);
        $title = (string) preg_replace('/\s+([,.!?:;\)\]])/u', '$1', $title);
        $title = (string) preg_replace('/([,.!?:;\(\[])\s+/u', '$1', $title);

        return Str::lower(trim($title));
    }

    public function url(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['host'])) {
            return $url;
        }

        $scheme = Str::lower($parts['scheme'] ?? 'https');
        $host = Str::lower($parts['host']);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '/';
        $path = $path === '/' ? '' : rtrim($path, '/');

        parse_str($parts['query'] ?? '', $query);
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid', 'mc_cid', 'mc_eid'] as $trackingKey) {
            unset($query[$trackingKey]);
        }
        ksort($query);
        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return $scheme.'://'.$host.$port.$path.($queryString === '' ? '' : '?'.$queryString);
    }
}
