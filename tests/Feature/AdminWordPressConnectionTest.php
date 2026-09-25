<?php

namespace Tests\Feature;

use App\Models\WordPressConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminWordPressConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_create_and_test_a_wordpress_connection_without_exposing_password(): void
    {
        Http::fake(fn () => Http::response(['id' => 1, 'name' => 'Ops']));
        $this->post('/admin/wordpress', ['name' => '主站', 'base_url' => 'https://example.test', 'username' => 'ops', 'application_password' => 'secret-pass'])
            ->assertRedirect('/admin/wordpress');
        $connection = WordPressConnection::query()->firstOrFail();
        $this->post("/admin/wordpress/{$connection->id}/test")->assertRedirect('/admin/wordpress');
        $this->get('/admin/wordpress')->assertOk()->assertSee('主站')->assertDontSee('secret-pass');
        $this->assertSame('healthy', $connection->fresh()->status);
    }

    public function test_connection_test_classifies_authentication_failures(): void
    {
        Http::fake(fn () => Http::response(['code' => 'rest_cannot_view'], 401));
        $connection = WordPressConnection::create(['name' => '站点', 'base_url' => 'https://example.test', 'username' => 'ops', 'application_password_encrypted' => 'secret']);
        $this->postJson("/admin/wordpress/{$connection->id}/test")->assertStatus(422)->assertJsonPath('code', 'authentication_failed');
    }

    public function test_rest_diagnostics_reads_user_categories_and_tags_without_writing(): void
    {
        Http::fake(function ($request) {
            return match (true) {
                str_contains($request->url(), '/users/me') => Http::response(['id' => 1, 'name' => '管理员']),
                str_contains($request->url(), '/categories') => Http::response([['id' => 1]]),
                str_contains($request->url(), '/tags') => Http::response([['id' => 2], ['id' => 3]]),
                default => Http::response([]),
            };
        });
        $connection = WordPressConnection::create(['name' => '站点', 'base_url' => 'https://example.test', 'username' => 'ops', 'application_password_encrypted' => 'secret']);

        $this->postJson("/admin/wordpress/{$connection->id}/diagnose")
            ->assertOk()->assertJsonPath('data.categories', 1)->assertJsonPath('data.tags', 2);
        Http::assertSentCount(3);
    }
}
