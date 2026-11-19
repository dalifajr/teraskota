<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        $userRole = $user->role ?: (($user->username === 'admin') ? 'admin' : 'kasir');

        // Allow Super Admin to act as tenant admin/kasir while impersonating a tenant
        if ($userRole === 'super_admin' && session()->has('impersonating_tenant_id')) {
            if (in_array('admin', $roles, true) || in_array('kasir', $roles, true) || in_array('super_admin', $roles, true)) {
                return $next($request);
            }
        }

        if (!in_array($userRole, $roles, true)) {
            if ($userRole === 'super_admin') {
                return redirect()->route('superadmin.dashboard')
                    ->with('info', 'Pilih toko (Impersonate) terlebih dahulu jika ingin masuk ke panel toko pelanggan.');
            }

            if ($userRole === 'kasir') {
                if ($request->routeIs('pos.*')) {
                    abort(403, 'Akses ditolak.');
                }
                return redirect()->route('pos.index')
                    ->with('error', 'Akses ditolak. Kasir hanya memiliki izin pada menu POS Kasir.');
            }

            // Prevent infinite loop if already requesting dashboard
            if ($request->routeIs('dashboard')) {
                abort(403, 'Akses ditolak. Akun Anda tidak memiliki hak akses administrator.');
            }

            return redirect()->route('dashboard')
                ->with('error', 'Akses ditolak.');
        }

        return $next($request);
    }
}
