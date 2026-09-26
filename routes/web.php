<?php

declare(strict_types=1);

use App\Http\Controllers\Api\BoqController;
use App\Http\Controllers\ProfileController;

use App\Livewire\Admin\AiProviders;
use App\Livewire\Admin\HardwareScanner as AdminHardwareScanner;
use App\Livewire\Admin\Index as AdminIndex;
use App\Livewire\Admin\PaymentGateways;
use App\Livewire\Admin\PlansManager;
use App\Livewire\Admin\QuotationsManager;
use App\Livewire\Admin\RatesManager;
use App\Livewire\Admin\RolesManager;
use App\Livewire\Admin\SiteSettings;
use App\Livewire\Admin\SubscriptionsManager;
use App\Livewire\Admin\SuppliersManager;
use App\Livewire\Admin\TopupsManager;
use App\Livewire\Admin\UsersManager;
use App\Livewire\Admin\VersionsManager;

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
use App\Livewire\Topups\Index as TopupsIndex;

use App\Models\Boq;

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
})->name('home');


/*
|--------------------------------------------------------------------------
| Authenticated Application Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function (): void {

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/dashboard',
        Dashboard::class
    )->name('dashboard');


    /*
    |--------------------------------------------------------------------------
    | Projects
    |--------------------------------------------------------------------------
    |
    | Projects intentionally retain permission middleware because the
    | application's tests and RBAC rules require these permissions.
    |
    */

    Route::get(
        '/projects',
        ProjectsIndex::class
    )
        ->middleware('permission:projects.view')
        ->name('projects.index');


    Route::get(
        '/projects/create',
        ProjectsCreate::class
    )
        ->middleware('permission:projects.create')
        ->name('projects.create');


    Route::get(
        '/projects/{project}',
        ProjectsShow::class
    )
        ->middleware('permission:projects.view')
        ->name('projects.show');


    Route::get(
        '/projects/{project}/edit',
        ProjectsEdit::class
    )
        ->middleware('permission:projects.edit')
        ->name('projects.edit');


    /*
    |--------------------------------------------------------------------------
    | BOQs
    |--------------------------------------------------------------------------
    |
    | Do NOT add permission middleware to these customer routes.
    |
    | Ownership / organisation access is enforced inside the BOQ
    | components/controllers.
    |
    */

    Route::get(
        '/boqs',
        BoqsIndex::class
    )->name('boqs.index');


    Route::get(
        '/boqs/create',
        BoqsCreate::class
    )->name('boqs.create');


    Route::get(
        '/boqs/{boq}',
        BoqsShow::class
    )->name('boqs.show');


    /*
    |--------------------------------------------------------------------------
    | BOQ PDF
    |--------------------------------------------------------------------------
    |
    | This web route forwards the authenticated user to the existing
    | BOQ API controller PDF method.
    |
    | Do NOT add permission:boq.view here. The WebRoutesTest expects
    | an authenticated BOQ owner to download their own BOQ without
    | requiring an additional permission assignment.
    |
    */

    Route::get(
        '/boqs/{boq}/pdf',
        function (
            Request $request,
            Boq $boq
        ) {
            $apiRequest = Request::create(
                '/api/v1/boqs/'.$boq->getKey().'/pdf',
                'GET'
            );

            $apiRequest->setUserResolver(
                fn () => $request->user()
            );

            return app(
                BoqController::class
            )->pdf(
                $apiRequest,
                $boq
            );
        }
    )->name('boqs.pdf');


    /*
    |--------------------------------------------------------------------------
    | Hardware Prices
    |--------------------------------------------------------------------------
    |
    | Hardware pricing requires permission according to the existing
    | WebRoutesTest and RBAC design.
    |
    */

    Route::middleware(
        'permission:hardware-prices.view'
    )->group(function (): void {

        Route::get(
            '/hardware-prices',
            HardwarePricesIndex::class
        )->name('hardware-prices.index');


        Route::get(
            '/hardware-prices/compare',
            HardwarePricesCompare::class
        )->name('hardware-prices.compare');


        Route::get(
            '/hardware-prices/recommendations',
            HardwarePricesRecommendations::class
        )->name(
            'hardware-prices.recommendations'
        );


        Route::get(
            '/hardware-prices/{hardwarePrice}',
            HardwarePricesShow::class
        )->name('hardware-prices.show');
    });


    /*
    |--------------------------------------------------------------------------
    | Plans
    |--------------------------------------------------------------------------
    |
    | Plans are available to all authenticated users.
    |
    */

    Route::get(
        '/plans',
        PlansIndex::class
    )->name('plans.index');


    /*
    |--------------------------------------------------------------------------
    | Customer Subscriptions
    |--------------------------------------------------------------------------
    |
    | This is a normal authenticated customer page.
    |
    | Do NOT place subscription management permission middleware here.
    | Admin subscription management is separately protected below.
    |
    */

    Route::get(
        '/subscriptions',
        SubscriptionsIndex::class
    )->name('subscriptions.index');


    /*
    |--------------------------------------------------------------------------
    | Top-ups
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/topups',
        TopupsIndex::class
    )->name('topups.index');


    /*
    |--------------------------------------------------------------------------
    | AI / MCP Activity
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/system/ai-mcp/activity',
        McpActivity::class
    )
        ->middleware(
            'role:admin,manager,super-admin'
        )
        ->name('system.mcp-activity');


    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/profile',
        ProfileIndex::class
    )->name('profile.edit');


    Route::patch(
        '/profile',
        [
            ProfileController::class,
            'update',
        ]
    )->name('profile.update');


    Route::delete(
        '/profile',
        [
            ProfileController::class,
            'delete',
        ]
    )->name('profile.delete');


    /*
    |--------------------------------------------------------------------------
    | Super Admin
    |--------------------------------------------------------------------------
    */

    Route::middleware(
        'role:super-admin'
    )
        ->prefix('admin')
        ->name('admin.')
        ->group(function (): void {

            /*
            |--------------------------------------------------------------------------
            | Dashboard
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/',
                AdminIndex::class
            )->name('index');


            /*
            |--------------------------------------------------------------------------
            | Plans
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/plans',
                PlansManager::class
            )->name('plans');


            /*
            |--------------------------------------------------------------------------
            | Top-ups
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/topups',
                TopupsManager::class
            )->name('topups');


            /*
            |--------------------------------------------------------------------------
            | Versions
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/versions',
                VersionsManager::class
            )->name('versions');


            /*
            |--------------------------------------------------------------------------
            | Rate Library
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/rates',
                RatesManager::class
            )->name('rates');


            /*
            |--------------------------------------------------------------------------
            | Suppliers
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/suppliers',
                SuppliersManager::class
            )->name('suppliers');


            /*
            |--------------------------------------------------------------------------
            | Supplier Quotations
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/quotations',
                QuotationsManager::class
            )->name('quotations');


            /*
            |--------------------------------------------------------------------------
            | Subscription Management
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/subscriptions',
                SubscriptionsManager::class
            )->name('subscriptions');


            /*
            |--------------------------------------------------------------------------
            | AI Providers
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/ai-providers',
                AiProviders::class
            )->name('ai-providers');


            /*
            |--------------------------------------------------------------------------
            | Payment Gateways
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/payment-gateways',
                PaymentGateways::class
            )->name('payment-gateways');


            /*
            |--------------------------------------------------------------------------
            | Users
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/users',
                UsersManager::class
            )->name('users');


            /*
            |--------------------------------------------------------------------------
            | Roles & Permissions
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/roles-permissions',
                RolesManager::class
            )->name(
                'roles-permissions'
            );


            /*
            |--------------------------------------------------------------------------
            | Hardware Scanner
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/hardware-scanner',
                AdminHardwareScanner::class
            )->name(
                'hardware-scanner'
            );


            /*
            |--------------------------------------------------------------------------
            | System Settings
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/settings',
                SiteSettings::class
            )->name('settings');
        });
});


/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

require __DIR__.'/auth.php';