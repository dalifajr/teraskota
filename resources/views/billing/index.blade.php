@extends('layouts.app')

@section('title', 'Langganan & Paket')
@section('page_title', 'Langganan & Tagihan Paket')

@section('breadcrumbs')
    <li class="breadcrumb-item active">Langganan &amp; Paket</li>
@endsection

@section('content')
<div class="row g-4 mb-4">
    <!-- Status Paket Aktif -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <small class="text-uppercase text-muted fw-bold">Paket Saat Ini</small>
                        <h3 class="fw-bold text-dark mt-1 mb-0">
                            {{ $tenant->is_lifetime ? 'VIP Lifetime (Pro)' : ($subscription?->plan?->name ?? 'Belum Aktif') }}
                        </h3>
                    </div>
                    @php
                        $badgeMap = [
                            'lifetime' => ['bg-success', 'VIP Lifetime'],
                            'active' => ['bg-success', 'Aktif'],
                            'trial' => ['bg-info text-dark', 'Masa Trial'],
                            'grace' => ['bg-warning text-dark', 'Masa Tenggang'],
                            'expired' => ['bg-danger', 'Kedaluwarsa'],
                            'suspended' => ['bg-dark', 'Disuspend'],
                        ];
                        [$bClass, $bLabel] = $badgeMap[$state] ?? ['bg-secondary', strtoupper($state)];
                    @endphp
                    <span class="badge {{ $bClass }} px-3 py-2 rounded-pill">{{ $bLabel }}</span>
                </div>

                @if($tenant->is_lifetime)
                    <p class="text-success small mb-3"><i class="fa-solid fa-infinity me-1"></i>Akses seluruh fitur Pro aktif selamanya tanpa batas waktu.</p>
                @elseif($subscription?->ends_at)
                    <p class="text-muted small mb-3">
                        Berlaku hingga: <strong class="text-dark">{{ $subscription->ends_at->translatedFormat('d F Y, H:i') }} WIB</strong>
                        @if($daysRemaining !== null)
                            ({{ $daysRemaining >= 0 ? "sisa {$daysRemaining} hari" : 'lewat ' . abs($daysRemaining) . ' hari' }})
                        @endif
                    </p>
                @endif

                <div class="bg-light rounded-3 p-3 border mb-3">
                    <div class="d-flex justify-content-between small mb-2">
                        <span class="text-muted"><i class="fa-solid fa-users me-1"></i>Akun Pengguna</span>
                        <strong>{{ $userUsage['current'] }} / {{ $userUsage['limit'] ?? 'Unlimited' }}</strong>
                    </div>
                    <div class="d-flex justify-content-between small mb-2">
                        <span class="text-muted"><i class="fa-solid fa-mug-hot me-1"></i>Menu Produk</span>
                        <strong>{{ $menuUsage['current'] }} / {{ $menuUsage['limit'] ?? 'Unlimited' }}</strong>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span class="text-muted"><i class="fa-solid fa-qrcode me-1"></i>QRIS Auto-Verify</span>
                        <strong class="{{ $tenant->hasFeature('qris_dynamic') ? 'text-success' : 'text-muted' }}">
                            {{ $tenant->hasFeature('qris_dynamic') ? 'Aktif' : 'Terkunci (Paket Pro)' }}
                        </strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tagihan Aktif Menunggu Pembayaran -->
    <div class="col-lg-7">
        @if($activeInvoice)
            <div class="card border-2 border-warning shadow-sm rounded-4 h-100" id="activeInvoiceCard" data-invoice-id="{{ $activeInvoice->id }}">
                <div class="card-header bg-warning bg-opacity-10 py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold m-0 text-dark"><i class="fa-solid fa-clock text-warning me-2"></i>Menunggu Pembayaran · {{ $activeInvoice->invoice_number }}</h6>
                    <form action="{{ route('billing.invoice.cancel', $activeInvoice->id) }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-danger py-0">Batalkan</button>
                    </form>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-7">
                            <div class="small text-muted">Paket: <strong>{{ $activeInvoice->plan->name }} ({{ $activeInvoice->billing_cycle === 'yearly' ? 'Tahunan' : 'Bulanan' }})</strong></div>
                            <div class="mt-2 p-3 bg-light rounded-3 border">
                                <small class="text-muted d-block">Total Transfer / Scan (Harus PERSIS hingga 3 digit akhir!):</small>
                                <div class="fs-3 fw-bold text-success font-monospace">Rp{{ number_format((float)$activeInvoice->final_amount, 0, ',', '.') }}</div>
                                <small class="text-muted">Harga Rp{{ number_format((float)$activeInvoice->base_amount, 0, ',', '.') }} + Kode Unik <strong>{{ $activeInvoice->unique_code }}</strong></small>
                            </div>

                            @if(!empty($bankInfo['account_number']))
                                <div class="mt-3 small">
                                    <div class="fw-bold text-dark">Transfer Bank Manual:</div>
                                    <div>{{ $bankInfo['bank_name'] }} — <strong class="font-monospace">{{ $bankInfo['account_number'] }}</strong></div>
                                    <div class="text-muted">a.n. {{ $bankInfo['account_name'] }}</div>
                                </div>
                            @endif

                            @if(!empty($bankInfo['support_whatsapp']))
                                @php
                                    $waNumber = preg_replace('/[^0-9]/', '', $bankInfo['support_whatsapp']);
                                    if (str_starts_with($waNumber, '0')) { $waNumber = '62' . substr($waNumber, 1); }
                                    $waText = urlencode("Halo Admin {$platformName}, saya sudah membayar tagihan {$activeInvoice->invoice_number} untuk usaha {$tenant->name} sebesar Rp" . number_format((float)$activeInvoice->final_amount, 0, ',', '.'));
                                @endphp
                                <a href="https://wa.me/{{ $waNumber }}?text={{ $waText }}" target="_blank" class="btn btn-sm btn-success mt-3">
                                    <i class="fa-brands fa-whatsapp me-1"></i>Konfirmasi Pembayaran via WhatsApp
                                </a>
                            @endif
                        </div>

                        <div class="col-md-5 text-center">
                            @if(!empty($activeInvoice->qris_payload))
                                <div class="p-2 bg-white border rounded-3 d-inline-block shadow-sm">
                                    <div id="invoiceQrisCanvas"></div>
                                </div>
                                <div class="small text-success fw-semibold mt-2"><i class="fa-solid fa-bolt me-1"></i>Scan QRIS (Auto-Aktif)</div>
                            @else
                                <div class="p-4 bg-light rounded-3 border text-muted small">
                                    <i class="fa-solid fa-building-columns fs-2 mb-2 d-block text-success"></i>
                                    Transfer sesuai nominal persis di samping. Paket aktif otomatis/setelah verifikasi admin.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4 d-flex flex-column justify-content-center">
                    <h5 class="fw-bold text-dark mb-2"><i class="fa-solid fa-shield-check text-success me-2"></i>Upgrade atau Perpanjang Paket</h5>
                    <p class="text-muted small mb-0">
                        Pilih paket <strong>Starter</strong> atau <strong>Pro</strong> di bawah ini untuk membuat tagihan perpanjangan. Pembayaran melalui QRIS Dinamis diverifikasi otomatis oleh sistem dalam hitungan detik, atau melalui Transfer Bank dengan kode unik.
                    </p>
                </div>
            </div>
        @endif
    </div>
