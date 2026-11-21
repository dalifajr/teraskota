@extends('layouts.app')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard Penjualan')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
@endsection

@section('content')
    <!-- Admin Hub Header & Quick Actions -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        @php
            $dashTz = $currentTenant?->timezone ?? 'Asia/Jakarta';
            $dashTzBadge = match($dashTz) {
                'Asia/Makassar' => 'WITA (UTC+8)',
                'Asia/Jayapura' => 'WIT (UTC+9)',
                default => 'WIB (UTC+7)',
            };
        @endphp
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h4 class="fw-bold text-dark m-0">Ringkasan Operasional</h4>
                <span class="badge badge-wib rounded-pill d-none d-sm-inline-block">Zona Waktu: {{ $dashTzBadge }}</span>
            </div>
            <p class="text-muted small m-0">
                Data performa penjualan, arus kas tunai/QRIS, dan laba bersih {{ $currentTenant?->name ?? ($platformName ?? 'KasirLoka') }}.
            </p>
        </div>

        <!-- Quick Action Buttons for Admin -->
        @php
            $storeTitleWa = $currentTenant?->name ?? ($platformName ?? 'KasirLoka');
            $dailyWaMsg = "*REKAP HARIAN " . strtoupper($storeTitleWa) . "*\n"
                . "Tanggal: " . now()->translatedFormat('d M Y') . " (WIB)\n"
                . "--------------------------------\n"
                . "Transaksi Lunas : " . number_format($todayTransactions, 0, ',', '.') . " nota\n"
                . "Total Omzet     : Rp" . number_format($todaySales, 0, ',', '.') . "\n"
                . "  - Tunai       : Rp" . number_format($todayCashSales ?? 0, 0, ',', '.') . "\n"
                . "  - QRIS        : Rp" . number_format($todayQrisSales ?? 0, 0, ',', '.') . "\n"
                . "Pengeluaran Kas : Rp" . number_format($todayExpenses, 0, ',', '.') . "\n"
                . "Est. Kas Laci   : Rp" . number_format($todayCashDrawer ?? 0, 0, ',', '.') . "\n"
                . "--------------------------------\n"
                . "*LABA BERSIH HARI INI: Rp" . number_format($todayNetProfit, 0, ',', '.') . "*";
        @endphp
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('pos.index') }}" target="_blank" class="btn btn-primary-green quick-action-btn shadow-sm" title="Buka Aplikasi Kasir POS di Tab Baru">
                <i class="fa-solid fa-cash-register text-lime"></i>
                <span>Buka Kasir POS</span>
                <i class="fa-solid fa-arrow-up-right-from-square small opacity-75"></i>
            </a>

            <!-- Desktop Quick Links -->
            <div class="d-none d-xl-flex align-items-center gap-2">
                <a href="https://wa.me/?text={{ urlencode($dailyWaMsg) }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-success quick-action-btn bg-white" title="Kirim Ringkasan Penjualan Hari Ini ke WhatsApp">
                    <i class="fa-brands fa-whatsapp text-success"></i>
                    <span>Rekap WA</span>
                </a>
                <a href="{{ route('expenses.index') }}" class="btn btn-outline-danger quick-action-btn bg-white" title="Catat Pengeluaran Kas Operasional">
                    <i class="fa-solid fa-plus-circle text-danger"></i>
                    <span>Pengeluaran</span>
                </a>
                <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary quick-action-btn bg-white" title="Buka Laporan Penjualan Lengkap">
                    <i class="fa-solid fa-file-invoice-dollar text-muted"></i>
                    <span>Laporan</span>
                </a>
            </div>

            <!-- Compact Hamburger Quick Actions for Tablet / Mobile -->
            <div class="dropdown kl-action-dropdown d-xl-none">
                <button type="button" class="btn btn-outline-secondary quick-action-btn bg-white" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Aksi Cepat Lainnya">
                    <i class="fa-solid fa-bars"></i>
                    <span class="d-none d-sm-inline">Aksi Cepat</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end kl-action-menu">
                    <li><h6 class="dropdown-header">Pintasan Operasional</h6></li>
                    <li>
                        <a href="https://wa.me/?text={{ urlencode($dailyWaMsg) }}" target="_blank" rel="noopener noreferrer" class="dropdown-item">
                            <i class="fa-brands fa-whatsapp text-success"></i>
                            <span>Kirim Rekap Harian WA</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('expenses.index') }}" class="dropdown-item">
                            <i class="fa-solid fa-plus-circle text-danger"></i>
                            <span>Catat Pengeluaran Kas</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('reports.index') }}" class="dropdown-item">
                            <i class="fa-solid fa-file-invoice-dollar text-secondary"></i>
                            <span>Buka Laporan Penjualan</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Compact Filter Bar -->
    <div class="card premium-card border-0 shadow-sm mb-4">
        <div class="card-body p-3 p-md-4">
            <form action="{{ route('dashboard') }}" method="GET" id="filterForm" class="row g-2 g-md-3 align-items-end">
                <div class="col-12 col-md-3">
                    <label for="period" class="form-label fw-semibold text-dark small mb-1">
                        <i class="fa-solid fa-calendar-days text-success me-1"></i> Periode Laporan
                    </label>
                    <select name="period" id="period" class="form-select form-select-sm border-success-subtle fw-medium">
                        <option value="today" {{ $period === 'today' ? 'selected' : '' }}>Hari Ini</option>
                        <option value="yesterday" {{ $period === 'yesterday' ? 'selected' : '' }}>Kemarin</option>
                        <option value="this_week" {{ $period === 'this_week' ? 'selected' : '' }}>Minggu Ini</option>
                        <option value="prev_week" {{ $period === 'prev_week' ? 'selected' : '' }}>Minggu Sebelumnya</option>
                        <option value="this_month" {{ $period === 'this_month' ? 'selected' : '' }}>Bulan Ini</option>
                        <option value="prev_month" {{ $period === 'prev_month' ? 'selected' : '' }}>Bulan Sebelumnya</option>
                        <option value="this_year" {{ $period === 'this_year' ? 'selected' : '' }}>Tahun Ini</option>
                        <option value="specific_day" {{ $period === 'specific_day' ? 'selected' : '' }}>Hari Tertentu...</option>
                        <option value="specific_month" {{ $period === 'specific_month' ? 'selected' : '' }}>Bulan Tertentu...</option>
                        <option value="specific_year" {{ $period === 'specific_year' ? 'selected' : '' }}>Tahun Tertentu...</option>
                        <option value="custom" {{ $period === 'custom' ? 'selected' : '' }}>Rentang Tanggal Custom...</option>
                    </select>
                </div>

                <!-- Conditional Fields -->
                <div class="col-12 col-sm-6 col-md-3 cond-field" id="field_specific_day" style="display:none;">
                    <label for="specific_day" class="form-label fw-semibold text-dark small mb-1">Pilih Tanggal</label>
                    <input type="date" name="specific_day" id="specific_day" class="form-control form-select-sm" value="{{ request('specific_day', date('Y-m-d')) }}">
                </div>

                <div class="col-12 col-sm-6 col-md-3 cond-field" id="field_specific_month" style="display:none;">
                    <label for="specific_month" class="form-label fw-semibold text-dark small mb-1">Pilih Bulan</label>
                    <input type="month" name="specific_month" id="specific_month" class="form-control form-select-sm" value="{{ request('specific_month', date('Y-m')) }}">
                </div>

                <div class="col-12 col-sm-6 col-md-3 cond-field" id="field_specific_year" style="display:none;">
                    <label for="specific_year" class="form-label fw-semibold text-dark small mb-1">Pilih Tahun</label>
                    <select name="specific_year" id="specific_year" class="form-select form-select-sm">
                        @for($y = date('Y'); $y >= 2020; $y--)
                            <option value="{{ $y }}" {{ request('specific_year') == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>

                <div class="col-12 col-sm-6 col-md-2 cond-field" id="field_start_date" style="display:none;">
                    <label for="start_date" class="form-label fw-semibold text-dark small mb-1">Mulai Tanggal</label>
                    <input type="date" name="start_date" id="start_date" class="form-control form-select-sm" value="{{ request('start_date', date('Y-m-01')) }}">
                </div>

                <div class="col-12 col-sm-6 col-md-2 cond-field" id="field_end_date" style="display:none;">
                    <label for="end_date" class="form-label fw-semibold text-dark small mb-1">Sampai Tanggal</label>
                    <input type="date" name="end_date" id="end_date" class="form-control form-select-sm" value="{{ request('end_date', date('Y-m-t')) }}">
                </div>

                <div class="col-12 col-md-3">
                    <label for="group_by" class="form-label fw-semibold text-dark small mb-1">
                        <i class="fa-solid fa-chart-simple text-success me-1"></i> Skala Grafik
                    </label>
                    <select name="group_by" id="group_by" class="form-select form-select-sm border-success-subtle">
                        <option value="daily" {{ $groupBy === 'daily' ? 'selected' : '' }}>Harian</option>
                        <option value="weekly" {{ $groupBy === 'weekly' ? 'selected' : '' }}>Mingguan</option>
                        <option value="monthly" {{ $groupBy === 'monthly' ? 'selected' : '' }}>Bulanan</option>
                        <option value="yearly" {{ $groupBy === 'yearly' ? 'selected' : '' }}>Tahunan</option>
                    </select>
                </div>

                <div class="col-12 col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary-green w-100 py-2">
                        <i class="fa-solid fa-filter me-1"></i> Terapkan
                    </button>
                </div>

                <!-- Active Date Range Label -->
                <div class="col-12 mt-2 pt-2 border-top d-flex justify-content-between align-items-center flex-wrap gap-2 text-muted small">
                    <div>
                        <i class="fa-regular fa-calendar-check text-success me-1"></i>
                        Rentang Aktif:
                        <strong class="text-dark">{{ $startDate->translatedFormat('d M Y') }}</strong> s/d <strong class="text-dark">{{ $endDate->translatedFormat('d M Y') }}</strong>
                        <span class="text-muted">(WIB)</span>
                    </div>
                    <div class="d-none d-md-block">
                        <span>Katalog Menu: <strong>{{ $totalActiveMenus }}</strong> aktif dari <strong>{{ $totalCategories }}</strong> kategori</span>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- SECTION 1: CORE FINANCIAL OVERVIEW (4 HERO CARDS) -->
    @if(($monthlySalesTarget ?? 0) > 0)
        @php
            $targetRemaining = max(0, (float) $monthlySalesTarget - (float) ($currentMonthSales ?? 0));
            $dailyNeeded = $targetRemaining > 0 ? round($targetRemaining / max(1, (int) ($daysLeftInMonth ?? 1))) : 0;
        @endphp
        <div class="card premium-card border-0 shadow-sm mb-4" style="font-variant-numeric: tabular-nums;">
            <div class="card-body py-3 px-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2 py-1">
                            <i class="fa-solid fa-bullseye me-1"></i>Target Omzet {{ now()->translatedFormat('F Y') }}
                        </span>
                        <span class="fw-bold text-dark small">
                            Rp{{ number_format($currentMonthSales ?? 0, 0, ',', '.') }}
                            <span class="text-muted fw-normal">/ Rp{{ number_format($monthlySalesTarget, 0, ',', '.') }}</span>
                        </span>
                    </div>
                    <div class="d-flex align-items-center gap-2 small">
                        @if($targetRemaining <= 0)
                            <span class="badge bg-success rounded-pill"><i class="fa-solid fa-trophy me-1"></i>Target Tercapai! ({{ $monthlyTargetPct }}%)</span>
                        @else
                            <span class="text-muted">Sisa <strong class="text-dark">Rp{{ number_format($targetRemaining, 0, ',', '.') }}</strong> (~Rp{{ number_format($dailyNeeded, 0, ',', '.') }}/hari)</span>
                            <span class="badge bg-primary rounded-pill">{{ $monthlyTargetPct }}%</span>
                        @endif
                    </div>
                </div>
                <div class="progress" style="height: 10px; border-radius: 999px;">
                    <div class="progress-bar {{ ($monthlyTargetPct ?? 0) >= 100 ? 'bg-success' : (($monthlyTargetPct ?? 0) >= 60 ? 'bg-primary' : 'bg-warning') }}"
                         role="progressbar"
                         style="width: {{ min(100, $monthlyTargetPct ?? 0) }}%"
                         aria-valuenow="{{ min(100, $monthlyTargetPct ?? 0) }}"
                         aria-valuemin="0"
                         aria-valuemax="100"></div>
                </div>
            </div>
        </div>
    @endif

    <div class="row g-3 mb-4" style="font-variant-numeric: tabular-nums;">
        <!-- 1. Total Omzet Penjualan -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card premium-card stat-hero-card hero-omzet h-100 p-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="stat-hero-label">Total Omzet</span>
                        @if(isset($salesGrowthPct) && $salesGrowthPct !== null)
                            <span class="badge ms-1 rounded-pill {{ $salesGrowthPct >= 0 ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger' }}" style="font-size:0.68rem;" title="Dibanding periode sebelumnya dengan durasi sama">
                                <i class="fa-solid {{ $salesGrowthPct >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }} me-1"></i>{{ $salesGrowthPct >= 0 ? '+' : '' }}{{ $salesGrowthPct }}%
                            </span>
                        @endif
                    </div>
                    <div class="card-icon-wrapper bg-mint-soft" style="width:40px; height:40px; font-size:1.15rem;">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                </div>
                <div class="stat-hero-value text-dark">
                    Rp{{ number_format($totalSales, 0, ',', '.') }}
                </div>
                <div class="stat-hero-sub d-flex align-items-center justify-content-between mt-auto pt-2 border-top">
                    <span>Transaksi Berhasil</span>
                    <strong class="text-dark">{{ number_format($totalTransactions, 0, ',', '.') }}</strong>
                </div>
            </div>
        </div>

        <!-- 2. Estimasi Modal / HPP -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card premium-card stat-hero-card hero-cost h-100 p-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="stat-hero-label">Estimasi Modal (HPP)</span>
                    <div class="card-icon-wrapper" style="width:40px; height:40px; font-size:1.15rem; background-color:#fee2e2; color:#dc2626;">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                </div>
                <div class="stat-hero-value text-danger">
                    Rp{{ number_format($estimatedCost, 0, ',', '.') }}
                </div>
                <div class="stat-hero-sub d-flex align-items-center justify-content-between mt-auto pt-2 border-top">
                    <span>Laba Kotor (Omzet - HPP)</span>
                    <strong class="text-dark">Rp{{ number_format($totalProfit, 0, ',', '.') }}</strong>
                </div>
            </div>
        </div>

        <!-- 3. Pengeluaran Operasional Toko -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card premium-card stat-hero-card hero-expense h-100 p-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="stat-hero-label">Pengeluaran Kas</span>
                    <div class="card-icon-wrapper" style="width:40px; height:40px; font-size:1.15rem; background-color:#fef3c7; color:#d97706;">
                        <i class="fa-solid fa-money-bill-transfer"></i>
                    </div>
                </div>
                <div class="stat-hero-value text-warning">
                    Rp{{ number_format($totalExpenses, 0, ',', '.') }}
                </div>
                <div class="stat-hero-sub d-flex align-items-center justify-content-between mt-auto pt-2 border-top">
                    <span>Kas Operasional Keluar</span>
                    <a href="{{ route('expenses.index') }}" class="text-decoration-none text-success fw-bold">
                        Kelola <i class="fa-solid fa-arrow-right small"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- 4. Laba Bersih Aktual (Net Profit) -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card premium-card stat-hero-card hero-netprofit h-100 p-3" style="background: linear-gradient(180deg, #ffffff 0%, #f0fdf4 100%);">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="stat-hero-label text-success">Laba Bersih Aktual</span>
                        @if(isset($netProfitGrowthPct) && $netProfitGrowthPct !== null)
                            <span class="badge ms-1 rounded-pill {{ $netProfitGrowthPct >= 0 ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger' }}" style="font-size:0.68rem;" title="Dibanding periode sebelumnya">
                                <i class="fa-solid {{ $netProfitGrowthPct >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }} me-1"></i>{{ $netProfitGrowthPct >= 0 ? '+' : '' }}{{ $netProfitGrowthPct }}%
                            </span>
                        @endif
                        <small class="d-block text-muted" style="font-size:0.7rem;">(Laba Kotor &minus; Pengeluaran Kas)</small>
                    </div>
                    <div class="card-icon-wrapper bg-mint-soft" style="width:40px; height:40px; font-size:1.15rem;">
                        <i class="fa-solid fa-sack-dollar"></i>
                    </div>
                </div>
                <div class="stat-hero-value {{ $netProfit >= 0 ? 'text-success' : 'text-danger' }}">
                    Rp{{ number_format($netProfit, 0, ',', '.') }}
                </div>
                <div class="stat-hero-sub d-flex align-items-center justify-content-between mt-auto pt-2 border-top">
                    <span>Status Finansial</span>
                    @if($netProfit >= 0)
                        <span class="badge bg-success-subtle text-success fw-bold px-2 py-1 rounded-pill">Surplus / Profit</span>
                    @else
                        <span class="badge bg-danger-subtle text-danger fw-bold px-2 py-1 rounded-pill">Defisit</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 2: OPERATIONAL VOLUME & AVERAGE TICKET (UNIFIED COMPACT METRIC STRIP) -->
    <div class="kl-metric-strip mb-4">
        <div class="row g-0">
            <div class="col-6 col-xl-3 kl-metric-strip-col">
                <div class="kl-metric-strip-item">
                    <div class="card-icon-wrapper bg-light text-primary flex-shrink-0" style="width:38px; height:38px; font-size:1rem; border-radius:10px;">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="fw-bold fs-5 text-dark lh-sm">{{ number_format($totalTransactions, 0, ',', '.') }}</div>
                        <div class="text-muted small text-truncate">Struk / Transaksi</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3 kl-metric-strip-col">
                <div class="kl-metric-strip-item">
                    <div class="card-icon-wrapper bg-light text-success flex-shrink-0" style="width:38px; height:38px; font-size:1rem; border-radius:10px;">
                        <i class="fa-solid fa-mug-saucer"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="fw-bold fs-5 text-dark lh-sm">{{ number_format($totalQuantity, 0, ',', '.') }} <span class="fs-6 fw-normal text-muted">pcs</span></div>
                        <div class="text-muted small text-truncate">Item / Produk Terjual</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3 kl-metric-strip-col">
                <div class="kl-metric-strip-item">
                    <div class="card-icon-wrapper bg-light text-info flex-shrink-0" style="width:38px; height:38px; font-size:1rem; border-radius:10px;">
                        <i class="fa-solid fa-calculator"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="fw-bold fs-5 text-dark lh-sm">Rp{{ number_format($averageSales, 0, ',', '.') }}</div>
                        <div class="text-muted small text-truncate">Rata-rata Struk</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3 kl-metric-strip-col">
                <div class="kl-metric-strip-item">
                    <div class="card-icon-wrapper bg-light text-warning flex-shrink-0" style="width:38px; height:38px; font-size:1rem; border-radius:10px;">
                        <i class="fa-solid fa-tags"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="fw-bold fs-5 text-dark lh-sm">Rp{{ number_format($totalDiscount, 0, ',', '.') }}</div>
                        <div class="text-muted small text-truncate">Diskon Promo</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 3: CASH FLOW BREAKDOWN (TUNAI vs QRIS) & RECENT TRANSACTIONS FEED -->
    <div class="row g-3 mb-4">
        <!-- Left: Payment Method Breakdown (Cash in Drawer vs QRIS in Bank) -->
        <div class="col-12 col-lg-5">
            <div class="card premium-card h-100">
                <div class="premium-header d-flex justify-content-between align-items-center py-3 px-4">
                    <h6 class="m-0 fw-bold text-dark">
                        <i class="fa-solid fa-scale-balanced text-success me-2"></i> Arus Masuk per Metode Bayar
                    </h6>
                    <span class="badge bg-light text-muted border">Periode Terpilih</span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3 mb-3">
                        <!-- Tunai (Laci Kasir) -->
                        <div class="col-12 col-sm-6">
                            <div class="payment-channel-box h-100">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="small fw-semibold text-muted">
                                        <i class="fa-solid fa-money-bill-wave text-success me-1"></i> Tunai (Laci Kasir)
                                    </span>
                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill" style="font-size:0.7rem;">
                                        {{ $totalCashCount }} trx
                                    </span>
                                </div>
                                <h5 class="fw-bold text-dark mb-1">Rp{{ number_format($totalCashSales, 0, ',', '.') }}</h5>
                                <div class="small text-muted">
                                    {{ $totalSales > 0 ? round(($totalCashSales / $totalSales) * 100, 1) : 0 }}% dari omzet
                                </div>
                            </div>
                        </div>

                        <!-- QRIS (Bank / Digital) -->
                        <div class="col-12 col-sm-6">
                            <div class="payment-channel-box h-100">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="small fw-semibold text-muted">
                                        <i class="fa-solid fa-qrcode text-info me-1"></i> QRIS Dinamis
                                    </span>
                                    <span class="badge bg-info bg-opacity-10 text-info rounded-pill" style="font-size:0.7rem;">
                                        {{ $totalQrisCount }} trx
                                    </span>
                                </div>
                                <h5 class="fw-bold text-dark mb-1">Rp{{ number_format($totalQrisSales, 0, ',', '.') }}</h5>
                                <div class="small text-muted">
                                    {{ $totalSales > 0 ? round(($totalQrisSales / $totalSales) * 100, 1) : 0 }}% dari omzet
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Bar Ratio -->
                    @php
                        $cashPct = $totalSales > 0 ? ($totalCashSales / $totalSales) * 100 : 50;
                        $qrisPct = $totalSales > 0 ? ($totalQrisSales / $totalSales) * 100 : 50;
                    @endphp
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Tunai: {{ round($cashPct, 1) }}%</span>
                            <span>QRIS: {{ round($qrisPct, 1) }}%</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: {{ $cashPct }}%" aria-valuenow="{{ $cashPct }}" aria-valuemin="0" aria-valuemax="100"></div>
                            <div class="progress-bar bg-info" role="progressbar" style="width: {{ $qrisPct }}%" aria-valuenow="{{ $qrisPct }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>

                    <!-- Today's Pulse Strip & Cash Drawer Reconciliation (WIB) -->
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold text-dark small">
                                <i class="fa-solid fa-cash-register text-success me-1"></i> Estimasi Kas Fisik Laci (Periode)
                            </span>
                            <strong class="fs-6 {{ ($cashDrawerEstimate ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                Rp{{ number_format($cashDrawerEstimate ?? 0, 0, ',', '.') }}
                            </strong>
                        </div>
                        <small class="text-muted d-block" style="font-size:0.73rem;">
                            Tunai Masuk (Rp{{ number_format($totalCashSales, 0, ',', '.') }}) &minus; Pengeluaran Tunai (Rp{{ number_format($totalCashExpenses ?? 0, 0, ',', '.') }})
                        </small>
                    </div>

                    <div class="p-3 bg-light rounded-3 border">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold text-dark small">
                                <i class="fa-solid fa-bolt text-warning me-1"></i> Performa Hari Ini (WIB)
                            </span>
                            <span class="badge bg-white text-secondary border small">{{ date('d M Y') }}</span>
                        </div>
                        <div class="d-flex justify-content-between flex-wrap gap-2 small text-muted mt-2 pt-2 border-top">
                            <span>Omzet: <strong class="text-dark">Rp{{ number_format($todaySales, 0, ',', '.') }}</strong></span>
                            <span>Kas Laci: <strong class="text-primary">Rp{{ number_format($todayCashDrawer ?? 0, 0, ',', '.') }}</strong></span>
                            <span>Bersih: <strong class="{{ $todayNetProfit >= 0 ? 'text-success' : 'text-danger' }}">Rp{{ number_format($todayNetProfit, 0, ',', '.') }}</strong></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: 5 Recent Successful Transactions Live Feed -->
        <div class="col-12 col-lg-7">
            <div class="card premium-card h-100">
                <div class="premium-header d-flex justify-content-between align-items-center py-3 px-4">
                    <h6 class="m-0 fw-bold text-dark">
                        <i class="fa-solid fa-clock-rotate-left text-success me-2"></i> 5 Transaksi Berhasil Terakhir
                    </h6>
                    <a href="{{ route('transactions.index') }}" class="btn btn-sm btn-outline-success rounded-pill px-3 py-1 text-decoration-none" style="font-size:0.78rem;">
                        Semua Transaksi <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr style="font-size:0.8rem;">
                                    <th class="ps-3">No. Nota</th>
                                    <th>Waktu (WIB)</th>
                                    <th>Pelanggan</th>
                                    <th>Metode</th>
                                    <th class="text-end pe-3">Total Belanja</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentTransactions as $tx)
                                    <tr>
                                        <td class="ps-3">
                                            <a href="{{ route('transactions.show', $tx->id) }}" class="fw-bold text-success text-decoration-none" title="Lihat Struk Transaksi">
                                                {{ $tx->transaction_number }}
                                            </a>
                                        </td>
                                        <td class="small text-muted">
                                            {{ \Carbon\Carbon::parse($tx->transaction_date)->translatedFormat('d M') }}
                                            <span class="fw-semibold text-dark">{{ substr((string) ($tx->transaction_time ?? '00:00'), 0, 5) }}</span>
                                        </td>
                                        <td class="small text-dark">
                                            {{ $tx->customer_name ?: 'Pelanggan' }}
                                        </td>
                                        <td>
                                            @if($tx->payment_method === 'qris')
                                                <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-1" style="font-size:0.7rem;">
                                                    <i class="fa-solid fa-qrcode me-1"></i> QRIS
                                                </span>
                                            @else
                                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1" style="font-size:0.7rem;">
                                                    <i class="fa-solid fa-money-bill-wave me-1"></i> Tunai
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-3 fw-bold text-dark">
                                            Rp{{ number_format($tx->final_amount ?: $tx->total_sales, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted small">
                                            <i class="fa-solid fa-inbox fs-3 mb-2 d-block text-secondary opacity-50"></i>
                                            Belum ada transaksi berhasil yang tercatat.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 3.5: PER-CASHIER SHIFT RECONCILIATION -->
    @if(isset($cashierStats) && $cashierStats->count() > 0)
    <div class="card premium-card mb-4">
        <div class="premium-header py-3 px-4 d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-dark">
                <i class="fa-solid fa-user-check text-success me-2"></i> Rekap Penjualan per Kasir / Penginput
            </h6>
            <span class="badge bg-light text-muted border">Serah Terima Shift</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small" style="font-variant-numeric: tabular-nums;">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Nama Kasir / Admin</th>
                        <th class="text-center">Jumlah Nota</th>
                        <th class="text-end">Tunai Diterima</th>
                        <th class="text-end">QRIS</th>
                        <th class="text-end pe-4">Total Omzet</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cashierStats as $cs)
                        <tr>
                            <td class="ps-4 fw-semibold text-dark">{{ $cs->name }}</td>
                            <td class="text-center"><span class="badge bg-secondary bg-opacity-10 text-dark">{{ $cs->tx_count }} trx</span></td>
                            <td class="text-end text-success fw-semibold">Rp{{ number_format($cs->cash_sales, 0, ',', '.') }}</td>
                            <td class="text-end text-info fw-semibold">Rp{{ number_format($cs->qris_sales, 0, ',', '.') }}</td>
                            <td class="text-end pe-4 fw-bold text-dark">Rp{{ number_format($cs->total_sales, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- SECTION 4: CHARTS (TREND & CATEGORY BREAKDOWN) -->
    <div class="row g-3 mb-4">
        <!-- Sales and Profit Trend Chart -->
        <div class="col-12 col-lg-8">
            <div class="card premium-card h-100">
                <div class="premium-header py-3 px-4 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-dark">
                        <i class="fa-solid fa-chart-line text-success me-2"></i> Perkembangan Omzet & Keuntungan
                    </h6>
                    <span class="badge bg-light text-muted border">Skala: {{ ucfirst($groupBy) }}</span>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div style="height: 330px; position: relative;">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales by Category Pie Chart -->
        <div class="col-12 col-lg-4">
            <div class="card premium-card h-100">
                <div class="premium-header py-3 px-4">
                    <h6 class="m-0 fw-bold text-dark">
                        <i class="fa-solid fa-chart-pie text-success me-2"></i> Penjualan per Kategori
                    </h6>
                </div>
                <div class="card-body p-3 p-md-4 d-flex align-items-center justify-content-center">
                    @if($categorySales->count() > 0)
                        <div style="width: 100%; max-height: 290px; position: relative;">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    @else
                        <div class="text-center text-muted py-5">
                            <i class="fa-solid fa-folder-open fs-1 mb-2 text-secondary opacity-50"></i>
                            <p class="small m-0">Tidak ada data kategori untuk periode ini</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 5: MENU PERFORMANCE (TOP BEST SELLERS & LEAST SOLD) -->
    <div class="row g-3">
        <!-- Top 10 Best Sellers -->
        <div class="col-12 col-lg-6">
            <div class="card premium-card h-100">
                <div class="premium-header py-3 px-4 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-dark">
                        <i class="fa-solid fa-trophy text-warning me-2"></i> 10 Menu Paling Laris
                    </h6>
                    <span class="badge bg-warning bg-opacity-10 text-dark" style="font-size:0.75rem;">Terpopuler</span>
                </div>
                <div class="card-body p-3 p-md-4">
                    @if($topMenus->count() > 0)
                        <div style="max-height: 220px; position: relative;" class="mb-3">
                            <canvas id="topMenusChart"></canvas>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle border mb-0">
                                <thead class="table-light small">
                                    <tr>
                                        <th style="width: 35px;">No</th>
                                        <th>Nama Menu</th>
                                        <th class="text-center">Terjual</th>
                                        <th class="text-end">Omzet</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($topMenus as $idx => $m)
                                        <tr>
                                            <td class="small text-muted">{{ $idx + 1 }}</td>
                                            <td class="fw-semibold text-dark small">{{ $m->menu_name_snapshot }}</td>
                                            <td class="text-center">
                                                <span class="badge bg-success bg-opacity-15 text-success rounded-pill px-2 py-1" style="font-size:0.75rem;">
                                                    {{ $m->qty }} pcs
                                                </span>
                                            </td>
                                            <td class="text-end small fw-semibold text-dark">
                                                Rp{{ number_format($m->sales, 0, ',', '.') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center text-muted py-5">
                            <i class="fa-solid fa-mug-hot fs-2 mb-2 text-secondary opacity-50"></i>
                            <p class="small m-0">Belum ada transaksi menu pada periode ini.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Least Sold Menus -->
        <div class="col-12 col-lg-6">
            <div class="card premium-card h-100">
                <div class="premium-header py-3 px-4 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-dark">
                        <i class="fa-solid fa-arrow-down-wide-short text-danger me-2"></i> Menu Perlu Perhatian
                    </h6>
                    <span class="badge bg-danger bg-opacity-10 text-danger" style="font-size:0.75rem;">Penjualan Terendah</span>
                </div>
                <div class="card-body p-3 p-md-4">
                    @if($bottomMenus->count() > 0)
                        <p class="text-muted small mb-3">
                            Daftar menu dengan frekuensi penjualan paling rendah untuk evaluasi promosi, resep, atau rotasi stok bahan.
                        </p>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle border mb-0">
                                <thead class="table-light small">
                                    <tr>
                                        <th style="width: 35px;">No</th>
                                        <th>Nama Menu</th>
                                        <th class="text-center">Jumlah Terjual</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($bottomMenus as $idx => $m)
                                        <tr>
                                            <td class="small text-muted">{{ $idx + 1 }}</td>
                                            <td class="fw-semibold text-dark small">{{ $m->menu_name_snapshot }}</td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary bg-opacity-15 text-secondary rounded-pill px-2 py-1" style="font-size:0.75rem;">
                                                    {{ $m->qty }} pcs
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center text-muted py-5">
                            <i class="fa-solid fa-circle-check fs-2 mb-2 text-success opacity-50"></i>
                            <p class="small m-0">Seluruh menu terjual merata pada periode ini.</p>
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
            togglePeriodFields();

            // ----------------------------------------------
            // Chart 1: Trend Chart (Sales vs Profit)
            // ----------------------------------------------
            const trendEl = document.getElementById('trendChart');
            if (trendEl) {
                const trendCtx = trendEl.getContext('2d');
                const trendLabels = @json(collect($trendData)->pluck('label'));
                const salesData = @json(collect($trendData)->pluck('sales'));
                const profitData = @json(collect($trendData)->pluck('profit'));

                new Chart(trendCtx, {
                    type: 'line',
                    data: {
                        labels: trendLabels,
                        datasets: [
                            {
                                label: 'Omzet Penjualan',
                                data: salesData,
                                borderColor: '#11361b',
                                backgroundColor: 'rgba(17, 54, 27, 0.06)',
                                fill: true,
                                tension: 0.35,
                                borderWidth: 3,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                yAxisID: 'y'
                            },
                            {
                                label: 'Keuntungan Kotor',
                                data: profitData,
                                borderColor: '#a3e635',
                                backgroundColor: 'rgba(163, 230, 83, 0.08)',
                                fill: true,
                                tension: 0.35,
                                borderWidth: 2,
                                pointRadius: 3,
                                pointHoverRadius: 5,
                                yAxisID: 'y'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    boxWidth: 8,
                                    font: { size: 12, family: 'Inter, system-ui, sans-serif' }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        let val = ctx.parsed.y || 0;
                                        return ctx.dataset.label + ': Rp' + val.toLocaleString('id-ID');
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: 'rgba(0,0,0,0.05)' },
                                ticks: {
                                    font: { size: 11 },
                                    callback: function(value) {
                                        return 'Rp' + value.toLocaleString('id-ID');
                                    }
                                }
                            },
                            x: {
                                grid: { display: false },
                                ticks: { font: { size: 11 } }
                            }
                        }
                    }
                });
            }

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
                                '#a3e635',
                                '#d9f99d',
                                '#64748b'
                            ],
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    boxWidth: 10,
                                    usePointStyle: true,
                                    font: { size: 11 }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        let val = ctx.parsed || 0;
                                        return ctx.label + ': Rp' + val.toLocaleString('id-ID');
                                    }
                                }
                            }
                        },
                        cutout: '65%'
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
                            label: 'Jumlah Terjual (pcs)',
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
                                grid: { color: 'rgba(0,0,0,0.05)' },
                                ticks: { font: { size: 11 } }
                            },
                            y: {
                                grid: { display: false },
                                ticks: { font: { size: 11 } }
                            }
                        }
                    }
                });
            }
        });
    </script>
@endsection
