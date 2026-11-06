<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'listener/*',
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

// Support dynamic public path for shared hosting (InfinityFree, cPanel /htdocs split)
if ($customPublicPath = env('LARAVEL_PUBLIC_PATH')) {
    $app->usePublicPath($customPublicPath);
} elseif (is_dir(dirname(__DIR__) . '/../htdocs') && !is_dir(__DIR__ . '/../public')) {
    $app->usePublicPath(realpath(dirname(__DIR__) . '/../htdocs'));
}

return $app;
