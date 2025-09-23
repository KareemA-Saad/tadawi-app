<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

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

public function boot()
{
    // يحدد طول الافتراضي لأي string جديد
    Schema::defaultStringLength(191);

    // يغير charset الافتراضي لكل الجداول الجديدة
    Schema::defaultStringLength(191);
    \DB::statement('SET SESSION innodb_strict_mode=0;'); // optional لمرونة أكبر
}

}