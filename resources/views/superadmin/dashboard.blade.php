@extends('layouts.app')

@section('title', 'Dashboard Super Admin')
@section('page_title', 'Dashboard Platform ' . ($platformName ?? 'KasirLoka'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Dashboard SaaS</li>
@endsection

@section('content')
<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <small class="text-uppercase text-muted fw-bold">Estimasi MRR (Bulanan)</small>
                <h3 class="fw-bold text-success mt-2 mb-1">Rp{{ number_format($mrr, 0, ',', '.') }}</h3>
                <small class="text-muted">Pendapatan berulang aktif / bulan</small>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <small class="text-uppercase text-muted fw-bold">Revenue Bulan Ini</small>
                <h3 class="fw-bold text-dark mt-2 mb-1">Rp{{ number_format($revenueThisMonth, 0, ',', '.') }}</h3>
                <small class="text-muted">Total semua waktu: Rp{{ number_format($revenueAllTime, 0, ',', '.') }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <small class="text-uppercase text-muted fw-bold">Total Usaha (Tenant)</small>
                <h3 class="fw-bold text-primary mt-2 mb-1">{{ $totalTenants }}</h3>
                <small class="text-muted">{{ $activeTenants }} Aktif · {{ $trialTenants }} Trial · {{ $expiredTenants }} Expired</small>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <small class="text-uppercase text-muted fw-bold">Invoice Menunggu</small>
                <h3 class="fw-bold {{ $pendingInvoicesCount > 0 ? 'text-warning' : 'text-secondary' }} mt-2 mb-1">{{ $pendingInvoicesCount }}</h3>
                <small class="text-muted">Total transaksi POS global: {{ number_format($totalPlatformTransactions, 0, ',', '.') }}</small>
            </div>
        </div>
    </div>
</div>

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
                                <td class="fw-bold text-success">Rp{{ number_format((float)$inv->final_amount, 0, ',', '.') }}</td>
                                <td class="text-end">
                                    <form action="{{ route('superadmin.invoices.approve', $inv->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Konfirmasi invoice ini LUNAS dan aktifkan paket usaha?');">
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
                                    <span class="badge {{ in_array($st, ['active','lifetime']) ? 'bg-success' : ($st === 'trial' ? 'bg-info text-dark' : 'bg-danger') }}">
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
