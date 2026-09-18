<?php

use App\Http\Controllers\Api\AiProviderController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BoqController;
use App\Http\Controllers\Api\BoqPricingJobController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\HardwareCategoryController;
use App\Http\Controllers\Api\HardwarePriceController;
use App\Http\Controllers\Api\Mcp\McpToolController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProxySubscriptionController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and assigned to the "api"
| middleware group. Enjoy building your API!
|
*/

Route::prefix('v1')->group(function () {

    // Public routes
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    // Public plans listing
    Route::get('plans', [PlanController::class, 'index'])->middleware('throttle:60,1');

    // Provider callbacks are never trusted as payment proof; the controller re-queries the configured provider.
    Route::post('payment-webhooks/{gatewayCode}', [PaymentController::class, 'webhook'])->middleware('throttle:120,1');

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {

        // Dedicated service-token boundary for MCP. Only whitelisted read/calculation tools are dispatched.
        Route::post('mcp/tools/{tool}', McpToolController::class)->middleware('throttle:60,1');

        // Auth
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::put('auth/profile', [AuthController::class, 'updateProfile']);

        // Dashboard
        Route::get('dashboard', [DashboardController::class, 'index']);
        Route::middleware(['permission:boq.edit', 'entitlement:boq.management'])->group(function () {
            Route::post('boqs', [BoqController::class, 'store'])
                ->middleware('throttle:10,1')
                ->name('api.v1.boqs.store');
        });

        Route::middleware(['permission:boq.view', 'entitlement:boq.management'])->group(function () {
            Route::get('boqs', [BoqController::class, 'index'])->name('api.v1.boqs.index');
            Route::get('boqs/{boq}', [BoqController::class, 'show'])->name('api.v1.boqs.show');
            Route::get('boqs/{boq}/items', [BoqController::class, 'items'])->name('api.v1.boqs.items');
            Route::get('boqs/{boq}/pdf', [BoqController::class, 'pdf'])
                ->middleware('throttle:10,1')
                ->name('api.v1.boqs.pdf');
        });

        Route::middleware(['permission:boq.edit', 'entitlement:boq.management', 'entitlement:boq.import.excel,boq_imports'])
            ->group(function () {
                Route::post('boqs/{boq}/process', [BoqController::class, 'process'])
                    ->middleware('throttle:5,1')
                    ->name('api.v1.boqs.process');
            });
        Route::post('boqs/{boq}/price-all', [BoqController::class, 'priceAll']);
        Route::post('boqs/{boq}/pricing-batches', [BoqController::class, 'startPricingBatch']);
        Route::get('pricing-batches/{batch}', [BoqController::class, 'pricingBatch']);
        Route::post('boq-items/{boqItem}/price', [BoqController::class, 'price']);
        Route::get('boqs/{boq}/pricing-history/{location}', [BoqController::class, 'pricingHistory']);
        Route::get('boqs/{boq}/pricing-history', [BoqController::class, 'pricingHistory']);

        // BOQ Pricing Jobs
        Route::middleware(['permission:boq.edit', 'entitlement:boq.management'])->group(function () {
            Route::post('boqs/{boq}/pricing-jobs', [BoqPricingJobController::class, 'store'])
                ->middleware('throttle:10,1')
                ->name('api.v1.boqs.pricing-jobs.store');
        });

        Route::middleware(['permission:boq.view', 'entitlement:boq.management'])->group(function () {
            Route::get('pricing-jobs/{job}', [BoqPricingJobController::class, 'show'])
                ->name('api.v1.pricing-jobs.show');
            Route::get('pricing-jobs/{job}/progress', [BoqPricingJobController::class, 'progress'])
                ->name('api.v1.pricing-jobs.progress');
        });

        Route::middleware(['permission:boq.edit', 'entitlement:boq.management'])->group(function () {
            Route::post('pricing-jobs/{job}/start', [BoqPricingJobController::class, 'start'])
                ->name('api.v1.pricing-jobs.start');
            Route::post('pricing-jobs/{job}/next-batch', [BoqPricingJobController::class, 'nextBatch'])
                ->name('api.v1.pricing-jobs.next-batch');
            Route::post('pricing-jobs/{job}/pause', [BoqPricingJobController::class, 'pause'])
                ->name('api.v1.pricing-jobs.pause');
            Route::post('pricing-jobs/{job}/resume', [BoqPricingJobController::class, 'resume'])
                ->name('api.v1.pricing-jobs.resume');
            Route::post('pricing-jobs/{job}/cancel', [BoqPricingJobController::class, 'cancel'])
                ->name('api.v1.pricing-jobs.cancel');
            Route::post('pricing-jobs/{job}/retry-failed', [BoqPricingJobController::class, 'retryFailed'])
                ->name('api.v1.pricing-jobs.retry-failed');
            Route::post('pricing-jobs/{job}/lock', [BoqPricingJobController::class, 'lock'])
                ->name('api.v1.pricing-jobs.lock');
            Route::post('pricing-jobs/{job}/unlock', [BoqPricingJobController::class, 'unlock'])
                ->name('api.v1.pricing-jobs.unlock');
        });

        // Hardware Prices
        Route::middleware('permission:hardware-prices.view')->group(function () {
            Route::get('hardware-prices', [HardwarePriceController::class, 'index']);
            Route::get('hardware-prices/statistics', [HardwarePriceController::class, 'statistics']);
            Route::get('hardware-prices/categories', [HardwarePriceController::class, 'categories']);
            Route::get('hardware-prices/recommendations', [HardwarePriceController::class, 'recommendations']);
            Route::post('hardware-prices/compare', [HardwarePriceController::class, 'compare']);
            Route::get('hardware-prices/{hardwarePrice}', [HardwarePriceController::class, 'show']);
            Route::get('hardware-prices/{hardwarePrice}/history', [HardwarePriceController::class, 'history']);
            Route::get('boq-items/{boqItem}/matches', [HardwarePriceController::class, 'matchBoqItem']);
            Route::post('boq-items/{boqItem}/apply-price', [HardwarePriceController::class, 'applyPrice']);
        });

        Route::middleware('permission:hardware-prices.manage')->group(function () {
            Route::post('hardware-prices/fetch', [HardwarePriceController::class, 'fetchNow']);
        });

        // AI Providers
        Route::middleware('permission:hardware-prices.view')->group(function () {
            Route::get('ai-providers', [AiProviderController::class, 'index'])->name('api.v1.ai-providers.index');
            Route::get('ai-providers/{provider}', [AiProviderController::class, 'show'])->name('api.v1.ai-providers.show');
        });

        Route::middleware('permission:hardware-prices.manage')->group(function () {
            Route::post('ai-providers', [AiProviderController::class, 'store'])->middleware('throttle:10,1')->name('api.v1.ai-providers.store');
            Route::put('ai-providers/{provider}', [AiProviderController::class, 'update'])->middleware('throttle:10,1')->name('api.v1.ai-providers.update');
            Route::delete('ai-providers/{provider}', [AiProviderController::class, 'destroy'])->middleware('throttle:10,1')->name('api.v1.ai-providers.destroy');
            Route::post('ai-providers/{provider}/test', [AiProviderController::class, 'test'])->middleware('throttle:30,1')->name('api.v1.ai-providers.test');
            Route::post('ai-providers/{provider}/set-default', [AiProviderController::class, 'setDefault'])->middleware('throttle:10,1')->name('api.v1.ai-providers.set-default');
        });

        // Hardware Categories
        Route::middleware('permission:hardware-prices.view')->group(function () {
            Route::get('hardware-categories', [HardwareCategoryController::class, 'index'])->name('api.v1.hardware-categories.index');
            Route::get('hardware-categories/{category}', [HardwareCategoryController::class, 'show'])->name('api.v1.hardware-categories.show');
        });

        Route::middleware('permission:hardware-prices.manage')->group(function () {
            Route::post('hardware-categories', [HardwareCategoryController::class, 'store'])->middleware('throttle:10,1')->name('api.v1.hardware-categories.store');
            Route::put('hardware-categories/{category}', [HardwareCategoryController::class, 'update'])->middleware('throttle:10,1')->name('api.v1.hardware-categories.update');
            Route::delete('hardware-categories/{category}', [HardwareCategoryController::class, 'destroy'])->middleware('throttle:10,1')->name('api.v1.hardware-categories.destroy');
            Route::post('hardware-categories/{category}/toggle-active', [HardwareCategoryController::class, 'toggleActive'])->middleware('throttle:10,1')->name('api.v1.hardware-categories.toggle-active');
        });

        // Plans
        Route::get('plans/{plan}', [PlanController::class, 'show']);

        // Payments
        Route::get('payment-gateways', [PaymentController::class, 'gateways']);
        Route::post('subscriptions/{subscription}/payments', [PaymentController::class, 'initiate'])->middleware('throttle:10,1');
        Route::post('transactions/{transaction}/verify', [PaymentController::class, 'verify'])->middleware('throttle:20,1');
        Route::get('transactions/{transaction}', [PaymentController::class, 'show']);
        Route::get('transactions/{transaction}/receipt', [PaymentController::class, 'receipt']);

        // Subscriptions
        Route::get('subscriptions', [SubscriptionController::class, 'index']);
        Route::post('subscriptions', [SubscriptionController::class, 'store']);
        Route::get('subscriptions/current', [SubscriptionController::class, 'current']);
        Route::get('subscriptions/{subscription}', [SubscriptionController::class, 'show']);

        // Proxy Subscriptions (Admin only - Super Admin/Admin roles)
        Route::middleware('permission:subscriptions.view')->group(function () {
            Route::get('subscriptions/proxy/beneficiaries', [ProxySubscriptionController::class, 'searchBeneficiaries'])
                ->middleware('throttle:30,1')
                ->name('api.v1.proxy-subscriptions.beneficiaries');
            Route::get('subscriptions/proxy', [ProxySubscriptionController::class, 'index'])
                ->name('api.v1.proxy-subscriptions.index');
            Route::get('subscriptions/proxy/{subscription}', [ProxySubscriptionController::class, 'show'])
                ->name('api.v1.proxy-subscriptions.show');
        });

        Route::middleware('permission:subscriptions.manage')->group(function () {
            Route::post('subscriptions/proxy', [ProxySubscriptionController::class, 'store'])
                ->middleware('throttle:10,1')
                ->name('api.v1.proxy-subscriptions.store');
            Route::post('subscriptions/proxy/{subscription}/payments', [ProxySubscriptionController::class, 'initiatePayment'])
                ->middleware('throttle:10,1')
                ->name('api.v1.proxy-subscriptions.payments.initiate');
            Route::post('transactions/proxy/{transaction}/verify', [ProxySubscriptionController::class, 'verifyPayment'])
                ->middleware('throttle:20,1')
                ->name('api.v1.proxy-transactions.verify');
            Route::post('subscriptions/proxy/{subscription}/cancel', [ProxySubscriptionController::class, 'cancel'])
                ->middleware('throttle:10,1')
                ->name('api.v1.proxy-subscriptions.cancel');
        });

        // Projects (requires project.view permission)
        Route::middleware('permission:projects.view')->group(function () {
            Route::get('projects', [ProjectController::class, 'index']);
            Route::get('projects/{project}', [ProjectController::class, 'show']);
        });

        // Project creation (requires project.create permission)
        Route::middleware('permission:projects.create')->group(function () {
            Route::post('projects', [ProjectController::class, 'store']);
        });

        // Project management (requires project.edit permission)
        Route::middleware('permission:projects.edit')->group(function () {
            Route::put('projects/{project}', [ProjectController::class, 'update']);
            Route::delete('projects/{project}', [ProjectController::class, 'destroy']);
        });
    });
});
