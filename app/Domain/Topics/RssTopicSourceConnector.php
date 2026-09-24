<?php

namespace App\Domain\Topics;

use App\Models\TopicSource;
use Carbon\Carbon;
use SimpleXMLElement;

final class RssTopicSourceConnector extends AbstractTopicSourceConnector
{
    public function collect(TopicSource $source): array
    {
        $body = $this->fetch($source)->body();
        $xml = @simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);

        if ($xml === false) {
            throw new MalformedTopicSourceException("Topic source [{$source->name}] returned invalid XML.");
        }

        $nodes = isset($xml->channel->item) ? $xml->channel->item : $xml->item;
        if (! isset($nodes) && isset($xml->entry)) {
            $nodes = $xml->entry;
        }

        $items = [];
        foreach ($nodes ?? [] as $node) {
            $title = trim((string) ($node->title ?? ''));
            if ($title === '') {
                continue;
            }

            $url = $this->link($node);
            $sourceKey = trim((string) ($node->guid ?? $node->id ?? '')) ?: ($url ?: $title);
            $publishedAt = $this->date($node->pubDate ?? $node->published ?? $node->updated ?? null);
            $raw = json_decode(json_encode($node, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true) ?: [];

            $items[] = [
                'source_key' => $sourceKey,
                'title' => $title,
                'summary' => $this->nullableString($node->description ?? $node->summary ?? null),
                'url' => $url,
                'published_at' => $publishedAt,
                'raw_payload' => $raw,
            ];
        }

        return $items;
    }

    private function link(SimpleXMLElement $node): ?string
    {
        if (isset($node->link)) {
            $link = $node->link;
            $href = trim((string) ($link['href'] ?? ''));

            return $href !== '' ? $href : (trim((string) $link) ?: null);
        }

        return null;
    }

    private function date(mixed $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
