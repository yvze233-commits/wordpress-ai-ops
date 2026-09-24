<?php

namespace App\Domain\Knowledge;

use App\Models\KnowledgeChunk;
use InvalidArgumentException;

final class EvidenceSnapshot implements \JsonSerializable
{
    /** @param list<array<string, mixed>> $evidence */
    public function __construct(
        private readonly array $evidence,
        private readonly string $retrievedAt = '',
    ) {}

    /** @return list<array<string, mixed>> */
    public function evidence(): array
    {
        return $this->evidence;
    }

    public function retrievedAt(): string
    {
        return $this->retrievedAt !== '' ? $this->retrievedAt : now()->toIso8601String();
    }

    public function validate(): bool
    {
        foreach ($this->evidence as $entry) {
            $chunkId = (int) ($entry['chunk_id'] ?? 0);
            $expectedHash = (string) ($entry['content_hash'] ?? '');
            $chunk = KnowledgeChunk::query()->find($chunkId);

            if ($chunk === null || $expectedHash === '' || ! hash_equals($expectedHash, (string) $chunk->content_hash)) {
                throw new InvalidArgumentException("Knowledge evidence chunk [{$chunkId}] changed after retrieval.");
            }
        }

        return true;
    }

    /** @return array{retrieved_at:string,evidence:list<array<string,mixed>>} */
    public function toArray(): array
    {
        return ['retrieved_at' => $this->retrievedAt(), 'evidence' => $this->evidence];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
