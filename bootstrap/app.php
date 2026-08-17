<?php

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
            'permission' => \App\Http\Middleware\Admin\CheckFeaturePermission::class,
            'can.create.campaigns' => \App\Http\Middleware\Admin\CanCreateCampaigns::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (\Illuminate\Http\Exceptions\PostTooLargeException $e, $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            $limits = \pluginManagerUploadLimits();

            return response()->json([
                'success' => false,
                'message' => sprintf(
                    'Upload rejected: request body exceeds server limit. Set nginx client_max_body_size and PHP upload_max_filesize/post_max_size to at least %s MB (current PHP limits: upload %s MB, post %s MB).',
                    $limits['max_zip_mb'],
                    $limits['php_upload_mb'],
                    $limits['php_post_mb']
                ),
            ], 413);
        });
    })->create();
