@extends('layouts.app')

@section('title', 'Kelola Paket & Harga')
@section('page_title', 'Konfigurasi Paket Langganan')

@section('breadcrumbs')
    <li class="breadcrumb-item active">Paket &amp; Harga</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <p class="text-muted small m-0">Atur harga bulanan/tahunan, kuota user &amp; menu, serta fitur per paket langganan.</p>
    <button type="button" class="btn btn-sm btn-primary-green" data-bs-toggle="modal" data-bs-target="#createPlanModal">
        <i class="fa-solid fa-plus me-1"></i>Tambah Paket Baru
    </button>
</div>

<div class="row g-4">
    @foreach($plans as $plan)
        @php
            $tenantCount = $planUsageCounts[$plan->id] ?? 0;
            $yearlyFull = (float) $plan->price_monthly * 12;
            $savePct = ($yearlyFull > 0 && (float) $plan->price_yearly < $yearlyFull)
                ? (int) round((1 - ((float) $plan->price_yearly / $yearlyFull)) * 100)
                : 0;
        @endphp
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center gap-2">
                    <div>
                        <h6 class="fw-bold m-0">{{ $plan->name }} <code class="ms-1 small">{{ $plan->slug }}</code></h6>
                        <small class="text-muted"><i class="fa-solid fa-store me-1"></i>{{ $tenantCount }} usaha menggunakan paket ini</small>
                    </div>
                    <span class="badge {{ $plan->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $plan->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('superadmin.plans.update', $plan->id) }}" method="POST" class="plan-config-form">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Nama Paket</label>
                            <input type="text" name="name" class="form-control form-control-sm" value="{{ $plan->name }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Deskripsi</label>
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

                        <div class="bg-light p-3 rounded-3 border mb-3 small">
                            <div class="fw-bold mb-2">Fitur Paket:</div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="feature_qris_dynamic" value="1" id="qris{{ $plan->id }}" {{ $plan->hasFeature('qris_dynamic') ? 'checked' : '' }}>
                                <label class="form-check-label" for="qris{{ $plan->id }}">QRIS Dinamis + Auto-Verify</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="feature_offline_sync" value="1" id="off{{ $plan->id }}" {{ $plan->hasFeature('offline_sync') ? 'checked' : '' }}>
                                <label class="form-check-label" for="off{{ $plan->id }}">Offline POS + Auto-Sync</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="feature_expenses" value="1" id="exp{{ $plan->id }}" {{ $plan->hasFeature('expenses') ? 'checked' : '' }}>
                                <label class="form-check-label" for="exp{{ $plan->id }}">Catatan Pengeluaran Kas</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="feature_pdf_export" value="1" id="pdf{{ $plan->id }}" {{ $plan->hasFeature('pdf_export') ? 'checked' : '' }}>
                                <label class="form-check-label" for="pdf{{ $plan->id }}">Export Laporan PDF</label>
                            </div>
                            <hr class="my-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="act{{ $plan->id }}" {{ $plan->is_active ? 'checked' : '' }}>
                                <label class="form-check-label" for="act{{ $plan->id }}">Paket Aktif</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_featured" value="1" id="feat{{ $plan->id }}" {{ $plan->is_featured ? 'checked' : '' }}>
                                <label class="form-check-label" for="feat{{ $plan->id }}">Tandai "Paling Populer"</label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary-green btn-sm w-100">Simpan Paket {{ $plan->name }}</button>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
</div>

<!-- Modal Tambah Paket Langganan Baru -->
<div class="modal fade" id="createPlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('superadmin.plans.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="fa-solid fa-layer-group text-success me-2"></i>Tambah Paket Langganan Baru</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Nama Paket <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="cth: Business / Enterprise" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Deskripsi Singkat</label>
                    <input type="text" name="description" class="form-control" placeholder="cth: Untuk usaha dengan banyak kasir & volume tinggi">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Harga Bulanan (Rp) <span class="text-danger">*</span></label>
                        <input type="number" name="price_monthly" class="form-control" inputmode="numeric" min="0" value="149000" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Harga Tahunan (Rp) <span class="text-danger">*</span></label>
                        <input type="number" name="price_yearly" class="form-control" inputmode="numeric" min="0" value="1490000" required>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Maks. User</label>
                        <input type="number" name="max_users" class="form-control" inputmode="numeric" min="1" placeholder="Kosong = Unlimited">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Maks. Menu</label>
                        <input type="number" name="max_menus" class="form-control" inputmode="numeric" min="1" placeholder="Kosong = Unlimited">
                    </div>
                </div>
                <div class="bg-light p-3 rounded-3 border small">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="feature_qris_dynamic" value="1" id="newPlanQris" checked>
                        <label class="form-check-label" for="newPlanQris">QRIS Dinamis + Auto-Verify</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="feature_offline_sync" value="1" id="newPlanOffline" checked>
                        <label class="form-check-label" for="newPlanOffline">Offline POS + Auto-Sync</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="feature_expenses" value="1" id="newPlanExp" checked>
                        <label class="form-check-label" for="newPlanExp">Catatan Pengeluaran Kas</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="feature_pdf_export" value="1" id="newPlanPdf" checked>
                        <label class="form-check-label" for="newPlanPdf">Export Laporan PDF</label>
                    </div>
                    <hr class="my-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="newPlanActive" checked>
                        <label class="form-check-label" for="newPlanActive">Langsung Aktifkan Paket</label>
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
