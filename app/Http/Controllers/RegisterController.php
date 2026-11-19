<?php

namespace App\Http\Controllers;

use App\Models\PlatformSetting;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    public function showRegister()
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->isSuperAdmin()) {
                return redirect()->route('superadmin.dashboard');
            }
            return $user->isKasir()
                ? redirect()->route('pos.index')
                : redirect()->route('dashboard');
        }

        $trialDays = (int) PlatformSetting::getValue('trial_days', config('platform.trial_days', 14));

        return view('auth.register', compact('trialDays'));
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'store_name' => ['required', 'string', 'max:120'],
            'business_type' => ['required', 'string', 'max:60'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'seed_sample' => ['nullable', 'boolean'],
        ], [
            'store_name.required' => 'Nama usaha wajib diisi.',
            'owner_name.required' => 'Nama pemilik wajib diisi.',
            'username.unique' => 'Username sudah digunakan akun lain.',
            'email.unique' => 'Email sudah terdaftar.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $provisioned = $this->subscriptionService->provisionTenant(
            [
                'name' => $validated['store_name'],
                'business_type' => $validated['business_type'],
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
            ],
            [
                'name' => $validated['owner_name'],
                'username' => $validated['username'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ],
            $request->boolean('seed_sample', true)
        );

        Auth::login($provisioned['user']);
        $request->session()->regenerate();

        $trialDays = (int) PlatformSetting::getValue('trial_days', config('platform.trial_days', 14));

        return redirect()->route('dashboard')
            ->with('success', "Selamat datang di " . PlatformSetting::platformName() . "! Usaha {$provisioned['tenant']->name} berhasil dibuat dengan masa uji coba gratis {$trialDays} hari.");
    }
}
