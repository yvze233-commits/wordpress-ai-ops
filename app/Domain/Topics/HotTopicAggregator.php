<?php

namespace App\Domain\Topics;

use App\Models\ContentTask;
use App\Models\TopicSource;
use Carbon\CarbonInterface;

final class HotTopicAggregator
{
    public function __construct(private readonly TopicSourceConnectorFactory $connectors) {}

    /** @return list<array<string,mixed>> */
    public function collect(ContentTask $task): array
    {
        $windowStart = now()->subHours(max(1, (int) ($task->topic_window_hours ?: 72)));
        $keywords = $this->keywords($task->topic_keywords);
        $items = [];

        foreach ((array) config('content-ops.hot_topic_sources', []) as $preset) {
            if (! is_array($preset) || ! is_string($preset['url'] ?? null)) {
                continue;
            }
            $source = new TopicSource([
                'name' => (string) ($preset['name'] ?? '系统热点聚合源'),
                'type' => (string) ($preset['type'] ?? 'rss'),
                'url' => $this->sourceUrl($preset['url'], $keywords),
                'parser_config' => $preset['parser_config'] ?? [],
                'trust_score' => (int) ($preset['trust_score'] ?? 50),
            ]);
            if ($source->trust_score < (int) $task->min_source_trust) {
                continue;
            }

            foreach ($this->connectors->make($source)->collect($source) as $item) {
                $publishedAt = $item['published_at'] ?? null;
                if ($publishedAt instanceof CarbonInterface && $publishedAt->lt($windowStart)) {
                    continue;
                }
                if ($keywords !== [] && ! $this->matchesKeywords($item, $keywords)) {
                    continue;
                }
                $items[] = [...$item, 'trust_score' => $source->trust_score, 'source_name' => $source->name];
            }
        }

        return $this->deduplicate($items);
    }

    /** @return list<string> */
    private function keywords(?string $value): array
    {
        return array_values(array_filter(array_map(
            static fn (string $keyword): string => trim($keyword),
            preg_split('/[,，\n]+/u', (string) $value) ?: [],
        )));
    }

    private function sourceUrl(string $template, array $keywords): string
    {
        $query = $keywords !== [] ? implode(' OR ', $keywords) : '教育 OR 人工智能';

        return str_replace('{keywords}', rawurlencode($query), $template);
    }

    /** @param array<string,mixed> $item */
    private function matchesKeywords(array $item, array $keywords): bool
    {
        $haystack = mb_strtolower((string) ($item['title'] ?? '').' '.($item['summary'] ?? ''), 'UTF-8');

        foreach ($keywords as $keyword) {
            if (mb_stripos($haystack, mb_strtolower($keyword, 'UTF-8'), 0, 'UTF-8') !== false) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array<string,mixed>> $items @return list<array<string,mixed>> */
    private function deduplicate(array $items): array
    {
        $seen = [];
        $result = [];
        foreach ($items as $item) {
            $key = TopicFingerprint::forUrl($item['url'] ?? null)
                ?? TopicFingerprint::forEvent($item);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $item;
        }

        usort($result, static function (array $left, array $right): int {
            $leftTime = $left['published_at'] instanceof \DateTimeInterface ? $left['published_at']->getTimestamp() : 0;
            $rightTime = $right['published_at'] instanceof \DateTimeInterface ? $right['published_at']->getTimestamp() : 0;
            return [$rightTime, (int) ($right['trust_score'] ?? 0)] <=> [$leftTime, (int) ($left['trust_score'] ?? 0)];
        });

        return $result;
    }
}
