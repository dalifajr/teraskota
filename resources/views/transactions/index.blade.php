@extends('layouts.app')

@section('title', 'Riwayat Transaksi')
@section('page_title', 'Riwayat Transaksi')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Riwayat Transaksi</li>
@endsection

@section('content')
<!-- Filter Panel -->
<div class="card premium-card mb-4">
    <div class="card-body p-4">
        <form action="{{ route('transactions.index') }}" method="GET" class="row g-3 align-items-end">
            <!-- Search Transaction Number -->
            <div class="col-md-3">
                <label for="search" class="form-label fw-medium text-dark">Cari No. Transaksi</label>
                <input type="text" name="search" id="search" class="form-control" placeholder="Contoh: TRX-2026..." value="{{ request('search') }}">
            </div>
            
            <!-- Date Filter -->
            <div class="col-md-2">
                <label for="date" class="form-label fw-medium text-dark">Filter Tanggal</label>
                <input type="date" name="date" id="date" class="form-control" value="{{ request('date') }}">
            </div>

            <!-- Month Filter -->
            <div class="col-md-2">
                <label for="month" class="form-label fw-medium text-dark">Filter Bulan</label>
                <select name="month" id="month" class="form-select">
                    <option value="">-- Semua Bulan --</option>
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" {{ request('month') == $m ? 'selected' : '' }}>
                            {{ Carbon\Carbon::create()->month($m)->translatedFormat('F') }}
                        </option>
                    @endfor
                </select>
            </div>

            <!-- Year Filter -->
            <div class="col-md-2">
                <label for="year" class="form-label fw-medium text-dark">Filter Tahun</label>
                <select name="year" id="year" class="form-select">
                    <option value="">-- Semua Tahun --</option>
                    @for($y = date('Y'); $y >= 2020; $y--)
                        <option value="{{ $y }}" {{ request('year') == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>

            <!-- Sort Order -->
            <div class="col-md-1.5">
                <label for="sort" class="form-label fw-medium text-dark">Urutan</label>
                <select name="sort" id="sort" class="form-select">
                    <option value="desc" {{ request('sort') !== 'asc' ? 'selected' : '' }}>Terbaru</option>
                    <option value="asc" {{ request('sort') === 'asc' ? 'selected' : '' }}>Terlama</option>
                </select>
            </div>

            <!-- Buttons -->
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary-green flex-grow-1"><i class="fa-solid fa-search"></i> Cari</button>
                <a href="{{ route('transactions.index') }}" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Transactions Table List -->
<div class="card premium-card">
    <div class="premium-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="m-0 fw-semibold text-dark"><i class="fa-solid fa-list-check text-success me-2"></i> Daftar Log Transaksi</h5>
        <a href="{{ route('transactions.create') }}" class="btn btn-primary-green btn-sm shadow-sm"><i class="fa-solid fa-plus me-1"></i> Transaksi Baru</a>
    </div>
    <div class="card-body p-4">
        @if($transactions->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle border mb-4">
                    <thead>
                        <tr>
                            <th>No. Transaksi</th>
                            <th>Tanggal & Waktu</th>
                            <th class="text-center">Menu Unik</th>
                            <th class="text-center">Qty Terjual</th>
                            <th class="text-end">Total Omzet</th>
                            <th class="text-end">Total Keuntungan</th>
                            <th>Catatan</th>
                            <th>Admin</th>
                            <th class="text-center" style="width: 150px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $trx)
                            <tr>
                                <td class="fw-bold text-dark">{{ $trx->transaction_number }}</td>
                                <td>
                                    <div>{{ $trx->transaction_date->translatedFormat('d M Y') }}</div>
                                    <small class="text-muted"><i class="fa-regular fa-clock me-1"></i> {{ date('H:i', strtotime($trx->transaction_time)) }}</small>
                                </td>
                                <td class="text-center"><span class="badge bg-secondary">{{ $trx->details_count ?? $trx->details()->count() }}</span></td>
                                <td class="text-center fw-semibold">{{ $trx->total_quantity }} pcs</td>
                                <td class="text-end fw-bold text-success">Rp{{ number_format($trx->total_sales, 0, ',', '.') }}</td>
                                <td class="text-end text-primary">Rp{{ number_format($trx->total_profit, 0, ',', '.') }}</td>
                                <td>
                                    <span class="small text-truncate d-inline-block" style="max-width: 150px;" title="{{ $trx->notes }}">
                                        {{ $trx->notes ?: '-' }}
                                    </span>
                                </td>
                                <td><span class="small fw-semibold">{{ $trx->admin->name ?? 'System' }}</span></td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="{{ route('transactions.show', $trx->id) }}" class="btn btn-outline-success" title="Detail"><i class="fa-solid fa-eye"></i></a>
                                        <a href="{{ route('transactions.edit', $trx->id) }}" class="btn btn-outline-primary" title="Edit"><i class="fa-solid fa-edit"></i></a>
                                        
                                        <form action="{{ route('transactions.destroy', $trx->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus transaksi {{ $trx->transaction_number }}? Transaksi ini akan dipindahkan ke folder sampah (soft delete).')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="Hapus"><i class="fa-solid fa-trash"></i></button>
                                        </form>
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
                <small>Silakan buat transaksi baru terlebih dahulu.</small>
            </div>
        @endif
    </div>
</div>
@endsection
