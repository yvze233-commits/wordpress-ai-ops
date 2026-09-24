<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Content\ContentState;
use App\Domain\Content\ContentStateTransition;
use App\Http\Controllers\Controller;
use App\Jobs\PublishWordPressDraftJob;
use App\Models\ContentItem;
use App\Models\WordPressConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $contentItem->load(['topicCandidate', 'imagePlacements.image', 'runs']);
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

    public function writeDraft(Request $request, ContentItem $contentItem): JsonResponse
    {
        if ($contentItem->state !== ContentState::APPROVED) {
            return response()->json(['message' => 'Only approved content can be written to a WordPress draft.'], 409);
        }
        $connection = WordPressConnection::query()->findOrFail((int) $request->input('connection_id'));
        PublishWordPressDraftJob::dispatch($contentItem->id, $connection->id);

        return response()->json(['message' => 'WordPress draft publishing queued.', 'content_item_id' => $contentItem->id], 202);
    }
}
