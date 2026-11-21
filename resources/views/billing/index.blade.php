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

                <div class="bg-light rounded-3 p-3 border mb-0">
                    @php
                        $userPct = $userUsage['limit'] ? min(100, (int) round(($userUsage['current'] / max(1, $userUsage['limit'])) * 100)) : 0;
                        $menuPct = $menuUsage['limit'] ? min(100, (int) round(($menuUsage['current'] / max(1, $menuUsage['limit'])) * 100)) : 0;
                    @endphp
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small mb-1" style="font-variant-numeric: tabular-nums;">
                            <span class="text-muted"><i class="fa-solid fa-users me-1"></i>Akun Pengguna</span>
                            <strong>{{ $userUsage['current'] }} / {{ $userUsage['limit'] ?? 'Unlimited' }}</strong>
                        </div>
                        @if($userUsage['limit'])
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar {{ $userPct >= 100 ? 'bg-danger' : ($userPct >= 80 ? 'bg-warning' : 'bg-success') }}" style="width: {{ $userPct }}%"></div>
                            </div>
                        @endif
                    </div>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small mb-1" style="font-variant-numeric: tabular-nums;">
                            <span class="text-muted"><i class="fa-solid fa-mug-hot me-1"></i>Menu Produk</span>
                            <strong>{{ $menuUsage['current'] }} / {{ $menuUsage['limit'] ?? 'Unlimited' }}</strong>
                        </div>
                        @if($menuUsage['limit'])
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar {{ $menuPct >= 100 ? 'bg-danger' : ($menuPct >= 80 ? 'bg-warning' : 'bg-success') }}" style="width: {{ $menuPct }}%"></div>
                            </div>
                        @endif
                    </div>
                    <div class="d-flex justify-content-between small pt-1 border-top">
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
                    <form action="{{ route('billing.invoice.cancel', $activeInvoice->id) }}" method="POST" class="m-0" data-confirm="Batalkan tagihan {{ $activeInvoice->invoice_number }}? Anda dapat membuat tagihan baru kapan saja.">
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
                                <div class="d-flex align-items-center justify-content-between gap-2">
                                    <div class="fs-3 fw-bold text-success font-monospace" style="font-variant-numeric: tabular-nums;">Rp{{ number_format((float)$activeInvoice->final_amount, 0, ',', '.') }}</div>
                                    <button type="button" class="btn btn-sm btn-outline-success js-billing-copy" data-copy-value="{{ (int) round((float) $activeInvoice->final_amount) }}" title="Salin nominal persis untuk transfer">
                                        <i class="fa-regular fa-copy me-1"></i>Salin
                                    </button>
                                </div>
                                <small class="text-muted">
                                    Harga Rp{{ number_format((float)$activeInvoice->base_amount, 0, ',', '.') }} + Kode Unik <strong>{{ $activeInvoice->unique_code }}</strong>
                                    @if((float)($activeInvoice->discount_amount ?? 0) > 0)
                                        <span class="badge bg-success bg-opacity-10 text-success ms-1">
                                            <i class="fa-solid fa-ticket me-1"></i>{{ $activeInvoice->promo_code_snapshot }} (-Rp{{ number_format((float)$activeInvoice->discount_amount, 0, ',', '.') }})
                                        </span>
                                    @endif
                                </small>
                            </div>

                            @if(!empty($bankInfo['account_number']))
                                <div class="mt-3 small">
                                    <div class="fw-bold text-dark">Transfer Bank Manual:</div>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span>{{ $bankInfo['bank_name'] }} — <strong class="font-monospace">{{ $bankInfo['account_number'] }}</strong></span>
                                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 js-billing-copy" data-copy-value="{{ $bankInfo['account_number'] }}" title="Salin nomor rekening">
                                            <i class="fa-regular fa-copy me-1"></i>Salin Rek
                                        </button>
                                    </div>
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
@php
    $maxSavePct = 0;
    foreach ($plans as $pCheck) {
        $yf = (float) $pCheck->price_monthly * 12;
        if ($yf > 0 && (float) $pCheck->price_yearly < $yf) {
            $pct = (int) round((1 - ((float) $pCheck->price_yearly / $yf)) * 100);
            if ($pct > $maxSavePct) $maxSavePct = $pct;
        }
    }
    $defaultMethod = $bankInfo['has_platform_qris'] ? 'qris' : 'transfer';
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div>
        <h5 class="fw-bold m-0">Pilihan Paket Langganan {{ $platformName }}</h5>
        <small class="text-muted">Pilih siklus &amp; metode bayar sekali di atas, lalu klik paket pilihan Anda.</small>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <!-- Global Cycle Toggle -->
        <div class="kl-segmented" role="group" aria-label="Pilih Siklus Langganan">
            <button type="button" class="btn active js-billing-cycle-btn" data-cycle="monthly">Bulanan</button>
            <button type="button" class="btn js-billing-cycle-btn" data-cycle="yearly">
                Tahunan
                @if($maxSavePct > 0)
                    <span class="badge bg-warning text-dark ms-1 rounded-pill" style="font-size: 0.68rem;">Hemat {{ $maxSavePct }}%</span>
                @endif
            </button>
        </div>

        <!-- Global Payment Method Toggle -->
        @if($bankInfo['has_platform_qris'])
            <div class="kl-segmented" role="group" aria-label="Pilih Metode Pembayaran">
                <button type="button" class="btn active js-billing-method-btn" data-method="qris">
                    <i class="fa-solid fa-qrcode me-1"></i>QRIS
                </button>
                <button type="button" class="btn js-billing-method-btn" data-method="transfer">
                    <i class="fa-solid fa-building-columns me-1"></i>Transfer
                </button>
            </div>
        @endif
    </div>
