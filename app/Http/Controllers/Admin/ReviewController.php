<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Content\ContentState;
use App\Domain\Content\ContentStateTransition;
use App\Http\Controllers\Controller;
use App\Jobs\PublishWordPressDraftJob;
use App\Jobs\GenerateContentItemJob;
use App\Models\ContentItem;
use App\Models\WordPressConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;

class ReviewController extends Controller
{
    public function index(Request $request): mixed
    {
        $allowed = [ContentState::AWAITING_REVIEW, ContentState::NEEDS_MANUAL_REVIEW, ContentState::APPROVED, ContentState::RETRYABLE_FAILED, ContentState::WP_DRAFT_WRITTEN, ContentState::PUBLISHED];
        $state = (string) $request->query('state', 'pending');
        $query = ContentItem::query()->with('topicCandidate');
        if ($state === 'pending') {
            $query->whereIn('state', [ContentState::AWAITING_REVIEW, ContentState::NEEDS_MANUAL_REVIEW]);
        } elseif (in_array($state, $allowed, true)) {
            $query->where('state', $state);
        }
        $items = $query->latest()->paginate(20)->withQueryString();
        if ($request->expectsJson()) {
            return response()->json(['data' => $items]);
        }

        return view('admin.reviews.index', compact('items', 'state', 'allowed'));
    }

    public function show(Request $request, ContentItem $contentItem): mixed
    {
        $contentItem->load(['topicCandidate', 'imagePlacements.image', 'runs']);
        foreach ($contentItem->imagePlacements as $placement) {
            if ($placement->image !== null && $contentItem->content_html) {
                $previewUrl = route('media.images.show', $placement->image);
                $path = (string) $placement->image->path;
                $variants = array_values(array_unique(array_filter([
                    $path,
                    '/storage/'.ltrim($path, '/\\'),
                    'storage/'.ltrim($path, '/\\'),
                    'storage/app/'.ltrim($path, '/\\'),
                ])));
                $contentItem->content_html = str_replace($variants, $previewUrl, (string) $contentItem->content_html);
            }
        }
        if ($request->expectsJson()) {
            return response()->json(['data' => $contentItem]);
        }

        return view('admin.reviews.show', ['item' => $contentItem]);
    }

    public function approve(Request $request, ContentItem $contentItem): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        ContentStateTransition::assertAllowed((string) $contentItem->state, ContentState::APPROVED);
        $contentItem->forceFill(['state' => ContentState::APPROVED])->save();

        if (! $request->expectsJson()) {
            return redirect()->route('admin.reviews.show', $contentItem)->with('status', '文章已批准，可以进入 WordPress 草稿发布队列。');
        }

        return response()->json(['data' => $contentItem->fresh()]);
    }

    public function requestManual(Request $request, ContentItem $contentItem): JsonResponse|\Illuminate\Http\RedirectResponse
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

    public function writeDraft(Request $request, ContentItem $contentItem): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        if ($contentItem->state !== ContentState::APPROVED) {
            if (! $request->expectsJson()) {
                return redirect()->route('admin.reviews.show', $contentItem)->with('status', '只有已通过文章才能写入 WordPress 草稿。');
            }
            return response()->json(['message' => '只有已通过文章才能写入 WordPress 草稿。'], 409);
        }
        $connection = WordPressConnection::query()->findOrFail((int) $request->input('connection_id'));
        PublishWordPressDraftJob::dispatch($contentItem->id, $connection->id);

        if (! $request->expectsJson()) {
            return redirect()->route('admin.reviews.show', $contentItem)->with('status', 'WordPress 草稿已加入发布队列。');
        }
        return response()->json(['message' => 'WordPress 草稿已加入发布队列。', 'content_item_id' => $contentItem->id], 202);
    }

    public function retry(Request $request, ContentItem $contentItem): mixed
    {
        if ($contentItem->state !== ContentState::RETRYABLE_FAILED) {
            return $request->expectsJson() ? response()->json(['message' => '只有可重试状态的文章才能重试。'], 409) : redirect()->route('admin.reviews.show', $contentItem)->with('status', '当前状态不可重试。');
        }
        Bus::dispatch(new GenerateContentItemJob($contentItem->id));
        return $request->expectsJson() ? response()->json(['message' => '文章已加入重试队列。'], 202) : redirect()->route('admin.reviews.show', $contentItem)->with('status', '文章已加入重试队列。');
    }
}
