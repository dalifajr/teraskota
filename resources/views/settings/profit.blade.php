@extends('layouts.app')

@section('title', 'Pengaturan Keuntungan')
@section('page_title', 'Pengaturan Keuntungan Global')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Pengaturan Keuntungan</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card premium-card">
            <div class="premium-header">
                <h5 class="m-0 fw-semibold text-dark"><i class="fa-solid fa-percent text-success me-2"></i> Margin Keuntungan Global</h5>
            </div>
            <div class="card-body p-4">
                
                @if ($errors->any())
                    <div class="alert alert-danger border-0 rounded-3 mb-4">
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="alert alert-info border-0 rounded-3 mb-4 text-dark" style="background-color: #e0f2fe;">
                    <div class="d-flex align-items-start">
                        <i class="fa-solid fa-circle-info text-info fs-4 me-3 mt-1"></i>
                        <div>
                            <strong>Informasi:</strong>
                            <p class="m-0 small text-muted">Persentase keuntungan global ini digunakan secara otomatis oleh semua menu produk yang tidak diatur secara khusus. Nilai default awal sistem adalah <strong>30%</strong>.</p>
                        </div>
                    </div>
                </div>

                <form action="{{ route('settings.profit.update') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label for="global_profit_percentage" class="form-label fw-semibold text-dark">Persentase Keuntungan Global (%) <span class="text-danger">*</span></label>
                        <div class="input-group" style="max-width: 250px;">
                            <input type="number" name="global_profit_percentage" id="global_profit_percentage" class="form-control fw-bold" inputmode="decimal" min="0" max="100" step="0.1" value="{{ old('global_profit_percentage', $globalProfitPercentage) }}" placeholder="Contoh: 30" required>
                            <span class="input-group-text fw-bold">%</span>
                        </div>
                        <small class="text-muted d-block mt-1">Masukkan nilai antara 0% sampai 100%.</small>
                    </div>

                    <!-- Live Margin Simulation Box -->
                    <div class="p-3 rounded-3 bg-light border mb-4 small" style="font-variant-numeric: tabular-nums;">
                        <div class="fw-bold text-dark mb-2"><i class="fa-solid fa-calculator text-success me-1"></i>Simulasi Perhitungan (Contoh Harga Jual Rp20.000)</div>
                        <div class="row g-2 text-center">
                            <div class="col-4">
                                <div class="bg-white rounded-3 p-2 border">
                                    <div class="text-muted" style="font-size: 0.72rem;">Harga Jual</div>
                                    <strong class="text-dark">Rp20.000</strong>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="bg-white rounded-3 p-2 border">
                                    <div class="text-muted" style="font-size: 0.72rem;">Estimasi Modal</div>
                                    <strong class="text-danger" id="simCostText">Rp14.000</strong>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="bg-white rounded-3 p-2 border">
                                    <div class="text-muted" style="font-size: 0.72rem;">Laba Kotor</div>
                                    <strong class="text-success" id="simProfitText">Rp6.000</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary px-4"><i class="fa-solid fa-xmark me-1"></i> Batal</a>
                        <button type="submit" class="btn btn-primary-green px-4"><i class="fa-solid fa-save me-1"></i> Simpan Perubahan</button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    (function () {
        const pctInput = document.getElementById('global_profit_percentage');
        const simCost = document.getElementById('simCostText');
        const simProfit = document.getElementById('simProfitText');
        const samplePrice = 20000;

        function updateSim() {
            const pct = Math.min(100, Math.max(0, parseFloat(pctInput?.value) || 0));
            const profit = Math.round(samplePrice * (pct / 100));
            const cost = samplePrice - profit;
            const fmt = (n) => 'Rp' + new Intl.NumberFormat('id-ID').format(n);
            if (simProfit) simProfit.textContent = fmt(profit);
            if (simCost) simCost.textContent = fmt(cost);
        }

        pctInput?.addEventListener('input', updateSim);
        updateSim();
    })();
</script>
@endsection
