<?php

namespace App\Providers;

use Carbon\Carbon;
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
        // Set default timezone for Carbon
        Carbon::setLocale(config('app.locale', 'id'));
        date_default_timezone_set(config('app.timezone', 'Asia/Jakarta'));
    }
}
