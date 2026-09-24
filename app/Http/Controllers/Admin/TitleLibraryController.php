<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TitleLibraryEntry;
use Illuminate\Http\Request;

class TitleLibraryController extends Controller
{
    public function index(Request $request): mixed
    {
        $titles = TitleLibraryEntry::query()->latest()->paginate(50);

        return $request->expectsJson() ? response()->json(['data' => $titles]) : view('admin.titles.index', compact('titles'));
    }
}
