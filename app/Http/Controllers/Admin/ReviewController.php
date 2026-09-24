<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Content\ArticleGenerationService;
use App\Domain\Content\ContentState;
use App\Domain\Content\ContentStateTransition;
use App\Domain\Content\PublishScheduler;
use App\Http\Controllers\Controller;
use App\Jobs\PublishWordPressDraftJob;
use App\Jobs\ReviewContentItemJob;
use App\Models\ContentItem;
use App\Models\WordPressConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ReviewController extends Controller
{
    public function index(Request $request): mixed
    {
        $items = ContentItem::query()->with('topicCandidate')->whereIn('state', [ContentState::AWAITING_REVIEW, ContentState::NEEDS_MANUAL_REVIEW])->latest()->paginate(20);
        if ($request->expectsJson()) {
            return response()->json(['data' => $items]);
        }

        return view('admin.reviews.index', compact('items'));
    }

    public function show(Request $request, ContentItem $contentItem): mixed
    {
        $contentItem->load([
            'topicCandidate',
            'imagePlacements.image',
            'runs' => fn ($query) => $query->orderBy('id'),
            'auditEvents' => fn ($query) => $query->orderByDesc('id')->limit(50),
        ]);
        if ($request->expectsJson()) {
            return response()->json(['data' => $contentItem]);
        }

        return view('admin.reviews.show', ['item' => $contentItem]);
    }

    public function approve(Request $request, ContentItem $contentItem): JsonResponse|RedirectResponse
    {
        ContentStateTransition::assertAllowed((string) $contentItem->state, ContentState::APPROVED);
        $contentItem->forceFill(['state' => ContentState::APPROVED])->save();

        $message = '文章已批准，可以进入 WordPress 草稿发布队列。';
        if (config('content-ops.publish_mode') === 'approval_publish') {
            app(PublishScheduler::class)->schedule($contentItem, 'publish');
            $message = '文章已批准，已按发布策略安排自动发布。';
        }

        if (! $request->expectsJson()) {
            return redirect()->route('admin.reviews.show', $contentItem)->with('status', $message);
        }

        return response()->json(['data' => $contentItem->fresh()]);
    }

    public function requestManual(Request $request, ContentItem $contentItem): JsonResponse|RedirectResponse
    {
        if ($contentItem->state !== ContentState::NEEDS_MANUAL_REVIEW) {
            ContentStateTransition::assertAllowed((string) $contentItem->state, ContentState::NEEDS_MANUAL_REVIEW);
            $contentItem->forceFill(['state' => ContentState::NEEDS_MANUAL_REVIEW])->save();
        }

        if (! $request->expectsJson()) {
            return redirect()->route('admin.reviews.show', $contentItem)->with('status', '文章已转入人工复核。');
        }

        return response()->json(['data' => $contentItem->fresh()]);
    }

    public function writeDraft(Request $request, ContentItem $contentItem): JsonResponse|RedirectResponse
    {
        if ($contentItem->state !== ContentState::APPROVED) {
            $message = 'Only approved content can be written to a WordPress draft.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 409);
            }

            return redirect()->back()->with('error', '只有已批准的文章可以写入草稿。');
        }
        $connection = filled($request->input('connection_id'))
            ? WordPressConnection::query()->findOrFail((int) $request->input('connection_id'))
            : WordPressConnection::query()->oldest('id')->first();
        if ($connection === null) {
            $message = 'No WordPress connection configured.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 409);
            }

            return redirect()->back()->with('error', '请先在系统配置中保存 WordPress 连接。');
        }
        PublishWordPressDraftJob::dispatch($contentItem->id, $connection->id);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'WordPress draft publishing queued.', 'content_item_id' => $contentItem->id], 202);
        }

        return redirect()->route('admin.reviews.show', $contentItem)->with('status', '已进入 WordPress 草稿发布队列。');
    }

    /**
     * Apply an operator's edit (title, excerpt, markdown) and optionally send the
     * article straight back into AI review.
     */
    public function update(Request $request, ContentItem $contentItem): RedirectResponse
    {
        if (! in_array($contentItem->state, [ContentState::AWAITING_REVIEW, ContentState::NEEDS_MANUAL_REVIEW], true)) {
            return redirect()->back()->with('error', '当前状态（'.$contentItem->state.'）不能编辑，只有待审核或人工复核中的文章可以编辑。');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content_markdown' => ['required', 'string', 'max:100000'],
            'rerun_review' => ['nullable', 'boolean'],
        ]);

        $generation = app(ArticleGenerationService::class);

        try {
            $generation->rerender($contentItem, $validated['title'], $validated['excerpt'] ?? null, $validated['content_markdown']);
        } catch (InvalidArgumentException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        $wasManual = (bool) $validated['rerun_review'] && $contentItem->state === ContentState::NEEDS_MANUAL_REVIEW;
        $contentItem->auditEvents()->create(['event_type' => 'content_edited', 'payload' => ['edited_at' => now()->toIso8601String(), 'rerun_review' => (bool) $validated['rerun_review']]]);

        if (! $validated['rerun_review']) {
            return redirect()->route('admin.reviews.show', $contentItem)->with('status', '文章已保存编辑。');
        }

        if ($wasManual) {
            ContentStateTransition::assertAllowed((string) $contentItem->state, ContentState::AWAITING_REVIEW);
            $contentItem->forceFill(['state' => ContentState::AWAITING_REVIEW])->save();
        }

        ReviewContentItemJob::dispatch($contentItem->id);

        return redirect()->route('admin.reviews.show', $contentItem)->with('status', '文章已保存并重新提交 AI 审核。');
    }

    /**
     * Re-run the AI review for an article in a reviewable state.
     */
    public function rerunReview(Request $request, ContentItem $contentItem): RedirectResponse
    {
        if ($contentItem->state === ContentState::NEEDS_MANUAL_REVIEW) {
            ContentStateTransition::assertAllowed((string) $contentItem->state, ContentState::AWAITING_REVIEW);
            $contentItem->forceFill(['state' => ContentState::AWAITING_REVIEW])->save();
        }

        if ($contentItem->state !== ContentState::AWAITING_REVIEW) {
            return redirect()->back()->with('error', '当前状态（'.$contentItem->state.'）不能重新审核。');
        }

        ReviewContentItemJob::dispatch($contentItem->id);

        return redirect()->route('admin.reviews.show', $contentItem)->with('status', '文章已重新提交 AI 审核。');
    }
}
