<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
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
         public function boot()
            {
    View::composer('*', function ($view) {
        $view->with('visualSistema', app(\App\Support\VisualSistema::class));
    });
            }
}
