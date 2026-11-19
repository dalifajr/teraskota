<?php

namespace App\Http\Controllers;

use App\Support\TenantContext;
use Illuminate\Http\Request;

class StoreSettingController extends Controller
{
    public function edit()
    {
        $tenant = TenantContext::current();
        if (!$tenant) {
            return redirect()->route('dashboard')->with('error', 'Profil usaha tidak ditemukan.');
        }

        return view('settings.store', compact('tenant'));
    }

    public function update(Request $request)
    {
        $tenant = TenantContext::current();
        if (!$tenant) {
            return redirect()->route('dashboard')->with('error', 'Profil usaha tidak ditemukan.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'business_type' => ['required', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
            'timezone' => ['required', 'in:Asia/Jakarta,Asia/Makassar,Asia/Jayapura'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        $updateData = [
            'name' => $validated['name'],
            'business_type' => $validated['business_type'],
            'address' => $validated['address'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'receipt_footer' => $validated['receipt_footer'] ?? 'Terima kasih atas kunjungan Anda!',
            'timezone' => $validated['timezone'],
        ];

        if ($request->hasFile('logo')) {
            $dir = public_path('uploads/tenants');
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $file = $request->file('logo');
            $filename = 'logo_tenant_' . $tenant->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move($dir, $filename);

            // Delete old uploaded logo if exists
            if ($tenant->logo_path && str_starts_with($tenant->logo_path, 'uploads/tenants/')) {
                @unlink(public_path($tenant->logo_path));
            }

            $updateData['logo_path'] = 'uploads/tenants/' . $filename;
        }

        $tenant->update($updateData);
        TenantContext::refresh();

        return redirect()->route('settings.store.edit')
            ->with('success', 'Profil & branding usaha berhasil diperbarui! Perubahan langsung berlaku pada struk dan laporan PDF.');
    }
}
