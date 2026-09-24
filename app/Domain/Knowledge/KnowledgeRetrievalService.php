<?php

namespace App\Domain\Knowledge;

use App\Models\KnowledgeChunk;
use Illuminate\Database\Eloquent\Builder;

final class KnowledgeRetrievalService
{
    public function retrieve(string $query, array $knowledgeBaseIds, int $limit = 8): EvidenceSnapshot
    {
        $terms = $this->terms($query);
        if ($limit <= 0 || $knowledgeBaseIds === []) {
            return new EvidenceSnapshot([], now()->toIso8601String());
        }

        $chunks = KnowledgeChunk::query()
            ->with('document')
            ->whereHas('document', function (Builder $document) use ($knowledgeBaseIds): void {
                $document->whereIn('knowledge_base_id', $knowledgeBaseIds)
                    ->whereHas('knowledgeBase', fn (Builder $base): Builder => $base->where('enabled', true))
                    ->where(function (Builder $query): void {
                        $query->where('risk_level', '!=', 'high')
                            ->orWhere('review_status', 'reviewed')
                            ->orWhere('review_status', 'approved');
                    });
            })
            ->limit(500)
            ->get();

        $ranked = $chunks->map(function (KnowledgeChunk $chunk) use ($terms): array {
            $document = $chunk->document;
            $haystack = mb_strtolower($chunk->content.' '.($chunk->heading ?? ''), 'UTF-8');
            $matches = 0;
            foreach ($terms as $term) {
                if ($term !== '' && mb_strpos($haystack, $term, 0, 'UTF-8') !== false) {
                    $matches++;
                }
            }

            $score = $matches * 10;
            if (in_array($document->review_status, ['reviewed', 'approved'], true)) {
                $score += 100;
            }
            if ($document->risk_level === 'medium') {
                $score -= 5;
            }

            return ['chunk' => $chunk, 'score' => $score, 'matches' => $matches];
        })
            ->filter(fn (array $entry): bool => $entry['matches'] > 0 || $terms === [])
            ->sortByDesc('score')
            ->take($limit);

        $evidence = $ranked->map(function (array $entry): array {
            /** @var KnowledgeChunk $chunk */
            $chunk = $entry['chunk'];
            $document = $chunk->document;

            return [
                'chunk_id' => $chunk->id,
                'document_id' => $document->id,
                'knowledge_base_id' => $document->knowledge_base_id,
                'heading' => $chunk->heading,
                'content' => $chunk->content,
                'content_hash' => $chunk->content_hash,
                'source_url' => $document->source_url,
                'source_name' => $document->name,
                'score' => $entry['score'],
                'retrieved_at' => now()->toIso8601String(),
            ];
        })->values()->all();

        return new EvidenceSnapshot($evidence, now()->toIso8601String());
    }

    /** @return list<string> */
    private function terms(string $query): array
    {
        $query = mb_strtolower(trim($query), 'UTF-8');
        if ($query === '') {
            return [];
        }

        return array_values(array_unique(array_filter(preg_split('/[\s,，。.!?！？、:：;；]+/u', $query) ?: [])));
    }
}
