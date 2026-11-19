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
        // Trust all reverse proxies (Cloudflare, InfinityFree/ByetHost SSL gateway)
        // to prevent ERR_TOO_MANY_REDIRECTS and ensure HTTPS scheme detection
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'subscription' => \App\Http\Middleware\CheckSubscription::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'listener/*',
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

// Support dynamic public path for shared hosting (InfinityFree, cPanel /htdocs)
if ($customPublicPath = env('LARAVEL_PUBLIC_PATH')) {
    $resolved = realpath(base_path($customPublicPath)) ?: (realpath($customPublicPath) ?: $customPublicPath);
    $app->usePublicPath($resolved);
} elseif (is_dir(dirname(__DIR__) . '/../htdocs') && !is_dir(__DIR__ . '/../public')) {
    $app->usePublicPath(realpath(dirname(__DIR__) . '/../htdocs'));
} elseif (basename(dirname(__DIR__)) === 'core' && file_exists(dirname(dirname(__DIR__)) . '/index.php')) {
    // Hosted directly inside htdocs/core -> parent folder is the web root
    $app->usePublicPath(dirname(dirname(__DIR__)));
}

// Ensure physical public and storage/fonts directories exist to prevent DomPDF/package errors
if (!is_dir(dirname(__DIR__) . '/public')) {
    @mkdir(dirname(__DIR__) . '/public', 0755, true);
}
if (!is_dir(dirname(__DIR__) . '/storage/fonts')) {
    @mkdir(dirname(__DIR__) . '/storage/fonts', 0755, true);
}

return $app;
