<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Domain\Media\ImageIngestionService;
use App\Models\ImageLibrary;
use Illuminate\Http\Request;

class ImageLibraryController extends Controller
{
    public function index(Request $request): mixed
    {
        $libraries = ImageLibrary::query()->withCount('images')->with(['images' => fn ($query) => $query->latest()->limit(12)])->orderBy('name')->get();

        return $request->expectsJson() ? response()->json(['data' => $libraries]) : view('admin.images.index', compact('libraries'));
    }

    public function store(Request $request): mixed
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000']]);
        $library = ImageLibrary::query()->create($data);
        return $request->expectsJson() ? response()->json(['data' => $library], 201) : redirect()->route('admin.images.index')->with('status', '图片库已创建。');
    }

    public function upload(Request $request, ImageLibrary $imageLibrary, ImageIngestionService $ingestion): mixed
    {
        if (! $request->hasFile('image')) {
            $request->validate(['image' => ['required', 'file']]);
        }
        $request->validate([
            'image.*' => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
            'caption' => ['nullable', 'string', 'max:255'], 'alt_text' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'], 'keywords' => ['nullable', 'string'],
            'scenes' => ['nullable', 'string'], 'applicable_categories' => ['nullable', 'string'],
        ]);
        $data = $request->only(['caption', 'alt_text', 'notes', 'keywords', 'scenes', 'applicable_categories']);
        $files = $request->file('image');
        $files = is_array($files) ? $files : [$files];
        $images = collect($files)->filter()->map(fn ($file) => $ingestion->ingestFile($imageLibrary, $file, $data))->values();
        return $request->expectsJson() ? response()->json(['data' => $images], 201) : redirect()->route('admin.images.index')->with('status', "已上传 {$images->count()} 张图片。");
    }

    public function toggle(Request $request, ImageLibrary $imageLibrary): mixed
    {
        $imageLibrary->update(['enabled' => ! $imageLibrary->enabled]);
        return $request->expectsJson() ? response()->json(['data' => $imageLibrary->fresh()]) : redirect()->route('admin.images.index')->with('status', $imageLibrary->enabled ? '图片库已启用。' : '图片库已停用。');
    }

    public function updateImage(Request $request, \App\Models\LibraryImage $libraryImage): mixed
    {
        $data = $request->validate(['caption' => ['nullable', 'string', 'max:255'], 'alt_text' => ['required', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:5000'], 'keywords' => ['nullable', 'string'], 'scenes' => ['nullable', 'string']]);
        foreach (['keywords', 'scenes'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = array_values(array_filter(array_map('trim', preg_split('/[,，\n]+/u', $data[$field]) ?: [])));
            }
        }
        $libraryImage->update($data);
        return $request->expectsJson() ? response()->json(['data' => $libraryImage->fresh()]) : redirect()->route('admin.images.index')->with('status', '图片信息已更新。');
    }
}
