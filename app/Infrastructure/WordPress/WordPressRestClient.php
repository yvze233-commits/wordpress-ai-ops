<?php

namespace App\Infrastructure\WordPress;

use App\Models\LibraryImage;
use App\Models\WordPressConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class WordPressRestClient
{
    public function __construct(private readonly WordPressRequestFactory $requests) {}

    /** @return array<string,mixed> */
    public function health(WordPressConnection $connection): array
    {
        return $this->get($connection, '/users/me?context=view');
    }

    /** @return list<array<string,mixed>> */
    public function categories(WordPressConnection $connection): array
    {
        return $this->getList($connection, '/categories?per_page=100');
    }

    /** @return list<array<string,mixed>> */
    public function tags(WordPressConnection $connection): array
    {
        return $this->getList($connection, '/tags?per_page=100');
    }

    /** @return array<string,mixed> */
    public function findPostByMarker(WordPressConnection $connection, string $marker): ?array
    {
        $items = $this->getList($connection, '/posts?search='.rawurlencode($marker).'&per_page=10&status=any');
        foreach ($items as $item) {
            if (($item['meta']['content_ops_idempotency_key'] ?? null) === $marker || str_contains((string) ($item['content']['raw'] ?? ''), $marker)) {
                return $item;
            }
        }

        return null;
    }

    /** @return array<string,mixed> */
    public function uploadMedia(WordPressConnection $connection, LibraryImage $image): array
    {
        $contents = $this->imageContents($image);
        $request = $this->requests->make($connection)
            ->withHeaders([
                'Content-Disposition' => 'attachment; filename="'.basename((string) $image->path).'"',
                'Content-Type' => (string) ($image->mime_type ?: 'application/octet-stream'),
            ]);
        $response = $this->send($request, 'post', $this->requests->endpoint($connection, '/media'), $contents, true, (string) ($image->mime_type ?: 'application/octet-stream'));

        return $this->decode($response);
    }

    /** @return array<string,mixed> */
    public function createDraft(WordPressConnection $connection, array $payload): array
    {
        return $this->createPost($connection, [...$payload, 'status' => 'draft']);
    }

    /** @param array<string,mixed> $payload */
    public function createPost(WordPressConnection $connection, array $payload): array
    {
        $payload['status'] = $this->postStatus($payload['status'] ?? 'draft');

        return $this->decode($this->send($this->requests->make($connection), 'post', $this->requests->endpoint($connection, '/posts'), $payload));
    }

    /** @return array<string,mixed> */
    public function updatePost(WordPressConnection $connection, int $postId, array $payload): array
    {
        $payload['status'] = $this->postStatus($payload['status'] ?? 'draft');

        return $this->decode($this->send($this->requests->make($connection), 'post', $this->requests->endpoint($connection, '/posts/'.$postId), $payload));
    }

    /** @return array{user:array<string,mixed>,categories:int,tags:int} */
    public function diagnostics(WordPressConnection $connection): array
    {
        return [
            'user' => $this->health($connection),
            'categories' => count($this->categories($connection)),
            'tags' => count($this->tags($connection)),
        ];
    }

    private function postStatus(mixed $status): string
    {
        return in_array($status, ['draft', 'pending', 'publish'], true) ? $status : 'draft';
    }

    /** @return list<array<string,mixed>> */
    private function getList(WordPressConnection $connection, string $path): array
    {
        $decoded = $this->decode($this->send($this->requests->make($connection), 'get', $this->requests->endpoint($connection, $path)));

        return array_is_list($decoded) ? $decoded : [];
    }

    /** @return array<string,mixed> */
    private function get(WordPressConnection $connection, string $path): array
    {
        return $this->decode($this->send($this->requests->make($connection), 'get', $this->requests->endpoint($connection, $path)));
    }

    private function imageContents(LibraryImage $image): string
    {
        if (str_starts_with((string) $image->path, 'data:')) {
            $parts = explode(',', (string) $image->path, 2);

            return base64_decode($parts[1] ?? '', true) ?: '';
        }
        if (is_file((string) $image->path)) {
            return (string) file_get_contents((string) $image->path);
        }

        $disk = Storage::disk(config('filesystems.default', 'local'));
        if (! $disk->exists((string) $image->path)) {
            throw new RuntimeException('WordPress image source is unavailable.');
        }

        return (string) $disk->get((string) $image->path);
    }

    private function send(PendingRequest $request, string $method, string $url, array|string|null $payload = null, bool $binary = false, ?string $contentType = null): Response
    {
        $retry = function (\Throwable $exception): bool {
            if ($exception instanceof ConnectionException) {
                return true;
            }
            if ($exception instanceof RequestException) {
                return in_array($exception->response->status(), [429, 500, 502, 503, 504], true);
            }

            return false;
        };
        $request = $request->retry(2, 250, $retry);
        $response = $binary
            ? $request->withBody((string) $payload, $contentType ?: 'application/octet-stream')->{$method}($url)
            : $request->{$method}($url, $payload ?? []);
        if ($response->failed()) {
            throw new RuntimeException('WordPress API request failed with HTTP '.$response->status().'.');
        }

        return $response;
    }

    /** @return array<string,mixed>|list<array<string,mixed>> */
    private function decode(Response $response): array
    {
        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('WordPress API returned invalid JSON.');
        }

        return $json;
    }
}
