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
    ->withMiddleware(function (Middleware $middleware) {
        // الأساسيات فقط
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);

        $middleware->group('admin', ['auth', 'role:admin']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // لا شيء - دع Laravel يتعامل مع الأخطاء تلقائياً
    })
    ->create();
