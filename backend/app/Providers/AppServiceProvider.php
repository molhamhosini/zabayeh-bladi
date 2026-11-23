<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Services\SmsSender::class, fn () => \App\Services\Sms::msg91Active()
            ? new \App\Services\Msg91SmsSender
            : new \App\Services\LogSmsSender);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
