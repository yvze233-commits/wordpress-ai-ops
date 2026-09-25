<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentBatch;
use App\Jobs\CreateDailyBatchJob;
use App\Jobs\GenerateContentItemJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use App\Models\TopicTitleCandidate;
use App\Domain\Content\ContentState;

class BatchController extends Controller
{
    public function index(Request $request): mixed
    {
        $batches = ContentBatch::query()->latest('run_date')->paginate(20);
        if ($request->expectsJson()) {
            return response()->json(['data' => $batches]);
        }

        return view('admin.batches.index', compact('batches'));
    }

    public function show(Request $request, ContentBatch $batch): mixed
    {
        $batch->load('contentItems.runs', 'contentItems.topicCandidate.titleCandidates');
        $stats = $batch->contentItems->groupBy('state')->map->count();
        $titleCandidates = $batch->contentItems
            ->flatMap(function ($item) {
                return ($item->topicCandidate?->titleCandidates ?? collect())->map(
                    fn (TopicTitleCandidate $candidate): array => ['item' => $item, 'candidate' => $candidate],
                );
            })
            ->sortByDesc(fn (array $entry): int => (int) $entry['candidate']->score)
            ->values();
        if ($request->expectsJson()) {
            return response()->json(['data' => $batch, 'stats' => $stats, 'title_candidates' => $titleCandidates]);
        }

        return view('admin.batches.show', compact('batch', 'stats', 'titleCandidates'));
    }

    public function store(Request $request): mixed
    {
        $data = $request->validate(['run_date' => ['required', 'date'], 'target_count' => ['nullable', 'integer', 'min:1', 'max:20']]);
        $job = new CreateDailyBatchJob($data['run_date'], isset($data['target_count']) ? (int) $data['target_count'] : null);
        Bus::dispatch($job);
        return $request->expectsJson() ? response()->json(['message' => '批次创建已加入队列。'], 202) : redirect()->route('admin.batches.index')->with('status', '批次创建已加入队列。');
    }

    public function run(Request $request, ContentBatch $batch): mixed
    {
        $items = $batch->contentItems()->whereIn('state', ['locked', 'retryable_failed'])->get();
        foreach ($items as $item) {
            Bus::dispatch(new GenerateContentItemJob($item->id));
        }
        $message = $items->count().' 篇文章已加入生成队列。';
        return $request->expectsJson() ? response()->json(['queued' => $items->count(), 'message' => $message], 202) : redirect()->route('admin.batches.show', $batch)->with('status', $message);
    }

    public function retry(Request $request, ContentBatch $batch): mixed
    {
        return $this->run($request, $batch);
    }

    public function selectTitle(Request $request, ContentBatch $batch, TopicTitleCandidate $titleCandidate): mixed
    {
        $titleCandidate->load('topicCandidate');
        $item = $batch->contentItems()->where('topic_candidate_id', $titleCandidate->topic_candidate_id)->first();
        if ($item === null || ! in_array($titleCandidate->status, ['available', 'selected'], true)) {
            return $request->expectsJson()
                ? response()->json(['message' => '标题候选不属于此批次或已经不可用。'], 409)
                : redirect()->route('admin.batches.show', $batch)->with('status', '标题候选不属于此批次或已经不可用。');
        }

        $titleCandidate->topicCandidate->titleCandidates()->where('status', 'selected')->update(['status' => 'available']);
        $titleCandidate->forceFill(['status' => 'selected'])->save();
        if (in_array($item->state, [ContentState::LOCKED, ContentState::GENERATING], true)) {
            $item->forceFill(['title' => $titleCandidate->title])->save();
        }
        $message = '标题候选已选用。';
        return $request->expectsJson() ? response()->json(['message' => $message, 'data' => $titleCandidate->fresh()]) : redirect()->route('admin.batches.show', $batch)->with('status', $message);
    }
}
