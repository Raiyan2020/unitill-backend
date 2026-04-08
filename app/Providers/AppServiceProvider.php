<?php

namespace App\Providers;

use App\Support\ChartJsBuilder;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('chartjs', fn () => new ChartJsBuilder);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

    }
}
