@extends('layouts.app')

@section('title', 'Daftar Usaha (Tenant)')
@section('page_title', 'Manajemen Usaha & Langganan')

@section('breadcrumbs')
    <li class="breadcrumb-item active">Daftar Usaha (Tenant)</li>
@endsection

@section('content')
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <h6 class="fw-bold m-0 me-2"><i class="fa-solid fa-store text-success me-2"></i>Seluruh Usaha Terdaftar</h6>
            @php $currentStatusFilter = request('status', ''); @endphp
            <div class="btn-group btn-group-sm" role="group" aria-label="Filter Status Langganan">
                <a href="{{ route('superadmin.tenants', array_filter(['search' => request('search')])) }}" class="btn {{ $currentStatusFilter === '' ? 'btn-dark' : 'btn-outline-secondary' }}">Semua</a>
                <a href="{{ route('superadmin.tenants', array_filter(['status' => 'active', 'search' => request('search')])) }}" class="btn {{ $currentStatusFilter === 'active' ? 'btn-success' : 'btn-outline-success' }}">Aktif</a>
                <a href="{{ route('superadmin.tenants', array_filter(['status' => 'trial', 'search' => request('search')])) }}" class="btn {{ $currentStatusFilter === 'trial' ? 'btn-info text-dark fw-semibold' : 'btn-outline-info text-dark' }}">Trial</a>
                <a href="{{ route('superadmin.tenants', array_filter(['status' => 'grace', 'search' => request('search')])) }}" class="btn {{ $currentStatusFilter === 'grace' ? 'btn-warning text-dark fw-semibold' : 'btn-outline-warning text-dark' }}">Grace</a>
                <a href="{{ route('superadmin.tenants', array_filter(['status' => 'expired', 'search' => request('search')])) }}" class="btn {{ $currentStatusFilter === 'expired' ? 'btn-danger' : 'btn-outline-danger' }}">Expired</a>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <form method="GET" class="d-flex gap-2">
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama usaha / slug..." value="{{ request('search') }}">
                <button class="btn btn-sm btn-outline-success" type="submit">Cari</button>
            </form>
            <button type="button" class="btn btn-sm btn-primary-green" data-bs-toggle="modal" data-bs-target="#createTenantModal">
                <i class="fa-solid fa-plus me-1"></i>Tambah Usaha Baru
            </button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Usaha / Tenant</th>
                    <th>Pemilik &amp; Kontak</th>
                    <th>Statistik</th>
                    <th>Paket &amp; Masa Aktif</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tenants as $tenant)
                    @php
                        $state = $tenant->subscriptionState();
                        $owner = $tenant->users->firstWhere('role', 'admin') ?? $tenant->users->first();
                        $waNumber = preg_replace('/[^0-9]/', '', (string) $tenant->phone);
                        if (str_starts_with($waNumber, '0')) {
                            $waNumber = '62' . substr($waNumber, 1);
                        }
                        $waGreeting = "Halo {$tenant->name}, kami dari tim support {$platformName}. Ada yang bisa kami bantu untuk operasional kasir Anda?";
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-bold text-dark">{{ $tenant->name }}</div>
                            <div class="text-muted" style="font-size: 0.78rem;">
                                <code>{{ $tenant->slug }}</code> · {{ $tenant->business_type }}
                            </div>
                        </td>
                        <td>
                            @if($owner)
                                <div class="fw-semibold text-dark">{{ $owner->name }}</div>
                                <div class="text-muted" style="font-size: 0.75rem;">
                                    <code>{{ $owner->username }}</code> · {{ $owner->email }}
                                </div>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                            @if($tenant->phone)
                                <div class="mt-1">
                                    <a href="https://wa.me/{{ $waNumber }}?text={{ urlencode($waGreeting) }}" target="_blank" rel="noopener noreferrer" class="text-success text-decoration-none fw-semibold" style="font-size: 0.76rem;">
                                        <i class="fa-brands fa-whatsapp me-1"></i>{{ $tenant->phone }}
                                    </a>
                                </div>
                            @endif
                        </td>
                        <td style="font-variant-numeric: tabular-nums;">
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
                            <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" data-bs-toggle="modal" data-bs-target="#detailModal{{ $tenant->id }}" title="Lihat Detail & Riwayat Usaha">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-info text-dark py-0 px-2" data-bs-toggle="modal" data-bs-target="#editTenantModal{{ $tenant->id }}" title="Edit Info Usaha">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success py-0 px-2" data-bs-toggle="modal" data-bs-target="#subModal{{ $tenant->id }}">
                                    Paket
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-warning text-dark py-0 px-2" data-bs-toggle="modal" data-bs-target="#pwdModal{{ $tenant->id }}" title="Reset Password Pemilik">
                                    <i class="fa-solid fa-key"></i>
                                </button>
                                <form action="{{ route('superadmin.tenants.impersonate', $tenant->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-primary py-0 px-2" title="Masuk ke Dashboard Usaha (Mode Support)">Masuk</button>
                                </form>
                                <form action="{{ route('superadmin.tenants.toggle', $tenant->id) }}" method="POST" class="d-inline" data-confirm="Ubah status aktif/suspend untuk usaha {{ $tenant->name }}?">
                                    @csrf
                                    <button type="submit" class="btn btn-sm {{ $tenant->is_active ? 'btn-outline-danger' : 'btn-outline-secondary' }} py-0 px-2">
                                        {{ $tenant->is_active ? 'Suspend' : 'Aktifkan' }}
                                    </button>
                                </form>
                                @if(!$tenant->is_active && !$tenant->is_lifetime)
                                    <form action="{{ route('superadmin.tenants.destroy', $tenant->id) }}" method="POST" class="d-inline" data-confirm="PERINGATAN: Hapus permanen usaha {{ $tenant->name }} beserta seluruh data user, menu, transaksi, dan invoice-nya? Tindakan ini tidak dapat dibatalkan!">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger py-0 px-2" title="Hapus Permanen Usaha">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada usaha terdaftar.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($tenants->hasPages())
        <div class="card-footer bg-white">{{ $tenants->links() }}</div>
    @endif