</div>

<!-- Compact Promo Code Bar -->
<div class="kl-filter-toolbar p-3 mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="rounded-3 bg-success bg-opacity-10 text-success d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                <i class="fa-solid fa-ticket"></i>
            </span>
            <div>
                <div class="fw-bold small text-dark">Punya Kode Promo / Kupon Diskon?</div>
                <div class="text-muted" style="font-size: 0.76rem;" id="promoStatusMsg">Masukkan kode promo lalu klik Terapkan sebelum memilih paket.</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-grow-1 flex-md-grow-0" style="min-width: 260px;">
            <input type="text" id="billingPromoCode" class="form-control form-control-sm text-uppercase font-monospace" placeholder="cth: HEMAT20" maxlength="50">
            <button type="button" id="btnCheckPromo" class="btn btn-sm btn-outline-success fw-semibold text-nowrap">
                <i class="fa-solid fa-check me-1"></i>Terapkan
            </button>
            <button type="button" id="btnClearPromo" class="btn btn-sm btn-outline-secondary d-none" title="Hapus kode promo">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    </div>
</div>

<div class="row g-3 g-lg-4 mb-5">
    @foreach($plans as $plan)
        @php
            $isCurrentPlan = !$tenant->is_lifetime && $subscription?->plan_id === $plan->id;
            $yearlyFull = (float) $plan->price_monthly * 12;
            $planSavePct = ($yearlyFull > 0 && (float) $plan->price_yearly < $yearlyFull)
                ? (int) round((1 - ((float) $plan->price_yearly / $yearlyFull)) * 100)
                : 0;
        @endphp
        <div class="col-md-6 col-xl-{{ $plans->count() >= 3 ? '4' : '6' }}">
            <div class="kl-plan-card p-3 p-md-4 {{ $plan->is_featured ? 'is-featured' : '' }}">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div>
                        <h5 class="fw-bold text-dark m-0">{{ $plan->name }}</h5>
                        <p class="text-muted small mb-0 mt-1">{{ $plan->description }}</p>
                    </div>
                    <div class="d-flex flex-column align-items-end gap-1 flex-shrink-0">
                        @if($plan->is_featured)
                            <span class="badge bg-success rounded-pill">Rekomendasi</span>
                        @endif
                        @if($isCurrentPlan)
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill">Paket Anda</span>
                        @endif
                    </div>
                </div>

                <!-- Dynamic Price Display -->
                <div class="kl-plan-price-box my-3">
                    <div class="d-flex align-items-baseline justify-content-between flex-wrap gap-2">
                        <div>
                            <span class="fs-3 fw-bold text-dark js-billing-price"
                                  data-monthly="Rp{{ number_format((float)$plan->price_monthly, 0, ',', '.') }}"
                                  data-yearly="Rp{{ number_format((float)$plan->price_yearly, 0, ',', '.') }}">
                                Rp{{ number_format((float)$plan->price_monthly, 0, ',', '.') }}
                            </span>
                            <span class="text-muted small js-billing-cycle-label">/ bulan</span>
                        </div>
                        @if($planSavePct > 0)
                            <span class="badge bg-success bg-opacity-10 text-success rounded-pill js-billing-save-badge d-none">
                                Hemat {{ $planSavePct }}%
                            </span>
                        @endif
                    </div>
                    <small class="text-muted d-block mt-1 js-billing-alt-price"
                           data-monthly="Atau Rp{{ number_format((float)$plan->price_yearly, 0, ',', '.') }}/tahun{{ $planSavePct > 0 ? ' (hemat ' . $planSavePct . '%)' : '' }}"
                           data-yearly="Setara Rp{{ number_format(round((float)$plan->price_yearly / 12), 0, ',', '.') }}/bulan">
                        Atau Rp{{ number_format((float)$plan->price_yearly, 0, ',', '.') }}/tahun{{ $planSavePct > 0 ? ' (hemat ' . $planSavePct . '%)' : '' }}
                    </small>
                </div>

                <!-- Compact Quota Pills -->
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="kl-plan-quota-pill">
                            <span class="text-muted"><i class="fa-solid fa-users me-1"></i>Akun</span>
                            <strong class="text-dark">{{ $plan->max_users ? "Maks. {$plan->max_users}" : 'Unlimited' }}</strong>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="kl-plan-quota-pill">
                            <span class="text-muted"><i class="fa-solid fa-mug-hot me-1"></i>Menu</span>
                            <strong class="text-dark">{{ $plan->max_menus ? "Maks. {$plan->max_menus}" : 'Unlimited' }}</strong>
                        </div>
                    </div>
                </div>

                <ul class="list-unstyled small d-grid gap-1 mb-4">
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
                    <input type="hidden" name="billing_cycle" value="monthly" class="js-billing-cycle-input">
                    <input type="hidden" name="payment_method" value="{{ $defaultMethod }}" class="js-billing-method-input">
                    <input type="hidden" name="promo_code" value="" class="js-billing-promo-input">
                    <button type="submit" class="btn {{ $plan->is_featured ? 'btn-primary-green' : 'btn-outline-success' }} w-100 fw-semibold">
                        {{ $isCurrentPlan ? 'Perpanjang Paket ' . $plan->name : 'Pilih Paket ' . $plan->name }}
                    </button>
                </form>
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
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Global Cycle Toggle (Monthly / Yearly)
        const cycleBtns = document.querySelectorAll('.js-billing-cycle-btn');
        const methodBtns = document.querySelectorAll('.js-billing-method-btn');
        const priceEls = document.querySelectorAll('.js-billing-price');
        const altPriceEls = document.querySelectorAll('.js-billing-alt-price');
        const labelEls = document.querySelectorAll('.js-billing-cycle-label');
        const saveBadges = document.querySelectorAll('.js-billing-save-badge');
        const cycleInputs = document.querySelectorAll('.js-billing-cycle-input');
        const methodInputs = document.querySelectorAll('.js-billing-method-input');

        function setBillingCycle(cycle) {
            const isYearly = cycle === 'yearly';
            cycleBtns.forEach(b => b.classList.toggle('active', b.dataset.cycle === cycle));
            priceEls.forEach(el => { el.textContent = isYearly ? el.dataset.yearly : el.dataset.monthly; });
            altPriceEls.forEach(el => { el.textContent = isYearly ? el.dataset.yearly : el.dataset.monthly; });
            labelEls.forEach(el => { el.textContent = isYearly ? '/ tahun' : '/ bulan'; });
            saveBadges.forEach(el => { el.classList.toggle('d-none', !isYearly); });
            cycleInputs.forEach(inp => { inp.value = cycle; });
        }

        cycleBtns.forEach(btn => {
            btn.addEventListener('click', () => setBillingCycle(btn.dataset.cycle || 'monthly'));
        });

        methodBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const method = btn.dataset.method || 'transfer';
                methodBtns.forEach(b => b.classList.toggle('active', b.dataset.method === method));
                methodInputs.forEach(inp => { inp.value = method; });
            });
        });

        // Promo code checker
        const promoInput = document.getElementById('billingPromoCode');
        const btnCheckPromo = document.getElementById('btnCheckPromo');
        const btnClearPromo = document.getElementById('btnClearPromo');
        const promoStatusMsg = document.getElementById('promoStatusMsg');
        const hiddenPromoInputs = document.querySelectorAll('.js-billing-promo-input');

        function syncPromoCode(code) {
            hiddenPromoInputs.forEach(inp => { inp.value = code; });
        }

        promoInput?.addEventListener('input', () => {
            syncPromoCode(promoInput.value.trim().toUpperCase());
        });

        btnClearPromo?.addEventListener('click', () => {
            if (promoInput) promoInput.value = '';
            syncPromoCode('');
            btnClearPromo.classList.add('d-none');
            if (promoStatusMsg) {
                promoStatusMsg.className = 'text-muted';
                promoStatusMsg.textContent = 'Masukkan kode promo lalu klik Terapkan sebelum memilih paket.';
            }
        });

        btnCheckPromo?.addEventListener('click', async () => {
            const code = (promoInput?.value || '').trim().toUpperCase();
            if (!code) {
                if (promoStatusMsg) {
                    promoStatusMsg.className = 'text-danger fw-semibold';
                    promoStatusMsg.textContent = 'Ketik kode promo terlebih dahulu.';
                }
                return;
            }
            const origHtml = btnCheckPromo.innerHTML;
            btnCheckPromo.disabled = true;
            btnCheckPromo.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const res = await fetch("{{ route('billing.promo.check') }}", {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ code })
                });
                const data = await res.json();
                if (res.ok && data.valid) {
                    if (promoInput) promoInput.value = data.code;
                    syncPromoCode(data.code);
                    btnClearPromo?.classList.remove('d-none');
                    if (promoStatusMsg) {
                        promoStatusMsg.className = 'text-success fw-semibold';
                        promoStatusMsg.textContent = data.message;
                    }
                } else {
                    syncPromoCode('');
                    if (promoStatusMsg) {
                        promoStatusMsg.className = 'text-danger fw-semibold';
                        promoStatusMsg.textContent = data.message || 'Kode promo tidak valid.';
                    }
                }
            } catch (err) {
                if (promoStatusMsg) {
                    promoStatusMsg.className = 'text-danger fw-semibold';
                    promoStatusMsg.textContent = 'Gagal memeriksa kode promo. Coba lagi.';
                }
            } finally {
                btnCheckPromo.disabled = false;
                btnCheckPromo.innerHTML = origHtml;
            }
        });
    });
