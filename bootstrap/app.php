<?php

use App\Exceptions\Handler;
use App\Http\Middleware\CheckEntitlement;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => CheckRole::class,
            'permission' => CheckPermission::class,
            'entitlement' => CheckEntitlement::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            // Only intercept API requests. Delegating web exceptions to the custom
            // Handler would call parent::render(), which re-invokes this callback
            // and recurses until memory exhaustion.
            if ($request->expectsJson() || $request->is('api/*')) {
                return app(Handler::class)->render($request, $e);
            }

            return null;
        });
    })->create();
