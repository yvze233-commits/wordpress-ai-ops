<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Domain\Topics\TopicSourceConnectorFactory;
use App\Jobs\CollectTopicSourceJob;
use App\Models\TopicSource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Throwable;

class TopicSourceController extends Controller
{
    public function index(Request $request): mixed
    {
        $sources = TopicSource::query()->withCount('feeds')->orderBy('name')->get();

        return $request->expectsJson() ? response()->json(['data' => $sources]) : view('admin.sources.index', compact('sources'));
    }

    public function store(Request $request): mixed
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'type' => ['required', 'in:rss,json,json_api,html'], 'url' => ['required', 'url', 'max:2000'], 'trust_score' => ['nullable', 'integer', 'min:0', 'max:100']]);
        $source = TopicSource::query()->create($data);
        return $request->expectsJson() ? response()->json(['data' => $source], 201) : redirect()->route('admin.sources.index')->with('status', '热点来源已创建。');
    }

    public function toggle(Request $request, TopicSource $topicSource): mixed
    {
        $topicSource->update(['enabled' => ! $topicSource->enabled]);
        return $request->expectsJson() ? response()->json(['data' => $topicSource->fresh()]) : redirect()->route('admin.sources.index')->with('status', $topicSource->enabled ? '热点来源已启用。' : '热点来源已停用。');
    }

    public function fetch(Request $request, TopicSource $topicSource): mixed
    {
        if (! $topicSource->enabled || $topicSource->status === 'paused') {
            return $request->expectsJson()
                ? response()->json(['message' => '请先启用此热点来源。'], 409)
                : redirect()->route('admin.sources.index')->with('status', '请先启用此热点来源。');
        }

        $topicSource->forceFill(['status' => 'fetching', 'last_error' => null])->save();
        Bus::dispatch(new CollectTopicSourceJob($topicSource->id));
        return $request->expectsJson() ? response()->json(['message' => '热点抓取已加入队列。'], 202) : redirect()->route('admin.sources.index')->with('status', '热点抓取已加入队列。');
    }

    public function preview(Request $request, TopicSource $topicSource, TopicSourceConnectorFactory $connectors): mixed
    {
        try {
            $items = $connectors->make($topicSource)->collect($topicSource);
            $preview = array_map(static function (array $item): array {
                return [
                    'title' => $item['title'] ?? null,
                    'summary' => $item['summary'] ?? null,
                    'url' => $item['url'] ?? null,
                    'published_at' => isset($item['published_at']) && $item['published_at'] instanceof \DateTimeInterface
                        ? $item['published_at']->format(DATE_ATOM)
                        : ($item['published_at'] ?? null),
                ];
            }, array_slice($items, 0, 5));

            return $request->expectsJson()
                ? response()->json(['data' => $preview])
                : redirect()->route('admin.sources.index')->with('status', '来源测试成功，解析到 '.count($preview).' 条内容。');
        } catch (Throwable $exception) {
            $payload = ['code' => 'source_preview_failed', 'message' => $exception->getMessage()];
            return $request->expectsJson()
                ? response()->json($payload, 422)
                : redirect()->route('admin.sources.index')->with('status', '来源测试失败：'.$exception->getMessage());
        }
    }
}
