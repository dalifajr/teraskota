@extends('layouts.app')

@section('title', 'Laporan Penjualan')
@section('page_title', 'Laporan Penjualan Teras Kota Berlian Makmur')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Laporan Penjualan</li>
@endsection

@section('content')
<!-- Filters card -->
<div class="card premium-card mb-4">
    <div class="card-body p-4">
        <form action="{{ route('reports.index') }}" method="GET" id="reportFilterForm" class="row g-3 align-items-end">
            <!-- Period dropdown -->
            <div class="col-md-2">
                <label for="period" class="form-label fw-semibold text-dark"><i class="fa-solid fa-calendar text-success me-1"></i> Periode</label>
                <select name="period" id="period" class="form-select">
                    <option value="today" {{ $period === 'today' ? 'selected' : '' }}>Hari Ini</option>
                    <option value="this_week" {{ $period === 'this_week' ? 'selected' : '' }}>Minggu Ini</option>
                    <option value="this_month" {{ $period === 'this_month' ? 'selected' : '' }}>Bulan Ini</option>
                    <option value="this_year" {{ $period === 'this_year' ? 'selected' : '' }}>Tahun Ini</option>
                    <option value="custom" {{ $period === 'custom' ? 'selected' : '' }}>Rentang Tanggal...</option>
                </select>
            </div>

            <!-- Custom date parameters -->
            <div class="col-md-2 custom-date-field" id="start_date_container" style="display: none;">
                <label for="start_date" class="form-label fw-semibold text-dark">Tanggal Mulai</label>
                <input type="date" name="start_date" id="start_date" class="form-control" value="{{ $start_date }}">
            </div>

            <div class="col-md-2 custom-date-field" id="end_date_container" style="display: none;">
                <label for="end_date" class="form-label fw-semibold text-dark">Tanggal Akhir</label>
                <input type="date" name="end_date" id="end_date" class="form-control" value="{{ $end_date }}">
            </div>

            <!-- Category selection -->
            <div class="col-md-2">
                <label for="category_id" class="form-label fw-semibold text-dark"><i class="fa-solid fa-tag text-success me-1"></i> Kategori</label>
                <select name="category_id" id="category_id" class="form-select">
                    <option value="">-- Semua Kategori --</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ $category_id == $cat->id ? 'selected' : '' }}>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Menu selection -->
            <div class="col-md-2">
                <label for="menu_id" class="form-label fw-semibold text-dark"><i class="fa-solid fa-mug-hot text-success me-1"></i> Menu</label>
                <select name="menu_id" id="menu_id" class="form-select">
                    <option value="">-- Semua Menu --</option>
                    @foreach($menus as $menu)
                        <option value="{{ $menu->id }}" {{ $menu_id == $menu->id ? 'selected' : '' }}>
                            [{{ $menu->code }}] {{ $menu->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Buttons -->
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary-green flex-grow-1"><i class="fa-solid fa-filter me-1"></i> Filter</button>
                <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary" title="Reset Filter"><i class="fa-solid fa-rotate-left"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Metrics summary boxes -->
<div class="row g-3 mb-4">
    <!-- Transactions count -->
    <div class="col-md-4 col-lg-2.4 col-sm-6">
        <div class="card premium-card border-0 bg-white h-100 shadow-sm">
            <div class="card-body p-3">
                <small class="text-muted d-block fw-semibold mb-2 text-uppercase">Transaksi</small>
                <h3 class="fw-bold text-dark m-0">{{ number_format($total_transactions, 0, ',', '.') }}</h3>
                <small class="text-muted">Total nota invoice</small>
            </div>
        </div>
    </div>
    
    <!-- Product quantity count -->
    <div class="col-md-4 col-lg-2.4 col-sm-6">
        <div class="card premium-card border-0 bg-white h-100 shadow-sm">
            <div class="card-body p-3">
                <small class="text-muted d-block fw-semibold mb-2 text-uppercase">Gelas Terjual</small>
                <h3 class="fw-bold text-success m-0">{{ number_format($total_quantity, 0, ',', '.') }} pcs</h3>
                <small class="text-muted">Volume penjualan minuman</small>
            </div>
        </div>
    </div>

    <!-- Sales Omzet -->
    <div class="col-md-4 col-lg-2.4 col-sm-6">
        <div class="card premium-card border-0 bg-white h-100 shadow-sm">
            <div class="card-body p-3">
                <small class="text-muted d-block fw-semibold mb-2 text-uppercase">Total Omzet</small>
                <h3 class="fw-bold text-primary m-0">Rp{{ number_format($total_sales, 0, ',', '.') }}</h3>
                <small class="text-muted">Pendapatan kotor</small>
            </div>
        </div>
    </div>

    <!-- Total profit -->
    <div class="col-md-6 col-lg-2.4 col-sm-6">
        <div class="card premium-card border-0 bg-white h-100 shadow-sm">
            <div class="card-body p-3">
                <small class="text-muted d-block fw-semibold mb-2 text-uppercase">Total Keuntungan</small>
                <h3 class="fw-bold text-success m-0">Rp{{ number_format($total_profit, 0, ',', '.') }}</h3>
                <small class="text-muted">Laba bersih (pendapatan - modal)</small>
            </div>
        </div>
    </div>

    <!-- Estimated cost -->
    <div class="col-md-6 col-lg-2.4 col-sm-6">
        <div class="card premium-card border-0 bg-white h-100 shadow-sm">
            <div class="card-body p-3">
                <small class="text-muted d-block fw-semibold mb-2 text-uppercase">Estimasi Modal</small>
                <h3 class="fw-bold text-danger m-0">Rp{{ number_format($estimated_cost, 0, ',', '.') }}</h3>
                <small class="text-muted">Harga pokok penjualan</small>
            </div>
        </div>
    </div>
</div>

<!-- Secondary metrics cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card premium-card border-0 p-3 bg-white shadow-sm">
            <span class="small text-muted d-block mb-1">Rata-rata Omzet / Hari</span>
            <strong class="fs-5 text-dark">Rp{{ number_format($average_sales_per_day, 0, ',', '.') }}</strong>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card premium-card border-0 p-3 bg-white shadow-sm">
            <span class="small text-muted d-block mb-1"><i class="fa-solid fa-trophy text-warning"></i> Menu Terlaris</span>
            <strong class="fs-5 text-success text-truncate d-block" title="{{ $best_seller }}">{{ $best_seller }}</strong>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card premium-card border-0 p-3 bg-white shadow-sm">
            <span class="small text-muted d-block mb-1"><i class="fa-solid fa-circle-exclamation text-danger"></i> Menu Paling Sedikit Terjual</span>
            <strong class="fs-5 text-danger text-truncate d-block" title="{{ $least_seller }}">{{ $least_seller }}</strong>
        </div>
    </div>
</div>

<!-- Laporan detail table -->
<div class="card premium-card">
    <div class="premium-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="m-0 fw-semibold text-dark"><i class="fa-solid fa-file-invoice text-success me-2"></i> Rincian Laporan</h5>
            <small class="text-muted">Menampilkan data periode: <strong>{{ $date_range_label }}</strong></small>
        </div>
        
        <!-- Print & Export Buttons -->
        <div class="d-flex gap-2">
            <!-- Cetak PDF -->
            <a href="{{ route('reports.pdf', request()->query()) }}" target="_blank" class="btn btn-outline-danger btn-sm fw-semibold">
                <i class="fa-solid fa-file-pdf me-1"></i> Cetak PDF
            </a>
            <!-- Ekspor Excel/CSV -->
            <a href="{{ route('reports.export', request()->query()) }}" class="btn btn-outline-success btn-sm fw-semibold">
                <i class="fa-solid fa-file-csv me-1"></i> Ekspor CSV
            </a>
        </div>
    </div>
    
    <div class="card-body p-4">
        @if(count($items) > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle border">
                    <thead>
                        <tr>
                            <th style="width: 60px;">No.</th>
                            <th>Menu</th>
                            <th>Kategori</th>
                            <th class="text-center">Jumlah Terjual</th>
                            <th class="text-end">Omzet</th>
                            <th class="text-end">Keuntungan</th>
                            <th class="text-end">Estimasi Modal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $idx => $item)
                            <tr>
                                <td>{{ $idx + 1 }}</td>
                                <td class="fw-semibold text-dark">{{ $item->menu_name }}</td>
                                <td><span class="badge bg-mint-soft text-success">{{ $item->category_name }}</span></td>
                                <td class="text-center fw-semibold">{{ $item->total_quantity }} pcs</td>
                                <td class="text-end fw-bold text-success">Rp{{ number_format($item->total_sales, 0, ',', '.') }}</td>
                                <td class="text-end text-primary">Rp{{ number_format($item->total_profit, 0, ',', '.') }}</td>
                                <td class="text-end text-danger small">Rp{{ number_format($item->total_estimated_cost, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center text-muted py-5">
                <i class="fa-solid fa-folder-open fs-1 mb-3 text-secondary"></i>
                <p class="m-0">Tidak ada rincian data penjualan untuk filter terpilih.</p>
            </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const periodSelect = document.getElementById('period');
        
        function toggleCustomDateFields() {
            const isCustom = periodSelect.value === 'custom';
            document.getElementById('start_date_container').style.display = isCustom ? 'block' : 'none';
            document.getElementById('end_date_container').style.display = isCustom ? 'block' : 'none';
        }

        periodSelect.addEventListener('change', toggleCustomDateFields);
        toggleCustomDateFields(); // Run on mount
    });
</script>
@endsection
