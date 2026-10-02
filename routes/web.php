<?php

declare(strict_types=1);

use App\Http\Controllers\Api\BoqController;
use App\Http\Controllers\LegalPageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ThemePreferenceController;

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

use App\Livewire\Checkout;
use App\Livewire\Dashboard;

use App\Livewire\HardwarePrices\Compare as HardwarePricesCompare;
use App\Livewire\HardwarePrices\Index as HardwarePricesIndex;
use App\Livewire\SupplierRatings\Index as SupplierRatingsIndex;
use App\Livewire\HardwarePrices\Recommendations as HardwarePricesRecommendations;
use App\Livewire\HardwarePrices\Show as HardwarePricesShow;

use App\Livewire\Plans\Index as PlansIndex;
use App\Livewire\Preferences;
use App\Livewire\Profile\Index as ProfileIndex;

use App\Livewire\Projects\Create as ProjectsCreate;
use App\Livewire\Projects\Edit as ProjectsEdit;
use App\Livewire\Projects\Index as ProjectsIndex;
use App\Livewire\Projects\Show as ProjectsShow;

use App\Livewire\Subscriptions\Index as SubscriptionsIndex;
use App\Livewire\System\McpActivity;
use App\Livewire\Topups\Index as TopupsIndex;
use App\Livewire\Admin\FaqsManager;
use App\Models\Boq;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laragear\WebAuthn\Http\Routes as WebAuthnRoutes;


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

// Biometric (WebAuthn passkey) sign-in and device registration.
WebAuthnRoutes::register()->middleware('throttle:20,1');

// Readiness probe for uptime monitoring (database, cache, storage, queue).
Route::get('/health', \App\Http\Controllers\HealthController::class)->middleware('throttle:30,1')->name('health');

// Signed, time-limited PDF links created by "Share" (WhatsApp / chat apps).
// Progressive Web App (installable site, offline page).
Route::get('/manifest.webmanifest', [\App\Http\Controllers\PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/sw.js', [\App\Http\Controllers\PwaController::class, 'serviceWorker'])->name('pwa.sw');
Route::get('/offline', [\App\Http\Controllers\PwaController::class, 'offline'])->name('pwa.offline');
Route::get('/pwa/icons/{variant}.png', [\App\Http\Controllers\PwaController::class, 'icon'])
    ->where('variant', 'icon-192|icon-512|maskable-512|apple-touch-icon')
    ->name('pwa.icon');

Route::get('/shared/boqs/{boq}/pdf', function (\App\Models\Boq $boq, \App\Services\BoqPdfService $pdfs) {
    try {
        return $pdfs->pdf($boq)->stream($pdfs->filename($boq))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    } catch (\Throwable $e) {
        report($e);
        abort(500);
    }
})->middleware(['signed', 'throttle:30,1'])->name('boqs.shared-pdf');

// One-time, 14-day signed links that let a client review and sign a BOQ without an account.
Route::get('/sign/boqs/{token}', [\App\Http\Controllers\BoqClientSignatureController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{32,64}')
    ->middleware('throttle:30,1')
    ->name('boqs.client-sign');
Route::post('/sign/boqs/{token}', [\App\Http\Controllers\BoqClientSignatureController::class, 'store'])
    ->where('token', '[A-Za-z0-9]{32,64}')
    ->middleware('throttle:10,1')
    ->name('boqs.client-sign.store');

Route::get('/privacy-policy', [LegalPageController::class, 'privacy'])->name('legal.privacy');
Route::get('/terms-of-use', [LegalPageController::class, 'terms'])->name('legal.terms');


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
    | Web checkout (plans and top-ups)
    |--------------------------------------------------------------------------
    */

    Route::get('/checkout/{type}/{id}', Checkout::class)
        ->whereIn('type', ['plan', 'topup'])
        ->whereNumber('id')
        ->name('checkout');


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
    )
        ->middleware('entitlement:boq.management')
        ->name('boqs.index');


    Route::get(
        '/boqs/create',
        BoqsCreate::class
    )
        ->middleware('entitlement:boq.management')
        ->name('boqs.create');


    Route::get(
        '/boqs/{boq}',
        BoqsShow::class
    )
        ->middleware('entitlement:boq.management')
        ->name('boqs.show');


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
                'GET',
                ['inline' => $request->boolean('inline') ? 1 : 0]
            );

            $apiRequest->setUserResolver(
                fn () => $request->user()
            );

            return app(
                BoqController::class
            )->pdf(
                $apiRequest,
                $boq,
                app(\App\Services\BoqPdfService::class)
            );
        }
    )
        ->middleware('entitlement:boq.management')
        ->name('boqs.pdf');

    Route::get('/boqs/{boq}/estimates-template', function (Request $request, Boq $boq) {
        return app(BoqController::class)->estimatesTemplate($request, $boq);
    })
        ->middleware('entitlement:boq.management')
        ->name('boqs.estimates-template');

    // Private copies of the physically signed BOQ (BoqPolicy::view).
    Route::get('/boqs/{boq}/signed-documents/{document}', [\App\Http\Controllers\BoqSignedDocumentController::class, 'download'])
        ->middleware('entitlement:boq.management')
        ->name('boqs.signed-documents.download');


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


        // Users rate hardware suppliers and factories; top 10 and performance charts.
        Route::get(
            '/supplier-ratings',
            SupplierRatingsIndex::class
        )->name('supplier-ratings.index');


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
    | Interface preferences (theme, accent, density, sidebar, font size)
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/preferences',
        Preferences::class
    )->name('preferences');

    Route::post(
        '/preferences/theme',
        ThemePreferenceController::class
    )
        ->middleware('throttle:30,1')
        ->name('preferences.theme');


    /*
    |--------------------------------------------------------------------------
    | Super Admin
    |--------------------------------------------------------------------------
    */

    // Categories are managed by admins as well as super admins.
    Route::get('/admin/categories', \App\Livewire\Admin\CategoriesManager::class)
        ->middleware('role:super-admin,administrator,admin')
        ->name('admin.categories');

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

            Route::get('/faqs', FaqsManager::class)->name('faqs');
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