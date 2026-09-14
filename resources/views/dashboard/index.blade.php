@extends('layouts.app')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard Penjualan')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
@endsection

@section('content')
    <!-- Dashboard Filters -->
    <div class="card premium-card mb-4">
        <div class="card-body p-4">
            <form action="{{ route('dashboard') }}" method="GET" id="filterForm" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="period" class="form-label fw-semibold text-dark"><i class="fa-solid fa-calendar-days text-success me-1"></i> Periode Laporan</label>
                    <select name="period" id="period" class="form-select border-success-subtle">
                        <option value="today" {{ $period === 'today' ? 'selected' : '' }}>Hari Ini</option>
                        <option value="yesterday" {{ $period === 'yesterday' ? 'selected' : '' }}>Kemarin</option>
                        <option value="this_week" {{ $period === 'this_week' ? 'selected' : '' }}>Minggu Ini</option>
                        <option value="prev_week" {{ $period === 'prev_week' ? 'selected' : '' }}>Minggu Sebelumnya</option>
                        <option value="this_month" {{ $period === 'this_month' ? 'selected' : '' }}>Bulan Ini</option>
                        <option value="prev_month" {{ $period === 'prev_month' ? 'selected' : '' }}>Bulan Sebelumnya</option>
                        <option value="this_year" {{ $period === 'this_year' ? 'selected' : '' }}>Tahun Ini</option>
                        <option value="specific_day" {{ $period === 'specific_day' ? 'selected' : '' }}>Hari Tertentu</option>
                        <option value="specific_month" {{ $period === 'specific_month' ? 'selected' : '' }}>Bulan Tertentu</option>
                        <option value="specific_year" {{ $period === 'specific_year' ? 'selected' : '' }}>Tahun Tertentu</option>
                        <option value="custom" {{ $period === 'custom' ? 'selected' : '' }}>Rentang Tanggal...</option>
                    </select>
                </div>

                <!-- Conditional Fields -->
                <div class="col-md-3 cond-field" id="field_specific_day" style="display:none;">
                    <label for="specific_day" class="form-label fw-semibold text-dark">Pilih Tanggal</label>
                    <input type="date" name="specific_day" id="specific_day" class="form-control" value="{{ request('specific_day', date('Y-m-d')) }}">
                </div>

                <div class="col-md-3 cond-field" id="field_specific_month" style="display:none;">
                    <label for="specific_month" class="form-label fw-semibold text-dark">Pilih Bulan</label>
                    <input type="month" name="specific_month" id="specific_month" class="form-control" value="{{ request('specific_month', date('Y-m')) }}">
                </div>

                <div class="col-md-3 cond-field" id="field_specific_year" style="display:none;">
                    <label for="specific_year" class="form-label fw-semibold text-dark">Pilih Tahun</label>
                    <select name="specific_year" id="specific_year" class="form-select">
                        @for($y = date('Y'); $y >= 2020; $y--)
                            <option value="{{ $y }}" {{ request('specific_year') == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>

                <div class="col-md-2 cond-field" id="field_start_date" style="display:none;">
                    <label for="start_date" class="form-label fw-semibold text-dark">Mulai Tanggal</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" value="{{ request('start_date', date('Y-m-01')) }}">
                </div>

                <div class="col-md-2 cond-field" id="field_end_date" style="display:none;">
                    <label for="end_date" class="form-label fw-semibold text-dark">Akhir Tanggal</label>
                    <input type="date" name="end_date" id="end_date" class="form-control" value="{{ request('end_date', date('Y-m-t')) }}">
                </div>

                <div class="col-md-3">
                    <label for="group_by" class="form-label fw-semibold text-dark"><i class="fa-solid fa-chart-bar text-success me-1"></i> Pengelompokan Grafik</label>
                    <select name="group_by" id="group_by" class="form-select border-success-subtle">
                        <option value="daily" {{ $groupBy === 'daily' ? 'selected' : '' }}>Harian</option>
                        <option value="weekly" {{ $groupBy === 'weekly' ? 'selected' : '' }}>Mingguan</option>
                        <option value="monthly" {{ $groupBy === 'monthly' ? 'selected' : '' }}>Bulanan</option>
                        <option value="yearly" {{ $groupBy === 'yearly' ? 'selected' : '' }}>Tahunan</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary-green w-100"><i class="fa-solid fa-filter me-2"></i> Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Stats Cards Grid -->
    <div class="row g-3 mb-4">
        <!-- Total Omzet -->
        <div class="col-md-6 col-xl-2 col-sm-6">
            <div class="card premium-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="card-icon-wrapper bg-mint-soft">
                            <i class="fa-solid fa-wallet"></i>
                        </div>
                    </div>
                    <div class="stat-value">Rp{{ number_format($totalSales, 0, ',', '.') }}</div>
                    <div class="stat-label">Total Omzet</div>
                </div>
            </div>
        </div>

        <!-- Total Keuntungan -->
        <div class="col-md-6 col-xl-2 col-sm-6">
            <div class="card premium-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="card-icon-wrapper bg-mint-soft" style="background-color:#e0f2fe; color:#0284c7;">
                            <i class="fa-solid fa-arrow-trend-up"></i>
                        </div>
                    </div>
                    <div class="stat-value">Rp{{ number_format($totalProfit, 0, ',', '.') }}</div>
                    <div class="stat-label">Total Keuntungan</div>
                </div>
            </div>
        </div>

        <!-- Estimasi Modal -->
        <div class="col-md-6 col-xl-2 col-sm-6">
            <div class="card premium-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="card-icon-wrapper bg-mint-soft" style="background-color:#fee2e2; color:#dc2626;">
                            <i class="fa-solid fa-hand-holding-dollar"></i>
                        </div>
                    </div>
                    <div class="stat-value">Rp{{ number_format($estimatedCost, 0, ',', '.') }}</div>
                    <div class="stat-label">Estimasi Modal</div>
                </div>
            </div>
        </div>

        <!-- Jumlah Transaksi -->
        <div class="col-md-6 col-xl-2 col-sm-6">
            <div class="card premium-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="card-icon-wrapper bg-mint-soft" style="background-color:#fef3c7; color:#d97706;">
                            <i class="fa-solid fa-file-invoice"></i>
                        </div>
                    </div>
                    <div class="stat-value">{{ number_format($totalTransactions, 0, ',', '.') }}</div>
                    <div class="stat-label">Jumlah Transaksi</div>
                </div>
            </div>
        </div>

        <!-- Jumlah Produk Terjual -->
        <div class="col-md-6 col-xl-2 col-sm-6">
            <div class="card premium-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="card-icon-wrapper bg-mint-soft" style="background-color:#faf5ff; color:#7c3aed;">
                            <i class="fa-solid fa-glass-water"></i>
                        </div>
                    </div>
                    <div class="stat-value">{{ number_format($totalQuantity, 0, ',', '.') }} pcs</div>
                    <div class="stat-label">Produk Terjual</div>
                </div>
            </div>
        </div>

        <!-- Rata-rata Nilai Transaksi -->
        <div class="col-md-6 col-xl-2 col-sm-6">
            <div class="card premium-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="card-icon-wrapper bg-mint-soft" style="background-color:#f0fdf4; color:#16a34a;">
                            <i class="fa-solid fa-calculator"></i>
                        </div>
                    </div>
                    <div class="stat-value">Rp{{ number_format($averageSales, 0, ',', '.') }}</div>
                    <div class="stat-label">Rata-rata Transaksi</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="row mb-4">
        <!-- Sales and Profit Trend Chart -->
        <div class="col-lg-8 mb-4 mb-lg-0">
            <div class="card premium-card h-100">
                <div class="premium-header">
                    <h5 class="m-0 fw-semibold text-dark"><i class="fa-solid fa-chart-line text-success me-2"></i> Perkembangan Omzet & Keuntungan</h5>
                </div>
                <div class="card-body p-4">
                    <canvas id="trendChart" style="height: 350px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Sales by Category Pie Chart -->
        <div class="col-lg-4">
            <div class="card premium-card h-100">
                <div class="premium-header">
                    <h5 class="m-0 fw-semibold text-dark"><i class="fa-solid fa-chart-pie text-success me-2"></i> Penjualan per Kategori</h5>
                </div>
                <div class="card-body p-4 d-flex align-items-center justify-content-center">
                    @if($categorySales->count() > 0)
                        <canvas id="categoryChart" style="max-height: 300px;"></canvas>
                    @else
                        <div class="text-center text-muted py-5">
                            <i class="fa-solid fa-folder-open fs-1 mb-3"></i>
                            <p class="m-0">Tidak ada data untuk periode ini</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Top 10 Best Sellers Chart & List -->
        <div class="col-lg-6 mb-4">
            <div class="card premium-card h-100">
                <div class="premium-header">
                    <h5 class="m-0 fw-semibold text-dark"><i class="fa-solid fa-trophy text-warning me-2"></i> 10 Menu Paling Laris (Terpopuler)</h5>
                </div>
                <div class="card-body p-4">
                    @if($topMenus->count() > 0)
                        <canvas id="topMenusChart" style="max-height: 250px;" class="mb-4"></canvas>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover border">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Nama Menu</th>
                                        <th class="text-center">Terjual</th>
                                        <th class="text-end">Omzet</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($topMenus as $idx => $m)
                                        <tr>
                                            <td>{{ $idx + 1 }}</td>
                                            <td class="fw-semibold text-dark">{{ $m->menu_name_snapshot }}</td>
                                            <td class="text-center"><span class="badge bg-success">{{ $m->qty }} pcs</span></td>
                                            <td class="text-end">Rp{{ number_format($m->sales, 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center text-muted py-5">
                            <i class="fa-solid fa-info-circle fs-1 mb-3 text-secondary"></i>
                            <p class="m-0">Tidak ada data transaksi</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Least Sold Menus -->
        <div class="col-lg-6 mb-4">
            <div class="card premium-card h-100">
                <div class="premium-header text-danger">
                    <h5 class="m-0 fw-semibold text-dark"><i class="fa-solid fa-arrow-down-wide-short text-danger me-2"></i> Menu Sedikit Terjual</h5>
                </div>
                <div class="card-body p-4">
                    @if($bottomMenus->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover border">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Nama Menu</th>
                                        <th class="text-center">Jumlah Terjual</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($bottomMenus as $idx => $m)
                                        <tr>
                                            <td>{{ $idx + 1 }}</td>
                                            <td class="fw-semibold text-dark">{{ $m->menu_name_snapshot }}</td>
                                            <td class="text-center"><span class="badge bg-danger">{{ $m->qty }} pcs</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center text-muted py-5">
                            <i class="fa-solid fa-info-circle fs-1 mb-3 text-secondary"></i>
                            <p class="m-0">Tidak ada data transaksi</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const periodSelect = document.getElementById('period');
            
            function togglePeriodFields() {
                const value = periodSelect.value;
                document.querySelectorAll('.cond-field').forEach(el => el.style.display = 'none');
                
                if (value === 'specific_day') {
                    document.getElementById('field_specific_day').style.display = 'block';
                } else if (value === 'specific_month') {
                    document.getElementById('field_specific_month').style.display = 'block';
                } else if (value === 'specific_year') {
                    document.getElementById('field_specific_year').style.display = 'block';
                } else if (value === 'custom') {
                    document.getElementById('field_start_date').style.display = 'block';
                    document.getElementById('field_end_date').style.display = 'block';
                }
            }

            periodSelect.addEventListener('change', togglePeriodFields);
            togglePeriodFields(); // Run on mount

            // ----------------------------------------------
            // Chart 1: Trend Chart (Sales vs Profit)
            // ----------------------------------------------
            const trendCtx = document.getElementById('trendChart').getContext('2d');
            const trendLabels = @json(collect($trendData)->pluck('label'));
            const salesData = @json(collect($trendData)->pluck('sales'));
            const profitData = @json(collect($trendData)->pluck('profit'));
            const qtyData = @json(collect($trendData)->pluck('quantity'));

            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: trendLabels,
                    datasets: [
                        {
                            label: 'Omzet (Sales)',
                            data: salesData,
                            borderColor: '#11361b',
                            backgroundColor: 'rgba(17, 54, 27, 0.05)',
                            fill: true,
                            tension: 0.3,
                            borderWidth: 3,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Keuntungan (Profit)',
                            data: profitData,
                            borderColor: '#a3e635',
                            backgroundColor: 'rgba(163, 230, 83, 0.05)',
                            fill: true,
                            tension: 0.3,
                            borderWidth: 2,
                            yAxisID: 'y'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Nilai (Rupiah)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return 'Rp' + value.toLocaleString('id-ID');
                                }
                            }
                        }
                    }
                }
            });

            // ----------------------------------------------
            // Chart 2: Category Breakdown
            // ----------------------------------------------
            const catCanvas = document.getElementById('categoryChart');
            if (catCanvas) {
                const catCtx = catCanvas.getContext('2d');
                const catLabels = @json($categorySales->pluck('name'));
                const catSales = @json($categorySales->pluck('sales'));

                new Chart(catCtx, {
                    type: 'doughnut',
                    data: {
                        labels: catLabels,
                        datasets: [{
                            data: catSales,
                            backgroundColor: [
                                '#11361b',
                                '#2e7d32',
                                '#4caf50',
                                '#81c784',
                                '#c8e6c9',
                                '#e8f5e9'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { position: 'bottom' }
                        }
                    }
                });
            }

            // ----------------------------------------------
            // Chart 3: Top 10 Best Sellers
            // ----------------------------------------------
            const topCtx = document.getElementById('topMenusChart')?.getContext('2d');
            if (topCtx) {
                const topLabels = @json(collect($topMenus)->pluck('menu_name_snapshot'));
                const topQtys = @json(collect($topMenus)->pluck('qty'));

                new Chart(topCtx, {
                    type: 'bar',
                    data: {
                        labels: topLabels,
                        datasets: [{
                            label: 'Jumlah Gelas Terjual',
                            data: topQtys,
                            backgroundColor: '#2e7d32',
                            borderRadius: 6
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                title: { display: true, text: 'Pcs' }
                            }
                        }
                    }
                });
            }
        });
    </script>
@endsection
