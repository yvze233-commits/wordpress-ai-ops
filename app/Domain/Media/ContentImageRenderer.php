<?php

namespace App\Domain\Media;

use App\Models\LibraryImage;

final class ContentImageRenderer
{
    public function render(string $html, ImagePlacementPlan $plan, iterable $images): string
    {
        $lookup = [];
        foreach ($images as $image) {
            if ($image instanceof LibraryImage) {
                $lookup[$image->id] = $image;
            }
        }

        $blocks = [];
        foreach ($plan->placements() as $placement) {
            $image = $lookup[$placement['image_id']] ?? null;
            if (! $image instanceof LibraryImage) {
                continue;
            }
            $alt = e($image->alt_text ?: $image->caption ?: $image->notes ?: '文章配图');
            $caption = trim((string) ($image->caption ?? ''));
            $captionHtml = $caption === '' ? '' : '<figcaption>'.e($caption).'</figcaption>';
            $blocks[] = [
                'placement' => (int) ($placement['position'] ?? count($blocks)),
                'paragraph_index' => $placement['paragraph_index'],
                'html' => '<figure data-content-image-placement="'.(int) $placement['position'].'"><img src="'.e(route('media.images.show', $image)).'" alt="'.$alt.'" loading="lazy">'.$captionHtml.'</figure>',
            ];
        }

        foreach (array_reverse($blocks) as $block) {
            if ($block['paragraph_index'] === null) {
                $html = $block['html'].$html;

                continue;
            }
            $paragraphs = preg_split('/(<\/p>)/i', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];
            $insertAt = min(count($paragraphs) - 1, ((int) $block['paragraph_index'] * 2) + 1);
            array_splice($paragraphs, $insertAt + 1, 0, $block['html']);
            $html = implode('', $paragraphs);
        }

        return $html;
    }

    public function renderString(string $html, ImagePlacementPlan $plan, iterable $images): string
    {
        return $this->render($html, $plan, $images);
    }
}
