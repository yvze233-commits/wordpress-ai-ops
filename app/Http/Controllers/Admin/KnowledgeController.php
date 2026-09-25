<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Domain\Knowledge\KnowledgeIngestionService;
use App\Models\KnowledgeBase;
use Illuminate\Http\Request;

class KnowledgeController extends Controller
{
    public function index(Request $request): mixed
    {
        $bases = KnowledgeBase::query()->withCount('documents')->orderBy('name')->get();

        return $request->expectsJson() ? response()->json(['data' => $bases]) : view('admin.knowledge.index', compact('bases'));
    }

    public function store(Request $request): mixed
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000']]);
        $base = KnowledgeBase::query()->create($data);
        return $request->expectsJson() ? response()->json(['data' => $base], 201) : redirect()->route('admin.knowledge.index')->with('status', '知识库已创建。');
    }

    public function upload(Request $request, KnowledgeBase $knowledgeBase, KnowledgeIngestionService $ingestion): mixed
    {
        $request->validate(['file' => ['required', 'file', 'mimes:txt,md,markdown', 'max:5120']]);
        $document = $ingestion->ingestFile($knowledgeBase, $request->file('file'));
        return $request->expectsJson() ? response()->json(['data' => $document], 201) : redirect()->route('admin.knowledge.index')->with('status', '资料已导入并完成分片。');
    }

    public function toggle(Request $request, KnowledgeBase $knowledgeBase): mixed
    {
        $knowledgeBase->update(['enabled' => ! $knowledgeBase->enabled]);
        return $request->expectsJson() ? response()->json(['data' => $knowledgeBase->fresh()]) : redirect()->route('admin.knowledge.index')->with('status', $knowledgeBase->enabled ? '知识库已启用。' : '知识库已停用。');
    }
}
