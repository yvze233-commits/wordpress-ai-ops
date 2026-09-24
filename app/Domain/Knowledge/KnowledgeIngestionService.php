<?php

namespace App\Domain\Knowledge;

use App\Models\KnowledgeBase;
use App\Models\KnowledgeDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class KnowledgeIngestionService
{
    /** @param array<string, mixed> $metadata */
    public function ingestText(KnowledgeBase $knowledgeBase, string $text, array $metadata = []): KnowledgeDocument
    {
        if ($knowledgeBase->enabled === false) {
            throw new InvalidArgumentException('Knowledge base is disabled.');
        }

        $name = trim((string) ($metadata['name'] ?? 'pasted-text.txt'));
        $this->assertSafeName($name);
        $normalized = $this->normalize($text);
        if ($normalized === '') {
            throw new InvalidArgumentException('Knowledge document cannot be empty.');
        }

        $maxBytes = (int) config('content-ops.knowledge_max_document_bytes', 5 * 1024 * 1024);
        if (strlen($normalized) > $maxBytes) {
            throw new InvalidArgumentException('Knowledge document exceeds the configured size limit.');
        }

        $hash = hash('sha256', $normalized);

        return DB::transaction(function () use ($knowledgeBase, $name, $normalized, $metadata, $hash): KnowledgeDocument {
            $document = KnowledgeDocument::query()->firstOrCreate(
                ['knowledge_base_id' => $knowledgeBase->id, 'content_hash' => $hash],
                [
                    'name' => $name,
                    'source_url' => $metadata['source_url'] ?? null,
                    'source_type' => $metadata['source_type'] ?? $this->sourceType($name),
                    'review_status' => $metadata['review_status'] ?? 'pending',
                    'risk_level' => $metadata['risk_level'] ?? 'low',
                    'content_length' => strlen($normalized),
                    'text_content' => $normalized,
                    'metadata' => $metadata,
                ],
            );

            if ($document->wasRecentlyCreated) {
                foreach ($this->chunks($normalized) as $position => $chunk) {
                    $document->chunks()->create([
                        'position' => $position,
                        'heading' => $chunk['heading'],
                        'content' => $chunk['content'],
                        'content_hash' => hash('sha256', $chunk['content']),
                    ]);
                }
            }

            return $document->load('chunks');
        });
    }

    /** @param array<string, mixed> $metadata */
    public function ingest(KnowledgeBase $knowledgeBase, string $text, array $metadata = []): KnowledgeDocument
    {
        return $this->ingestText($knowledgeBase, $text, $metadata);
    }

    /** @param array<string, mixed> $metadata */
    public function ingestFile(KnowledgeBase $knowledgeBase, UploadedFile $file, array $metadata = []): KnowledgeDocument
    {
        $name = $file->getClientOriginalName();
        $this->assertSafeName($name);

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($extension, ['txt', 'md', 'markdown'], true)) {
            throw new InvalidArgumentException('Only TXT and Markdown files can be ingested as text.');
        }

        return $this->ingestText($knowledgeBase, (string) file_get_contents($file->getRealPath()), [
            ...$metadata,
            'name' => $name,
            'source_type' => $metadata['source_type'] ?? $extension,
        ]);
    }

    private function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = array_map(static fn (string $line): string => rtrim($line), explode("\n", $text));
        $text = implode("\n", $lines);

        return trim((string) preg_replace("/\n{3,}/u", "\n\n", $text));
    }

    /** @return list<array{heading:?string,content:string}> */
    private function chunks(string $text): array
    {
        $maxChars = (int) config('content-ops.knowledge_chunk_max_chars', 1400);
        $sections = [];
        $heading = null;
        $buffer = [];

        foreach (explode("\n", $text) as $line) {
            if (preg_match('/^#{1,6}\s+(.+)$/u', trim($line), $matches) === 1) {
                if (trim(implode("\n", $buffer)) !== '') {
                    $sections[] = ['heading' => $heading, 'content' => trim(implode("\n", $buffer))];
                }
                $heading = trim($matches[1]);
                $buffer = [];

                continue;
            }
            $buffer[] = $line;
        }
        if (trim(implode("\n", $buffer)) !== '') {
            $sections[] = ['heading' => $heading, 'content' => trim(implode("\n", $buffer))];
        }
        if ($sections === []) {
            $sections[] = ['heading' => null, 'content' => $text];
        }

        $chunks = [];
        foreach ($sections as $section) {
            $content = $section['content'];
            while (mb_strlen($content) > $maxChars) {
                $chunks[] = ['heading' => $section['heading'], 'content' => mb_substr($content, 0, $maxChars)];
                $content = ltrim(mb_substr($content, $maxChars));
            }
            if ($content !== '') {
                $chunks[] = ['heading' => $section['heading'], 'content' => $content];
            }
        }

        return $chunks;
    }

    private function assertSafeName(string $name): void
    {
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($extension, ['php', 'phar', 'exe', 'bat', 'cmd', 'ps1', 'sh', 'js', 'com'], true)) {
            throw new InvalidArgumentException('Executable knowledge files are not allowed.');
        }
    }

    private function sourceType(string $name): string
    {
        return match (strtolower((string) pathinfo($name, PATHINFO_EXTENSION))) {
            'md', 'markdown' => 'markdown',
            default => 'text',
        };
    }
}
