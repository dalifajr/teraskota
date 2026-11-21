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
                <h6 class="fw-bold m-0"><i class="fa-solid fa-sliders text-success me-2"></i>Identitas Platform, Pembayaran &amp; Pengumuman Global</h6>
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
                            <input type="tel" name="support_whatsapp" class="form-control" inputmode="tel" placeholder="0812xxxxxxxx" value="{{ old('support_whatsapp', $settings['support_whatsapp']) }}">
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
                        <div class="col-12">
                            <div class="form-check form-switch p-2 ps-5 rounded-3 bg-light border">
                                <input class="form-check-input" type="checkbox" role="switch" id="registrationEnabled" name="registration_enabled" value="1" {{ old('registration_enabled', $settings['registration_enabled'] ?? '1') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label small fw-semibold text-dark" for="registrationEnabled">
                                    Buka Pendaftaran Usaha Baru Publik (<code>/register</code>)
                                </label>
                                <div class="text-muted" style="font-size: 0.74rem;">Matikan saklar ini jika ingin menutup pendaftaran mandiri sementara (Super Admin tetap bisa menambah usaha manual).</div>
                            </div>
                        </div>

                        <!-- Platform Broadcast Banner -->
                        <div class="col-12 mt-3">
                            <small class="text-uppercase fw-bold text-primary d-block border-bottom pb-1">
                                <i class="fa-solid fa-bullhorn me-1"></i>Pengumuman Global (Broadcast Banner ke Semua Tenant)
                            </small>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch mt-1">
                                <input class="form-check-input" type="checkbox" role="switch" id="announcementEnabled" name="announcement_enabled" value="1" {{ old('announcement_enabled', $settings['announcement_enabled']) == '1' ? 'checked' : '' }}>
                                <label class="form-check-label small fw-semibold" for="announcementEnabled">Tampilkan Banner di Dashboard Semua Usaha</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <select name="announcement_type" class="form-select form-select-sm" aria-label="Tipe Banner Pengumuman">
                                <option value="info" {{ old('announcement_type', $settings['announcement_type']) === 'info' ? 'selected' : '' }}>Info (Biru)</option>
                                <option value="success" {{ old('announcement_type', $settings['announcement_type']) === 'success' ? 'selected' : '' }}>Promo / Rilis Fitur (Hijau)</option>
                                <option value="warning" {{ old('announcement_type', $settings['announcement_type']) === 'warning' ? 'selected' : '' }}>Peringatan / Maintenance (Kuning)</option>
                                <option value="danger" {{ old('announcement_type', $settings['announcement_type']) === 'danger' ? 'selected' : '' }}>Penting / Darurat (Merah)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <input type="text" name="announcement_message" class="form-control form-control-sm" maxlength="500" placeholder="Contoh: Pembaruan fitur struk WhatsApp kini aktif untuk semua paket!" value="{{ old('announcement_message', $settings['announcement_message']) }}">
                        </div>

                        <!-- Manual Bank Transfer -->
                        <div class="col-12 mt-3">
                            <small class="text-uppercase fw-bold text-success d-block border-bottom pb-1">Rekening Transfer Bank Manual</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Nama Bank</label>
                            <input type="text" name="bank_name" class="form-control" placeholder="BCA / BRI / Mandiri" value="{{ old('bank_name', $settings['bank_name']) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Nomor Rekening</label>
                            <input type="text" name="bank_account_number" class="form-control" inputmode="numeric" value="{{ old('bank_account_number', $settings['bank_account_number']) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Atas Nama</label>
                            <input type="text" name="bank_account_name" class="form-control" value="{{ old('bank_account_name', $settings['bank_account_name']) }}">
                        </div>

                        <!-- Dynamic QRIS + Auto-Scanner -->
                        <div class="col-12 mt-3">
                            <small class="text-uppercase fw-bold text-success d-block border-bottom pb-1">QRIS Dinamis &amp; Android Listener Billing (0% MDR)</small>
                        </div>
                        <div class="col-12">
                            <div class="p-3 bg-light rounded-3 border mb-2">
                                <label for="platformQrisImageInput" class="form-label small fw-bold text-dark mb-1">
                                    <i class="fa-solid fa-qrcode text-success me-1"></i>Scan Otomatis dari Gambar QRIS Statis (Opsional)
                                </label>
                                <input type="file" id="platformQrisImageInput" class="form-control form-control-sm" accept="image/*">
                                <div id="platformQrisScanStatus" class="small mt-2 d-none"></div>
                            </div>
                            <label class="form-label small fw-semibold">String Payload QRIS Statis Milik Anda (EMVCo)</label>
                            <textarea name="platform_qris_payload" id="platformQrisPayload" rows="3" class="form-control font-monospace small" placeholder="000201010211...">{{ old('platform_qris_payload', $settings['platform_qris_payload']) }}</textarea>
                            <small class="text-muted">Jika diisi, setiap pelanggan yang klik perpanjang paket akan langsung mendapatkan QRIS Dinamis dengan nominal + kode unik.</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Platform Webhook Secret Key <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" name="platform_qris_secret" id="platformSecretInput" class="form-control font-monospace" value="{{ old('platform_qris_secret', $settings['platform_qris_secret']) }}" required minlength="8">
                                <button type="button" class="btn btn-outline-secondary" id="btnGenPlatformSecret" title="Acak Secret Key Baru (32 karakter hex)">
                                    <i class="fa-solid fa-shuffle me-1"></i>Acak
                                </button>
                            </div>
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

    <div class="col-lg-5 d-flex flex-column gap-4">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold m-0"><i class="fa-brands fa-android text-success me-2"></i>Auto-Verify Langganan via Android Listener</h6>
            </div>
            <div class="card-body p-4 small">
                <p class="text-muted">
                    Hubungkan aplikasi <strong>Android Payment Listener</strong> di HP penerima pembayaran Anda ke endpoint berikut. Setiap pembayaran QRIS/Transfer langganan masuk dengan nominal kode unik yang cocok akan <strong>otomatis mengaktifkan paket pelanggan</strong> tanpa klik manual.
                </p>
                <div class="mb-3">
                    <label class="fw-bold d-block mb-1">URL Webhook Pembayaran Langganan:</label>
                    <div class="input-group input-group-sm">
                        <input type="text" id="copyWebhookUrl" class="form-control font-monospace bg-light" value="{{ $platformWebhookUrl }}" readonly>
                        <button type="button" class="btn btn-outline-secondary js-copy-btn" data-copy-target="copyWebhookUrl">
                            <i class="fa-regular fa-copy"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="fw-bold d-block mb-1">URL Test Koneksi:</label>
                    <div class="input-group input-group-sm">
                        <input type="text" id="copyTestUrl" class="form-control font-monospace bg-light" value="{{ $platformTestUrl }}" readonly>
                        <button type="button" class="btn btn-outline-secondary js-copy-btn" data-copy-target="copyTestUrl">
                            <i class="fa-regular fa-copy"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="fw-bold d-block mb-1">Secret Key Aktif:</label>
                    <div class="input-group input-group-sm">
                        <input type="text" id="copySecretKey" class="form-control font-monospace bg-light" value="{{ $settings['platform_qris_secret'] }}" readonly>
                        <button type="button" class="btn btn-outline-secondary js-copy-btn" data-copy-target="copySecretKey">
                            <i class="fa-regular fa-copy"></i>
                        </button>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-success w-100 fw-semibold" id="btnTestPlatformWebhook">
                    <i class="fa-solid fa-plug-circle-check me-1"></i>Uji Koneksi &amp; Signature Webhook (Live)
                </button>
            </div>
        </div>

        <!-- Platform Billing Webhook Audit Logs -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center gap-2">
                <h6 class="fw-bold m-0"><i class="fa-solid fa-satellite-dish text-primary me-2"></i>Log Webhook Billing Terbaru</h6>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-dark border">{{ $platformWebhookLogs->count() }} log</span>
                    <form action="{{ route('superadmin.settings.prune-logs') }}" method="POST" class="d-inline" data-confirm="Hapus log webhook billing yang berusia lebih dari 30 hari?">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" title="Bersihkan log lama (>30 hari)">
                            <i class="fa-solid fa-broom"></i>
                        </button>
                    </form>
                </div>
            </div>
            <div class="table-responsive" style="max-height: 360px; overflow-y: auto;">
                <table class="table table-hover align-middle mb-0 small" style="font-variant-numeric: tabular-nums;">
                    <thead class="table-light">
                        <tr>
                            <th>Waktu &amp; Sumber</th>
                            <th>Nominal</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($platformWebhookLogs as $log)
                            <tr>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $log->source_app ?: 'Android' }}</div>
                                    <small class="text-muted">{{ $log->created_at?->translatedFormat('d M, H:i:s') }}</small>
                                    @if($log->invoice)
                                        <div class="text-success font-monospace" style="font-size: 0.72rem;">{{ $log->invoice->invoice_number }}</div>
                                    @endif
                                </td>
                                <td class="fw-bold">Rp{{ number_format((float)$log->amount, 0, ',', '.') }}</td>
                                <td>
                                    @if($log->status === 'matched')
                                        <span class="badge bg-success">MATCHED</span>
                                    @elseif($log->status === 'invalid_signature')
                                        <span class="badge bg-danger">BAD SIG</span>
                                    @else
                                        <span class="badge bg-warning text-dark">UNMATCHED</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">Belum ada notifikasi webhook billing masuk.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script>
    (function () {
        // Modern Clipboard API with graceful fallback
        document.querySelectorAll('.js-copy-btn').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const targetId = btn.getAttribute('data-copy-target');
                const input = document.getElementById(targetId);
                if (!input) return;

                let copied = false;
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    try {
                        await navigator.clipboard.writeText(input.value);
                        copied = true;
                    } catch (e) {}
                }
                if (!copied) {
                    input.select();
                    document.execCommand('copy');
                }

                const prev = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-check text-success"></i>';
                setTimeout(() => { btn.innerHTML = prev; }, 1400);
            });
        });

        // Test Platform Webhook Live Ping
        document.getElementById('btnTestPlatformWebhook')?.addEventListener('click', async function () {
            const btn = this;
            const prev = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menguji...';
            try {
                const res = await fetch("{{ route('superadmin.settings.test-webhook') }}", {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    }
                });
                const data = await res.json();
                Swal.fire({
                    icon: res.ok && data.status === 'success' ? 'success' : 'error',
                    title: res.ok ? 'Webhook Billing Siap!' : 'Verifikasi Gagal',
                    text: data.message || 'Tidak dapat menghubungi server.',
                    confirmButtonColor: '#14532d'
                });
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Gagal Menguji', text: 'Terjadi kesalahan jaringan.' });
            } finally {
                btn.disabled = false;
                btn.innerHTML = prev;
            }
        });

        // Generate 32-char cryptographic random hex secret
        document.getElementById('btnGenPlatformSecret')?.addEventListener('click', function () {
            const bytes = new Uint8Array(16);
            if (window.crypto && window.crypto.getRandomValues) {
                window.crypto.getRandomValues(bytes);
            } else {
                for (let i = 0; i < 16; i++) bytes[i] = Math.floor(Math.random() * 256);
            }
            const hex = Array.from(bytes).map(b => b.toString(16).padStart(2, '0')).join('');
            const input = document.getElementById('platformSecretInput');
            if (input) input.value = hex;
        });

        // Auto-Scan Static QRIS Image via BarcodeDetector / jsQR
        document.getElementById('platformQrisImageInput')?.addEventListener('change', async function (e) {
            const file = e.target.files?.[0];
            const statusEl = document.getElementById('platformQrisScanStatus');
            const payloadEl = document.getElementById('platformQrisPayload');
            if (!file || !statusEl || !payloadEl) return;

            statusEl.className = 'small mt-2 text-primary fw-semibold';
            statusEl.textContent = 'Memindai kode QRIS dari gambar...';

            let decoded = null;
            if ('BarcodeDetector' in window) {
                try {
                    const detector = new BarcodeDetector({ formats: ['qr_code'] });
                    const bmp = await createImageBitmap(file);
                    const codes = await detector.detect(bmp);
                    if (codes.length > 0 && codes[0].rawValue) decoded = codes[0].rawValue;
                } catch (err) {}
            }

            if (!decoded && typeof jsQR !== 'undefined') {
                decoded = await new Promise((resolve) => {
                    const reader = new FileReader();
                    reader.onload = function (ev) {
                        const img = new Image();
                        img.onload = function () {
                            const canvas = document.createElement('canvas');
                            const scale = Math.min(1, 1000 / Math.max(img.width, img.height));
                            canvas.width = Math.round(img.width * scale);
                            canvas.height = Math.round(img.height * scale);
                            const ctx = canvas.getContext('2d');
                            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                            const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                            const qr = jsQR(imgData.data, imgData.width, imgData.height);
                            resolve(qr ? qr.data : null);
                        };
                        img.onerror = () => resolve(null);
                        img.src = ev.target.result;
                    };
                    reader.onerror = () => resolve(null);
                    reader.readAsDataURL(file);
                });
            }

            if (decoded) {
                payloadEl.value = decoded.trim().replace(/[\r\n\t]+/g, '');
                statusEl.className = 'small mt-2 text-success fw-bold';
                statusEl.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i>Payload QRIS berhasil diekstrak! Klik Simpan Pengaturan Platform untuk menerapkan.';
            } else {
                statusEl.className = 'small mt-2 text-danger fw-semibold';
                statusEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i>Gagal membaca kode QR pada gambar. Pastikan gambar jelas & tidak terpotong.';
            }
        });
    })();
</script>
@endsection
