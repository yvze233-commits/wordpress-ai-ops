<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TitleLibraryEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TitleLibraryController extends Controller
{
    public function index(Request $request): mixed
    {
        $titles = TitleLibraryEntry::query()->latest()->paginate(50);

        return $request->expectsJson() ? response()->json(['data' => $titles]) : view('admin.titles.index', compact('titles'));
    }

    public function store(Request $request): mixed
    {
        $data = $request->validate(['raw_title' => ['required', 'string', 'max:255'], 'category' => ['nullable', 'string', 'max:100'], 'priority' => ['nullable', 'integer', 'min:0', 'max:100']]);
        $title = $this->upsertTitle($data['raw_title'], $data);
        return $request->expectsJson() ? response()->json(['data' => $title], 201) : redirect()->route('admin.titles.index')->with('status', '标题已加入标题库。');
    }

    public function import(Request $request): mixed
    {
        $data = $request->validate(['titles' => ['required', 'string', 'max:50000'], 'category' => ['nullable', 'string', 'max:100'], 'priority' => ['nullable', 'integer', 'min:0', 'max:100']]);
        $created = 0;
        foreach (preg_split('/\r?\n/u', $data['titles']) ?: [] as $raw) {
            $raw = trim($raw);
            if ($raw === '') continue;
            $before = TitleLibraryEntry::query()->where('normalized_title', $this->normalize($raw))->exists();
            $this->upsertTitle($raw, $data);
            $created += $before ? 0 : 1;
        }
        return $request->expectsJson() ? response()->json(['created' => $created]) : redirect()->route('admin.titles.index')->with('status', "已导入 {$created} 条新标题，重复标题已跳过。");
    }

    public function toggle(Request $request, TitleLibraryEntry $titleLibraryEntry): mixed
    {
        $titleLibraryEntry->update(['enabled' => ! $titleLibraryEntry->enabled]);
        return $request->expectsJson() ? response()->json(['data' => $titleLibraryEntry->fresh()]) : redirect()->route('admin.titles.index')->with('status', $titleLibraryEntry->enabled ? '标题已启用。' : '标题已停用。');
    }

    private function upsertTitle(string $raw, array $data): TitleLibraryEntry
    {
        $normalized = $this->normalize($raw);
        return TitleLibraryEntry::query()->firstOrCreate(['normalized_title' => $normalized], ['raw_title' => $raw, 'category' => $data['category'] ?? null, 'priority' => $data['priority'] ?? 50, 'enabled' => true]);
    }

    private function normalize(string $value): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', '', trim($value)));
    }
}
