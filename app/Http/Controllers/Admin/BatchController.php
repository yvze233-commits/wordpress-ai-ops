<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentBatch;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    public function index(Request $request): mixed
    {
        $batches = ContentBatch::query()->latest('run_date')->paginate(20);
        if ($request->expectsJson()) {
            return response()->json(['data' => $batches]);
        }

        return view('admin.batches.index', compact('batches'));
    }

    public function show(Request $request, ContentBatch $batch): mixed
    {
        $batch->load('contentItems');
        if ($request->expectsJson()) {
            return response()->json(['data' => $batch]);
        }

        return view('admin.batches.show', compact('batch'));
    }
}
