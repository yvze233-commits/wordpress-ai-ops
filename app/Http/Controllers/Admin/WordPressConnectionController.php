<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WordPressConnection;
use App\Infrastructure\WordPress\WordPressRestClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;
use InvalidArgumentException;

class WordPressConnectionController extends Controller
{
    public function index(Request $request): mixed
    {
        $connections = WordPressConnection::query()->orderBy('name')->get()->makeHidden(['application_password_encrypted']);

        return $request->expectsJson() ? response()->json(['data' => $connections]) : view('admin.wordpress.index', compact('connections'));
    }

    public function store(Request $request): mixed
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'base_url' => ['required', 'url', 'max:2000'], 'username' => ['required', 'string', 'max:255'], 'application_password' => ['required', 'string', 'max:500'], 'default_post_status' => ['nullable', 'in:draft']]);
        $connection = WordPressConnection::query()->create(['name' => $data['name'], 'base_url' => $data['base_url'], 'username' => $data['username'], 'application_password_encrypted' => $data['application_password'], 'default_post_status' => $data['default_post_status'] ?? 'draft', 'status' => 'unverified']);
        return $request->expectsJson() ? response()->json(['data' => $connection->makeHidden(['application_password_encrypted'])], 201) : redirect()->route('admin.wordpress.index')->with('status', 'WordPress 连接已保存，请测试连接。');
    }

    public function update(Request $request, WordPressConnection $connection): mixed
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'base_url' => ['required', 'url', 'max:2000'], 'username' => ['required', 'string', 'max:255'], 'application_password' => ['nullable', 'string', 'max:500']]);
        $connection->fill(['name' => $data['name'], 'base_url' => $data['base_url'], 'username' => $data['username']]);
        if (($data['application_password'] ?? '') !== '') $connection->application_password_encrypted = $data['application_password'];
        $connection->save();
        return $request->expectsJson() ? response()->json(['data' => $connection->fresh()->makeHidden(['application_password_encrypted'])]) : redirect()->route('admin.wordpress.index')->with('status', 'WordPress 连接已更新。');
    }

    public function test(Request $request, WordPressConnection $connection, WordPressRestClient $client): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        try {
            $client->health($connection);
            $connection->forceFill(['status' => 'healthy', 'last_health_checked_at' => now(), 'last_error' => null])->save();
            $payload = ['message' => '连接正常。', 'status' => 'healthy'];
            return $request->expectsJson() ? response()->json($payload) : redirect()->route('admin.wordpress.index')->with('status', $payload['message']);
        } catch (Throwable $exception) {
            $message = $exception->getMessage();
            $code = $exception instanceof InvalidArgumentException ? 'invalid_url' : (str_contains($message, '401') ? 'authentication_failed' : (str_contains($message, '403') ? 'permission_denied' : (str_contains($message, 'invalid JSON') ? 'invalid_json' : 'connection_failed')));
            $connection->forceFill(['status' => 'error', 'last_health_checked_at' => now(), 'last_error' => $message])->save();
            if ($request->expectsJson()) return response()->json(['code' => $code, 'message' => $message], 422);
            return redirect()->route('admin.wordpress.index')->with('status', '连接测试失败：'.$message);
        }
    }

    public function diagnose(Request $request, WordPressConnection $connection, WordPressRestClient $client): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        try {
            $diagnostics = $client->diagnostics($connection);
            $message = 'REST 接口诊断完成：当前用户可访问，分类 '.$diagnostics['categories'].' 个，标签 '.$diagnostics['tags'].' 个。';
            return $request->expectsJson()
                ? response()->json(['message' => $message, 'data' => $diagnostics])
                : redirect()->route('admin.wordpress.index')->with('status', $message);
        } catch (Throwable $exception) {
            $message = 'REST 接口诊断失败：'.$exception->getMessage();
            return $request->expectsJson()
                ? response()->json(['message' => $message, 'code' => 'diagnostic_failed'], 422)
                : redirect()->route('admin.wordpress.index')->with('status', $message);
        }
    }
}
