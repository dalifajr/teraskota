@extends('layouts.app')

@section('title', 'Tagihan & Pembayaran Langganan')
@section('page_title', 'Tagihan & Verifikasi Pembayaran')

@section('breadcrumbs')
    <li class="breadcrumb-item active">Tagihan &amp; Pembayaran</li>
@endsection

@section('content')
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <h6 class="fw-bold m-0 me-1"><i class="fa-solid fa-file-invoice-dollar text-success me-2"></i>Daftar Invoice Langganan</h6>
            <div class="btn-group btn-group-sm" role="group" aria-label="Filter Status Invoice">
                @foreach(['' => 'Semua', 'unpaid' => 'Menunggu', 'paid' => 'Lunas', 'expired' => 'Expired', 'cancelled' => 'Batal'] as $val => $lbl)
                    <a href="{{ route('superadmin.invoices', array_filter(['status' => $val, 'search' => request('search')])) }}"
                       class="btn {{ request('status', '') === $val ? 'btn-success' : 'btn-outline-secondary' }}">
                        {{ $lbl }}
                    </a>
                @endforeach
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <form method="GET" action="{{ route('superadmin.invoices') }}" class="d-flex gap-2">
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                <input type="text" name="search" class="form-control form-control-sm" style="min-width: 220px;" placeholder="Cari no. invoice / usaha / kode unik..." value="{{ request('search') }}">
                <button type="submit" class="btn btn-sm btn-outline-success">Cari</button>
            </form>
            <a href="{{ route('superadmin.invoices.export', array_filter(['status' => request('status'), 'search' => request('search')])) }}" class="btn btn-sm btn-outline-dark" title="Download CSV Pembukuan Revenue SaaS">
                <i class="fa-solid fa-file-csv me-1"></i>Export CSV
            </a>
            <button type="button" class="btn btn-sm btn-primary-green" data-bs-toggle="modal" data-bs-target="#createInvoiceModal">
                <i class="fa-solid fa-plus me-1"></i>Buat Invoice Manual
            </button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small" style="font-variant-numeric: tabular-nums;">
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
                    @php
                        $invPhone = preg_replace('/[^0-9]/', '', (string) ($inv->tenant?->phone ?? ''));
                        if (str_starts_with($invPhone, '0')) {
                            $invPhone = '62' . substr($invPhone, 1);
                        }
                        $waBillText = "Halo {$inv->tenant?->name}, berikut pengingat tagihan langganan {$platformName}:\nNo. Invoice: {$inv->invoice_number}\nPaket: {$inv->plan?->name} ({$inv->billing_cycle})\nTotal Bayar: Rp" . number_format((float)$inv->final_amount, 0, ',', '.') . " (termasuk kode unik {$inv->unique_code}).\nSilakan lakukan pembayaran melalui menu Langganan & Paket agar aktif otomatis.";
                    @endphp
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
                            <small class="text-muted">Kode unik: <strong class="text-dark">{{ $inv->unique_code }}</strong></small>
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
                                <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                    @if($invPhone !== '')
                                        <a href="https://wa.me/{{ $invPhone }}?text={{ urlencode($waBillText) }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-success py-0 px-2" title="Kirim Pengingat Tagihan via WhatsApp">
                                            <i class="fa-brands fa-whatsapp"></i>
                                        </a>
                                    @endif
                                    <form action="{{ route('superadmin.invoices.approve', $inv->id) }}" method="POST" class="d-inline" data-confirm="Konfirmasi invoice {{ $inv->invoice_number }} LUNAS dan aktifkan langganan usaha {{ $inv->tenant?->name }}?">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success py-0 px-2">Approve Lunas</button>
                                    </form>
                                    <form action="{{ route('superadmin.invoices.cancel', $inv->id) }}" method="POST" class="d-inline" data-confirm="Batalkan invoice {{ $inv->invoice_number }}?">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2">Batal</button>
                                    </form>
                                </div>
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

<!-- Modal Buat Invoice Manual -->
<div class="modal fade" id="createInvoiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('superadmin.invoices.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="fa-solid fa-file-invoice-dollar text-success me-2"></i>Buat Invoice Langganan Manual</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Pilih Usaha (Tenant) <span class="text-danger">*</span></label>
                    <select name="tenant_id" class="form-select" required>
                        <option value="">-- Pilih Usaha --</option>
                        @foreach($allTenants ?? [] as $t)
                            <option value="{{ $t->id }}">{{ $t->name }} ({{ $t->slug }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Pilih Paket Berbayar <span class="text-danger">*</span></label>
                    <select name="plan_id" class="form-select" required>
                        @foreach($purchasablePlans ?? [] as $p)
                            <option value="{{ $p->id }}">{{ $p->name }} — Rp{{ number_format((float)$p->price_monthly, 0, ',', '.') }}/bln · Rp{{ number_format((float)$p->price_yearly, 0, ',', '.') }}/thn</option>
                        @endforeach
                    </select>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Siklus Tagihan</label>
                        <select name="billing_cycle" class="form-select" required>
                            <option value="monthly">Bulanan (1 Bulan)</option>
                            <option value="yearly">Tahunan (1 Tahun)</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Metode Bayar</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="qris">QRIS Dinamis</option>
                            <option value="transfer">Transfer Bank</option>
                        </select>
                    </div>
                </div>
                <div class="alert alert-info small mt-3 mb-0 py-2">
                    <i class="fa-solid fa-circle-info me-1"></i>Kode unik 3 digit akan dibuat otomatis. Tagihan langsung muncul di akun usaha dan siap dikirim lewat tombol <strong>WA</strong>.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary-green">Terbitkan Invoice</button>
            </div>
        </form>
    </div>
</div>
@endsection
