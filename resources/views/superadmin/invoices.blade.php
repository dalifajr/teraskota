@extends('layouts.app')

@section('title', 'Tagihan & Pembayaran Langganan')
@section('page_title', 'Tagihan & Verifikasi Pembayaran')

@section('breadcrumbs')
    <li class="breadcrumb-item active">Tagihan &amp; Pembayaran</li>
@endsection

@section('content')
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom">
        <h6 class="fw-bold m-0"><i class="fa-solid fa-file-invoice-dollar text-success me-2"></i>Daftar Invoice Langganan</h6>
        <div class="d-flex gap-1">
            @foreach(['' => 'Semua', 'unpaid' => 'Menunggu Bayar', 'paid' => 'Lunas', 'expired' => 'Expired', 'cancelled' => 'Batal'] as $val => $lbl)
                <a href="{{ route('superadmin.invoices', $val ? ['status' => $val] : []) }}"
                   class="btn btn-sm {{ request('status', '') === $val ? 'btn-success' : 'btn-outline-secondary' }}">
                    {{ $lbl }}
                </a>
            @endforeach
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Invoice &amp; Usaha</th>
                    <th>Paket</th>
                    <th>Nominal (Unik)</th>
                    <th>Metode / Referensi</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $inv)
                    <tr>
                        <td>
                            <div class="fw-bold font-monospace">{{ $inv->invoice_number }}</div>
                            <div class="text-muted">{{ $inv->tenant?->name ?? '-' }}</div>
                            <small class="text-muted">{{ $inv->created_at->translatedFormat('d M Y, H:i') }}</small>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $inv->plan?->name ?? '-' }}</div>
                            <small class="text-muted text-capitalize">{{ $inv->billing_cycle }}</small>
                        </td>
                        <td>
                            <div class="fw-bold text-success">Rp{{ number_format((float)$inv->final_amount, 0, ',', '.') }}</div>
                            <small class="text-muted">Kode unik: {{ $inv->unique_code }}</small>
                        </td>
                        <td>
                            <div class="text-uppercase fw-semibold">{{ $inv->payment_method }}</div>
                            @if($inv->payment_source_app)
                                <small class="text-muted d-block">{{ $inv->payment_source_app }}</small>
                            @endif
                            @if($inv->payment_reference)
                                <small class="text-muted font-monospace">{{ $inv->payment_reference }}</small>
                            @endif
                        </td>
                        <td>
                            @if($inv->status === 'paid')
                                <span class="badge bg-success">LUNAS</span>
                                <small class="d-block text-muted">{{ $inv->paid_at?->translatedFormat('d/m/Y H:i') }}</small>
                            @elseif($inv->status === 'unpaid')
                                <span class="badge bg-warning text-dark">UNPAID</span>
                            @else
                                <span class="badge bg-secondary">{{ strtoupper($inv->status) }}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if($inv->status === 'unpaid')
                                <form action="{{ route('superadmin.invoices.approve', $inv->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Konfirmasi invoice ini LUNAS & aktifkan langganan?');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success py-0 px-2">Approve Lunas</button>
                                </form>
                                <form action="{{ route('superadmin.invoices.cancel', $inv->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Batalkan invoice ini?');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2">Batal</button>
                                </form>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada invoice langganan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($invoices->hasPages())
        <div class="card-footer bg-white">{{ $invoices->links() }}</div>
    @endif
</div>
@endsection