</div>

<!-- Pilihan Paket -->
<h5 class="fw-bold mb-3">Pilihan Paket Langganan {{ $platformName }}</h5>
<div class="row g-4 mb-5">
    @foreach($plans as $plan)
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 {{ $plan->is_featured ? 'border border-2 border-success' : '' }}">
                <div class="card-body p-4 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="fw-bold m-0">{{ $plan->name }}</h5>
                        @if($plan->is_featured)
                            <span class="badge bg-success rounded-pill">Rekomendasi</span>
                        @endif
                    </div>
                    <p class="text-muted small mb-3">{{ $plan->description }}</p>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="p-2 bg-light rounded-3 border text-center">
                                <small class="text-muted d-block">Bulanan</small>
                                <strong class="text-dark">Rp{{ number_format((float)$plan->price_monthly, 0, ',', '.') }}</strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 bg-success bg-opacity-10 rounded-3 border border-success border-opacity-25 text-center">
                                <small class="text-success d-block fw-semibold">Tahunan (Hemat 2 Bln)</small>
                                <strong class="text-success">Rp{{ number_format((float)$plan->price_yearly, 0, ',', '.') }}</strong>
                            </div>
                        </div>
                    </div>

                    <ul class="list-unstyled small d-grid gap-1 mb-4">
                        <li><i class="fa-solid fa-check text-success me-2"></i>{{ $plan->max_users ? "Hingga {$plan->max_users} akun pengguna" : 'Pengguna unlimited' }}</li>
                        <li><i class="fa-solid fa-check text-success me-2"></i>{{ $plan->max_menus ? "Hingga {$plan->max_menus} menu" : 'Menu tanpa batas' }}</li>
                        <li><i class="fa-solid fa-check text-success me-2"></i>Mode Offline POS + Auto-Sync</li>
                        <li><i class="fa-solid fa-check text-success me-2"></i>Pengeluaran Kas, Laba Bersih &amp; PDF</li>
                        <li>
                            @if($plan->hasFeature('qris_dynamic'))
                                <i class="fa-solid fa-star text-warning me-2"></i><strong class="text-success">QRIS Dinamis + Auto-Verify Listener</strong>
                            @else
                                <i class="fa-solid fa-xmark text-muted me-2"></i><span class="text-muted">Tanpa QRIS Dinamis Auto-Verify</span>
                            @endif
                        </li>
                    </ul>

                    <form action="{{ route('billing.subscribe') }}" method="POST" class="mt-auto">
                        @csrf
                        <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <select name="billing_cycle" class="form-select form-select-sm">
                                    <option value="monthly">1 Bulan (Rp{{ number_format((float)$plan->price_monthly, 0, ',', '.') }})</option>
                                    <option value="yearly">1 Tahun (Rp{{ number_format((float)$plan->price_yearly, 0, ',', '.') }})</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <select name="payment_method" class="form-select form-select-sm">
                                    @if($bankInfo['has_platform_qris'])
                                        <option value="qris">QRIS Dinamis</option>
                                    @endif
                                    <option value="transfer">Transfer Bank</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn {{ $plan->is_featured ? 'btn-primary-green' : 'btn-outline-success' }} w-100 fw-semibold">
                            Pilih Paket {{ $plan->name }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
</div>

<!-- Riwayat Tagihan -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-white py-3 border-bottom">
        <h6 class="fw-bold m-0"><i class="fa-solid fa-file-invoice text-success me-2"></i>Riwayat Tagihan Langganan</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>No. Invoice</th>
                    <th>Paket</th>
                    <th>Nominal</th>
                    <th>Metode</th>
                    <th>Status</th>
                    <th>Tanggal</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $inv)
                    <tr>
                        <td class="fw-bold font-monospace">{{ $inv->invoice_number }}</td>
                        <td>{{ $inv->plan?->name ?? '-' }} ({{ $inv->billing_cycle === 'yearly' ? 'Tahunan' : 'Bulanan' }})</td>
                        <td class="fw-bold">Rp{{ number_format((float)$inv->final_amount, 0, ',', '.') }}</td>
                        <td class="text-uppercase">{{ $inv->payment_method }}</td>
                        <td>
                            @if($inv->status === 'paid')
                                <span class="badge bg-success">Lunas</span>
                            @elseif($inv->status === 'unpaid')
                                <span class="badge bg-warning text-dark">Menunggu Bayar</span>
                            @else
                                <span class="badge bg-secondary">{{ ucfirst($inv->status) }}</span>
                            @endif
                        </td>
                        <td>{{ $inv->created_at->translatedFormat('d M Y, H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada riwayat tagihan langganan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('scripts')
@if($activeInvoice && !empty($activeInvoice->qris_payload))
<script src="{{ asset('assets/js/qrcode.min.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('invoiceQrisCanvas');
        if (container && window.QRCode) {
            new QRCode(container, {
                text: @json($activeInvoice->qris_payload),
                width: 170,
                height: 170,
                correctLevel: QRCode.CorrectLevel.M
            });
        }

        // Auto-poll invoice payment status every 5 seconds
        setInterval(async () => {
            try {
                const res = await fetch("{{ route('billing.invoice.status', $activeInvoice->id) }}", {
                    headers: { 'Accept': 'application/json' }
                });
                if (res.ok) {
                    const data = await res.json();
                    if (data.status === 'paid') {
                        window.location.reload();
                    }
                }
            } catch (e) {}
        }, 5000);
    });
</script>
@endif
@endsection
