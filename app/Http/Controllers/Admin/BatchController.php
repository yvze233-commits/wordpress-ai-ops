<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Content\ContentStateTransition;
use App\Http\Controllers\Controller;
use App\Jobs\CollectTopicSourceJob;
use App\Jobs\CreateDailyBatchJob;
use App\Jobs\GenerateContentItemJob;
use App\Jobs\ReviewContentItemJob;
use App\Models\ContentBatch;
use App\Models\ContentItem;
use App\Models\TopicSource;
use Illuminate\Http\Request;
use Throwable;

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
        $batch->load('contentItems');
        if ($request->expectsJson()) {
            return response()->json(['data' => $batch]);
        }

        return view('admin.batches.show', compact('batch'));
    }

    /**
     * One-click daily batch from the admin: collect enabled sources, select topics
     * up to the daily target, and queue generation for the locked items.
     */
    public function createDaily(Request $request): mixed
    {
        $sourceCount = TopicSource::query()->where('enabled', true)->where('status', '!=', 'paused')->count();
        foreach (TopicSource::query()->where('enabled', true)->where('status', '!=', 'paused')->pluck('id') as $sourceId) {
            try {
                (new CollectTopicSourceJob((int) $sourceId))->handle();
            } catch (Throwable) {
                // Source failures are recorded on the source itself; batch creation continues.
            }
        }

        try {
            $batch = (new CreateDailyBatchJob($request->input('date') ?: now()->toDateString()))->handle();
        } catch (Throwable $exception) {
            return redirect()->back()->with('error', '批次创建失败：'.$exception->getMessage());
        }

        $items = $batch->contentItems()
            ->where('state', 'locked')
            ->whereDoesntHave('auditEvents', fn ($query) => $query->where('event_type', 'generation_queued'))
            ->get();
        foreach ($items as $item) {
            $item->auditEvents()->create(['event_type' => 'generation_queued', 'payload' => ['queued_at' => now()->toIso8601String()]]);
            GenerateContentItemJob::dispatch((int) $item->id);
        }

        // With the sync queue generation has completed above; chain the AI review stage automatically.
        foreach ($batch->contentItems()->where('state', 'awaiting_review')->get() as $item) {
            ReviewContentItemJob::dispatch((int) $item->id);
        }

        $selected = $batch->contentItems()->count();

        return redirect()->route('admin.batches.show', $batch)->with(
            'status',
            "已创建/更新 {$batch->run_date->toDateString()} 批次：选中 {$selected}/{$batch->target_count} 篇".($sourceCount > 0 ? "，抓取了 {$sourceCount} 个来源。" : '。').($selected < (int) $batch->target_count ? ' 数量不足，请补充标题库或检查来源。' : ''),
        );
    }

    /**
     * Generate (or regenerate) a selected article from the batch console.
     */
    public function generateItem(Request $request, ContentItem $item): mixed
    {
        if (! in_array($item->state, ['locked', 'retryable_failed'], true)) {
            return $this->itemActionResponse($request, $item, 'error', '当前状态（'.$item->state.'）不能生成，只有已锁定或可重试的文章可以生成。');
        }

        $item->auditEvents()->create(['event_type' => 'generation_queued', 'payload' => ['queued_at' => now()->toIso8601String(), 'source' => 'admin']]);
        GenerateContentItemJob::dispatch($item->id);

        return $this->itemActionResponse($request, $item, 'status', '文章「'.$item->title.'」已进入生成队列。');
    }

    /**
     * Run the AI review for an article waiting for review.
     */
    public function reviewItem(Request $request, ContentItem $item): mixed
    {
        if ($item->state !== 'awaiting_review') {
            return $this->itemActionResponse($request, $item, 'error', '当前状态（'.$item->state.'）不能提交 AI 审核。');
        }

        ReviewContentItemJob::dispatch($item->id);

        return $this->itemActionResponse($request, $item, 'status', '文章「'.$item->title.'」已提交 AI 审核。');
    }

    /**
     * Cancel a not-yet-generated selection: it will not be produced.
     */
    public function cancelItem(Request $request, ContentItem $item): mixed
    {
        if (! in_array($item->state, ['candidate', 'locked'], true)) {
            return $this->itemActionResponse($request, $item, 'error', '当前状态（'.$item->state.'）不能取消，只有尚未生成的文章可以取消。');
        }

        ContentStateTransition::assertAllowed((string) $item->state, 'permanently_failed');
        $item->forceFill(['state' => 'permanently_failed'])->save();
        $item->auditEvents()->create(['event_type' => 'content_cancelled', 'payload' => ['cancelled_at' => now()->toIso8601String(), 'source' => 'admin']]);

        return $this->itemActionResponse($request, $item, 'status', '文章「'.$item->title.'」已取消。');
    }

    private function itemActionResponse(Request $request, ContentItem $item, string $sessionKey, string $message): mixed
    {
        if ($request->expectsJson()) {
            return response()->json(['data' => ['message' => $message, 'state' => $item->fresh()?->state]]);
        }

        return $item->content_batch_id !== null
            ? redirect()->route('admin.batches.show', $item->content_batch_id)->with($sessionKey, $message)
            : redirect()->back()->with($sessionKey, $message);
    }
}
