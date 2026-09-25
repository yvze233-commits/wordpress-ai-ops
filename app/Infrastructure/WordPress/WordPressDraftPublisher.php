<?php

namespace App\Infrastructure\WordPress;

use App\Domain\Content\ContentState;
use App\Domain\Content\ContentStateTransition;
use App\Models\ContentItem;
use App\Models\WordPressConnection;
use Illuminate\Support\Facades\DB;

final class WordPressDraftPublisher
{
    public function __construct(
        private readonly WordPressRestClient $client,
        private readonly WordPressTaxonomySync $taxonomy,
        private readonly WordPressMediaUploader $media,
    ) {}

    /** @return array<string,mixed> */
    public function publish(ContentItem $item, WordPressConnection $connection, string $status = 'draft'): array
    {
        if ($item->state !== ContentState::APPROVED && $item->state !== ContentState::WP_DRAFT_WRITTEN) {
            throw new \InvalidArgumentException('Only approved content can be written to a WordPress draft.');
        }
        $marker = (string) $item->idempotency_key;
        $remote = $item->wordpress_post_id !== null ? ['id' => $item->wordpress_post_id, 'link' => $item->wordpress_url] : $this->client->findPostByMarker($connection, $marker);
        $mediaUrls = $this->media->uploadSelected($connection, $item);
        $html = $this->rewriteImages((string) $item->content_html, $mediaUrls);
        $taxonomies = $this->taxonomy->resolve($connection, $item->generation_meta['category'] ?? null, $item->generation_meta['keywords'] ?? []);
        $status = in_array($status, ['draft', 'pending', 'publish'], true) ? $status : 'draft';
        $payload = [
            'title' => $item->title,
            'slug' => $item->slug,
            'excerpt' => $item->excerpt,
            'content' => $html,
            'status' => $status,
            'categories' => $taxonomies['categories'],
            'tags' => $taxonomies['tags'],
            'meta' => ['content_ops_idempotency_key' => $marker],
        ];
        $post = $remote !== null && isset($remote['id'])
            ? $this->client->updatePost($connection, (int) $remote['id'], $payload)
            : $this->client->createPost($connection, $payload);

        DB::transaction(function () use ($item, $post, $marker, $status): void {
            $fresh = $item->fresh();
            $nextState = $status === 'publish' ? ContentState::PUBLISHED : ContentState::WP_DRAFT_WRITTEN;
            if ($fresh->state !== $nextState) {
                ContentStateTransition::assertAllowed((string) $fresh->state, $nextState);
            }
            $fresh->forceFill(['wordpress_post_id' => (int) ($post['id'] ?? 0), 'wordpress_url' => $post['link'] ?? null, 'state' => $nextState])->save();
            $fresh->auditEvents()->create(['event_type' => $status === 'publish' ? 'wordpress_published' : 'wordpress_draft_written', 'payload' => ['post_id' => $post['id'] ?? null, 'status' => $status, 'idempotency_key' => $marker]]);
        });

        return $post;
    }

    /** @param array<int,string> $mediaUrls */
    private function rewriteImages(string $html, array $mediaUrls): string
    {
        return preg_replace_callback('/(<figure[^>]*data-content-image-placement="(\d+)"[^>]*>.*?<img\s+[^>]*src=")[^"]+("[^>]*>)/is', function (array $matches) use ($mediaUrls): string {
            $url = $mediaUrls[(int) $matches[2]] ?? null;

            return $url === null ? $matches[0] : $matches[1].e($url).$matches[3];
        }, $html) ?: $html;
    }
}
