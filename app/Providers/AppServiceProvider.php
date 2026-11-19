<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(\App\Support\VisualSistema::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::anonymousComponentPath(resource_path('views/Components'));
        if (config('deployment.force_https')) {
            URL::forceScheme('https');
        }
        View::composer('*', function ($view) {
            $view->with('visualSistema', app(\App\Support\VisualSistema::class));
        });
    }
}
