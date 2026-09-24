<?php

namespace App\Infrastructure\WordPress;

use App\Models\ContentItem;
use App\Models\WordPressConnection;

final class WordPressMediaUploader
{
    /** @return array<int,string> */
    public function uploadSelected(WordPressConnection $connection, ContentItem $item): array
    {
        $urls = [];
        foreach ($item->imagePlacements()->with('image')->get() as $placement) {
            $image = $placement->image;
            if ($image === null) {
                continue;
            }
            $remote = app(WordPressRestClient::class)->uploadMedia($connection, $image);
            if (isset($remote['source_url'])) {
                $urls[(int) $placement->position] = (string) $remote['source_url'];
            }
        }

        return $urls;
    }
}
