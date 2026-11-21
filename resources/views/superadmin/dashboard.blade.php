@extends('layouts.app')

@section('title', 'Dashboard Super Admin')
@section('page_title', 'Dashboard Platform ' . ($platformName ?? 'KasirLoka'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Dashboard SaaS</li>
@endsection

@section('content')
<!-- KPI Cards -->
<div class="row g-3 mb-4" style="font-variant-numeric: tabular-nums;">
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <small class="text-uppercase text-muted fw-bold">Estimasi MRR (Bulanan)</small>
                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill"><i class="fa-solid fa-chart-line"></i></span>
                </div>
                <h3 class="fw-bold text-success mt-2 mb-1">Rp{{ number_format($mrr, 0, ',', '.') }}</h3>
                <small class="text-muted">Pendapatan berulang aktif / bulan</small>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <small class="text-uppercase text-muted fw-bold">Revenue Bulan Ini</small>
                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill"><i class="fa-solid fa-wallet"></i></span>
                </div>
                <h3 class="fw-bold text-dark mt-2 mb-1">Rp{{ number_format($revenueThisMonth, 0, ',', '.') }}</h3>
                <small class="text-muted">Semua waktu: Rp{{ number_format($revenueAllTime, 0, ',', '.') }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <small class="text-uppercase text-muted fw-bold">Total Usaha (Tenant)</small>
                    <span class="badge bg-info bg-opacity-10 text-info rounded-pill"><i class="fa-solid fa-store"></i></span>
                </div>
                <h3 class="fw-bold text-primary mt-2 mb-1">{{ $totalTenants }}</h3>
                <small class="text-muted">
                    {{ $activeTenants }} Aktif · {{ $trialTenants }} Trial
                    @if(($graceTenants ?? 0) > 0)
                        · <span class="text-warning fw-semibold">{{ $graceTenants }} Grace</span>
                    @endif
                    · {{ $expiredTenants }} Expired
                </small>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <small class="text-uppercase text-muted fw-bold">Invoice Menunggu</small>
                    <span class="badge {{ $pendingInvoicesCount > 0 ? 'bg-warning text-dark' : 'bg-secondary bg-opacity-10 text-secondary' }} rounded-pill"><i class="fa-solid fa-clock"></i></span>
                </div>
                <h3 class="fw-bold {{ $pendingInvoicesCount > 0 ? 'text-warning' : 'text-secondary' }} mt-2 mb-1">{{ $pendingInvoicesCount }}</h3>
                <small class="text-muted">Transaksi POS global: {{ number_format($totalPlatformTransactions, 0, ',', '.') }}</small>
            </div>
        </div>
    </div>
</div>

<!-- Advanced SaaS Business Metrics Strip -->
<div class="row g-3 mb-4" style="font-variant-numeric: tabular-nums;">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-uppercase text-muted fw-bold d-block">Trial Conversion Rate</small>
                    <h4 class="fw-bold text-success mt-1 mb-0">{{ number_format($conversionRatePct ?? 0, 1) }}%</h4>
                    <small class="text-muted">{{ $paidActiveTenants ?? 0 }} usaha berbayar aktif</small>
                </div>
                <div class="rounded-3 bg-success bg-opacity-10 text-success p-2 fs-5"><i class="fa-solid fa-bullseye"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-uppercase text-muted fw-bold d-block">ARPU (Avg. Revenue / Tenant)</small>
                    <h4 class="fw-bold text-primary mt-1 mb-0">Rp{{ number_format($arpu ?? 0, 0, ',', '.') }}</h4>
                    <small class="text-muted">Rata-rata nilai langganan / bulan</small>
                </div>
                <div class="rounded-3 bg-primary bg-opacity-10 text-primary p-2 fs-5"><i class="fa-solid fa-coins"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-uppercase text-muted fw-bold d-block">GMV POS Global (Bulan Ini)</small>
                    <h4 class="fw-bold text-dark mt-1 mb-0">Rp{{ number_format($platformGmvThisMonth ?? 0, 0, ',', '.') }}</h4>
                    <small class="text-muted">Total volume penjualan seluruh tenant</small>
                </div>
                <div class="rounded-3 bg-warning bg-opacity-10 text-warning p-2 fs-5"><i class="fa-solid fa-cash-register"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Row 2: 6-Month SaaS Growth Chart + Expiring Soon Watchlist -->
<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h6 class="fw-bold m-0"><i class="fa-solid fa-chart-column text-success me-2"></i>Tren Revenue &amp; Pertumbuhan Tenant (6 Bulan)</h6>
                    <small class="text-muted">Perbandingan pendapatan langganan lunas vs pendaftar usaha baru</small>
                </div>
            </div>
            <div class="card-body p-3 p-md-4">
                <div style="height: 250px; position: relative;">
                    <canvas id="saasGrowthChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h6 class="fw-bold m-0"><i class="fa-solid fa-hourglass-half text-danger me-2"></i>Segera Berakhir (&le; 7 Hari / Grace)</h6>
                    <small class="text-muted">Prioritas follow-up perpanjangan &amp; konversi trial</small>
                </div>
                <span class="badge {{ ($expiringSoonTenants->count() ?? 0) > 0 ? 'bg-danger' : 'bg-success' }} rounded-pill">
                    {{ $expiringSoonTenants->count() }}
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Usaha</th>
                            <th>Sisa Waktu</th>
                            <th class="text-end">Follow-up</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($expiringSoonTenants as $et)
                            @php
                                $etState = $et->subscriptionState();
                                $etDays = $et->daysRemaining();
                                $etPhone = preg_replace('/[^0-9]/', '', (string) $et->phone);
                                if (str_starts_with($etPhone, '0')) {
                                    $etPhone = '62' . substr($etPhone, 1);
                                }
                                $waMsg = "Halo {$et->name}, masa " . ($etState === 'trial' ? 'uji coba gratis' : 'aktif langganan') . " {$platformName} Anda " . ($etState === 'grace' ? 'sedang dalam masa tenggang' : 'akan segera berakhir') . ". Yuk perpanjang paket melalui menu Langganan & Paket agar operasional kasir tetap lancar.";
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark">{{ $et->name }}</div>
                                    <small class="text-muted">{{ $et->currentPlan()?->name ?? '-' }} · {{ strtoupper($etState) }}</small>
                                </td>
                                <td>
                                    @if($etState === 'grace')
                                        <span class="badge bg-warning text-dark">Masa Tenggang</span>
                                    @elseif($etDays !== null && $etDays <= 0)
                                        <span class="badge bg-danger">Hari Ini</span>
                                    @else
                                        <span class="badge bg-danger bg-opacity-10 text-danger fw-bold">{{ $etDays }} hari lagi</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($etPhone !== '')
                                        <a href="https://wa.me/{{ $etPhone }}?text={{ urlencode($waMsg) }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-success py-0 px-2" title="Follow-up via WhatsApp">
                                            <i class="fa-brands fa-whatsapp me-1"></i>WA
                                        </a>
                                    @else
                                        <a href="{{ route('superadmin.tenants', ['search' => $et->slug]) }}" class="btn btn-sm btn-outline-secondary py-0 px-2">Lihat</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">
                                    <i class="fa-solid fa-circle-check text-success fs-4 d-block mb-2"></i>
                                    Semua tenant masih memiliki masa aktif aman (&gt; 7 hari).
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Row 3: Pending Invoices + Recent Tenants -->
<div class="row g-4">
    <!-- Pending Invoices -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                <h6 class="fw-bold m-0"><i class="fa-solid fa-clock text-warning me-2"></i>Tagihan Menunggu Konfirmasi</h6>
                <a href="{{ route('superadmin.invoices') }}" class="btn btn-sm btn-outline-success">Semua Invoice</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Invoice / Usaha</th>
                            <th>Nominal</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pendingInvoices as $inv)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $inv->tenant?->name ?? '-' }}</div>
                                    <small class="text-muted font-monospace">{{ $inv->invoice_number }} · {{ $inv->plan?->name }} ({{ $inv->billing_cycle }})</small>
                                </td>
                                <td class="fw-bold text-success" style="font-variant-numeric: tabular-nums;">Rp{{ number_format((float)$inv->final_amount, 0, ',', '.') }}</td>
                                <td class="text-end">
                                    <form action="{{ route('superadmin.invoices.approve', $inv->id) }}" method="POST" class="d-inline" data-confirm="Konfirmasi invoice {{ $inv->invoice_number }} LUNAS dan aktifkan paket usaha {{ $inv->tenant?->name }}?">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success py-0 px-2">Konfirmasi Lunas</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-4">Tidak ada invoice pending.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Tenants -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                <h6 class="fw-bold m-0"><i class="fa-solid fa-store text-success me-2"></i>Usaha (Tenant) Terbaru</h6>
                <a href="{{ route('superadmin.tenants') }}" class="btn btn-sm btn-outline-success">Kelola Semua</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Usaha</th>
                            <th>Paket / Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentTenants as $t)
                            @php $st = $t->subscriptionState(); @endphp
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $t->name }}</div>
                                    <small class="text-muted">{{ $t->business_type }} · {{ $t->transactions_count }} trx</small>
                                </td>
                                <td>
                                    <span class="badge {{ in_array($st, ['active','lifetime']) ? 'bg-success' : ($st === 'trial' ? 'bg-info text-dark' : ($st === 'grace' ? 'bg-warning text-dark' : 'bg-danger')) }}">
                                        {{ strtoupper($st) }}
                                    </span>
                                    <div class="text-muted" style="font-size: 0.72rem;">{{ $t->currentPlan()?->name ?? '-' }}</div>
                                </td>
                                <td class="text-end">
                                    <form action="{{ route('superadmin.tenants.impersonate', $t->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary py-0 px-2" title="Masuk ke panel usaha ini">Support</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-4">Belum ada tenant terdaftar.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    (function() {
        const ctx = document.getElementById('saasGrowthChart');
        if (!ctx || typeof Chart === 'undefined') return;

        const trend = @json($monthlyTrend);

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: trend.labels || [],
                datasets: [
                    {
                        label: 'Revenue Langganan (Rp)',
                        data: trend.revenues || [],
                        backgroundColor: 'rgba(22, 163, 74, 0.78)',
                        borderColor: '#15803d',
                        borderWidth: 1,
                        borderRadius: 6,
                        yAxisID: 'y',
                        order: 2
                    },
                    {
                        label: 'Usaha Baru',
                        data: trend.new_tenants || [],
                        type: 'line',
                        borderColor: '#0284c7',
                        backgroundColor: 'rgba(2, 132, 199, 0.14)',
                        borderWidth: 2.5,
                        pointRadius: 4,
                        pointBackgroundColor: '#0284c7',
                        tension: 0.35,
                        fill: true,
                        yAxisID: 'y1',
                        order: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                if (context.datasetIndex === 0) {
                                    return ' Revenue: Rp' + new Intl.NumberFormat('id-ID').format(context.parsed.y || 0);
                                }
                                return ' Usaha Baru: ' + (context.parsed.y || 0) + ' tenant';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        position: 'left',
                        ticks: {
                            callback: function(value) {
                                return 'Rp' + new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(value);
                            }
                        },
                        grid: { color: 'rgba(148, 163, 184, 0.15)' }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        ticks: { stepSize: 1, precision: 0 },
                        grid: { drawOnChartArea: false }
                    }
                }
            }
        });
    })();
</script>
@endsection
