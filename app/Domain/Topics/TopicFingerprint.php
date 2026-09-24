<?php

namespace App\Domain\Topics;

final class TopicFingerprint
{
    public static function forUrl(?string $url): ?string
    {
        $normalized = (new TopicNormalizer)->url($url);

        return $normalized === null ? null : hash('sha256', 'url:'.$normalized);
    }

    /** @param array<string, mixed> $event */
    public static function forEvent(array $event): string
    {
        $normalizer = new TopicNormalizer;
        $payload = [
            'source_key' => trim((string) ($event['source_key'] ?? '')),
            'title' => $normalizer->title($event['title'] ?? null),
            'url' => $normalizer->url($event['url'] ?? null),
            'published_at' => ($event['published_at'] ?? null) instanceof \DateTimeInterface
                ? $event['published_at']->format(DATE_ATOM)
                : trim((string) ($event['published_at'] ?? '')),
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public static function fromUrl(?string $url): ?string
    {
        return self::forUrl($url);
    }
}
