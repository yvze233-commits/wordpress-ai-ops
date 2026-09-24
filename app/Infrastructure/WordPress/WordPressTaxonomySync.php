<?php

namespace App\Infrastructure\WordPress;

use App\Models\WordPressConnection;

final class WordPressTaxonomySync
{
    /** @return array{categories:list<int>,tags:list<int>} */
    public function resolve(WordPressConnection $connection, ?string $category, array $keywords): array
    {
        $mapping = $connection->category_mapping ?? [];
        $categoryName = $category !== null && $category !== '' ? (string) ($mapping[$category] ?? $category) : null;
        $categories = [];
        if ($categoryName !== null) {
            foreach (app(WordPressRestClient::class)->categories($connection) as $remote) {
                if (mb_strtolower((string) ($remote['name'] ?? ''), 'UTF-8') === mb_strtolower($categoryName, 'UTF-8')) {
                    $categories[] = (int) $remote['id'];
                    break;
                }
            }
        }

        return ['categories' => $categories, 'tags' => []];
    }
}
