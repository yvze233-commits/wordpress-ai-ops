<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Media\ContentItemDraft;
use App\Domain\Media\ImageIngestionService;
use App\Domain\Media\ImageMatchingService;
use App\Http\Controllers\Controller;
use App\Models\ImageLibrary;
use App\Models\LibraryImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class ImageLibraryController extends Controller
{
    public const COPYRIGHT_STATES = ['unknown', 'owned', 'licensed', 'public_domain'];

    public const COPYRIGHT_LABELS = ['unknown' => '未知', 'owned' => '自有版权', 'licensed' => '已授权', 'public_domain' => '公共领域'];

    public function index(Request $request): mixed
    {
        $libraries = ImageLibrary::query()->withCount('images')->orderBy('name')->get();

        if ($request->expectsJson()) {
            return response()->json(['data' => $libraries]);
        }

        return view('admin.images.index', compact('libraries'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        ImageLibrary::query()->create([...$validated, 'enabled' => true]);

        return redirect()->route('admin.images.index')->with('status', '图片库已创建，可以开始上传图片。');
    }

    public function show(Request $request, ImageLibrary $library): mixed
    {
        $library->load(['images' => fn ($query) => $query->withCount('placements')->latest()]);

        if ($request->expectsJson()) {
            return response()->json(['data' => $library]);
        }

        return view('admin.images.show', [
            'library' => $library,
            'copyrightStates' => self::COPYRIGHT_STATES,
            'copyrightLabels' => self::COPYRIGHT_LABELS,
        ]);
    }

    public function update(Request $request, ImageLibrary $library): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $library->fill($validated);
        $library->save();

        return redirect()->route('admin.images.show', $library)->with('status', '图片库信息已更新。');
    }

    public function destroy(ImageLibrary $library): RedirectResponse
    {
        $placed = LibraryImage::query()->where('image_library_id', $library->id)->has('placements')->exists();
        if ($placed) {
            return redirect()->back()->with('error', '该图片库中有图片已被文章使用，无法删除。');
        }

        $library->delete();

        return redirect()->route('admin.images.index')->with('status', '图片库及其图片已删除。');
    }

    /**
     * Batch upload: shared metadata applies to every file; per-file failures are reported
     * individually so one bad image does not block the rest.
     */
    public function uploadImages(Request $request, ImageLibrary $library, ImageIngestionService $ingestion): RedirectResponse
    {
        if (! $library->enabled) {
            return redirect()->back()->with('error', '图片库已停用，请先启用后再上传。');
        }

        $validated = $request->validate([
            'images' => ['required', 'array', 'max:10'],
            'images.*' => ['file', 'max:5120', 'extensions:jpg,jpeg,png,webp,gif'],
            'caption' => ['nullable', 'string', 'max:200'],
            'alt_text' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'keywords' => ['nullable', 'string', 'max:500'],
            'scenes' => ['nullable', 'string', 'max:500'],
            'copyright_state' => ['nullable', Rule::in(self::COPYRIGHT_STATES)],
        ], [
            'images.*.max' => '图片 :attribute 超过 5MB 大小限制。',
            'images.*.extensions' => '图片 :attribute 格式不支持，仅支持 JPG / PNG / WebP / GIF。',
            'images.max' => '一次最多上传 10 张图片。',
        ]);

        $metadata = [
            'caption' => $validated['caption'] ?? null,
            'alt_text' => $validated['alt_text'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'keywords' => $validated['keywords'] ?? '',
            'scenes' => $validated['scenes'] ?? '',
            'copyright_state' => $validated['copyright_state'] ?? 'unknown',
        ];

        $imported = 0;
        $errors = [];
        foreach ($request->file('images', []) as $file) {
            try {
                $ingestion->ingestFile($library, $file, $metadata);
                $imported++;
            } catch (InvalidArgumentException $exception) {
                $errors[] = $file->getClientOriginalName().'：'.$this->readableIngestionError($exception);
            }
        }

        if ($imported > 0) {
            $message = "已导入 {$imported} 张图片。";
            if ($errors !== []) {
                return redirect()->route('admin.images.show', $library)->with('status', $message)->with('error', implode('；', $errors));
            }

            return redirect()->route('admin.images.show', $library)->with('status', $message);
        }

        return redirect()->back()->with('error', implode('；', $errors ?: ['没有图片被导入。']));
    }

    public function updateImage(Request $request, ImageLibrary $library, LibraryImage $image): RedirectResponse
    {
        abort_unless($image->image_library_id === $library->id, 404);

        $validated = $request->validate([
            'caption' => ['nullable', 'string', 'max:200'],
            'alt_text' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'keywords' => ['nullable', 'string', 'max:500'],
            'scenes' => ['nullable', 'string', 'max:500'],
            'copyright_state' => ['required', Rule::in(self::COPYRIGHT_STATES)],
        ]);

        $image->fill([
            'caption' => $validated['caption'] ?? null,
            'alt_text' => $validated['alt_text'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'keywords' => $this->splitList($validated['keywords'] ?? null),
            'scenes' => $this->splitList($validated['scenes'] ?? null),
            'copyright_state' => $validated['copyright_state'],
        ]);
        $image->save();

        return redirect()->route('admin.images.show', $library)->with('status', '图片信息已更新。备注和关键词会用于文章配图匹配。');
    }

    public function toggleImage(ImageLibrary $library, LibraryImage $image): RedirectResponse
    {
        abort_unless($image->image_library_id === $library->id, 404);

        $image->enabled = ! $image->enabled;
        $image->save();

        return redirect()->back()->with('status', $image->enabled ? '图片已启用。' : '图片已停用，停用后不参与配图匹配。');
    }

    public function destroyImage(ImageLibrary $library, LibraryImage $image): RedirectResponse
    {
        abort_unless($image->image_library_id === $library->id, 404);

        if ($image->placements()->exists()) {
            return redirect()->back()->with('error', '该图片已被文章使用，无法删除，可改为停用。');
        }

        $image->delete();

        return redirect()->back()->with('status', '图片已删除。');
    }

    /**
     * Rehearse image matching for a hypothetical article: shows which images the
     * pipeline would pick as featured and body images, without persisting anything.
     */
    public function matchTest(Request $request, ImageLibrary $library, ImageMatchingService $matching): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'article_text' => ['required', 'string', 'max:60000'],
            'category' => ['nullable', 'string', 'max:60'],
            'scene' => ['nullable', 'string', 'max:200'],
        ]);

        $draft = ContentItemDraft::fromText($validated['title'], $validated['article_text'], $validated['category'] ?? null, $validated['scene'] ?? null);
        $plan = $matching->match($draft, $library->images()->get());

        $images = $library->images->keyBy('id');

        return response()->json(['data' => [
            'count' => $plan->count(),
            'placements' => collect($plan->placements())->map(fn (array $placement): array => [
                'role' => $placement['role'] === 'featured' ? '特色图' : '正文配图',
                'paragraph_anchor' => $placement['paragraph_anchor'] ?? (($placement['paragraph_index'] ?? null) !== null ? '第 '.($placement['paragraph_index'] + 1).' 段' : null),
                'confidence' => round((float) $placement['confidence'] * 100, 1),
                'reason' => $placement['reason'],
                'caption' => $images->get((int) $placement['image_id'])?->caption,
                'url' => $images->has((int) $placement['image_id']) ? route('admin.images.file', (int) $placement['image_id']) : null,
            ])->all(),
        ]]);
    }

    /**
     * Serve the stored image so the admin grid can preview it without public storage.
     */
    public function serve(Request $request, LibraryImage $image): Response
    {
        $disk = Storage::disk(config('filesystems.default', 'local'));

        if (! $disk->exists($image->path)) {
            abort(404);
        }

        return $disk->response($image->path, null, ['Content-Type' => $image->mime_type, 'Cache-Control' => 'private, max-age=86400']);
    }

    private function readableIngestionError(InvalidArgumentException $exception): string
    {
        return str_contains($exception->getMessage(), 'Unsupported image MIME')
            ? '图片格式不支持，仅支持 JPG / PNG / WebP / GIF。'
            : $exception->getMessage();
    }

    /**
     * @return list<string>
     */
    private function splitList(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $item): string => trim($item),
            preg_split('/[,，\n]+/u', $value) ?: [],
        )));
    }
}
