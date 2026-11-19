<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SubscriptionService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    public function index()
    {
        $tenantId = TenantContext::id();
        $tenant = TenantContext::current();

        $users = User::where('tenant_id', $tenantId)
            ->where('role', '!=', 'super_admin')
            ->orderBy('role', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        $userLimitInfo = $tenant ? $this->subscriptionService->checkUserLimit($tenant) : null;

        return view('users.index', compact('users', 'userLimitInfo'));
    }

    public function store(Request $request)
    {
        $tenant = TenantContext::current();
        $tenantId = TenantContext::id();

        if ($tenant) {
            $limitCheck = $this->subscriptionService->checkUserLimit($tenant);
            if (!$limitCheck['allowed']) {
                return redirect()->route('users.index')->with('error', $limitCheck['message']);
            }
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'in:admin,kasir'],
        ]);

        User::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        return redirect()->route('users.index')
            ->with('success', "Pengguna {$validated['name']} berhasil ditambahkan sebagai " . strtoupper($validated['role']) . '!');
    }

    public function update(Request $request, User $user)
    {
        $tenantId = TenantContext::id();
        if ($user->isSuperAdmin() || ($tenantId !== null && (int) $user->tenant_id !== $tenantId)) {
            abort(403, 'Akses ditolak. Anda tidak memiliki izin mengubah pengguna usaha lain.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users')->ignore($user->id)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['required', 'in:admin,kasir'],
        ]);

        if ($user->id === Auth::id() && $validated['role'] !== 'admin') {
            return back()->with('error', 'Anda tidak dapat mengubah peran akun Anda sendiri.');
        }

        $userData = [
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'role' => $validated['role'],
        ];

        if (!empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }

        $user->update($userData);

        return redirect()->route('users.index')
            ->with('success', "Data pengguna {$user->name} berhasil diperbarui!");
    }

    public function destroy(User $user)
    {
        $tenantId = TenantContext::id();
        if ($user->isSuperAdmin() || ($tenantId !== null && (int) $user->tenant_id !== $tenantId)) {
            abort(403, 'Akses ditolak. Anda tidak memiliki izin menghapus pengguna usaha lain.');
        }

        if ($user->id === Auth::id()) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        if ($user->isAdmin() && User::where('tenant_id', $tenantId)->where('role', 'admin')->count() <= 1) {
            return back()->with('error', 'Tidak dapat menghapus administrator terakhir di usaha Anda.');
        }

        $userName = $user->name;
        $user->delete();

        return redirect()->route('users.index')
            ->with('success', "Pengguna {$userName} berhasil dihapus.");
    }
}
