@extends('layouts.app')

@section('title', 'Riwayat Transaksi')
@section('page_title', 'Riwayat Transaksi')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Riwayat Transaksi</li>
@endsection

@section('content')
@if(isset($summaryStats))
<!-- Summary Strip -->
<div class="row g-3 mb-4" style="font-variant-numeric: tabular-nums;">
    <div class="col-md-4">
        <div class="card premium-card border-0 shadow-sm p-3 h-100">
            <small class="text-muted text-uppercase fw-semibold">Total Nota (Sesuai Filter)</small>
            <h4 class="fw-bold text-dark mt-1 mb-0">{{ number_format($summaryStats['count'], 0, ',', '.') }} Transaksi</h4>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card premium-card border-0 shadow-sm p-3 h-100">
            <small class="text-muted text-uppercase fw-semibold">Total Omzet Lunas</small>
            <h4 class="fw-bold text-success mt-1 mb-0">Rp{{ number_format($summaryStats['total_sales'], 0, ',', '.') }}</h4>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card premium-card border-0 shadow-sm p-3 h-100">
            <small class="text-muted text-uppercase fw-semibold">Total Keuntungan Kotor</small>
            <h4 class="fw-bold text-primary mt-1 mb-0">Rp{{ number_format($summaryStats['total_profit'], 0, ',', '.') }}</h4>
        </div>
    </div>
</div>
@endif

@php
    $trxAdvancedFilterKeys = ['status', 'payment_method', 'cashier_id', 'date', 'month', 'year'];
    $trxActiveFilters = 0;
    foreach ($trxAdvancedFilterKeys as $fk) {
        if (filled(request($fk))) $trxActiveFilters++;
    }
    if (request('sort') === 'asc') $trxActiveFilters++;
    $hasAnyTrxFilter = $trxActiveFilters > 0 || filled(request('search'));
@endphp

