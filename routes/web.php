<?php

use App\Http\Controllers\Api\BoqController;
use App\Http\Controllers\ProfileController;
use App\Livewire\Admin\AiProviders;
use App\Livewire\Admin\HardwareScanner as AdminHardwareScanner;
use App\Livewire\Admin\Index as AdminIndex;
use App\Livewire\Admin\PaymentGateways;
use App\Livewire\Admin\PlansManager;
use App\Livewire\Admin\RolesManager;
use App\Livewire\Admin\SiteSettings;
use App\Livewire\Admin\SubscriptionsManager;
use App\Livewire\Admin\UsersManager;
use App\Livewire\Boqs\Create as BoqsCreate;
use App\Livewire\Boqs\Index as BoqsIndex;
use App\Livewire\Boqs\Show as BoqsShow;
use App\Livewire\Dashboard;
use App\Livewire\HardwarePrices\Compare as HardwarePricesCompare;
use App\Livewire\HardwarePrices\Index as HardwarePricesIndex;
use App\Livewire\HardwarePrices\Recommendations as HardwarePricesRecommendations;
use App\Livewire\HardwarePrices\Show as HardwarePricesShow;
use App\Livewire\Plans\Index as PlansIndex;
use App\Livewire\Profile\Index as ProfileIndex;
use App\Livewire\Projects\Create as ProjectsCreate;
use App\Livewire\Projects\Edit as ProjectsEdit;
use App\Livewire\Projects\Index as ProjectsIndex;
use App\Livewire\Projects\Show as ProjectsShow;
use App\Livewire\Subscriptions\Index as SubscriptionsIndex;
use App\Livewire\System\McpActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Root
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});


/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    Route::get('/dashboard', Dashboard::class)
        ->name('dashboard');


    /*
    |--------------------------------------------------------------------------
    | Projects
    |--------------------------------------------------------------------------
    */

    Route::get('/projects', ProjectsIndex::class)
        ->middleware('permission:projects.view')
        ->name('projects.index');

    Route::get('/projects/create', ProjectsCreate::class)
        ->middleware('permission:projects.create')
        ->name('projects.create');

    Route::get('/projects/{project}', ProjectsShow::class)
        ->middleware('permission:projects.view')
        ->name('projects.show');

    Route::get('/projects/{project}/edit', ProjectsEdit::class)
        ->middleware('permission:projects.edit')
        ->name('projects.edit');


    /*
    |--------------------------------------------------------------------------
    | BOQs
    |--------------------------------------------------------------------------
    */

    Route::get('/boqs', BoqsIndex::class)
        ->name('boqs.index');

    Route::get('/boqs/create', BoqsCreate::class)
        ->name('boqs.create');

    Route::get('/boqs/{boq}', BoqsShow::class)
        ->name('boqs.show');

    Route::get('/boqs/{boq}/pdf', function (
        Request $request,
        \App\Models\Boq $boq
    ) {
        $apiRequest = Request::create(
            '/api/v1/boqs/'.$boq->id.'/pdf',
            'GET'
        );

        $apiRequest->setUserResolver(
            fn () => $request->user()
        );

        return app(BoqController::class)->pdf(
            $apiRequest,
            $boq
        );
    })
        ->name('boqs.pdf');


    /*
    |--------------------------------------------------------------------------
    | Hardware Prices
    |--------------------------------------------------------------------------
    */

    Route::middleware('permission:hardware-prices.view')
        ->group(function () {

            Route::get(
                '/hardware-prices',
                HardwarePricesIndex::class
            )
                ->name('hardware-prices.index');

            Route::get(
                '/hardware-prices/compare',
                HardwarePricesCompare::class
            )
                ->name('hardware-prices.compare');

            Route::get(
                '/hardware-prices/recommendations',
                HardwarePricesRecommendations::class
            )
                ->name('hardware-prices.recommendations');

            Route::get(
                '/hardware-prices/{hardwarePrice}',
                HardwarePricesShow::class
            )
                ->name('hardware-prices.show');

        });


    /*
    |--------------------------------------------------------------------------
    | Plans & Subscriptions
    |--------------------------------------------------------------------------
    */

    Route::get('/plans', PlansIndex::class)
        ->name('plans.index');

    Route::get('/subscriptions', SubscriptionsIndex::class)
        ->name('subscriptions.index');


    /*
    |--------------------------------------------------------------------------
    | AI / MCP Activity
    |--------------------------------------------------------------------------
    */

    Route::get('/system/ai-mcp/activity', McpActivity::class)
        ->middleware('role:admin,manager,super-admin')
        ->name('system.mcp-activity');


    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */

    Route::get('/profile', ProfileIndex::class)
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'delete'])
        ->name('profile.delete');


    /*
    |--------------------------------------------------------------------------
    | Super Admin
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:super-admin')
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {

            /*
            |--------------------------------------------------------------------------
            | Admin Overview
            |--------------------------------------------------------------------------
            */

            Route::get('/', AdminIndex::class)
                ->name('index');


            /*
            |--------------------------------------------------------------------------
            | Plans
            |--------------------------------------------------------------------------
            */

            Route::get('/plans', PlansManager::class)
                ->name('plans');


            /*
            |--------------------------------------------------------------------------
            | Subscriptions
            |--------------------------------------------------------------------------
            */

            Route::get('/subscriptions', SubscriptionsManager::class)
                ->name('subscriptions');


            /*
            |--------------------------------------------------------------------------
            | AI API Providers
            |--------------------------------------------------------------------------
            */

            Route::get('/ai-providers', AiProviders::class)
                ->name('ai-providers');


            /*
            |--------------------------------------------------------------------------
            | Payment Gateways
            |--------------------------------------------------------------------------
            */

            Route::get('/payment-gateways', PaymentGateways::class)
                ->name('payment-gateways');


            /*
            |--------------------------------------------------------------------------
            | Users
            |--------------------------------------------------------------------------
            */

            Route::get('/users', UsersManager::class)
                ->name('users');


            /*
            |--------------------------------------------------------------------------
            | Roles & Permissions
            |--------------------------------------------------------------------------
            */

            Route::get('/roles-permissions', RolesManager::class)
                ->name('roles-permissions');


            /*
            |--------------------------------------------------------------------------
            | Hardware Scanner
            |--------------------------------------------------------------------------
            */

            Route::get('/hardware-scanner', AdminHardwareScanner::class)
                ->name('hardware-scanner');


            /*
            |--------------------------------------------------------------------------
            | Settings
            |--------------------------------------------------------------------------
            */

            Route::get('/settings', SiteSettings::class)
                ->name('settings');

        });
});


/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

require __DIR__.'/auth.php';
