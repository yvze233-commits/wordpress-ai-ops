<?php

namespace Tests\Unit;

use App\Infrastructure\WordPress\WordPressRequestFactory;
use App\Infrastructure\WordPress\WordPressRestClient;
use App\Models\ImageLibrary;
use App\Models\LibraryImage;
use App\Models\WordPressConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WordPressRestClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_uses_basic_auth_and_normalized_rest_root(): void
    {
        Http::fake(['https://wp.example.test/wp-json/wp/v2/*' => Http::response(['id' => 1, 'name' => 'admin'])]);
        $connection = $this->connection();

        $result = (new WordPressRestClient(new WordPressRequestFactory))->health($connection);

        $this->assertSame(1, $result['id']);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/wp-json/wp/v2/users/me')
            && $request->hasHeader('Accept'));
    }

    public function test_unauthorized_response_is_not_retried(): void
    {
        Http::fake(['https://wp.example.test/*' => Http::response(['code' => 'rest_cannot_view'], 401)]);

        try {
            (new WordPressRestClient(new WordPressRequestFactory))->health($this->connection());
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('401', $exception->getMessage());
        }

        Http::assertSentCount(1);
    }

    public function test_media_upload_sends_binary_body_and_draft_forces_draft_status(): void
    {
        Http::fake(['https://wp.example.test/*' => Http::response(['id' => 9, 'source_url' => 'https://wp.example.test/image.jpg'])]);
        $connection = $this->connection();
        $library = ImageLibrary::create(['name' => 'Test images', 'slug' => 'test-images', 'enabled' => true]);
        $image = LibraryImage::create([
            'image_library_id' => $library->id,
            'path' => 'data:image/jpeg;base64,'.base64_encode('image-bytes'), 'mime_type' => 'image/jpeg', 'enabled' => true,
        ]);
        $client = new WordPressRestClient(new WordPressRequestFactory);
        $client->uploadMedia($connection, $image);
        $client->createDraft($connection, ['title' => '标题', 'status' => 'publish']);

        Http::assertSentCount(2);
        Http::assertSent(function ($request): bool {
            if (str_ends_with($request->url(), '/media')) {
                return $request->body() === 'image-bytes' && $request->header('Content-Type')[0] === 'image/jpeg';
            }

            return ($request->data()['status'] ?? null) === 'draft';
        });
    }

    private function connection(): WordPressConnection
    {
        return WordPressConnection::create([
            'name' => 'Test WordPress', 'base_url' => 'https://wp.example.test/', 'username' => 'admin',
            'application_password_encrypted' => 'secret-password', 'default_post_status' => 'draft',
        ]);
    }
}
