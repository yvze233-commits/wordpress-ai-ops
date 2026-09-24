<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WordPressConnection;
use Illuminate\Http\Request;

class WordPressConnectionController extends Controller
{
    public function index(Request $request): mixed
    {
        $connections = WordPressConnection::query()->orderBy('name')->get()->makeHidden(['application_password_encrypted']);

        return $request->expectsJson() ? response()->json(['data' => $connections]) : view('admin.wordpress.index', compact('connections'));
    }
}
