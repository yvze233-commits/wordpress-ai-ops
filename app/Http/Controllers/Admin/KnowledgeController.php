<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeBase;
use Illuminate\Http\Request;

class KnowledgeController extends Controller
{
    public function index(Request $request): mixed
    {
        $bases = KnowledgeBase::query()->withCount('documents')->orderBy('name')->get();

        return $request->expectsJson() ? response()->json(['data' => $bases]) : view('admin.knowledge.index', compact('bases'));
    }
}
