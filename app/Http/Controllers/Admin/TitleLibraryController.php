<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Topics\TopicNormalizer;
use App\Http\Controllers\Controller;
use App\Models\TitleLibraryEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TitleLibraryController extends Controller
{
    public function index(Request $request): mixed
    {
        $titles = TitleLibraryEntry::query()->latest()->paginate(50)->withQueryString();
        $stats = [
            'total' => TitleLibraryEntry::query()->count(),
            'enabled' => TitleLibraryEntry::query()->where('enabled', true)->count(),
            'used' => TitleLibraryEntry::query()->where('use_count', '>', 0)->count(),
        ];

        if ($request->expectsJson()) {
            return response()->json(['data' => $titles]);
        }

        return view('admin.titles.index', ['titles' => $titles, 'stats' => $stats]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'category' => ['nullable', 'string', 'max:60'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $result = $this->importRows([[$validated['title'], $validated['category'] ?? '', $validated['priority'] ?? '']]);

        return redirect()->route('admin.titles.index')->with(
            $result['imported'] > 0 ? 'status' : 'error',
            $result['imported'] > 0 ? '标题已添加。' : '标题已存在，未重复添加。',
        );
    }

    /**
     * Batch import: a CSV/TXT upload or pasted text. CSV columns: title, category, priority;
     * TXT/pasted text uses one title per line. Existing normalized titles are skipped.
     */
    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'import_file' => ['nullable', 'file', 'max:2048', 'extensions:csv,txt'],
            'pasted_text' => ['nullable', 'string', 'max:200000'],
        ], [
            'import_file.extensions' => '仅支持 CSV 或 TXT 文件。',
        ]);

        if (! $request->hasFile('import_file') && trim((string) ($validated['pasted_text'] ?? '')) === '') {
            return redirect()->back()->with('error', '请选择文件或粘贴标题文本。');
        }

        $rows = [];
        if ($request->hasFile('import_file')) {
            $extension = strtolower((string) $request->file('import_file')->getClientOriginalExtension());
            $content = (string) file_get_contents($request->file('import_file')->getRealPath());
            $isCsv = $extension === 'csv';
            foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $line) {
                if (trim($line) === '') {
                    continue;
                }
                $rows[] = $isCsv ? (array) str_getcsv($line) : [trim($line)];
            }
        }

        $pasted = trim((string) ($validated['pasted_text'] ?? ''));
        if ($pasted !== '') {
            foreach (preg_split('/\r\n|\r|\n/', $pasted) ?: [] as $line) {
                if (trim($line) !== '') {
                    $rows[] = [trim($line)];
                }
            }
        }

        $result = $this->importRows($rows);

        $message = "导入完成：新增 {$result['imported']} 条，跳过重复 {$result['skipped']} 条，无效 {$result['invalid']} 条。";
        if ($result['imported'] === 0 && $result['skipped'] > 0) {
            return redirect()->route('admin.titles.index')->with('error', $message);
        }

        return redirect()->route('admin.titles.index')->with('status', $message);
    }

    public function update(Request $request, TitleLibraryEntry $title): RedirectResponse
    {
        $validated = $request->validate([
            'raw_title' => ['required', 'string', 'max:500'],
            'category' => ['nullable', 'string', 'max:60'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $normalized = (new TopicNormalizer)->title($validated['raw_title']);
        $clash = TitleLibraryEntry::query()
            ->where('normalized_title', $normalized)
            ->where('id', '!=', $title->id)
            ->exists();
        if ($clash) {
            return redirect()->back()->with('error', '修改后的标题与库中已有标题重复。');
        }

        $title->fill([
            'raw_title' => $validated['raw_title'],
            'normalized_title' => $normalized,
            'category' => filled($validated['category'] ?? null) ? $validated['category'] : null,
            'priority' => filled($validated['priority'] ?? null) ? $validated['priority'] : $title->priority,
        ]);
        $title->save();

        return redirect()->route('admin.titles.index')->with('status', '标题已更新。');
    }

    public function toggle(TitleLibraryEntry $title): RedirectResponse
    {
        $title->enabled = ! $title->enabled;
        $title->save();

        return redirect()->back()->with('status', $title->enabled ? '标题已启用。' : '标题已停用，停用后不会进入每日选题。');
    }

    public function destroy(TitleLibraryEntry $title): RedirectResponse
    {
        $title->delete();

        return redirect()->back()->with('status', '标题已删除。');
    }

    /**
     * @param  list<array<int, string>>  $rows  each row: [title, category?, priority?]
     * @return array{imported:int, skipped:int, invalid:int}
     */
    private function importRows(array $rows): array
    {
        $normalizer = new TopicNormalizer;
        $imported = 0;
        $skipped = 0;
        $invalid = 0;
        $seen = [];

        foreach ($rows as $row) {
            $raw = trim((string) ($row[0] ?? ''));
            if ($raw === '') {
                continue;
            }
            if (mb_strlen($raw) < 4) {
                $invalid++;

                continue;
            }
            if (preg_match('/^(标题|title)$/iu', $raw) === 1) {
                continue;
            }

            $normalized = $normalizer->title($raw);
            if ($normalized === '' || isset($seen[$normalized])) {
                $skipped++;

                continue;
            }
            $seen[$normalized] = true;

            $category = isset($row[1]) && trim((string) $row[1]) !== '' ? trim((string) $row[1]) : null;
            $priority = isset($row[2]) && is_numeric(trim((string) $row[2])) ? max(0, min(100, (int) trim((string) $row[2]))) : 50;

            $existing = TitleLibraryEntry::query()->where('normalized_title', $normalized)->first();
            if ($existing !== null) {
                $skipped++;

                continue;
            }

            TitleLibraryEntry::query()->create([
                'raw_title' => $raw,
                'normalized_title' => $normalized,
                'category' => $category,
                'priority' => $priority,
                'enabled' => true,
            ]);
            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'invalid' => $invalid];
    }
}
