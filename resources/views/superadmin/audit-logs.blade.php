@extends('layouts.app')

@section('title', 'Log Aktivitas Audit Platform')
@section('page_title', 'Audit Trail Aktivitas Super Admin')

@section('breadcrumbs')
    <li class="breadcrumb-item active">Log Aktivitas Audit</li>
@endsection

@section('content')
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <h6 class="fw-bold m-0 me-1"><i class="fa-solid fa-clipboard-list text-primary me-2"></i>Riwayat Tindakan Super Admin</h6>
            @php $curCat = request('category', ''); @endphp
            <div class="btn-group btn-group-sm" role="group" aria-label="Filter Kategori Audit">
                @foreach(['' => 'Semua', 'tenant' => 'Tenant', 'invoice' => 'Invoice', 'plan' => 'Paket', 'promo' => 'Promo', 'settings' => 'Pengaturan', 'impersonate' => 'Impersonate'] as $catVal => $catLbl)
                    <a href="{{ route('superadmin.audit-logs', array_filter(['category' => $catVal, 'search' => request('search')])) }}"
                       class="btn {{ $curCat === $catVal ? 'btn-dark' : 'btn-outline-secondary' }}">
                        {{ $catLbl }}
                    </a>
                @endforeach
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <form method="GET" action="{{ route('superadmin.audit-logs') }}" class="d-flex gap-2">
                @if(request('category'))
                    <input type="hidden" name="category" value="{{ request('category') }}">
                @endif
                <input type="text" name="search" class="form-control form-control-sm" style="min-width: 200px;" placeholder="Cari aksi / nama usaha / detail..." value="{{ request('search') }}">
                <button type="submit" class="btn btn-sm btn-outline-success">Cari</button>
            </form>
            <a href="{{ route('superadmin.audit-logs.export', request()->query()) }}" class="btn btn-sm btn-outline-dark" title="Unduh Arsip Audit Trail (CSV)">
                <i class="fa-solid fa-file-csv me-1"></i>Export CSV
            </a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th style="width: 155px;">Waktu (WIB)</th>
                    <th style="width: 115px;">Kategori</th>
                    <th>Pelaku &amp; Target</th>
                    <th>Detail Perubahan</th>
                    <th class="text-end" style="width: 120px;">IP Address</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    @php
                        $catBadge = match($log->category) {
                            'tenant' => 'bg-success',
                            'invoice' => 'bg-primary',
                            'plan' => 'bg-info text-dark',
                            'promo' => 'bg-dark text-warning',
                            'impersonate' => 'bg-warning text-dark',
                            default => 'bg-secondary',
                        };
                    @endphp
                    <tr>
                        <td class="text-muted font-monospace" style="font-size: 0.78rem;">
                            {{ $log->created_at?->translatedFormat('d M Y, H:i:s') }}
                        </td>
                        <td>
                            <span class="badge {{ $catBadge }} text-uppercase">{{ $log->category }}</span>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark">{{ $log->admin?->name ?? 'System' }}</div>
                            @if($log->target_label)
                                <small class="text-muted">Target: <strong>{{ $log->target_label }}</strong></small>
                            @endif
                        </td>
                        <td class="text-dark">{{ $log->description }}</td>
                        <td class="text-end font-monospace text-muted" style="font-size: 0.78rem;">{{ $log->ip_address ?: '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-5">
                            <i class="fa-solid fa-clipboard-check fs-2 mb-2 d-block opacity-50"></i>
                            Belum ada catatan aktivitas audit yang terekam.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())
        <div class="card-footer bg-white">{{ $logs->links() }}</div>
    @endif
</div>
@endsection
