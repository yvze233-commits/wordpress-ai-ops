<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TopicSource;
use Illuminate\Http\Request;

class TopicSourceController extends Controller
{
    public function index(Request $request): mixed
    {
        $sources = TopicSource::query()->withCount('feeds')->orderBy('name')->get();

        return $request->expectsJson() ? response()->json(['data' => $sources]) : view('admin.sources.index', compact('sources'));
    }
}