</div>

<!-- Shared Smart Business Type Presets Datalist -->
<datalist id="businessTypePresets">
    <option value="Kedai Minuman / Kopi">
    <option value="Warung Makan / Resto">
    <option value="Bakery / Kue">
    <option value="Toko Kelontong / Retail">
</datalist>

@foreach($tenants as $tenant)
    @php
        $owner = $tenant->users->firstWhere('role', 'admin') ?? $tenant->users->first();
        $waNumber = preg_replace('/[^0-9]/', '', (string) $tenant->phone);
        if (str_starts_with($waNumber, '0')) {
            $waNumber = '62' . substr($waNumber, 1);
        }
    @endphp

    <!-- Modal Detail Usaha & Riwayat Invoice -->
    <div class="modal fade" id="detailModal{{ $tenant->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold"><i class="fa-solid fa-store text-success me-2"></i>Detail Usaha: {{ $tenant->name }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body small">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="p-3 rounded-3 bg-light border h-100">
                                <div class="text-uppercase text-muted fw-bold mb-2" style="font-size: 0.7rem;">Profil &amp; Kontak</div>
                                <dl class="row mb-0 g-1">
                                    <dt class="col-4 text-muted">Slug</dt>
                                    <dd class="col-8 mb-1"><code>{{ $tenant->slug }}</code></dd>
                                    <dt class="col-4 text-muted">Jenis Usaha</dt>
                                    <dd class="col-8 mb-1">{{ $tenant->business_type }}</dd>
                                    <dt class="col-4 text-muted">WhatsApp</dt>
                                    <dd class="col-8 mb-1">
                                        @if($tenant->phone)
                                            <a href="https://wa.me/{{ $waNumber }}" target="_blank" rel="noopener noreferrer" class="text-success fw-semibold text-decoration-none">
                                                <i class="fa-brands fa-whatsapp me-1"></i>{{ $tenant->phone }}
                                            </a>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </dd>
                                    <dt class="col-4 text-muted">Alamat</dt>
                                    <dd class="col-8 mb-1">{{ $tenant->address ?: '-' }}</dd>
                                    <dt class="col-4 text-muted">Terdaftar</dt>
                                    <dd class="col-8 mb-0">{{ $tenant->created_at?->translatedFormat('d M Y, H:i') }}</dd>
                                </dl>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 rounded-3 bg-light border h-100">
                                <div class="text-uppercase text-muted fw-bold mb-2" style="font-size: 0.7rem;">Daftar Akun Usaha ({{ $tenant->users->count() }})</div>
                                <ul class="list-unstyled mb-0">
                                    @forelse($tenant->users as $tu)
                                        <li class="d-flex justify-content-between align-items-center py-1 border-bottom">
                                            <div>
                                                <div class="fw-semibold text-dark">{{ $tu->name }}</div>
                                                <small class="text-muted"><code>{{ $tu->username }}</code> · {{ $tu->email }}</small>
                                            </div>
                                            <span class="badge {{ $tu->role === 'admin' ? 'bg-primary' : 'bg-success' }}">{{ strtoupper($tu->role) }}</span>
                                        </li>
                                    @empty
                                        <li class="text-muted">Belum ada akun.</li>
                                    @endforelse
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="fw-bold text-dark mb-2"><i class="fa-solid fa-file-invoice-dollar text-success me-1"></i>5 Invoice Langganan Terakhir</div>
                    <div class="table-responsive border rounded-3">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>No. Invoice</th>
                                    <th>Paket</th>
                                    <th>Nominal</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($tenant->invoices->take(5) as $tInv)
                                    <tr>
                                        <td class="font-monospace">{{ $tInv->invoice_number }}</td>
                                        <td>{{ $tInv->plan?->name ?? '-' }} ({{ $tInv->billing_cycle }})</td>
                                        <td class="fw-semibold">Rp{{ number_format((float)$tInv->final_amount, 0, ',', '.') }}</td>
                                        <td>
                                            <span class="badge {{ $tInv->status === 'paid' ? 'bg-success' : ($tInv->status === 'unpaid' ? 'bg-warning text-dark' : 'bg-secondary') }}">
                                                {{ strtoupper($tInv->status) }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-3">Belum ada riwayat invoice.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Edit Info Usaha -->
    <div class="modal fade" id="editTenantModal{{ $tenant->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('superadmin.tenants.update', $tenant->id) }}" method="POST" class="modal-content">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h6 class="modal-title fw-bold"><i class="fa-solid fa-pen text-info me-2"></i>Edit Info Usaha: {{ $tenant->name }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Usaha / Toko <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ $tenant->name }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Jenis Usaha <span class="text-danger">*</span></label>
                        <input type="text" name="business_type" list="businessTypePresets" class="form-control" value="{{ $tenant->business_type }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">No. WhatsApp / Telepon</label>
                        <input type="tel" name="phone" class="form-control" inputmode="tel" placeholder="0812xxxxxxxx" value="{{ $tenant->phone }}">
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-semibold">Alamat Usaha</label>
                        <textarea name="address" rows="2" class="form-control" placeholder="Alamat lengkap usaha...">{{ $tenant->address }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary-green">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Atur Paket Manual -->
    <div class="modal fade" id="subModal{{ $tenant->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('superadmin.tenants.subscription', $tenant->id) }}" method="POST" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title fw-bold"><i class="fa-solid fa-crown text-success me-2"></i>Atur Langganan: {{ $tenant->name }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
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
                    <button type="submit" class="btn btn-primary-green">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Reset Password Pemilik -->
    <div class="modal fade" id="pwdModal{{ $tenant->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('superadmin.tenants.reset-password', $tenant->id) }}" method="POST" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title fw-bold"><i class="fa-solid fa-key text-warning me-2"></i>Reset Password Owner</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="p-3 rounded-3 bg-light border mb-3 small">
                        <div>Usaha: <strong class="text-dark">{{ $tenant->name }}</strong></div>
                        <div>Username Owner: <code>{{ $owner?->username ?? '-' }}</code></div>
                    </div>
                    <label class="form-label small fw-semibold">Password Baru <span class="text-danger">*</span></label>
                    <input type="text" name="new_password" class="form-control" minlength="6" placeholder="Minimal 6 karakter" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning fw-bold">Simpan Password</button>
                </div>
            </form>
        </div>
    </div>
@endforeach

<!-- Modal Tambah Usaha Baru Manual -->
<div class="modal fade" id="createTenantModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('superadmin.tenants.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="fa-solid fa-store text-success me-2"></i>Tambah Usaha (Tenant) Baru</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label small fw-semibold">Nama Usaha / Toko <span class="text-danger">*</span></label>
                        <input type="text" name="store_name" class="form-control form-control-sm" placeholder="cth: Kopi Kenangan Kita" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold">Jenis Usaha <span class="text-danger">*</span></label>
                        <input type="text" name="business_type" list="businessTypePresets" class="form-control form-control-sm" value="Kedai Minuman / Kopi" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">No. WhatsApp Usaha</label>
                        <input type="tel" name="phone" class="form-control form-control-sm" inputmode="tel" placeholder="0812xxxxxxxx">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Alamat Singkat</label>
                        <input type="text" name="address" class="form-control form-control-sm" placeholder="Kota / Jalan...">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Nama Pemilik <span class="text-danger">*</span></label>
                        <input type="text" name="owner_name" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Username Login <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Password Awal <span class="text-danger">*</span></label>
                        <input type="text" name="password" class="form-control form-control-sm" minlength="6" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Paket Awal</label>
                        <select name="plan_id" class="form-select form-select-sm" required>
                            @foreach($plans as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Siklus</label>
                        <select name="billing_cycle" class="form-select form-select-sm" required>
                            <option value="trial">Trial (14 Hari)</option>
                            <option value="monthly">Aktif 1 Bulan</option>
                            <option value="yearly">Aktif 1 Tahun</option>
                            <option value="lifetime">VIP Lifetime</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="seed_sample" value="1" id="saSeedSample" checked>
                            <label class="form-check-label small" for="saSeedSample">Buatkan contoh kategori &amp; menu awal sesuai Jenis Usaha</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary-green btn-sm">Buat Usaha &amp; Akun</button>
            </div>
        </form>
    </div>
</div>
@endsection
