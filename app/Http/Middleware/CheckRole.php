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

        if (!in_array($user->role, $roles)) {
            if ($user->isKasir()) {
                return redirect()->route('pos.index')
                    ->with('error', 'Akses ditolak. Kasir hanya memiliki izin pada menu POS Kasir.');
            }

            return redirect()->route('dashboard')
                ->with('error', 'Akses ditolak.');
        }

        return $next($request);
    }
}
