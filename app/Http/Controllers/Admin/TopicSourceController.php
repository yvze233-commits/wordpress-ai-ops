<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Topics\TopicSourceProbe;
use App\Http\Controllers\Controller;
use App\Jobs\CollectTopicSourceJob;
use App\Models\TopicSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class TopicSourceController extends Controller
{
    public const TYPES = ['rss', 'atom', 'json', 'json_api', 'api', 'html'];

    public function index(Request $request): mixed
    {
        $sources = TopicSource::query()->withCount('feeds')->orderBy('name')->get();

        if ($request->expectsJson()) {
            return response()->json(['data' => $sources]);
        }

        return view('admin.sources.index', [
            'sources' => $sources,
            'types' => self::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateSource($request);

        TopicSource::query()->create([...$validated, 'enabled' => true, 'status' => 'active']);

        return redirect()->route('admin.sources.index')->with('status', '来源已创建，可先「测试」确认能解析到标题。');
    }

    public function update(Request $request, TopicSource $source): RedirectResponse
    {
        $validated = $this->validateSource($request);

        $source->fill($validated);
        $source->save();

        return redirect()->route('admin.sources.index')->with('status', '来源「'.$source->name.'」已更新。');
    }

    public function toggle(TopicSource $source): RedirectResponse
    {
        $source->enabled = ! $source->enabled;
        $source->save();

        return redirect()->back()->with('status', $source->enabled ? '来源已启用，下次批次前会自动抓取。' : '来源已停用，不再参与抓取。');
    }

    public function pause(TopicSource $source): RedirectResponse
    {
        $source->status = $source->status === 'paused' ? 'active' : 'paused';
        $source->save();

        return redirect()->back()->with('status', $source->status === 'paused' ? '来源已暂停抓取，可随时恢复。' : '来源已恢复抓取。');
    }

    public function destroy(TopicSource $source): RedirectResponse
    {
        $source->delete();

        return redirect()->route('admin.sources.index')->with('status', '来源及其抓取记录已删除。');
    }

    /**
     * Probe the source without writing anything: validates address, credentials and parser config.
     */
    public function test(Request $request, TopicSource $source, TopicSourceProbe $probe): JsonResponse
    {
        $result = $probe->probe($source);

        return response()->json(['data' => $result], $result['ok'] ? 200 : 422);
    }

    /**
     * Run a real collection now: refreshes feeds/candidates and the source health record.
     */
    public function collect(Request $request, TopicSource $source): RedirectResponse
    {
        try {
            $count = (new CollectTopicSourceJob((int) $source->id))->handle();
        } catch (Throwable $exception) {
            return redirect()->back()->with('error', '抓取失败：'.$exception->getMessage());
        }

        $source->refresh();

        if ($source->last_error !== null) {
            return redirect()->back()->with('error', '抓取失败：'.$source->last_error);
        }

        return redirect()->back()->with('status', "抓取完成，本次处理 {$count} 条内容。");
    }

    /**
     * @return array{name:string, type:string, url:string, trust_score:int, parser_config:?array}
     */
    private function validateSource(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:'.implode(',', self::TYPES)],
            'url' => ['required', 'url', 'max:2000'],
            'trust_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'parser_config' => [
                'nullable', 'string', 'max:5000',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (filled($value) && ! is_array(json_decode((string) $value, true))) {
                        $fail('解析配置必须是合法的 JSON 对象。');
                    }
                },
            ],
        ], [
            'url.url' => '来源地址必须是合法的 URL。',
        ]);

        $parserConfig = filled($validated['parser_config'] ?? null)
            ? json_decode((string) $validated['parser_config'], true)
            : null;

        return [
            'name' => $validated['name'],
            'type' => strtolower($validated['type']),
            'url' => trim($validated['url']),
            'trust_score' => (int) ($validated['trust_score'] ?? 50),
            'parser_config' => $parserConfig,
        ];
    }
}
