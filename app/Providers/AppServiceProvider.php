<?php

namespace App\Providers;

use App\Domain\Content\LaravelAiGateway;
use App\Domain\Content\StructuredAiGateway;
use App\Models\SystemSetting;
use Illuminate\Database\QueryException;
use Illuminate\Support\ServiceProvider;

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
        } catch (QueryException) {
            // The settings table is not available during the first migration.
        }
    }
}
