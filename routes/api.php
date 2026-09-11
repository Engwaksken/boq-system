<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BoqController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\ProjectController;
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

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::put('auth/profile', [AuthController::class, 'updateProfile']);

        // Dashboard
        Route::get('dashboard', [DashboardController::class, 'index']);
        Route::post('boqs', [BoqController::class, 'store']);
        Route::get('boqs/{boq}', [BoqController::class, 'show']);
        Route::get('boqs/{boq}/pdf', [BoqController::class, 'pdf']);
        Route::post('boqs/{boq}/process', [BoqController::class, 'process']);
        Route::post('boqs/{boq}/price-all', [BoqController::class, 'priceAll']);
        Route::post('boqs/{boq}/pricing-batches', [BoqController::class, 'startPricingBatch']);
        Route::get('pricing-batches/{batch}', [BoqController::class, 'pricingBatch']);
        Route::post('boq-items/{boqItem}/price', [BoqController::class, 'price']);
        Route::get('boqs/{boq}/pricing-history/{location}', [BoqController::class, 'pricingHistory']);

        // Plans
        Route::get('plans/{plan}', [PlanController::class, 'show']);

        // Subscriptions
        Route::get('subscriptions', [SubscriptionController::class, 'index']);
        Route::post('subscriptions', [SubscriptionController::class, 'store']);
        Route::get('subscriptions/current', [SubscriptionController::class, 'current']);
        Route::get('subscriptions/{subscription}', [SubscriptionController::class, 'show']);

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
