<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImageLibrary;
use Illuminate\Http\Request;

class ImageLibraryController extends Controller
{
    public function index(Request $request): mixed
    {
        $libraries = ImageLibrary::query()->withCount('images')->orderBy('name')->get();

        return $request->expectsJson() ? response()->json(['data' => $libraries]) : view('admin.images.index', compact('libraries'));
    }
}
