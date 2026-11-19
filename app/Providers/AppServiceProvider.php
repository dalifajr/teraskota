<?php

namespace App\Providers;

use App\Models\PlatformSetting;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Set Default Timezone and Locale to WIB (Asia/Jakarta)
        $tz = config('app.timezone', 'Asia/Jakarta');
        date_default_timezone_set($tz);
        Carbon::setLocale(config('app.locale', 'id'));

        // Resolve a safe and existing public path for DomPDF & shared hosting
        $resolvedPublicPath = (function_exists('public_path') && is_dir(public_path()))
            ? public_path()
            : (is_dir(base_path('public')) ? base_path('public') : base_path());

        // Ensure storage/fonts directory exists for DomPDF font metrics cache
        $fontDir = storage_path('fonts');
        if (!is_dir($fontDir)) {
            @mkdir($fontDir, 0755, true);
        }

        config([
            'dompdf.public_path' => $resolvedPublicPath,
            'dompdf.options.font_dir' => $fontDir,
            'dompdf.options.font_cache' => $fontDir,
            'dompdf.options.chroot' => array_filter([
                realpath(base_path()),
                realpath($resolvedPublicPath),
            ]),
        ]);

        // Enforce HTTPS URLs when app URL is configured with https or request is proxied via https
        if (str_starts_with((string) config('app.url'), 'https://') || request()->server('HTTP_X_FORWARDED_PROTO') === 'https') {
            URL::forceScheme('https');
        }

        // Share platform branding & active tenant context to all Blade views
        View::composer('*', function ($view) {
            static $cachedPlatformName = null;
            if ($cachedPlatformName === null) {
                $cachedPlatformName = PlatformSetting::platformName();
            }

            $tenant = Auth::check() ? TenantContext::current() : null;
            $isImpersonating = Auth::check()
                && Auth::user()->isSuperAdmin()
                && session()->has('impersonating_tenant_id');

            $view->with([
                'platformName' => $cachedPlatformName,
                'currentTenant' => $tenant,
                'subscriptionState' => $tenant ? $tenant->subscriptionState() : null,
                'isImpersonatingTenant' => $isImpersonating,
            ]);
        });
    }
}
