<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    /**
     * Enforce tenant subscription status and optional feature gating.
     *
     * Usage:
     *   ->middleware('subscription')                // Allow read-only when expired; block POS & write methods
     *   ->middleware('subscription:active')         // Strictly require active/trial/grace (e.g. POS terminal)
     *   ->middleware('subscription:qris_dynamic')   // Require specific plan feature
     */
    public function handle(Request $request, Closure $next, ?string $requirement = null): Response
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        // Super admin without impersonation is not gated by tenant subscriptions
        if ($user->isSuperAdmin() && !session()->has('impersonating_tenant_id')) {
            return $next($request);
        }

        $tenant = TenantContext::current();
        if (!$tenant) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun Anda belum terhubung ke usaha/toko manapun.',
                ], 403);
            }
            return redirect()->route('login')->with('error', 'Akun tidak memiliki profil usaha aktif.');
        }

        // Sync PHP backend timezone with active tenant store timezone (WIB/WITA/WIT)
        if (in_array($tenant->timezone, ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'], true)) {
            date_default_timezone_set($tenant->timezone);
            config(['app.timezone' => $tenant->timezone]);
        }

        $state = $tenant->subscriptionState();

        // 1. Suspended or Expired check
        if (!$tenant->canOperate()) {
            $isWriteMethod = !in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);
            $strictlyRequired = $requirement === 'active' || ($requirement !== null && $requirement !== 'readonly');

            if ($isWriteMethod || $strictlyRequired) {
                $reason = $state === 'suspended'
                    ? 'Akun usaha Anda sedang dinonaktifkan sementara oleh pengelola platform. Hubungi tim support.'
                    : 'Masa aktif langganan usaha Anda telah berakhir. Perpanjang paket untuk melanjutkan transaksi & perubahan data.';

                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'subscription_expired' => true,
                        'state' => $state,
                        'message' => $reason,
                        'billing_url' => route('billing.index'),
                    ], 402);
                }

                if ($user->isKasir()) {
                    return response()->view('billing.locked-cashier', [
                        'tenant' => $tenant,
                        'reason' => $reason,
                    ], 402);
                }

                return redirect()->route('billing.index')->with('error', $reason);
            }
        }

        // 2. Specific plan feature check (e.g., qris_dynamic)
        if ($requirement !== null && !in_array($requirement, ['active', 'readonly'], true)) {
            if (!$tenant->hasFeature($requirement)) {
                $featureLabel = match ($requirement) {
                    'qris_dynamic' => 'QRIS Dinamis & Auto-Verify Android Listener',
                    'offline_sync' => 'Mode Offline POS & Auto-Sync',
                    'pdf_export' => 'Ekspor Laporan PDF',
                    'expenses' => 'Pencatatan Pengeluaran Kas',
                    default => ucfirst(str_replace('_', ' ', $requirement)),
                };

                $msg = "Fitur {$featureLabel} hanya tersedia pada paket Pro. Upgrade paket Anda untuk mengaktifkan fitur ini.";

                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'feature_locked' => true,
                        'feature' => $requirement,
                        'message' => $msg,
                        'billing_url' => route('billing.index'),
                    ], 403);
                }

                return redirect()->route('billing.index')->with('error', $msg);
            }
        }

        return $next($request);
    }
}
