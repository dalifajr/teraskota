@extends('layouts.app')

@section('title', 'Pengaturan QRIS & Webhook Listener')

@section('styles')
<style>
    .qris-dropzone {
        border: 2px dashed #059669;
        background: #f0fdf4;
        border-radius: 12px;
        padding: 24px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
    }
    .qris-dropzone:hover, .qris-dropzone.dragover {
        background: #dcfce7;
        border-color: #047857;
    }
    .badge-merchant {
        background: #ecfdf5;
        color: #047857;
        border: 1px solid #a7f3d0;
        font-weight: 600;
        padding: 6px 12px;
        border-radius: 20px;
    }
    .nav-tabs .nav-link {
        font-weight: 600;
        color: #4b5563;
        border: none;
        border-bottom: 3px solid transparent;
        padding: 12px 18px;
    }
    .nav-tabs .nav-link.active {
        color: #047857;
        border-color: #047857;
        background: transparent;
    }
    .code-box {
        background: #1e293b;
        color: #f8fafc;
        border-radius: 8px;
        padding: 12px 16px;
        font-family: monospace;
        font-size: 0.9rem;
        position: relative;
    }
</style>
@endsection

@section('content')
<div class="container-fluid px-0 px-md-3">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-muted">Dashboard</a></li>
                    <li class="breadcrumb-item text-muted">Pengaturan</li>
                    <li class="breadcrumb-item active text-success fw-bold" aria-current="page">QRIS & Listener</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold text-dark mb-0">Pengaturan QRIS & Webhook Listener</h1>
            <p class="text-muted small mb-0">Konfigurasi payload QRIS statis, konversi kode unik dinamis, dan integrasi Android Listener.</p>
        </div>
        <div>
            <a href="{{ route('pos.index') }}" target="_blank" class="btn btn-outline-success fw-bold">
                <i class="fa-solid fa-cash-register me-1"></i> Buka Kasir POS
            </a>
        </div>
    </div>

    <!-- Alert Success -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 rounded-3 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="fa-solid fa-circle-check fs-4 me-3 text-success"></i>
                <div>{{ session('success') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Main Card with Tabs -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom pt-3 pb-0 px-4">
            <ul class="nav nav-tabs card-header-tabs" id="qrisSettingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="config-tab" data-bs-toggle="tab" data-bs-target="#tab-config" type="button" role="tab">
                        <i class="fa-solid fa-qrcode me-2"></i> Konfigurasi QRIS & Kode Unik
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="guide-tab" data-bs-toggle="tab" data-bs-target="#tab-guide" type="button" role="tab">
                        <i class="fa-brands fa-android me-2 text-success"></i> Panduan Android Listener
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="logs-tab" data-bs-toggle="tab" data-bs-target="#tab-logs" type="button" role="tab">
                        <i class="fa-solid fa-clock-rotate-left me-2"></i> Log Notifikasi Masuk
                        @if($logs->count() > 0)
                            <span class="badge bg-secondary rounded-pill ms-1">{{ $logs->count() }}</span>
                        @endif
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body p-4">
            <div class="tab-content" id="qrisSettingsTabsContent">

                <!-- TAB 1: KONFIGURASI QRIS -->
                <div class="tab-pane fade show active" id="tab-config" role="tabpanel">
                    <form action="{{ route('settings.qris.update') }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row g-4">
                            <!-- Left Column: Payload & Image Scan -->
                            <div class="col-lg-7">
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label for="qris_payload" class="form-label fw-bold text-dark mb-0">
                                            Payload QRIS Statis (EMVCo String)
                                        </label>
                                        @if(!empty($merchantName))
                                            <span class="badge-merchant">
                                                <i class="fa-solid fa-store me-1"></i> {{ $merchantName }} {{ $merchantCity ? '('.$merchantCity.')' : '' }}
                                            </span>
                                        @endif
                                    </div>
                                    <textarea 
                                        name="qris_payload" 
                                        id="qris_payload" 
                                        rows="4" 
                                        class="form-control font-monospace small @error('qris_payload') is-invalid @enderror" 
                                        placeholder="00020101021126580014ID.GO.GPN.WWW0118... (dimulai dengan 000201...)"
                                    >{{ old('qris_payload', $payload) }}</textarea>
                                    @error('qris_payload')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text small text-muted">
                                        Masukkan kode teks QRIS statis dari aplikasi m-banking atau Payment Gateway Anda. Sistem akan mengonversinya menjadi QRIS Dinamis berangka unik secara otomatis saat kasir melakukan transaksi.
                                    </div>
                                </div>

                                <!-- Upload / Scan Image QRIS -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold text-dark small mb-2">
                                        <i class="fa-solid fa-camera me-1 text-success"></i> Atau Ekstrak Otomatis dari Gambar / Foto QRIS
                                    </label>
                                    <div class="qris-dropzone" id="qrisDropzone" onclick="document.getElementById('qrisFileInput').click();">
                                        <input type="file" id="qrisFileInput" accept="image/*" class="d-none">
                                        <div class="text-center">
                                            <i class="fa-solid fa-cloud-arrow-up text-success fs-1 mb-2"></i>
                                            <h6 class="fw-bold text-dark mb-1">Klik untuk upload atau geser gambar QRIS ke sini</h6>
                                            <p class="text-muted small mb-0">Mendukung format JPG, PNG, WEBP dari tangkapan layar atau foto stiker QRIS toko Anda.</p>
                                        </div>
                                    </div>
                                    <div id="scanStatus" class="mt-2 text-center small fw-semibold" style="display: none;"></div>
                                </div>
                            </div>

                            <!-- Right Column: Settings & Unique Numbers -->
                            <div class="col-lg-5">
                                <div class="bg-light p-3 rounded-4 border mb-4">
                                    <h6 class="fw-bold text-dark mb-3">
                                        <i class="fa-solid fa-key me-2 text-warning"></i> Keamanan Webhook Listener
                                    </h6>
                                    
                                    <div class="mb-3">
                                        <label for="qris_secret" class="form-label small fw-bold text-dark">
                                            Shared Secret Key (HMAC-SHA256)
                                        </label>
                                        <div class="input-group">
                                            <input 
                                                type="text" 
                                                name="qris_secret" 
                                                id="qris_secret" 
                                                class="form-control font-monospace @error('qris_secret') is-invalid @enderror" 
                                                value="{{ old('qris_secret', $secret) }}" 
                                                minlength="8"
                                                maxlength="128"
                                                required
                                            >
                                            <button class="btn btn-outline-secondary" type="button" id="btnToggleSecret" onclick="toggleSecretVisibility()" title="Lihat/Sembunyikan Secret">
                                                <i class="fa-regular fa-eye" id="iconToggleSecret"></i>
                                            </button>
                                            <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('qris_secret')" title="Salin Secret">
                                                <i class="fa-regular fa-copy"></i>
                                            </button>
                                        </div>
                                        @error('qris_secret')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <div class="form-text small text-muted">
                                            Secret ini digunakan untuk menandatangani request dari aplikasi Android Listener via header <code>X-Signature</code>.
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center gap-2">
                                        <button type="button" class="btn btn-sm btn-success fw-bold px-3 py-1" data-bs-toggle="modal" data-bs-target="#modalCustomSecret">
                                            <i class="fa-solid fa-pen-to-square me-1"></i> Ubah Secret Custom
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmRegenerateSecret()">
                                            <i class="fa-solid fa-arrows-rotate me-1"></i> Acak Ulang (Random)
                                        </button>
                                    </div>
                                </div>

                                <div class="bg-light p-3 rounded-4 border mb-4">
                                    <h6 class="fw-bold text-dark mb-3">
                                        <i class="fa-solid fa-hashtag me-2 text-primary"></i> Pengaturan Angka Unik & Waktu
                                    </h6>

                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label for="qris_unique_min" class="form-label small fw-semibold text-muted">Min Angka Unik</label>
                                            <input 
                                                type="number" 
                                                name="qris_unique_min" 
                                                id="qris_unique_min" 
                                                class="form-control" 
                                                value="{{ old('qris_unique_min', $uniqueMin) }}" 
                                                min="1" 
                                                max="999" 
                                                required
                                            >
                                        </div>
                                        <div class="col-6">
                                            <label for="qris_unique_max" class="form-label small fw-semibold text-muted">Max Angka Unik</label>
                                            <input 
                                                type="number" 
                                                name="qris_unique_max" 
                                                id="qris_unique_max" 
                                                class="form-control" 
                                                value="{{ old('qris_unique_max', $uniqueMax) }}" 
                                                min="10" 
                                                max="9999" 
                                                required
                                            >
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <label for="qris_expiry_minutes" class="form-label small fw-semibold text-muted">Masa Berlaku QRIS (Menit)</label>
                                        <div class="input-group">
                                            <input 
                                                type="number" 
                                                name="qris_expiry_minutes" 
                                                id="qris_expiry_minutes" 
                                                class="form-control" 
                                                value="{{ old('qris_expiry_minutes', $expiryMinutes) }}" 
                                                min="1" 
                                                max="120" 
                                                required
                                            >
                                            <span class="input-group-text small">Menit</span>
                                        </div>
                                        <div class="form-text small text-muted">
                                            Setelah waktu habis, kode unik dilepaskan kembali dan transaksi QRIS yang belum dibayar dinyatakan kadaluarsa.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-success fw-bold px-4 py-2 rounded-3">
                                <i class="fa-solid fa-floppy-disk me-2"></i> Simpan Pengaturan
                            </button>
                        </div>
                    </form>

                    <!-- Hidden form to regenerate secret -->
                    <form id="formRegenerateSecret" action="{{ route('settings.qris.regenerate-secret') }}" method="POST" class="d-none">
                        @csrf
                    </form>
                </div>

                <!-- TAB 2: PANDUAN ANDROID LISTENER -->
                <div class="tab-pane fade" id="tab-guide" role="tabpanel">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <h5 class="fw-bold text-dark mb-3">
                                <i class="fa-brands fa-android text-success me-2"></i> Konfigurasi Aplikasi Android Listener
                            </h5>
                            <p class="text-muted small">
                                Gunakan aplikasi pendamping Android yang ada di direktori <code>android_listener/</code>. Aplikasi ini bertindak sebagai jembatan yang membaca notifikasi uang masuk dari bank/dompet digital Anda secara lokal dan otomatis meneruskannya ke website Teras Kota.
                            </p>

                            <!-- Endpoint URL Card -->
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-dark">URL Endpoint Pembayaran (Payment Webhook):</label>
                                <div class="code-box d-flex justify-content-between align-items-center">
                                    <span id="textWebhookUrl">{{ $webhookUrl }}</span>
                                    <button type="button" class="btn btn-sm btn-light py-0 px-2 text-dark" onclick="copyDirect('textWebhookUrl')">
                                        <i class="fa-regular fa-copy"></i> Salin
                                    </button>
                                </div>
                            </div>

                            <!-- Test Endpoint URL Card -->
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-dark">URL Endpoint Tes Koneksi (Test Ping):</label>
                                <div class="code-box d-flex justify-content-between align-items-center">
                                    <span id="textTestUrl">{{ $testUrl }}</span>
                                    <button type="button" class="btn btn-sm btn-light py-0 px-2 text-dark" onclick="copyDirect('textTestUrl')">
                                        <i class="fa-regular fa-copy"></i> Salin
                                    </button>
                                </div>
                            </div>

                            <!-- Secret Key Box -->
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label fw-bold small text-dark mb-0">Shared Secret:</label>
                                    <button type="button" class="btn btn-sm btn-link text-success p-0 text-decoration-none fw-bold" data-bs-toggle="modal" data-bs-target="#modalCustomSecret">
                                        <i class="fa-solid fa-pen-to-square me-1"></i> Ubah Secret Custom
                                    </button>
                                </div>
                                <div class="code-box d-flex justify-content-between align-items-center">
                                    <span id="textSecretDoc">{{ $secret }}</span>
                                    <button type="button" class="btn btn-sm btn-light py-0 px-2 text-dark" onclick="copyDirect('textSecretDoc')">
                                        <i class="fa-regular fa-copy"></i> Salin
                                    </button>
                                </div>
                            </div>

                            <!-- Supported Apps Badge List -->
                            <h6 class="fw-bold text-dark small mb-2">Aplikasi Pembayaran yang Didukung Otomatis:</h6>
                            <div class="d-flex flex-wrap gap-2 mb-4">
                                <span class="badge bg-primary px-3 py-2"><i class="fa-solid fa-building-columns me-1"></i> BCA Mobile / myBCA</span>
                                <span class="badge bg-info text-dark px-3 py-2"><i class="fa-solid fa-wallet me-1"></i> DANA</span>
                                <span class="badge bg-success px-3 py-2"><i class="fa-solid fa-motorcycle me-1"></i> GoPay / Gojek</span>
                                <span class="badge bg-purple text-white px-3 py-2" style="background:#5b21b6;"><i class="fa-solid fa-coins me-1"></i> OVO</span>
                                <span class="badge bg-warning text-dark px-3 py-2"><i class="fa-solid fa-bag-shopping me-1"></i> ShopeePay</span>
                                <span class="badge bg-primary px-3 py-2"><i class="fa-solid fa-university me-1"></i> Livin' by Mandiri</span>
                                <span class="badge bg-secondary px-3 py-2"><i class="fa-solid fa-plus me-1"></i> Semua notifikasi QRIS Bank lain</span>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card bg-light border-0 rounded-4 p-3 h-100">
                                <h6 class="fw-bold text-dark mb-3">
                                    <i class="fa-solid fa-list-check me-2 text-success"></i> Langkah-Langkah Pemasangan
                                </h6>
                                <ol class="small text-muted ps-3 mb-0" style="line-height: 1.8;">
                                    <li class="mb-2">
                                        <strong class="text-dark">Build & Install APK:</strong>
                                        Buka direktori <code>android_listener/</code> dan jalankan:
                                        <div class="bg-dark text-white p-2 rounded my-1 font-monospace" style="font-size: 0.8rem;">
                                            flutter build apk --release --split-per-abi
                                        </div>
                                        Kirim file <code>app-arm64-v8a-release.apk</code> ke HP kasir/toko.
                                    </li>
                                    <li class="mb-2">
                                        <strong class="text-dark">Masukkan Endpoint & Secret:</strong>
                                        Buka aplikasi <strong>Jualan Listener</strong> di HP, tempel <em>URL Endpoint</em> dan <em>Shared Secret</em> yang ada di sebelah kiri.
                                    </li>
                                    <li class="mb-2">
                                        <strong class="text-dark">Aktifkan Izin Akses Notifikasi:</strong>
                                        Klik tombol <strong>"Aktifkan Listener"</strong> di aplikasi, sistem akan membuka pengaturan Android. Cari "Jualan Listener" dan geser toggle ke posisi <strong>Izinkan</strong>.
                                    </li>
                                    <li class="mb-2">
                                        <strong class="text-dark">Bebaskan Optimasi Baterai (Unrestricted):</strong>
                                        Buka info aplikasi di Pengaturan Android &rarr; Baterai &rarr; Pilih <strong>"Tidak Dibatasi / Unrestricted"</strong> agar Android tidak mematikan service listener di latar belakang.
                                    </li>
                                    <li>
                                        <strong class="text-dark">Tes Koneksi:</strong>
                                        Tekan tombol <strong>"Tes Koneksi"</strong> di aplikasi Android. Jika berhasil, status koneksi akan menjadi hijau (HTTP 200).
                                    </li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: LOG NOTIFIKASI MASUK -->
                <div class="tab-pane fade" id="tab-logs" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="fa-solid fa-history me-1 text-muted"></i> 15 Log Transaksi Listener Terakhir
                        </h6>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.location.reload();">
                            <i class="fa-solid fa-rotate me-1"></i> Refresh
                        </button>
                    </div>

                    <div class="table-responsive rounded-3 border">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th>Waktu</th>
                                    <th>Aplikasi</th>
                                    <th>Nominal</th>
                                    <th>Status</th>
                                    <th>Referensi</th>
                                    <th>Teks Notifikasi Asli</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($logs as $log)
                                    <tr>
                                        <td class="text-nowrap text-muted">{{ $log->created_at->format('d M Y, H:i:s') }}</td>
                                        <td>
                                            <span class="badge bg-light text-dark border">{{ $log->source_app ?? 'Android' }}</span>
                                        </td>
                                        <td class="fw-bold text-success">
                                            Rp {{ number_format($log->amount, 0, ',', '.') }}
                                        </td>
                                        <td>
                                            @if($log->status === 'matched')
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                    <i class="fa-solid fa-circle-check me-1"></i> Cocok #{{ $log->matched_transaction_id }}
                                                </span>
                                            @elseif($log->status === 'duplicate')
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                                    <i class="fa-solid fa-clock me-1"></i> Duplikat
                                                </span>
                                            @else
                                                <span class="badge bg-secondary-subtle text-secondary border">
                                                    {{ ucfirst($log->status) }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="font-monospace small text-muted">{{ $log->reference ?? '-' }}</td>
                                        <td class="text-truncate" style="max-width: 250px;" title="{{ $log->raw_text }}">
                                            {{ $log->raw_text ?? '-' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="fa-solid fa-inbox fs-2 mb-2 d-block text-secondary"></i>
                                            Belum ada log notifikasi masuk dari Android Listener.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Modal Ubah Secret Key Webhook Listener Custom -->
<div class="modal fade" id="modalCustomSecret" tabindex="-1" aria-labelledby="modalCustomSecretLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form action="{{ route('settings.qris.custom-secret') }}" method="POST" id="formCustomSecret">
                @csrf
                <div class="modal-header bg-dark text-white border-0 py-3" style="background-color: var(--primary-green) !important;">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-key text-warning fs-5"></i>
                        <h5 class="modal-title fw-bold mb-0" id="modalCustomSecretLabel">Ubah Secret Key Webhook</h5>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="alert alert-warning py-2 px-3 small rounded-3 mb-3 d-flex align-items-start gap-2">
                        <i class="fa-solid fa-triangle-exclamation text-warning fs-5 flex-shrink-0 mt-1"></i>
                        <div>
                            <strong>PENTING:</strong> Mengubah Secret Key akan membuat aplikasi <strong>Android Listener</strong> di HP kasir tidak dapat memverifikasi notifikasi uang masuk sampai Anda memperbarui Secret Key baru ini di aplikasi HP kasir.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="inputCustomSecret" class="form-label small fw-bold text-dark">
                            Secret Key Baru (Custom):
                        </label>
                        <div class="input-group mb-1">
                            <input 
                                type="text" 
                                name="custom_secret" 
                                id="inputCustomSecret" 
                                class="form-control font-monospace" 
                                placeholder="Masukkan secret key custom..." 
                                minlength="8" 
                                maxlength="128" 
                                value="{{ $secret }}" 
                                required
                            >
                            <button class="btn btn-outline-secondary" type="button" onclick="generateRandomCustomSecret()" title="Buat Kunci Acak Baru">
                                <i class="fa-solid fa-arrows-rotate me-1"></i> Acak
                            </button>
                            <button class="btn btn-outline-secondary" type="button" onclick="copyDirect('inputCustomSecret', true)" title="Salin">
                                <i class="fa-regular fa-copy"></i>
                            </button>
                        </div>
                        <div class="form-text small text-muted">
                            Minimal 8 karakter. Bebas menggunakan kombinasi huruf, angka, atau simbol.
                        </div>
                    </div>

                    <div class="p-2 rounded-3 bg-light border text-muted small">
                        <i class="fa-solid fa-circle-info text-info me-1"></i>
                        Secret Key ini dipakai oleh HP Android Listener untuk membuat tanda tangan digital (HMAC-SHA256) saat mendeteksi dana QRIS masuk.
                    </div>
                </div>

                <div class="modal-footer bg-light border-top d-flex justify-content-between p-3">
                    <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success fw-bold px-4 py-2 rounded-3 btn-sm" id="btnSubmitCustomSecret">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Secret Key
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<!-- Multi-engine QR extraction: Html5Qrcode (ZXing) + jsQR fallback -->
<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>

<script>
    // Copy to clipboard helper
    function copyToClipboard(elementId) {
        const input = document.getElementById(elementId);
        if (!input) return;
        input.select();
        input.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(input.value);
        showToast('Berhasil disalin ke clipboard!');
    }

    function copyDirect(elementId, isInput = false) {
        let text = '';
        if (isInput) {
            text = document.getElementById(elementId)?.value;
        } else {
            text = document.getElementById(elementId)?.innerText;
        }
        if (!text) return;
        navigator.clipboard.writeText(text);
        showToast('Berhasil disalin ke clipboard!');
    }

    function toggleSecretVisibility() {
        const input = document.getElementById('qris_secret');
        const icon = document.getElementById('iconToggleSecret');
        if (!input || !icon) return;
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        }
    }

    function generateRandomCustomSecret() {
        const chars = '0123456789abcdef';
        let res = '';
        for (let i = 0; i < 32; i++) {
            res += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        const input = document.getElementById('inputCustomSecret');
        if (input) {
            input.value = res;
            input.focus();
            showToast('Secret acak 32-karakter berhasil dibuat!');
        }
    }

    function confirmRegenerateSecret() {
        if (confirm('PERINGATAN: Mengubah Secret Key akan membuat aplikasi Android Listener tidak bisa mengirim webhook sampai Secret Key baru diperbarui di aplikasi HP. Lanjutkan?')) {
            document.getElementById('formRegenerateSecret').submit();
        }
    }

    // Handle AJAX submission of Custom Secret Modal
    document.getElementById('formCustomSecret')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmitCustomSecret');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...';

        const secretVal = document.getElementById('inputCustomSecret').value.trim();
        const token = document.querySelector('input[name="_token"]')?.value;

        try {
            const response = await fetch("{{ route('settings.qris.custom-secret') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token
                },
                body: JSON.stringify({ custom_secret: secretVal })
            });
            const data = await response.json();
            if (response.ok && data.status === 'success') {
                // Update input and doc boxes
                const qrisSecretInput = document.getElementById('qris_secret');
                if (qrisSecretInput) qrisSecretInput.value = data.secret;
                const textSecretDoc = document.getElementById('textSecretDoc');
                if (textSecretDoc) textSecretDoc.textContent = data.secret;

                // Close modal
                const modalEl = document.getElementById('modalCustomSecret');
                const modalInstance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                modalInstance.hide();

                if (window.Swal) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: 'Secret Key Webhook berhasil diperbarui secara custom: ' + data.secret,
                        confirmButtonColor: '#16a34a'
                    });
                } else {
                    alert('Secret Key Webhook berhasil diperbarui!');
                }
            } else {
                if (window.Swal) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: data.message || 'Gagal menyimpan secret key.',
                        confirmButtonColor: '#dc2626'
                    });
                } else {
                    alert(data.message || 'Gagal menyimpan secret key.');
                }
            }
        } catch (err) {
            if (window.Swal) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Terjadi kesalahan jaringan saat memperbarui secret key.',
                    confirmButtonColor: '#dc2626'
                });
            } else {
                alert('Terjadi kesalahan jaringan.');
            }
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    });

    function showToast(message) {
        if (window.Swal) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: message,
                showConfirmButton: false,
                timer: 2000
            });
        } else {
            alert(message);
        }
    }

    // Image QR Extractor via jsQR + HTML5 Canvas
    const dropzone = document.getElementById('qrisDropzone');
    const fileInput = document.getElementById('qrisFileInput');
    const statusDiv = document.getElementById('scanStatus');
    const payloadTextarea = document.getElementById('qris_payload');

    if (dropzone && fileInput) {
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropzone.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropzone.classList.remove('dragover');
            });
        });

        dropzone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                processQRFile(files[0]);
            }
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                processQRFile(e.target.files[0]);
            }
        });
    }

    async function processQRFile(file) {
        if (!file.type.startsWith('image/')) {
            alert('Silakan upload file berupa gambar (JPG, PNG, atau WEBP).');
            return;
        }

        statusDiv.style.display = 'block';
        statusDiv.className = 'mt-2 text-center small text-primary fw-semibold';
        statusDiv.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menganalisa & mengekstrak QR Code...';

        let extractedPayload = null;

        // Engine 1: Html5Qrcode (ZXing Engine - handles real photos, angles, perspective)
        if (window.Html5Qrcode) {
            try {
                const scanner = new Html5Qrcode('qrisDropzone');
                extractedPayload = await scanner.scanFile(file, false);
            } catch (err) {
                // ZXing couldn't lock, fallback to jsQR
            }
        }

        // Engine 2: Downscaled Canvas + jsQR
        if (!extractedPayload) {
            extractedPayload = await new Promise((resolve) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = new Image();
                    img.onload = function() {
                        const canvas = document.createElement('canvas');
                        const ctx = canvas.getContext('2d');
                        
                        // Downscale huge camera photos (e.g. 4000x3000 down to max 1200px)
                        let w = img.width;
                        let h = img.height;
                        const maxDim = 1200;
                        if (w > maxDim || h > maxDim) {
                            if (w > h) {
                                h = Math.round((h * maxDim) / w);
                                w = maxDim;
                            } else {
                                w = Math.round((w * maxDim) / h);
                                h = maxDim;
                            }
                        }

                        canvas.width = w;
                        canvas.height = h;
                        ctx.drawImage(img, 0, 0, w, h);

                        if (window.jsQR) {
                            const imgData = ctx.getImageData(0, 0, w, h);
                            let code = jsQR(imgData.data, w, h, { inversionAttempts: "dontInvert" });
                            if (!code) {
                                code = jsQR(imgData.data, w, h, { inversionAttempts: "attemptBoth" });
                            }
                            if (code && code.data) {
                                resolve(code.data);
                                return;
                            }
                        }
                        resolve(null);
                    };
                    img.onerror = () => resolve(null);
                    img.src = e.target.result;
                };
                reader.onerror = () => resolve(null);
                reader.readAsDataURL(file);
            });
        }

        if (extractedPayload) {
            // Clean outer whitespace, tabs, and newlines
            const cleanPayload = extractedPayload.trim().replace(/[\r\n\t]+/g, '');
            payloadTextarea.value = cleanPayload;

            // Extract merchant name preview (Tag 59)
            let merchantName = 'Teras Kota';
            const mMatch = cleanPayload.match(/59([0-9]{2})([A-Za-z0-9\s\.\,\-\*]+)/);
            if (mMatch) {
                const len = parseInt(mMatch[1], 10);
                merchantName = mMatch[2].substring(0, len).trim();
            }

            statusDiv.className = 'mt-2 text-center small text-success fw-bold p-2 bg-success-subtle rounded-3';
            statusDiv.innerHTML = `<i class="fa-solid fa-circle-check me-1"></i> QRIS Berhasil Diekstrak!<div class="text-dark small fw-normal mt-1">Merchant: <strong>${merchantName}</strong> (${cleanPayload.length} karakter)</div>`;
            showToast('QR Code QRIS berhasil diekstrak ke dalam Payload!');
        } else {
            statusDiv.className = 'mt-2 text-center small text-danger fw-semibold p-2 bg-danger-subtle rounded-3';
            statusDiv.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i> Tidak dapat menemukan barcode QRIS pada gambar ini. Pastikan gambar tajam, tidak terlalu gelap, dan fokus pada kode QR.';
        }
    }
</script>
@endsection
