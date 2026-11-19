@extends('layouts.app')

@section('title', 'Daftar Usaha (Tenant)')
@section('page_title', 'Manajemen Usaha & Langganan')

@section('breadcrumbs')
    <li class="breadcrumb-item active">Daftar Usaha (Tenant)</li>
@endsection

@section('content')
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom">
        <h6 class="fw-bold m-0"><i class="fa-solid fa-store text-success me-2"></i>Seluruh Usaha Terdaftar</h6>
        <form method="GET" class="d-flex gap-2">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama usaha / slug..." value="{{ request('search') }}">
            <button class="btn btn-sm btn-success" type="submit">Cari</button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Usaha / Tenant</th>
                    <th>Statistik</th>
                    <th>Paket &amp; Masa Aktif</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tenants as $tenant)
                    @php $state = $tenant->subscriptionState(); @endphp
                    <tr>
                        <td>
                            <div class="fw-bold text-dark">{{ $tenant->name }}</div>
                            <div class="text-muted" style="font-size: 0.78rem;">
                                <code>{{ $tenant->slug }}</code> · {{ $tenant->business_type }}
                            </div>
                        </td>
                        <td>
                            <div><i class="fa-solid fa-users text-muted me-1"></i>{{ $tenant->users_count }} user · <i class="fa-solid fa-mug-hot text-muted me-1"></i>{{ $tenant->menus_count }} menu</div>
                            <div class="text-muted">{{ number_format($tenant->transactions_count, 0, ',', '.') }} transaksi</div>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $tenant->is_lifetime ? 'VIP Lifetime (Pro)' : ($tenant->currentPlan()?->name ?? '-') }}</div>
                            @if($tenant->activeSubscription?->ends_at && !$tenant->is_lifetime)
                                <small class="text-muted">s/d {{ $tenant->activeSubscription->ends_at->translatedFormat('d M Y') }}</small>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ in_array($state, ['active','lifetime']) ? 'bg-success' : ($state === 'trial' ? 'bg-info text-dark' : ($state === 'grace' ? 'bg-warning text-dark' : 'bg-danger')) }}">
                                {{ strtoupper($state) }}
                            </span>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-success py-0 px-2" data-bs-toggle="modal" data-bs-target="#subModal{{ $tenant->id }}">
                                Atur Paket
                            </button>
                            <form action="{{ route('superadmin.tenants.impersonate', $tenant->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-primary py-0 px-2">Masuk</button>
                            </form>
                            <form action="{{ route('superadmin.tenants.toggle', $tenant->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Ubah status aktif/suspend usaha ini?');">
                                @csrf
                                <button type="submit" class="btn btn-sm {{ $tenant->is_active ? 'btn-outline-danger' : 'btn-outline-secondary' }} py-0 px-2">
                                    {{ $tenant->is_active ? 'Suspend' : 'Aktifkan' }}
                                </button>
                            </form>
                        </td>
                    </tr>

                    <!-- Modal Atur Paket Manual -->
                    <div class="modal fade" id="subModal{{ $tenant->id }}" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <form action="{{ route('superadmin.tenants.subscription', $tenant->id) }}" method="POST" class="modal-content rounded-4 border-0 shadow">
                                @csrf
                                <div class="modal-header">
                                    <h6 class="modal-title fw-bold">Atur Langganan: {{ $tenant->name }}</h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Paket</label>
                                        <select name="plan_id" class="form-select" required>
                                            @foreach($plans as $p)
                                                <option value="{{ $p->id }}" {{ $tenant->currentPlan()?->id === $p->id ? 'selected' : '' }}>
                                                    {{ $p->name }} (Rp{{ number_format((float)$p->price_monthly, 0, ',', '.') }}/bln)
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Siklus / Durasi</label>
                                        <select name="billing_cycle" class="form-select" required>
                                            <option value="monthly">1 Bulan (Monthly)</option>
                                            <option value="yearly">1 Tahun (Yearly)</option>
                                            <option value="trial">Perpanjang Trial (14 Hari)</option>
                                            <option value="lifetime" {{ $tenant->is_lifetime ? 'selected' : '' }}>VIP Lifetime (Selamanya)</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Atau Durasi Kustom (Hari, opsional)</label>
                                        <input type="number" name="custom_days" class="form-control" min="1" max="3650" placeholder="Kosongkan untuk durasi default siklus">
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label small fw-semibold">Catatan</label>
                                        <input type="text" name="notes" class="form-control" placeholder="cth: Pembayaran tunai / promo referral">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-success">Simpan Perubahan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">Belum ada usaha terdaftar.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($tenants->hasPages())
        <div class="card-footer bg-white">{{ $tenants->links() }}</div>
    @endif
</div>
@endsection
