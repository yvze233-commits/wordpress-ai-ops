<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Content\ContentState;
use App\Http\Controllers\Controller;
use App\Models\ContentBatch;
use App\Models\ContentItem;
use App\Models\ImageLibrary;
use App\Models\KnowledgeBase;
use App\Models\Skill;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): mixed
    {
        $counts = collect(ContentState::values())->mapWithKeys(fn (string $state): array => [$state => ContentItem::query()->where('state', $state)->count()])->all();
        $batch = ContentBatch::query()->whereDate('run_date', now()->toDateString())->first();
        $reviewItems = ContentItem::query()
            ->with('topicCandidate')
            ->whereIn('state', [ContentState::AWAITING_REVIEW, ContentState::NEEDS_MANUAL_REVIEW])
            ->latest('updated_at')
            ->limit(5)
            ->get();
        $knowledgeBases = KnowledgeBase::query()->where('enabled', true)->withCount('documents')->get();
        $imageLibraries = ImageLibrary::query()->where('enabled', true)->withCount('images')->get();
        $skills = Skill::query()->get(['enabled']);
        $data = [
            'daily_target' => (int) ($batch?->target_count ?? config('content-ops.topic_daily_target', 4)),
            'selected' => (int) ($batch?->completed_count ?? 0),
            'states' => $counts,
        ];
        $knowledgeCount = $knowledgeBases->count();
        $knowledgeDocuments = (int) $knowledgeBases->sum('documents_count');
        $imageLibraryCount = $imageLibraries->count();
        $imageCount = (int) $imageLibraries->sum('images_count');
        $skillCount = $skills->count();
        $enabledSkillCount = $skills->where('enabled', true)->count();
        if ($request->expectsJson()) {
            return response()->json(['data' => $data + compact('knowledgeCount', 'knowledgeDocuments', 'imageLibraryCount', 'imageCount', 'skillCount', 'enabledSkillCount')]);
        }

        return view('admin.dashboard', compact('data', 'batch', 'reviewItems', 'knowledgeCount', 'knowledgeDocuments', 'imageLibraryCount', 'imageCount', 'skillCount', 'enabledSkillCount'));
    }
}
