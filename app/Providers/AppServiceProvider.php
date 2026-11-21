<?php

namespace App\Providers;

use App\Models\PlatformSetting;
use App\Models\SubscriptionInvoice;
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

        // Share platform branding, active tenant context, superadmin badge & broadcast banner to all Blade views
        View::composer('*', function ($view) {
            $req = request();
            $shared = $req->attributes->get('_kl_view_shared');

            if ($shared === null) {
                $platformName = PlatformSetting::platformName();
                $user = Auth::user();
                $tenant = $user ? TenantContext::current() : null;
                $isImpersonating = $user
                    && $user->isSuperAdmin()
                    && session()->has('impersonating_tenant_id');

                $pendingInvoicesBadgeCount = 0;
                if ($user && $user->isSuperAdmin() && !$isImpersonating) {
                    try {
                        SubscriptionInvoice::expireOverdue();
                        $pendingInvoicesBadgeCount = SubscriptionInvoice::where('status', 'unpaid')->count();
                    } catch (\Throwable $e) {
                        $pendingInvoicesBadgeCount = 0;
                    }
                }

                $announcementEnabled = PlatformSetting::getValue('announcement_enabled', '0') === '1';
                $announcementMessage = trim((string) PlatformSetting::getValue('announcement_message', ''));
                $announcementType = (string) PlatformSetting::getValue('announcement_type', 'info');

                $platformAnnouncement = ($announcementEnabled && $announcementMessage !== '')
                    ? [
                        'message' => $announcementMessage,
                        'type' => in_array($announcementType, ['info', 'warning', 'success', 'danger'], true) ? $announcementType : 'info',
                    ]
                    : null;

                $shared = [
                    'platformName' => $platformName,
                    'currentTenant' => $tenant,
                    'subscriptionState' => $tenant ? $tenant->subscriptionState() : null,
                    'isImpersonatingTenant' => $isImpersonating,
                    'pendingInvoicesBadgeCount' => $pendingInvoicesBadgeCount,
                    'platformAnnouncement' => $platformAnnouncement,
                ];

                $req->attributes->set('_kl_view_shared', $shared);
            }

            $view->with($shared);
        });
    }
}
