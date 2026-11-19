@extends('layouts.app')

@section('title', 'Pengaturan Platform & Pembayaran')
@section('page_title', 'Pengaturan Platform ' . ($platformName ?? 'KasirLoka'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Pengaturan Platform</li>
@endsection

@section('content')
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold m-0"><i class="fa-solid fa-sliders text-success me-2"></i>Identitas Platform &amp; Rekening Pembayaran</h6>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('superadmin.settings.update') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nama Brand Platform <span class="text-danger">*</span></label>
                            <input type="text" name="platform_name" class="form-control" value="{{ old('platform_name', $settings['platform_name']) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">No. WhatsApp Support</label>
                            <input type="text" name="support_whatsapp" class="form-control" placeholder="0812xxxxxxxx" value="{{ old('support_whatsapp', $settings['support_whatsapp']) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Tagline Landing Page</label>
                            <input type="text" name="platform_tagline" class="form-control" value="{{ old('platform_tagline', $settings['platform_tagline']) }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Durasi Free Trial (Hari)</label>
                            <input type="number" name="trial_days" class="form-control" min="1" max="90" value="{{ old('trial_days', $settings['trial_days']) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Masa Tenggang / Grace Period (Hari)</label>
                            <input type="number" name="grace_days" class="form-control" min="0" max="30" value="{{ old('grace_days', $settings['grace_days']) }}" required>
                        </div>

                        <div class="col-12 mt-3">
                            <small class="text-uppercase fw-bold text-success d-block border-bottom pb-1">Rekening Transfer Bank Manual</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Nama Bank</label>
                            <input type="text" name="bank_name" class="form-control" placeholder="BCA / BRI / Mandiri" value="{{ old('bank_name', $settings['bank_name']) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Nomor Rekening</label>
                            <input type="text" name="bank_account_number" class="form-control" value="{{ old('bank_account_number', $settings['bank_account_number']) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Atas Nama</label>
                            <input type="text" name="bank_account_name" class="form-control" value="{{ old('bank_account_name', $settings['bank_account_name']) }}">
                        </div>

                        <div class="col-12 mt-3">
                            <small class="text-uppercase fw-bold text-success d-block border-bottom pb-1">QRIS Dinamis &amp; Android Listener Billing (0% MDR)</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">String Payload QRIS Statis Milik Anda (EMVCo)</label>
                            <textarea name="platform_qris_payload" rows="3" class="form-control font-monospace small" placeholder="000201010211...">{{ old('platform_qris_payload', $settings['platform_qris_payload']) }}</textarea>
                            <small class="text-muted">Jika diisi, setiap pelanggan yang klik perpanjang paket akan langsung mendapatkan QRIS Dinamis dengan nominal + kode unik.</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Platform Webhook Secret Key <span class="text-danger">*</span></label>
                            <input type="text" name="platform_qris_secret" class="form-control font-monospace" value="{{ old('platform_qris_secret', $settings['platform_qris_secret']) }}" required minlength="8">
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary-green px-4">
                            <i class="fa-solid fa-save me-2"></i>Simpan Pengaturan Platform
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold m-0"><i class="fa-brands fa-android text-success me-2"></i>Auto-Verify Langganan via Android Listener</h6>
            </div>
            <div class="card-body p-4 small">
                <p class="text-muted">
                    Hubungkan aplikasi <strong>Android Payment Listener</strong> di HP penerima pembayaran Anda ke endpoint berikut. Setiap pembayaran QRIS/Transfer langganan masuk dengan nominal kode unik yang cocok akan <strong>otomatis mengaktifkan paket pelanggan</strong> tanpa Anda klik manual.
                </p>
                <div class="mb-3">
                    <label class="fw-bold d-block mb-1">URL Webhook Pembayaran Langganan:</label>
                    <input type="text" class="form-control form-control-sm font-monospace bg-light" value="{{ $platformWebhookUrl }}" readonly onclick="this.select()">
                </div>
                <div class="mb-3">
                    <label class="fw-bold d-block mb-1">URL Test Koneksi:</label>
                    <input type="text" class="form-control form-control-sm font-monospace bg-light" value="{{ $platformTestUrl }}" readonly onclick="this.select()">
                </div>
                <div class="mb-0">
                    <label class="fw-bold d-block mb-1">Secret Key:</label>
                    <input type="text" class="form-control form-control-sm font-monospace bg-light" value="{{ $settings['platform_qris_secret'] }}" readonly onclick="this.select()">
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
