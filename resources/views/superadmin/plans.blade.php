@extends('layouts.app')

@section('title', 'Kelola Paket & Harga')
@section('page_title', 'Konfigurasi Paket Langganan')

@section('breadcrumbs')
    <li class="breadcrumb-item active">Paket &amp; Harga</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h5 class="fw-bold text-dark m-0">Daftar Paket Langganan ({{ $plans->count() }})</h5>
        <p class="text-muted small m-0">Bandingkan harga, kuota, dan fitur paket secara ringkas. Klik <strong>Edit Paket</strong> untuk mengubah konfigurasi.</p>
    </div>
    <button type="button" class="btn btn-sm btn-primary-green shadow-sm" data-bs-toggle="modal" data-bs-target="#createPlanModal">
        <i class="fa-solid fa-plus me-1"></i>Tambah Paket Baru
    </button>
</div>

<div class="row g-3 g-lg-4">
    @foreach($plans as $plan)
        @php
            $tenantCount = $planUsageCounts[$plan->id] ?? 0;
            $yearlyFull = (float) $plan->price_monthly * 12;
            $savePct = ($yearlyFull > 0 && (float) $plan->price_yearly < $yearlyFull)
                ? (int) round((1 - ((float) $plan->price_yearly / $yearlyFull)) * 100)
                : 0;
            $featureList = [
                ['key' => 'qris_dynamic', 'label' => 'QRIS Dinamis'],
                ['key' => 'offline_sync', 'label' => 'Offline Sync'],
                ['key' => 'expenses', 'label' => 'Kas Keluar'],
                ['key' => 'pdf_export', 'label' => 'Laporan PDF'],
            ];
        @endphp
        <div class="col-md-6 col-xl-4">
            <div class="kl-plan-card p-3 p-md-4 {{ $plan->is_featured ? 'is-featured' : '' }}">
                <!-- Header: Name + Badges -->
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div class="min-w-0">
                        <div class="d-flex align-items-center flex-wrap gap-1">
                            <h5 class="fw-bold text-dark m-0">{{ $plan->name }}</h5>
                            @if($plan->is_featured)
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill" style="font-size: 0.68rem;">
                                    <i class="fa-solid fa-star me-1"></i>Populer
                                </span>
                            @endif
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-1 small text-muted">
                            <code style="font-size: 0.74rem;">{{ $plan->slug }}</code>
                            <span>·</span>
                            <span><i class="fa-solid fa-store me-1"></i>{{ $tenantCount }} usaha</span>
                        </div>
                    </div>
                    <span class="badge rounded-pill {{ $plan->is_active ? 'bg-success' : 'bg-secondary' }}">
                        {{ $plan->is_active ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </div>

                <p class="text-muted small mb-3" style="min-height: 2.3rem;">
                    {{ $plan->description ?: 'Paket langganan layanan kasir POS & pembukuan.' }}
                </p>

                <!-- Compact Price Block -->
                <div class="kl-plan-price-box mb-3">
                    <div class="d-flex align-items-baseline justify-content-between gap-2">
                        <div>
                            <span class="fs-4 fw-bold text-dark">Rp{{ number_format((float)$plan->price_monthly, 0, ',', '.') }}</span>
                            <span class="text-muted small">/bln</span>
                        </div>
                        @if($savePct > 0)
                            <span class="badge bg-success bg-opacity-10 text-success rounded-pill" style="font-size: 0.7rem;">
                                Hemat {{ $savePct }}%
                            </span>
                        @endif
                    </div>
                    <div class="small text-muted mt-1">
                        Tahunan: <strong class="text-dark">Rp{{ number_format((float)$plan->price_yearly, 0, ',', '.') }}</strong>/thn
                    </div>
                </div>

                <!-- Quota Pills -->
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="kl-plan-quota-pill">
                            <span class="text-muted"><i class="fa-solid fa-users me-1"></i>User</span>
                            <strong class="text-dark">{{ $plan->max_users ?: '∞' }}</strong>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="kl-plan-quota-pill">
                            <span class="text-muted"><i class="fa-solid fa-mug-hot me-1"></i>Menu</span>
                            <strong class="text-dark">{{ $plan->max_menus ?: '∞' }}</strong>
                        </div>
                    </div>
                </div>

                <!-- Feature Matrix (2x2 Chips) -->
                <div class="kl-plan-feature-grid mb-3">
                    @foreach($featureList as $feat)
                        @php $enabled = $plan->hasFeature($feat['key']); @endphp
                        <div class="kl-plan-feature-chip {{ $enabled ? 'is-enabled' : 'is-disabled' }}" title="{{ $feat['label'] }}: {{ $enabled ? 'Aktif' : 'Tidak Termasuk' }}">
                            <i class="fa-solid {{ $enabled ? 'fa-circle-check text-success' : 'fa-circle-xmark' }}"></i>
                            <span class="text-truncate">{{ $feat['label'] }}</span>
                        </div>
                    @endforeach
                </div>

                <!-- Action Footer -->
                <div class="mt-auto pt-2 border-top d-flex gap-2">
                    <button type="button" class="btn btn-sm {{ $plan->is_featured ? 'btn-primary-green' : 'btn-outline-success' }} w-100 fw-semibold" data-bs-toggle="modal" data-bs-target="#editPlanModal{{ $plan->id }}">
                        <i class="fa-solid fa-sliders me-1"></i>Edit Paket {{ $plan->name }}
                    </button>
                </div>
            </div>
        </div>
    @endforeach
</div>

<!-- Modal Edit Paket Per Plan -->
@foreach($plans as $plan)
    @php
        $yearlyFull = (float) $plan->price_monthly * 12;
        $savePct = ($yearlyFull > 0 && (float) $plan->price_yearly < $yearlyFull)
            ? (int) round((1 - ((float) $plan->price_yearly / $yearlyFull)) * 100)
            : 0;
    @endphp
    <div class="modal fade" id="editPlanModal{{ $plan->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('superadmin.plans.update', $plan->id) }}" method="POST" class="modal-content plan-config-form">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">
                        <i class="fa-solid fa-sliders text-success me-2"></i>Edit Paket: {{ $plan->name }} <code class="ms-1 small">{{ $plan->slug }}</code>
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Paket <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-sm" value="{{ $plan->name }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Deskripsi Singkat</label>
                        <input type="text" name="description" class="form-control form-control-sm" value="{{ $plan->description }}">
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Harga Bulanan (Rp)</label>
                            <input type="number" name="price_monthly" class="form-control form-control-sm js-price-monthly" inputmode="numeric" value="{{ (int)$plan->price_monthly }}" min="0" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Harga Tahunan (Rp)</label>
                            <input type="number" name="price_yearly" class="form-control form-control-sm js-price-yearly" inputmode="numeric" value="{{ (int)$plan->price_yearly }}" min="0" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <span class="badge bg-success bg-opacity-10 text-success js-yearly-savings {{ $savePct > 0 ? '' : 'd-none' }}">
                            <i class="fa-solid fa-tag me-1"></i>Hemat {{ $savePct }}% untuk langganan tahunan
                        </span>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Maks. User</label>
                            <input type="number" name="max_users" class="form-control form-control-sm" inputmode="numeric" value="{{ $plan->max_users }}" placeholder="Kosong = Unlimited">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Maks. Menu</label>
                            <input type="number" name="max_menus" class="form-control form-control-sm" inputmode="numeric" value="{{ $plan->max_menus }}" placeholder="Kosong = Unlimited">
                        </div>
                    </div>

                    <div class="bg-light p-3 rounded-3 border small">
                        <div class="fw-bold mb-2">Fitur &amp; Status Paket:</div>
                        <div class="row g-1">
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="feature_qris_dynamic" value="1" id="qris{{ $plan->id }}" {{ $plan->hasFeature('qris_dynamic') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="qris{{ $plan->id }}">QRIS Dinamis</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="feature_offline_sync" value="1" id="off{{ $plan->id }}" {{ $plan->hasFeature('offline_sync') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="off{{ $plan->id }}">Offline Sync</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="feature_expenses" value="1" id="exp{{ $plan->id }}" {{ $plan->hasFeature('expenses') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="exp{{ $plan->id }}">Pengeluaran Kas</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="feature_pdf_export" value="1" id="pdf{{ $plan->id }}" {{ $plan->hasFeature('pdf_export') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="pdf{{ $plan->id }}">Export PDF</label>
                                </div>
                            </div>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex flex-wrap gap-3">
                            <div class="form-check m-0">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="act{{ $plan->id }}" {{ $plan->is_active ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="act{{ $plan->id }}">Paket Aktif</label>
                            </div>
                            <div class="form-check m-0">
                                <input class="form-check-input" type="checkbox" name="is_featured" value="1" id="feat{{ $plan->id }}" {{ $plan->is_featured ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="feat{{ $plan->id }}">Tandai "Populer"</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary-green">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
@endforeach

<!-- Modal Tambah Paket Langganan Baru -->
<div class="modal fade" id="createPlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('superadmin.plans.store') }}" method="POST" class="modal-content plan-config-form">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="fa-solid fa-layer-group text-success me-2"></i>Tambah Paket Langganan Baru</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Nama Paket <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control form-control-sm" placeholder="cth: Business / Enterprise" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Deskripsi Singkat</label>
                    <input type="text" name="description" class="form-control form-control-sm" placeholder="cth: Untuk usaha dengan banyak kasir & volume tinggi">
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Harga Bulanan (Rp) <span class="text-danger">*</span></label>
                        <input type="number" name="price_monthly" class="form-control form-control-sm js-price-monthly" inputmode="numeric" min="0" value="149000" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Harga Tahunan (Rp) <span class="text-danger">*</span></label>
                        <input type="number" name="price_yearly" class="form-control form-control-sm js-price-yearly" inputmode="numeric" min="0" value="1490000" required>
                    </div>
                </div>
                <div class="mb-3">
                    <span class="badge bg-success bg-opacity-10 text-success js-yearly-savings">
                        <i class="fa-solid fa-tag me-1"></i>Hemat 17% untuk langganan tahunan
                    </span>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Maks. User</label>
                        <input type="number" name="max_users" class="form-control form-control-sm" inputmode="numeric" min="1" placeholder="Kosong = Unlimited">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Maks. Menu</label>
                        <input type="number" name="max_menus" class="form-control form-control-sm" inputmode="numeric" min="1" placeholder="Kosong = Unlimited">
                    </div>
                </div>
                <div class="bg-light p-3 rounded-3 border small">
                    <div class="row g-1">
                        <div class="col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="feature_qris_dynamic" value="1" id="newPlanQris" checked>
                                <label class="form-check-label" for="newPlanQris">QRIS Dinamis</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="feature_offline_sync" value="1" id="newPlanOffline" checked>
                                <label class="form-check-label" for="newPlanOffline">Offline Sync</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="feature_expenses" value="1" id="newPlanExp" checked>
                                <label class="form-check-label" for="newPlanExp">Pengeluaran Kas</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="feature_pdf_export" value="1" id="newPlanPdf" checked>
                                <label class="form-check-label" for="newPlanPdf">Export PDF</label>
                            </div>
                        </div>
                    </div>
                    <hr class="my-2">
                    <div class="form-check m-0">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="newPlanActive" checked>
                        <label class="form-check-label fw-semibold" for="newPlanActive">Langsung Aktifkan Paket</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary-green">Simpan Paket Baru</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.querySelectorAll('.plan-config-form').forEach(function (form) {
        const monthlyEl = form.querySelector('.js-price-monthly');
        const yearlyEl = form.querySelector('.js-price-yearly');
        const badgeEl = form.querySelector('.js-yearly-savings');
        if (!monthlyEl || !yearlyEl || !badgeEl) return;

        function recalc() {
            const m = parseFloat(monthlyEl.value) || 0;
            const y = parseFloat(yearlyEl.value) || 0;
            const full = m * 12;
            if (full > 0 && y > 0 && y < full) {
                const pct = Math.round((1 - (y / full)) * 100);
                badgeEl.innerHTML = `<i class="fa-solid fa-tag me-1"></i>Hemat ${pct}% untuk langganan tahunan`;
                badgeEl.classList.remove('d-none');
            } else {
                badgeEl.classList.add('d-none');
            }
        }

        monthlyEl.addEventListener('input', recalc);
        yearlyEl.addEventListener('input', recalc);
    });
</script>
@endsection
