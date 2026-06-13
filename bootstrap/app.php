<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust the X-Forwarded-* headers from Railway's reverse proxy
        // This ensures HTTPS detection works correctly behind the proxy
        $middleware->trustProxies(
            at: '*',
            headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
                    | \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST
                    | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO
                    | \Illuminate\Http\Request::HEADER_X_FORWARDED_AWS_ELB,
        );
        
        // Register middleware aliases
        $middleware->alias([
            'superadmin' => \App\Http\Middleware\SuperAdminMiddleware::class,
            'ensure.role' => \App\Http\Middleware\EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
