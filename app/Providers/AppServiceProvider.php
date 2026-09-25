<?php

namespace App\Providers;

use App\Domain\Content\LaravelAiGateway;
use App\Domain\Content\StructuredAiGateway;
use App\Domain\Skills\SkillCatalog;
use App\Models\SystemSetting;
use Illuminate\Database\QueryException;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StructuredAiGateway::class, LaravelAiGateway::class);
    }

    public function boot(): void
    {
        try {
            SystemSetting::applyToConfig();
            $knowledgeBaseIds = \App\Models\KnowledgeBase::query()->where('enabled', true)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
            if ($knowledgeBaseIds !== []) {
                config(['content-ops.default_knowledge_base_ids' => $knowledgeBaseIds]);
            }
            app(SkillCatalog::class)->seedBuiltIns();
            View::composer('admin.layout', function ($view): void {
                $view->with('navCounts', [
                    'reviews' => \App\Models\ContentItem::query()->whereIn('state', ['awaiting_review', 'needs_manual_review'])->count(),
                    'failed' => \App\Models\ContentItem::query()->where('state', 'retryable_failed')->count(),
                    'source_errors' => \App\Models\TopicSource::query()->where('status', 'error')->count(),
                ]);
            });
        } catch (QueryException) {
            // The settings table is not available during the first migration.
        }
    }
}
