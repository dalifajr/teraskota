@extends('layouts.app')

@section('title', 'Kode Promo & Kupon Langganan')
@section('page_title', 'Manajemen Kode Promo & Kupon')

@section('breadcrumbs')
    <li class="breadcrumb-item active">Kode Promo &amp; Kupon</li>
@endsection

@section('content')
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom">
        <div>
            <h6 class="fw-bold m-0"><i class="fa-solid fa-ticket text-success me-2"></i>Daftar Kode Promo Langganan</h6>
            <small class="text-muted">Buat kode diskon persentase atau nominal tetap untuk akuisisi dan retensi usaha.</small>
        </div>
        <button type="button" class="btn btn-sm btn-primary-green shadow-sm" data-bs-toggle="modal" data-bs-target="#createPromoModal">
            <i class="fa-solid fa-plus me-1"></i>Tambah Kode Promo
        </button>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small" style="font-variant-numeric: tabular-nums;">
            <thead class="table-light">
                <tr>
                    <th>Kode &amp; Nama Promo</th>
                    <th>Potongan Diskon</th>
                    <th>Target Paket</th>
                    <th>Pemakaian</th>
                    <th>Masa Berlaku</th>
                    <th>Status</th>
                    <th class="text-end" style="width: 72px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($promos as $promo)
                    @php
                        $isExpired = $promo->expires_at && now()->gt($promo->expires_at);
                        $isExhausted = $promo->max_uses !== null && $promo->used_count >= $promo->max_uses;
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-bold font-monospace text-success fs-6">{{ $promo->code }}</div>
                            <div class="text-muted">{{ $promo->name }}</div>
                        </td>
                        <td>
                            @if($promo->discount_type === 'percent')
                                <span class="fw-bold text-dark">{{ rtrim(rtrim(number_format((float)$promo->discount_value, 2, '.', ''), '0'), '.') }}%</span>
                                @if($promo->max_discount_amount)
                                    <small class="d-block text-muted">Maks. Rp{{ number_format((float)$promo->max_discount_amount, 0, ',', '.') }}</small>
                                @endif
                            @else
                                <span class="fw-bold text-dark">Rp{{ number_format((float)$promo->discount_value, 0, ',', '.') }}</span>
                                <small class="d-block text-muted">Potongan tetap</small>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">{{ $promo->plan?->name ?? 'Semua Paket' }}</span>
                        </td>
                        <td>
                            <strong>{{ $promo->used_count }}</strong> / {{ $promo->max_uses ?? '∞' }}
                            @if($isExhausted)
                                <span class="badge bg-danger bg-opacity-10 text-danger ms-1">Habis</span>
                            @endif
                        </td>
                        <td class="text-muted">
                            @if($promo->starts_at || $promo->expires_at)
                                <div>{{ $promo->starts_at?->format('d M Y') ?? 'Sekarang' }} &ndash; {{ $promo->expires_at?->format('d M Y') ?? 'Tanpa batas' }}</div>
                                @if($isExpired)
                                    <span class="badge bg-secondary">Kedaluwarsa</span>
                                @endif
                            @else
                                <span>Selamanya</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $promo->is_active && !$isExpired && !$isExhausted ? 'bg-success' : 'bg-secondary' }}">
                                {{ $promo->is_active ? ($isExpired ? 'Expired' : ($isExhausted ? 'Kuota Habis' : 'Aktif')) : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="dropdown kl-action-dropdown d-inline-block">
                                <button type="button" class="btn kl-action-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menu Aksi Promo {{ $promo->code }}">
                                    <i class="fa-solid fa-bars"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end kl-action-menu">
                                    <li>
                                        <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editPromoModal{{ $promo->id }}">
                                            <i class="fa-solid fa-pen-to-square text-primary"></i>
                                            <span>Edit Promo</span>
                                        </button>
                                    </li>
                                    <li>
                                        <form action="{{ route('superadmin.promos.toggle', $promo->id) }}" method="POST" class="m-0">
                                            @csrf
                                            <button type="submit" class="dropdown-item">
                                                <i class="fa-solid {{ $promo->is_active ? 'fa-toggle-off text-warning' : 'fa-toggle-on text-success' }}"></i>
                                                <span>{{ $promo->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</span>
                                            </button>
                                        </form>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form action="{{ route('superadmin.promos.destroy', $promo->id) }}" method="POST" class="m-0" data-confirm="Hapus kode promo {{ $promo->code }}? Invoice lama tetap menyimpan riwayat potongan.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="fa-solid fa-trash"></i>
                                                <span>Hapus Promo</span>
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="fa-solid fa-ticket fs-2 mb-2 d-block opacity-50"></i>
                            Belum ada kode promo. Klik <strong>Tambah Kode Promo</strong> untuk mulai kampanye diskon.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($promos->hasPages())
        <div class="card-footer bg-white">{{ $promos->links() }}</div>
    @endif
</div>

<!-- Edit Modals -->
@foreach($promos as $promo)
<div class="modal fade" id="editPromoModal{{ $promo->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('superadmin.promos.update', $promo->id) }}" method="POST" class="modal-content">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="fa-solid fa-ticket text-success me-2"></i>Edit Promo: {{ $promo->code }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-5">
                        <label class="form-label small fw-semibold">Kode Kupon <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control form-control-sm text-uppercase font-monospace" value="{{ $promo->code }}" required>
                    </div>
                    <div class="col-7">
                        <label class="form-label small fw-semibold">Nama Promo <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-sm" value="{{ $promo->name }}" required>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <label class="form-label small fw-semibold">Tipe Diskon</label>
                        <select name="discount_type" class="form-select form-select-sm" required>
                            <option value="percent" {{ $promo->discount_type === 'percent' ? 'selected' : '' }}>Persen (%)</option>
                            <option value="fixed" {{ $promo->discount_type === 'fixed' ? 'selected' : '' }}>Nominal (Rp)</option>
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-semibold">Nilai Diskon</label>
                        <input type="number" name="discount_value" class="form-control form-control-sm" min="1" value="{{ (float)$promo->discount_value }}" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-semibold">Maks. Potongan (Rp)</label>
                        <input type="number" name="max_discount_amount" class="form-control form-control-sm" min="1" value="{{ $promo->max_discount_amount ? (int)$promo->max_discount_amount : '' }}" placeholder="Opsional">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Berlaku untuk Paket</label>
                        <select name="plan_id" class="form-select form-select-sm">
                            <option value="">Semua Paket Berbayar</option>
                            @foreach($plans as $p)
                                <option value="{{ $p->id }}" {{ (int)$promo->plan_id === (int)$p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Batas Kuota Pakai</label>
                        <input type="number" name="max_uses" class="form-control form-control-sm" min="1" value="{{ $promo->max_uses }}" placeholder="Kosong = Unlimited">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Mulai Berlaku</label>
                        <input type="date" name="starts_at" class="form-control form-control-sm" value="{{ $promo->starts_at?->format('Y-m-d') }}">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Berakhir Pada</label>
                        <input type="date" name="expires_at" class="form-control form-control-sm" value="{{ $promo->expires_at?->format('Y-m-d') }}">
                    </div>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="editPromoAct{{ $promo->id }}" {{ $promo->is_active ? 'checked' : '' }}>
                    <label class="form-check-label small fw-semibold" for="editPromoAct{{ $promo->id }}">Kode Promo Aktif</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary-green">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
@endforeach

<!-- Create Modal -->
<div class="modal fade" id="createPromoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('superadmin.promos.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="fa-solid fa-ticket text-success me-2"></i>Buat Kode Promo Baru</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-5">
                        <label class="form-label small fw-semibold">Kode Kupon <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control form-control-sm text-uppercase font-monospace" placeholder="cth: HEMAT20" required>
                    </div>
                    <div class="col-7">
                        <label class="form-label small fw-semibold">Nama Promo <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="cth: Promo Pelanggan Baru 20%" required>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <label class="form-label small fw-semibold">Tipe Diskon</label>
                        <select name="discount_type" class="form-select form-select-sm" required>
                            <option value="percent">Persen (%)</option>
                            <option value="fixed">Nominal (Rp)</option>
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-semibold">Nilai Diskon</label>
                        <input type="number" name="discount_value" class="form-control form-control-sm" min="1" value="20" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-semibold">Maks. Potongan</label>
                        <input type="number" name="max_discount_amount" class="form-control form-control-sm" min="1" placeholder="Opsional (Rp)">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Berlaku untuk Paket</label>
                        <select name="plan_id" class="form-select form-select-sm">
                            <option value="">Semua Paket Berbayar</option>
                            @foreach($plans as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Batas Kuota Pakai</label>
                        <input type="number" name="max_uses" class="form-control form-control-sm" min="1" placeholder="Kosong = Unlimited">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Mulai Berlaku</label>
                        <input type="date" name="starts_at" class="form-control form-control-sm">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Berakhir Pada</label>
                        <input type="date" name="expires_at" class="form-control form-control-sm">
                    </div>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="createPromoAct" checked>
                    <label class="form-check-label small fw-semibold" for="createPromoAct">Langsung Aktifkan Kode Promo</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary-green">Simpan Kode Promo</button>
            </div>
        </form>
    </div>
</div>
@endsection
