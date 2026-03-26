<?php

use App\Http\Middleware\Admin\ApiAdminAuth;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // ✅ Enable session + cookie middleware for API routes
        $middleware->api(prepend: [
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        ]);

        //
        $middleware->alias([
            'admin.auth' => \App\Http\Middleware\Admin\AdminAuth::class,
            'admin.guest' => \App\Http\Middleware\Admin\AdminGuest::class,
            'admin.api.auth' => \App\Http\Middleware\Admin\ApiAdminAuth::class,
            'role' => \App\Http\Middleware\Admin\CheckRole::class,
            'can.create.campaigns' => \App\Http\Middleware\Admin\CanCreateCampaigns::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
