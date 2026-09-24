<?php

namespace App\Domain\Media;

use App\Models\ContentItem;
use App\Models\LibraryImage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ImageMatchingService
{
    public function match(ContentItemDraft $draft, Collection $images): ImagePlacementPlan
    {
        $available = $images->filter(fn (mixed $image): bool => $image instanceof LibraryImage && $image->enabled !== false)->values();
        if ($available->isEmpty()) {
            return new ImagePlacementPlan([]);
        }

        $placements = [];
        $used = [];
        $featured = $available
            ->map(fn (LibraryImage $image): array => $this->scored($image, $draft->title.' '.($draft->category ?? '').' '.($draft->scene ?? '')))
            ->sortByDesc('confidence')
            ->first();

        if ($featured !== null && $featured['confidence'] >= $this->threshold()) {
            $placements[] = [
                'image_id' => $featured['image']->id,
                'role' => 'featured',
                'paragraph_index' => null,
                'paragraph_anchor' => null,
                'confidence' => $featured['confidence'],
                'reason' => $featured['reason'],
                'position' => 0,
            ];
            $used[$featured['image']->id] = true;
        }

        $bodyLimit = $this->bodyLimit($draft->paragraphCount());
        $paragraphCandidates = [];
        foreach ($draft->paragraphs as $index => $paragraph) {
            $heading = (string) ($paragraph['heading'] ?? '');
            $content = (string) ($paragraph['content'] ?? '');
            $best = $available
                ->reject(fn (LibraryImage $image): bool => isset($used[$image->id]))
                ->map(fn (LibraryImage $image): array => [
                    'image' => $image,
                    'paragraph_index' => $index,
                    ...$this->scored($image, $heading.' '.$content.' '.($draft->category ?? '').' '.($draft->scene ?? '')),
                ])
                ->sortByDesc('confidence')
                ->first();

            if ($best !== null && $best['confidence'] >= $this->threshold()) {
                $paragraphCandidates[] = $best;
            }
        }

        foreach (collect($paragraphCandidates)->sortByDesc('confidence')->take($bodyLimit) as $candidate) {
            $image = $candidate['image'];
            if (isset($used[$image->id])) {
                continue;
            }
            $used[$image->id] = true;
            $placements[] = [
                'image_id' => $image->id,
                'role' => 'body',
                'paragraph_index' => $candidate['paragraph_index'],
                'paragraph_anchor' => $draft->paragraphs[$candidate['paragraph_index']]['heading'] ?? null,
                'confidence' => $candidate['confidence'],
                'reason' => $candidate['reason'],
                'position' => count($placements),
            ];
        }

        return new ImagePlacementPlan($placements);
    }

    public function matchDraft(ContentItemDraft $draft, Collection $images): ImagePlacementPlan
    {
        return $this->match($draft, $images);
    }

    public function persist(ContentItem $contentItem, ImagePlacementPlan $plan): void
    {
        DB::transaction(function () use ($contentItem, $plan): void {
            $contentItem->imagePlacements()->delete();
            foreach ($plan->placements() as $placement) {
                $contentItem->imagePlacements()->create([
                    'library_image_id' => $placement['image_id'],
                    'role' => $placement['role'],
                    'paragraph_index' => $placement['paragraph_index'],
                    'paragraph_anchor' => $placement['paragraph_anchor'],
                    'confidence' => $placement['confidence'],
                    'reason' => $placement['reason'],
                    'position' => $placement['position'],
                ]);
                LibraryImage::query()->whereKey($placement['image_id'])->increment('usage_count');
            }
        });
    }

    /** @return array{image:LibraryImage,confidence:float,reason:string} */
    private function scored(LibraryImage $image, string $context): array
    {
        $context = mb_strtolower($context, 'UTF-8');
        $terms = $this->terms(implode(' ', [
            (string) $image->notes,
            implode(' ', $image->keywords ?? []),
            implode(' ', $image->applicable_categories ?? []),
            implode(' ', $image->scenes ?? []),
            (string) $image->ocr_text,
        ]));
        $matches = 0;
        foreach ($terms as $term) {
            if ($term !== '' && mb_stripos($context, $term, 0, 'UTF-8') !== false) {
                $matches++;
            }
        }

        $score = min(1.0, 0.25 + ($matches * 0.18));
        if (in_array($image->copyright_state, ['approved', 'licensed'], true)) {
            $score += 0.08;
        } elseif ($image->copyright_state === 'unknown') {
            $score -= 0.04;
        }
        if (($image->width ?? 0) > ($image->height ?? 0)) {
            $score += 0.02;
        }
        $score = max(0.0, min(1.0, $score));

        return [
            'image' => $image,
            'confidence' => round($score, 4),
            'reason' => $matches > 0
                ? "Matched {$matches} annotated image term(s) in the article context."
                : 'No annotated term match; image confidence is below the preferred semantic score.',
        ];
    }

    /** @return list<string> */
    private function terms(string $value): array
    {
        $terms = preg_split('/[\s,，。.!?！？、:：;；|\/]+/u', mb_strtolower($value, 'UTF-8')) ?: [];

        return array_values(array_unique(array_filter(array_map('trim', $terms), static fn (string $term): bool => mb_strlen($term, 'UTF-8') >= 2)));
    }

    private function threshold(): float
    {
        return (float) config('content-ops.image_match_min_confidence', 0.46);
    }

    private function bodyLimit(int $paragraphCount): int
    {
        if ($paragraphCount <= 3) {
            return 0;
        }
        if ($paragraphCount <= 7) {
            return min(2, (int) config('content-ops.image_match_max_body_images', 4));
        }

        return min(4, (int) config('content-ops.image_match_max_body_images', 4));
    }
}
