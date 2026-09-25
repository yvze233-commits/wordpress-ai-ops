<?php

namespace App\Http\Controllers;

use App\Models\LibraryImage;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaPreviewController extends Controller
{
    public function show(LibraryImage $libraryImage): Response|StreamedResponse
    {
        $path = (string) $libraryImage->path;
        if (str_starts_with($path, 'data:')) {
            [, $payload] = explode(',', $path, 2);
            return response(base64_decode($payload, true) ?: '', 200, ['Content-Type' => $libraryImage->mime_type ?: 'application/octet-stream', 'Cache-Control' => 'public, max-age=3600']);
        }
        if (is_file($path)) {
            return response()->file($path, ['Content-Type' => $libraryImage->mime_type ?: 'application/octet-stream']);
        }

        $normalizedPath = str_replace('\\', '/', $path);
        $candidates = [ltrim($normalizedPath, '/')];
        foreach (['storage/', 'public/', 'storage/app/'] as $prefix) {
            if (str_starts_with(strtolower($normalizedPath), $prefix)) {
                $candidates[] = substr($normalizedPath, strlen($prefix));
            }
        }
        $candidates = array_values(array_unique(array_filter($candidates)));
        foreach (['local', 'public'] as $diskName) {
            $disk = Storage::disk($diskName);
            foreach ($candidates as $candidate) {
                if (! $disk->exists($candidate)) {
                    continue;
                }

                return $disk->response($candidate, basename($candidate), ['Content-Type' => $libraryImage->mime_type ?: $disk->mimeType($candidate)]);
            }
        }

        abort(404, '图片文件不存在或已被移除。');
    }
}
