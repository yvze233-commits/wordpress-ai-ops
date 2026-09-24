<?php

namespace App\Domain\Topics;

use App\Models\TopicSource;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

/**
 * Runs a topic source collection without persisting anything: used by the
 * admin "测试来源" button to validate address, parsing rules and shape.
 */
final class TopicSourceProbe
{
    /** @return array{ok:bool, category:string, message:string, count:int, samples:list<string>} */
    public function probe(TopicSource $source): array
    {
        try {
            $items = $this->connector($source)->collect($source);
        } catch (ConnectionException $exception) {
            return ['ok' => false, 'category' => 'network', 'message' => '网络错误：无法连接到来源地址，请检查地址或网络。'.$this->brief($exception->getMessage()), 'count' => 0, 'samples' => []];
        } catch (RequestException $exception) {
            $status = $exception->response?->status();

            return ['ok' => false, 'category' => $status === 401 ? 'auth' : 'http', 'message' => "请求失败（HTTP {$status}）：来源地址返回错误。", 'count' => 0, 'samples' => []];
        } catch (MalformedTopicSourceException $exception) {
            return ['ok' => false, 'category' => 'parse', 'message' => '内容解析失败：'.$exception->getMessage(), 'count' => 0, 'samples' => []];
        } catch (TopicSourceException $exception) {
            if ($exception->getPrevious() instanceof ConnectionException) {
                return ['ok' => false, 'category' => 'network', 'message' => '网络错误：无法连接到来源地址，请检查地址或网络。'.$this->brief($exception->getMessage()), 'count' => 0, 'samples' => []];
            }

            return ['ok' => false, 'category' => 'error', 'message' => '抓取失败：'.$exception->getMessage(), 'count' => 0, 'samples' => []];
        }

        $titles = array_values(array_filter(array_map(
            static fn (array $item): ?string => isset($item['title']) && is_string($item['title']) && trim($item['title']) !== '' ? trim($item['title']) : null,
            $items,
        )));

        if ($titles === []) {
            return ['ok' => false, 'category' => 'empty', 'message' => '连接成功，但没有解析到任何标题，请检查地址或解析配置。', 'count' => 0, 'samples' => []];
        }

        return [
            'ok' => true,
            'category' => 'success',
            'message' => '测试成功：解析到 '.count($titles).' 条标题。',
            'count' => count($titles),
            'samples' => array_slice($titles, 0, 5),
        ];
    }

    private function connector(TopicSource $source): TopicSourceConnector
    {
        return match (strtolower($source->type)) {
            'rss', 'atom' => new RssTopicSourceConnector,
            'json', 'json_api', 'api' => new JsonApiTopicSourceConnector,
            'html' => new HtmlTopicSourceConnector,
            default => throw new MalformedTopicSourceException("Unsupported topic source type [{$source->type}]."),
        };
    }

    private function brief(string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        return $text === '' ? '' : ' 详情：'.mb_substr($text, 0, 160);
    }
}
