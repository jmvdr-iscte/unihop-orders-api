<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware as CustomMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            '/orders/*',
            '/order/*',
            '/health'
        ]);
        $middleware->alias( [
            'webhook' => CustomMiddleware\Webhook::class,
            'request.logger' => CustomMiddleware\RequestLogger::class
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