</script>
@if($activeInvoice)
@if(!empty($activeInvoice->qris_payload))
<script src="{{ asset('assets/js/qrcode.min.js') }}"></script>
@endif
<script>
    document.addEventListener('DOMContentLoaded', function() {
        @if(!empty($activeInvoice->qris_payload))
        const container = document.getElementById('invoiceQrisCanvas');
        if (container && window.QRCode) {
            new QRCode(container, {
                text: @json($activeInvoice->qris_payload),
                width: 170,
                height: 170,
                correctLevel: QRCode.CorrectLevel.M
            });
        }
        @endif

        // Copy buttons (Nominal & Account Number) via Modern Clipboard API + fallback
        document.querySelectorAll('.js-billing-copy').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const val = btn.getAttribute('data-copy-value') || '';
                if (!val) return;
                let ok = false;
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    try {
                        await navigator.clipboard.writeText(val);
                        ok = true;
                    } catch (e) {}
                }
                if (!ok) {
                    const tmp = document.createElement('textarea');
                    tmp.value = val;
                    tmp.style.position = 'fixed';
                    tmp.style.opacity = '0';
                    document.body.appendChild(tmp);
                    tmp.select();
                    document.execCommand('copy');
                    tmp.remove();
                }
                const orig = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-check me-1"></i>Tersalin';
                setTimeout(() => { btn.innerHTML = orig; }, 1400);
            });
        });

        // Auto-poll invoice payment status every 5 seconds for BOTH QRIS and Bank Transfer
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
