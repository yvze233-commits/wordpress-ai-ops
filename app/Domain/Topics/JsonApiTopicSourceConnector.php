<?php

namespace App\Domain\Topics;

use App\Models\TopicSource;
use Carbon\Carbon;
use JsonException;

final class JsonApiTopicSourceConnector extends AbstractTopicSourceConnector
{
    public function collect(TopicSource $source): array
    {
        try {
            $data = json_decode($this->fetch($source)->body(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new MalformedTopicSourceException("Topic source [{$source->name}] returned invalid JSON.", $exception);
        }

        $config = $source->parser_config ?? [];
        $items = $this->items($data, $config['items_path'] ?? null);
        $result = [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $title = $this->scalar(data_get($item, $config['title_path'] ?? 'title'));
            if ($title === null) {
                continue;
            }

            $url = $this->scalar(data_get($item, $config['url_path'] ?? 'url'));
            $sourceKey = $this->scalar(data_get($item, $config['source_key_path'] ?? 'id'))
                ?? $url
                ?? $title.'#'.$index;
            $published = $this->scalar(data_get($item, $config['published_at_path'] ?? 'published_at'));

            $result[] = [
                'source_key' => $sourceKey,
                'title' => $title,
                'summary' => $this->scalar(data_get($item, $config['summary_path'] ?? 'summary')),
                'url' => $url,
                'published_at' => $this->date($published),
                'raw_payload' => $item,
            ];
        }

        return $result;
    }

    /** @return list<mixed> */
    private function items(mixed $data, ?string $path): array
    {
        $items = $path === null ? $data : data_get($data, $path);
        if (is_array($items) && array_is_list($items)) {
            return $items;
        }

        if (is_array($items)) {
            return [$items];
        }

        throw new MalformedTopicSourceException('JSON topic source did not contain a list of items.');
    }

    private function date(?string $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
