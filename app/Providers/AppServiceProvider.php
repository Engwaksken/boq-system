<?php

namespace App\Providers;

use App\Http\Middleware\CheckEntitlement;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckRole;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

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
        // Livewire action requests re-run these route middleware, so role, permission
        // and subscription checks still apply after the page has loaded.
        Livewire::addPersistentMiddleware([
            CheckRole::class,
            CheckPermission::class,
            CheckEntitlement::class,
        ]);
    }
}
