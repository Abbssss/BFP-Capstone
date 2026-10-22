<?php

namespace App\Providers;

use App\Services\RecentProjectChanges;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', function ($view): void {
            $view->with('recentProjectChanges', app(RecentProjectChanges::class)->latest());
        });
    }
}
