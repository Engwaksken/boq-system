<?php

namespace App\Providers;

use App\Http\Middleware\CheckEntitlement;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckRole;
use App\Models\Plan;
use App\Policies\SubscriptionPolicy;
use Illuminate\Support\Facades\Gate;
use App\Support\DatabaseTranslationLoader;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // UI translations edited in Settings > Languages are layered over lang/*.json.
        $this->app->extend('translation.loader', function ($loader, $app) {
            $frameworkLang = dirname((new \ReflectionClass(\Illuminate\Translation\TranslationServiceProvider::class))->getFileName()).'/lang';

            return new DatabaseTranslationLoader($app['files'], [$frameworkLang, $app['path.lang']]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Plan::class, SubscriptionPolicy::class);
        // Livewire action requests re-run these route middleware, so role, permission
        // and subscription checks still apply after the page has loaded.
        Livewire::addPersistentMiddleware([
            CheckRole::class,
            CheckPermission::class,
            CheckEntitlement::class,
        ]);
    }
}
