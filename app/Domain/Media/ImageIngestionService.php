<?php

namespace App\Domain\Media;

use App\Models\ImageLibrary;
use App\Models\LibraryImage;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

final class ImageIngestionService
{
    /** @param array<string,mixed> $metadata */
    public function ingestFile(ImageLibrary $library, UploadedFile $file, array $metadata = []): LibraryImage
    {
        $mime = (string) ($file->getMimeType() ?: $file->getClientMimeType());
        if (! in_array(strtolower($mime), $this->allowedMimeTypes(), true)) {
            throw new InvalidArgumentException('Unsupported image MIME type.');
        }
        $path = $file->store('content-images');

        return $this->create($library, $path, $mime, $metadata);
    }

    /** @param array<string,mixed> $metadata */
    public function import(ImageLibrary $library, string $path, array $metadata = []): LibraryImage
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException('Image file does not exist.');
        }

        $mime = (string) (mime_content_type($path) ?: '');

        return $this->create($library, $path, $mime, $metadata);
    }

    /** @param array<string,mixed> $metadata */
    public function create(ImageLibrary $library, string $path, string $mimeType, array $metadata = []): LibraryImage
    {
        if ($library->enabled === false || ! in_array(strtolower($mimeType), $this->allowedMimeTypes(), true)) {
            throw new InvalidArgumentException('Only enabled libraries and supported image MIME types are allowed.');
        }

        $dimensions = is_file($path) ? @getimagesize($path) : false;
        $notes = array_key_exists('notes', $metadata) ? (string) $metadata['notes'] : null;

        return $library->images()->create([
            'path' => $path,
            'mime_type' => strtolower($mimeType),
            'width' => is_array($dimensions) ? (int) ($dimensions[0] ?? 0) : ($metadata['width'] ?? null),
            'height' => is_array($dimensions) ? (int) ($dimensions[1] ?? 0) : ($metadata['height'] ?? null),
            'caption' => $metadata['caption'] ?? null,
            'alt_text' => $metadata['alt_text'] ?? null,
            'notes' => $notes,
            'keywords' => $this->list($metadata['keywords'] ?? []),
            'applicable_categories' => $this->list($metadata['applicable_categories'] ?? []),
            'scenes' => $this->list($metadata['scenes'] ?? []),
            'copyright_state' => $metadata['copyright_state'] ?? 'unknown',
            'ocr_text' => $metadata['ocr_text'] ?? null,
            'enabled' => $metadata['enabled'] ?? true,
        ]);
    }

    /** @return list<string> */
    private function allowedMimeTypes(): array
    {
        return ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    }

    /** @return list<string> */
    private function list(mixed $value): array
    {
        if (is_string($value)) {
            return array_values(array_filter(array_map('trim', preg_split('/[,，\n]+/u', $value) ?: [])));
        }

        return is_array($value) ? array_values(array_filter(array_map(static fn (mixed $item): string => trim((string) $item), $value))) : [];
    }
}