<!-- Compact Search & Collapsible Filter Toolbar -->
<div class="kl-filter-toolbar p-3 mb-4">
    <form action="{{ route('transactions.index') }}" method="GET">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm flex-grow-1" style="min-width: 220px;">
                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" name="search" id="search" class="form-control border-start-0" placeholder="Cari No. Transaksi atau nama pelanggan..." value="{{ request('search') }}">
            </div>

            <button type="button"
                    class="btn btn-sm {{ $trxActiveFilters > 0 ? 'btn-success' : 'btn-outline-secondary' }} kl-filter-toggle-btn"
                    data-bs-toggle="collapse"
                    data-bs-target="#trxAdvancedFilters"
                    aria-expanded="{{ $trxActiveFilters > 0 ? 'true' : 'false' }}"
                    aria-controls="trxAdvancedFilters">
                <i class="fa-solid fa-sliders"></i>
                <span>Filter</span>
                @if($trxActiveFilters > 0)
                    <span class="badge bg-white text-success rounded-pill">{{ $trxActiveFilters }}</span>
                @endif
            </button>

            <button type="submit" class="btn btn-sm btn-primary-green px-3">
                <i class="fa-solid fa-search me-1"></i>Cari
            </button>

            @if($hasAnyTrxFilter)
                <a href="{{ route('transactions.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset Semua Filter">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            @endif
        </div>

        <div class="collapse {{ $trxActiveFilters > 0 ? 'show' : '' }}" id="trxAdvancedFilters">
            <div class="row g-2 g-md-3 pt-3 mt-3 border-top align-items-end">
                <div class="col-6 col-md-3 col-lg-2">
                    <label for="status" class="form-label fw-medium text-dark small mb-1">Status</label>
                    <select name="status" id="status" class="form-select form-select-sm">
                        <option value="">Semua Status</option>
                        <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Lunas (Paid)</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Menunggu (Pending)</option>
                        <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>Kadaluarsa</option>
                        <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Dibatalkan</option>
                    </select>
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                    <label for="payment_method" class="form-label fw-medium text-dark small mb-1">Metode Bayar</label>
                    <select name="payment_method" id="payment_method" class="form-select form-select-sm">
                        <option value="">Semua Metode</option>
                        <option value="tunai" {{ request('payment_method') === 'tunai' ? 'selected' : '' }}>Tunai</option>
                        <option value="qris" {{ request('payment_method') === 'qris' ? 'selected' : '' }}>QRIS</option>
                    </select>
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                    <label for="cashier_id" class="form-label fw-medium text-dark small mb-1">Kasir / Admin</label>
                    <select name="cashier_id" id="cashier_id" class="form-select form-select-sm">
                        <option value="">Semua Kasir</option>
                        @foreach($cashiers ?? [] as $c)
                            <option value="{{ $c->id }}" {{ (string) request('cashier_id') === (string) $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                    <label for="date" class="form-label fw-medium text-dark small mb-1">Tanggal</label>
                    <input type="date" name="date" id="date" class="form-control form-control-sm" value="{{ request('date') }}">
                </div>

                <div class="col-6 col-md-4 col-lg-2">
                    <label for="month" class="form-label fw-medium text-dark small mb-1">Bulan</label>
                    <select name="month" id="month" class="form-select form-select-sm">
                        <option value="">Semua Bulan</option>
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ request('month') == $m ? 'selected' : '' }}>
                                {{ Carbon\Carbon::create()->month($m)->translatedFormat('F') }}
                            </option>
                        @endfor
                    </select>
                </div>

                <div class="col-3 col-md-4 col-lg-1">
                    <label for="year" class="form-label fw-medium text-dark small mb-1">Tahun</label>
                    <select name="year" id="year" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        @for($y = date('Y'); $y >= 2020; $y--)
                            <option value="{{ $y }}" {{ request('year') == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>

                <div class="col-3 col-md-4 col-lg-1">
                    <label for="sort" class="form-label fw-medium text-dark small mb-1">Urut</label>
                    <select name="sort" id="sort" class="form-select form-select-sm">
                        <option value="desc" {{ request('sort') !== 'asc' ? 'selected' : '' }}>Terbaru</option>
                        <option value="asc" {{ request('sort') === 'asc' ? 'selected' : '' }}>Terlama</option>
                    </select>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Transactions Table List -->
<div class="card premium-card">
    <div class="premium-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="m-0 fw-semibold text-dark"><i class="fa-solid fa-list-check text-success me-2"></i> Daftar Log Transaksi</h5>
        <div class="d-flex gap-2">
            <a href="{{ route('transactions.export', request()->query()) }}" class="btn btn-outline-dark btn-sm">
                <i class="fa-solid fa-file-csv me-1"></i> Export CSV
            </a>
            <a href="{{ route('transactions.create') }}" class="btn btn-primary-green btn-sm shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> Transaksi Baru
            </a>
        </div>
    </div>
    <div class="card-body p-4">
        @if($transactions->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle border mb-4" style="font-variant-numeric: tabular-nums;">
                    <thead>
                        <tr>
                            <th>No. Transaksi</th>
                            <th>Tanggal &amp; Waktu</th>
                            <th class="text-center">Metode &amp; Status</th>
                            <th class="text-center">Menu Unik</th>
                            <th class="text-center">Qty Terjual</th>
                            <th class="text-end">Total Omzet</th>
                            <th class="text-end">Total Keuntungan</th>
                            <th>Catatan</th>
                            <th>Kasir / Admin</th>
                            <th class="text-end" style="width: 120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $trx)
                            <tr>
                                <td class="fw-bold text-dark">
                                    {{ $trx->transaction_number }}
                                    @if($trx->customer_name)
                                        <div class="small fw-normal text-muted"><i class="fa-regular fa-user me-1"></i>{{ $trx->customer_name }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $trx->transaction_date->translatedFormat('d M Y') }}</div>
                                    <small class="text-muted"><i class="fa-regular fa-clock me-1"></i> {{ date('H:i', strtotime($trx->transaction_time)) }}</small>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border text-uppercase">{{ $trx->payment_method ?? 'tunai' }}</span>
                                    @if(($trx->status ?? 'paid') === 'paid')
                                        <span class="badge bg-success">Lunas</span>
                                    @elseif($trx->status === 'pending')
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    @elseif($trx->status === 'expired')
                                        <span class="badge bg-secondary">Expired</span>
                                    @elseif($trx->status === 'cancelled')
                                        <span class="badge bg-danger">Batal</span>
                                    @endif
                                </td>
                                <td class="text-center"><span class="badge bg-secondary">{{ $trx->details_count ?? $trx->details()->count() }}</span></td>
                                <td class="text-center fw-semibold">{{ $trx->total_quantity }} pcs</td>
                                <td class="text-end fw-bold text-success">Rp{{ number_format($trx->total_sales, 0, ',', '.') }}</td>
                                <td class="text-end text-primary">Rp{{ number_format($trx->total_profit, 0, ',', '.') }}</td>
                                <td>
                                    <span class="small text-truncate d-inline-block" style="max-width: 140px;" title="{{ $trx->notes }}">
                                        {{ $trx->notes ?: '-' }}
                                    </span>
                                </td>
                                <td><span class="small fw-semibold">{{ $trx->admin->name ?? 'System' }}</span></td>
                                <td class="text-end">
                                    <div class="kl-action-group">
                                        <a href="{{ route('transactions.show', $trx->id) }}" class="btn btn-sm btn-outline-success px-2 py-1 fw-semibold" title="Lihat Detail Transaksi">
                                            Detail
                                        </a>
                                        <div class="dropdown kl-action-dropdown">
                                            <button type="button" class="btn kl-action-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menu Aksi {{ $trx->transaction_number }}" title="Aksi Lainnya">
                                                <i class="fa-solid fa-bars"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end kl-action-menu">
                                                <li>
                                                    <a href="{{ route('pos.receipt', $trx->id) }}" target="_blank" class="dropdown-item">
                                                        <i class="fa-solid fa-print text-dark"></i>
                                                        <span>Cetak Struk Thermal</span>
                                                    </a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('transactions.edit', $trx->id) }}" class="dropdown-item">
                                                        <i class="fa-solid fa-pen-to-square text-primary"></i>
                                                        <span>Edit Transaksi</span>
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form action="{{ route('transactions.destroy', $trx->id) }}" method="POST" class="m-0" data-confirm="Apakah Anda yakin ingin menghapus transaksi {{ $trx->transaction_number }}? Transaksi ini akan dipindahkan ke folder sampah (soft delete).">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="fa-solid fa-trash"></i>
                                                            <span>Hapus Transaksi</span>
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <small class="text-muted">Menampilkan {{ $transactions->firstItem() }} sampai {{ $transactions->lastItem() }} dari {{ $transactions->total() }} transaksi</small>
                <div>
                    {{ $transactions->links('pagination::bootstrap-5') }}
                </div>
            </div>
        @else
            <div class="text-center text-muted py-5">
                <i class="fa-solid fa-folder-open fs-1 mb-3 text-secondary"></i>
                <p class="m-0">Tidak ada riwayat transaksi ditemukan</p>
                <small>Coba ubah filter atau buat transaksi baru terlebih dahulu.</small>
            </div>
        @endif
    </div>
</div>
@endsection
