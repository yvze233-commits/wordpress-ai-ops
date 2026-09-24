<?php

namespace App\Providers;

use App\Domain\Content\LaravelAiGateway;
use App\Domain\Content\StructuredAiGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StructuredAiGateway::class, LaravelAiGateway::class);
    }
}
