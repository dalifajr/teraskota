<?php

namespace App\Http\Controllers;

use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return $this->redirectByRole(Auth::user());
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $loginField = filter_var($credentials['username'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $authData = [
            $loginField => $credentials['username'],
            'password' => $credentials['password'],
        ];

        if (Auth::attempt($authData, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $request->session()->forget('impersonating_tenant_id');
            TenantContext::clear();

            $user = Auth::user();
            $defaultRedirect = $this->defaultRedirectUrl($user);

            return redirect()->intended($defaultRedirect)
                ->with('success', 'Selamat datang kembali, ' . $user->name . '!');
        }

        return back()->withErrors([
            'username' => 'Kredensial yang diberikan tidak cocok dengan data kami.',
        ])->onlyInput('username');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('impersonating_tenant_id');
        TenantContext::clear();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda telah berhasil keluar.');
    }

    protected function defaultRedirectUrl($user): string
    {
        if ($user->isSuperAdmin()) {
            return route('superadmin.dashboard');
        }

        return $user->isKasir() ? route('pos.index') : route('dashboard');
    }

    protected function redirectByRole($user)
    {
        return redirect()->to($this->defaultRedirectUrl($user));
    }
}
