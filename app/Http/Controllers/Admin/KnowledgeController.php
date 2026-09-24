<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Knowledge\KnowledgeIngestionService;
use App\Domain\Knowledge\KnowledgeRetrievalService;
use App\Http\Controllers\Controller;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class KnowledgeController extends Controller
{
    public const REVIEW_STATUSES = ['pending', 'reviewed', 'excluded'];

    public const RISK_LEVELS = ['low', 'medium', 'high'];

    public function index(Request $request): mixed
    {
        $bases = KnowledgeBase::query()
            ->withCount('documents')
            ->withSum('documents', 'content_length')
            ->orderBy('name')
            ->get();

        if ($request->expectsJson()) {
            return response()->json(['data' => $bases]);
        }

        return view('admin.knowledge.index', compact('bases'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        KnowledgeBase::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'enabled' => true,
        ]);

        return redirect()->route('admin.knowledge.index')->with('status', '知识库已创建，可以开始上传文档。');
    }

    public function show(Request $request, KnowledgeBase $base): mixed
    {
        $base->load(['documents' => fn ($query) => $query->withCount('chunks')->orderByDesc('created_at')]);

        if ($request->expectsJson()) {
            return response()->json(['data' => $base]);
        }

        return view('admin.knowledge.show', [
            'base' => $base,
            'reviewStatuses' => self::REVIEW_STATUSES,
            'riskLevels' => self::RISK_LEVELS,
        ]);
    }

    public function update(Request $request, KnowledgeBase $base): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'enabled' => ['nullable', 'boolean'],
        ]);

        $base->fill([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'enabled' => $request->boolean('enabled', false),
        ]);
        $base->save();

        return redirect()->route('admin.knowledge.show', $base)->with('status', '知识库已更新。');
    }

    public function destroy(KnowledgeBase $base): RedirectResponse
    {
        $base->delete();

        return redirect()->route('admin.knowledge.index')->with('status', '知识库及其文档已删除。');
    }

    public function toggle(KnowledgeBase $base): RedirectResponse
    {
        $base->enabled = ! $base->enabled;
        $base->save();

        return redirect()->back()->with('status', $base->enabled ? '知识库已启用。' : '知识库已停用，停用后不参与文章检索。');
    }

    public function uploadDocuments(Request $request, KnowledgeBase $base, KnowledgeIngestionService $ingestion): RedirectResponse
    {
        $validated = $request->validate([
            'documents' => ['nullable', 'array', 'max:10'],
            'documents.*' => ['file', 'max:8192', 'extensions:txt,md,markdown,docx'],
            'pasted_text' => ['nullable', 'string', 'max:600000'],
            'pasted_name' => ['nullable', 'string', 'max:100'],
            'review_status' => ['nullable', Rule::in(self::REVIEW_STATUSES)],
            'risk_level' => ['nullable', Rule::in(self::RISK_LEVELS)],
            'source_url' => ['nullable', 'url', 'max:500'],
        ]);

        if (($validated['documents'] ?? []) === [] && trim((string) ($validated['pasted_text'] ?? '')) === '') {
            return redirect()->back()->with('error', '请选择文件或粘贴文本。');
        }

        $metadata = [
            'review_status' => $validated['review_status'] ?? 'pending',
            'risk_level' => $validated['risk_level'] ?? 'low',
            'source_url' => $validated['source_url'] ?? null,
        ];

        $imported = 0;
        $errors = [];
        foreach ($validated['documents'] ?? [] as $file) {
            try {
                $ingestion->ingestFile($base, $file, $metadata);
                $imported++;
            } catch (InvalidArgumentException $exception) {
                $errors[] = $file->getClientOriginalName().'：'.$exception->getMessage();
            }
        }

        $pasted = trim((string) ($validated['pasted_text'] ?? ''));
        if ($pasted !== '') {
            try {
                $ingestion->ingestText($base, $pasted, [
                    ...$metadata,
                    'name' => filled($validated['pasted_name'] ?? null) ? (string) $validated['pasted_name'] : ('粘贴文本-'.now()->format('Ymd-His').'.txt'),
                    'source_type' => 'text',
                ]);
                $imported++;
            } catch (InvalidArgumentException $exception) {
                $errors[] = '粘贴文本：'.$exception->getMessage();
            }
        }

        if ($imported > 0) {
            $message = "已导入 {$imported} 个文档。";
            if ($errors !== []) {
                return redirect()->route('admin.knowledge.show', $base)->with('status', $message)->with('error', implode('；', $errors));
            }

            return redirect()->route('admin.knowledge.show', $base)->with('status', $message);
        }

        return redirect()->back()->with('error', implode('；', $errors ?: ['没有文档被导入。']));
    }

    public function updateDocument(Request $request, KnowledgeBase $base, KnowledgeDocument $document): RedirectResponse
    {
        abort_unless($document->knowledge_base_id === $base->id, 404);

        $validated = $request->validate([
            'review_status' => ['required', Rule::in(self::REVIEW_STATUSES)],
            'risk_level' => ['nullable', Rule::in(self::RISK_LEVELS)],
        ]);

        $document->review_status = $validated['review_status'];
        if (filled($validated['risk_level'] ?? null)) {
            $document->risk_level = $validated['risk_level'];
        }
        $document->save();

        return redirect()->route('admin.knowledge.show', $base)->with('status', '文档「'.$document->name.'」状态已更新。');
    }

    public function destroyDocument(KnowledgeBase $base, KnowledgeDocument $document): RedirectResponse
    {
        abort_unless($document->knowledge_base_id === $base->id, 404);

        $document->delete();

        return redirect()->route('admin.knowledge.show', $base)->with('status', '文档「'.$document->name.'」已删除。');
    }

    public function chunks(Request $request, KnowledgeBase $base, KnowledgeDocument $document): mixed
    {
        abort_unless($document->knowledge_base_id === $base->id, 404);

        $chunks = $document->chunks()->orderBy('position')->get(['id', 'position', 'heading', 'content']);

        if ($request->expectsJson()) {
            return response()->json(['data' => $chunks]);
        }

        return view('admin.knowledge.chunks', ['base' => $base, 'document' => $document, 'chunks' => $chunks]);
    }

    /**
     * Retrieval rehearsal: run a query against this base and show the ranked chunks an article would receive.
     */
    public function testRetrieval(Request $request, KnowledgeBase $base, KnowledgeRetrievalService $retrieval): JsonResponse
    {
        $validated = $request->validate(['query' => ['required', 'string', 'max:500']]);

        $snapshot = $retrieval->retrieve($validated['query'], [$base->id], 5);

        return response()->json(['data' => [
            'query' => $validated['query'],
            'evidence_count' => count($snapshot->evidence()),
            'evidence' => collect($snapshot->evidence())->map(fn (array $item): array => [
                'heading' => $item['heading'],
                'source_name' => $item['source_name'],
                'score' => $item['score'],
                'preview' => mb_substr((string) $item['content'], 0, 160),
            ])->all(),
        ]]);
    }
}
