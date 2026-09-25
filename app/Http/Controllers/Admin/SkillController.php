<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Skills\SkillValidator;
use App\Domain\Skills\SkillCatalog;
use App\Http\Controllers\Controller;
use App\Models\Skill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SkillController extends Controller
{
    public function index(Request $request): mixed
    {
        app(SkillCatalog::class)->seedBuiltIns();
        $skills = Skill::query()->with('versions')->orderBy('kind')->orderBy('name')->get();
        if ($request->expectsJson()) {
            return response()->json(['data' => $skills]);
        }

        return view('admin.skills.index', compact('skills'));
    }

    public function store(Request $request, SkillValidator $validator): JsonResponse
    {
        $name = trim((string) $request->input('name'));
        $kind = (string) $request->input('kind');
        $file = $request->file('skill_file');
        $rawText = $file?->get() ?? (string) $request->input('raw_text');

        $errors = [];
        if ($name === '') {
            $errors['name'][] = 'Skill name is required.';
        }
        if (mb_strlen($name, 'UTF-8') > 120) {
            $errors['name'][] = 'Skill name must not exceed 120 characters.';
        }
        $report = $file === null
            ? $validator->validate($rawText, $kind, $request->only(['output_schema', 'prohibited_terms', 'pass_threshold']))
            : $validator->validateFile($file, $kind, $request->only(['output_schema', 'prohibited_terms', 'pass_threshold']));
        $errors = [...$errors, ...$report['errors']];
        if ($errors !== []) {
            return response()->json([
                'message' => 'Skill validation failed.',
                'errors' => $errors,
            ], 422);
        }

        $skill = DB::transaction(function () use ($name, $kind, $rawText, $report): Skill {
            $baseSlug = Str::slug($name) ?: 'skill';
            $slug = $baseSlug;
            $suffix = 2;
            while (Skill::query()->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$suffix++;
            }
            $skill = Skill::query()->create([
                'name' => $name,
                'slug' => $slug,
                'kind' => $kind,
                'source' => 'user',
                'enabled' => true,
                'current_version' => 1,
            ]);
            $skill->versions()->create([
                'version' => 1,
                'raw_text' => $rawText,
                ...$report['fields'],
                'validation_report' => $report,
                'content_hash' => hash('sha256', $rawText),
            ]);

            return $skill->load('versions');
        });

        return response()->json(['data' => $skill], 201);
    }

    public function enable(Skill $skill): JsonResponse
    {
        $skill->update(['enabled' => true]);

        return response()->json(['data' => $skill->fresh()]);
    }

    public function disable(Skill $skill): JsonResponse
    {
        $skill->update(['enabled' => false]);

        return response()->json(['data' => $skill->fresh()]);
    }

    public function version(Request $request, Skill $skill, SkillValidator $validator): JsonResponse
    {
        $rawText = (string) $request->input('raw_text');
        $report = $validator->assertValid($rawText, $skill->kind, $request->only(['output_schema', 'prohibited_terms', 'pass_threshold']));
        $version = DB::transaction(function () use ($skill, $rawText, $report): mixed {
            $number = ((int) $skill->versions()->max('version')) + 1;
            $version = $skill->versions()->create([
                'version' => $number,
                'raw_text' => $rawText,
                ...$report['fields'],
                'validation_report' => $report,
                'content_hash' => hash('sha256', $rawText),
            ]);
            $skill->update(['current_version' => $number]);

            return $version;
        });

        return response()->json(['data' => $version], 201);
    }
}
