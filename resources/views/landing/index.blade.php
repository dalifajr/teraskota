<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{{ $platformName ?? 'KasirLoka' }} — {{ $platformTagline ?? 'Aplikasi Kasir POS & Keuangan UMKM Anti-Offline' }}. Struk thermal & WhatsApp, QRIS Dinamis Auto-Verify, dan laporan laba bersih otomatis.">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="theme-color" content="#14532d">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
    <title>{{ $platformName ?? 'KasirLoka' }} — {{ $platformTagline ?? 'Aplikasi Kasir POS & Keuangan UMKM' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --kl-dark: #0c2613;
            --kl-primary: #14532d;
            --kl-accent: #84cc16;
            --kl-soft: #f0fdf4;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #0f172a;
            background: #ffffff;
        }
        .kl-nav {
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid #e2e8f0;
        }
        .kl-hero {
            background: radial-gradient(circle at 85% 15%, rgba(132, 204, 22, 0.18), transparent 45%),
                        linear-gradient(135deg, #0c2613 0%, #14532d 55%, #166534 100%);
            color: #ffffff;
            padding: 5.5rem 0 4.5rem;
        }
        .kl-badge-pill {
            background: rgba(163, 230, 53, 0.16);
            border: 1px solid rgba(163, 230, 53, 0.4);
            color: #bef264;
            font-weight: 600;
            font-size: 0.82rem;
            padding: 0.4rem 0.95rem;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-kl-lime {
            background: #a3e635;
            color: #0c2613;
            font-weight: 700;
            border: none;
        }
        .btn-kl-lime:hover {
            background: #bef264;
            color: #0c2613;
        }
        .btn-kl-primary {
            background: var(--kl-primary);
            color: #ffffff;
            font-weight: 600;
            border: none;
        }
        .btn-kl-primary:hover {
            background: var(--kl-dark);
            color: #ffffff;
        }
        .feature-card {
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 1.75rem;
            height: 100%;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            background: #ffffff;
        }
        .feature-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 18px 36px rgba(15, 23, 42, 0.08);
        }
        .feature-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            background: var(--kl-soft);
            color: var(--kl-primary);
            margin-bottom: 1.1rem;
        }
        .pricing-card {
            border: 1px solid #e2e8f0;
            border-radius: 22px;
            padding: 2rem;
            background: #ffffff;
            height: 100%;
            position: relative;
        }
        .pricing-card.featured {
            border: 2px solid var(--kl-primary);
            box-shadow: 0 22px 45px rgba(20, 83, 45, 0.14);
        }
        .hero-mock {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 20px;
            padding: 1.5rem;
            backdrop-filter: blur(8px);
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg kl-nav sticky-top py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-dark fs-4" href="{{ route('landing') }}">
                <span class="d-inline-flex align-items-center justify-content-center rounded-3 text-white" style="width: 38px; height: 38px; background: var(--kl-primary);">
                    <i class="fa-solid fa-cash-register fs-6 text-warning"></i>
                </span>
                <span>{{ $platformName ?? 'KasirLoka' }}</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#klNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="klNavbar">
                <ul class="navbar-nav mx-auto gap-lg-3 fw-medium">
                    <li class="nav-item"><a class="nav-link text-dark" href="#keunggulan">Keunggulan</a></li>
                    <li class="nav-item"><a class="nav-link text-dark" href="#harga">Harga Paket</a></li>
                    <li class="nav-item"><a class="nav-link text-dark" href="#faq">FAQ</a></li>
                </ul>
                <div class="d-flex gap-2 mt-3 mt-lg-0">
                    <a href="{{ route('login') }}" class="btn btn-outline-success px-4 rounded-pill fw-semibold">Masuk</a>
                    <a href="{{ route('register') }}" class="btn btn-kl-primary px-4 rounded-pill">Coba Gratis {{ $trialDays }} Hari</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero -->
    <section class="kl-hero">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-7">
                    <div class="kl-badge-pill mb-3">
                        <i class="fa-solid fa-bolt"></i>
                        <span>Gratis Uji Coba {{ $trialDays }} Hari · Tanpa Kartu Kredit</span>
                    </div>
                    <h1 class="display-5 fw-bold lh-sm mb-3">
                        Kasir POS Anti-Offline &amp; Hitung Laba Bersih Otomatis untuk Usaha Anda
                    </h1>
                    <p class="lead text-white-50 mb-4" style="max-width: 600px;">
                        {{ $platformTagline }}. Dirancang khusus untuk kedai minuman, kopi, warung makan, dan UMKM agar tetap melayani pembeli meski sinyal hilang—lengkap dengan QRIS Dinamis verifikasi otomatis.
                    </p>
                    @php
                        $landingWaClean = preg_replace('/[^0-9]/', '', (string) ($supportWhatsapp ?? ''));
                        if (str_starts_with($landingWaClean, '0')) {
                            $landingWaClean = '62' . substr($landingWaClean, 1);
                        }
                        $landingWaMsg = 'Halo Admin ' . ($platformName ?? 'KasirLoka') . ', saya ingin bertanya mengenai paket dan fitur kasir POS untuk usaha saya.';
                    @endphp
                    <div class="d-flex flex-wrap gap-3 mb-4">
                        <a href="{{ route('register') }}" class="btn btn-kl-lime btn-lg px-4 rounded-pill shadow">
                            <i class="fa-solid fa-rocket me-2"></i>Daftar Usaha Sekarang
                        </a>
                        <a href="#harga" class="btn btn-outline-light btn-lg px-4 rounded-pill">
                            Lihat Harga Paket
                        </a>
                        @if($landingWaClean !== '')
                            <a href="https://wa.me/{{ $landingWaClean }}?text={{ urlencode($landingWaMsg) }}" target="_blank" rel="noopener noreferrer" class="btn btn-success btn-lg px-4 rounded-pill shadow-sm">
                                <i class="fa-brands fa-whatsapp me-2"></i>Tanya via WA
                            </a>
                        @endif
                    </div>
                    <div class="d-flex flex-wrap gap-4 small text-white-50">
                        <span><i class="fa-solid fa-circle-check text-warning me-1"></i>Jalan di HP, Tablet &amp; Laptop</span>
                        <span><i class="fa-solid fa-circle-check text-warning me-1"></i>Struk Thermal &amp; Kirim WA Rp0</span>
                        <span><i class="fa-solid fa-circle-check text-warning me-1"></i>Hold Pesanan &amp; Siap Pakai 2 Menit</span>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="hero-mock shadow-lg">
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-white border-opacity-10">
                            <span class="fw-bold"><i class="fa-solid fa-chart-pie text-warning me-2"></i>Ringkasan Harian Live</span>
                            <span class="badge bg-success">Online · Auto-Sync</span>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div class="p-3 rounded-3 bg-white bg-opacity-10">
                                    <small class="text-white-50 d-block">Omzet Hari Ini</small>
                                    <div class="fs-5 fw-bold">Rp1.485.000</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 rounded-3 bg-white bg-opacity-10">
                                    <small class="text-white-50 d-block">Laba Bersih</small>
                                    <div class="fs-5 fw-bold text-warning">Rp492.500</div>
                                </div>
                            </div>
                        </div>
                        <div class="p-3 rounded-3 bg-white bg-opacity-10 small">
                            <div class="d-flex justify-content-between mb-1">
                                <span><i class="fa-solid fa-qrcode text-warning me-2"></i>QRIS Auto-Verify</span>
                                <span class="badge bg-success">Lunas Otomatis</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span><i class="fa-solid fa-wifi text-info me-2"></i>Mode Offline Fallback</span>
                                <span class="text-white-50">0 antrean tertunda</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features -->
    <section id="keunggulan" class="py-5 my-4">
        <div class="container">
            <div class="text-center mb-5">
                <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill fw-semibold mb-2">KENAPA {{ strtoupper($platformName ?? 'KASIRLOKA') }}?</span>
                <h2 class="fw-bold">Semua yang Dibutuhkan Usaha Harian, Tanpa Ribet</h2>
                <p class="text-muted mx-auto" style="max-width: 620px;">
                    Fokus pada kecepatan transaksi kasir dan kejelasan arus kas bersih pemilik usaha.
                </p>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                        <h5 class="fw-bold">Offline-First Anti-Mati Gaya</h5>
                        <p class="text-muted small mb-0">
                            Internet booth tiba-tiba putus? Kasir tetap bisa melayani pembayaran &amp; mencetak struk. Data tersimpan aman di perangkat dan otomatis sinkron begitu sinyal kembali.
                        </p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fa-solid fa-qrcode"></i></div>
                        <h5 class="fw-bold">QRIS Dinamis + Auto-Verify</h5>
                        <p class="text-muted small mb-0">
                            Nominal belanja otomatis tertanam pada QRIS pelanggan dengan kode unik. Begitu uang masuk, status transaksi otomatis LUNAS tanpa kasir harus cek HP pemilik!
                        </p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fa-solid fa-wallet"></i></div>
                        <h5 class="fw-bold">Omzet, HPP, Kas &amp; Laba Bersih</h5>
                        <p class="text-muted small mb-0">
                            Catat belanja es batu, cup, bahan baku, dan listrik di menu Pengeluaran Kas. Dashboard langsung menampilkan Laba Bersih riil bukan sekadar omzet kotor.
                        </p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fa-brands fa-whatsapp"></i></div>
                        <h5 class="fw-bold">Struk Thermal &amp; Digital WhatsApp</h5>
                        <p class="text-muted small mb-0">
                            Atur sendiri nama usaha, logo, alamat, dan footer struk Anda. Cetak lewat printer thermal 58mm/80mm atau kirim struk digital langsung ke WhatsApp pelanggan tanpa biaya API.
                        </p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fa-solid fa-users-gear"></i></div>
                        <h5 class="fw-bold">Multi-Kasir &amp; Kontrol Akses</h5>
                        <p class="text-muted small mb-0">
                            Pisahkan akun Pemilik (Admin) dan staf Kasir. Kasir fokus di layar POS tanpa bisa melihat atau mengubah laporan laba dan data sensitif pemilik.
                        </p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fa-solid fa-file-pdf"></i></div>
                        <h5 class="fw-bold">Ekspor Laporan PDF &amp; Excel</h5>
                        <p class="text-muted small mb-0">
                            Rekap penjualan harian, mingguan, bulanan, produk terlaris, dan rincian transaksi siap unduh dalam format PDF maupun CSV/Excel kapan saja.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing -->
    <section id="harga" class="py-5 bg-light">
        <div class="container py-3">
            <div class="text-center mb-4">
                <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill fw-semibold mb-2">HARGA TRANSPARAN</span>
                <h2 class="fw-bold">Mulai Gratis {{ $trialDays }} Hari, Lanjut Sesuai Kebutuhan</h2>
                <p class="text-muted mb-4">Tanpa potongan komisi per transaksi. Data usaha Anda tetap aman kapan pun.</p>

                @php
                    $landingMaxSave = 0;
                    foreach ($plans as $lpCheck) {
                        $lpYf = (float) $lpCheck->price_monthly * 12;
                        if ($lpYf > 0 && (float) $lpCheck->price_yearly < $lpYf) {
                            $lpPct = (int) round((1 - ((float) $lpCheck->price_yearly / $lpYf)) * 100);
                            if ($lpPct > $landingMaxSave) $landingMaxSave = $lpPct;
                        }
                    }
                @endphp
                <div class="btn-group bg-white p-1 rounded-pill shadow-sm border" role="group">
                    <button type="button" class="btn btn-sm btn-success rounded-pill px-4 fw-semibold" id="btnCycleMonthly">Bulanan</button>
                    <button type="button" class="btn btn-sm btn-light rounded-pill px-4 fw-semibold" id="btnCycleYearly">
                        Tahunan
                        @if($landingMaxSave > 0)
                            <span class="badge bg-warning text-dark ms-1">Hemat {{ $landingMaxSave }}%</span>
                        @endif
                    </button>
                </div>
            </div>

            <div class="row justify-content-center g-4 mt-2">
                @foreach($plans as $plan)
                    @php
                        $lpYearlyFull = (float) $plan->price_monthly * 12;
                        $lpSavePct = ($lpYearlyFull > 0 && (float) $plan->price_yearly < $lpYearlyFull)
                            ? (int) round((1 - ((float) $plan->price_yearly / $lpYearlyFull)) * 100)
                            : 0;
                    @endphp
                    <div class="col-md-6 col-lg-{{ $plans->count() >= 3 ? '4' : '5' }}">
                        <div class="pricing-card d-flex flex-column {{ $plan->is_featured ? 'featured' : '' }}">
                            @if($plan->is_featured)
                                <span class="badge bg-success position-absolute top-0 end-0 m-3 px-3 py-2 rounded-pill">PALING POPULER</span>
                            @endif
                            <h4 class="fw-bold mb-1">{{ $plan->name }}</h4>
                            <p class="text-muted small mb-3">{{ $plan->description }}</p>

                            <div class="p-3 rounded-3 bg-light border mb-4" style="font-variant-numeric: tabular-nums;">
                                <div class="d-flex align-items-baseline justify-content-between flex-wrap gap-2">
                                    <div>
                                        <span class="display-6 fw-bold text-dark price-display"
                                              data-monthly="Rp{{ number_format((float)$plan->price_monthly, 0, ',', '.') }}"
                                              data-yearly="Rp{{ number_format((float)$plan->price_yearly, 0, ',', '.') }}">
                                            Rp{{ number_format((float)$plan->price_monthly, 0, ',', '.') }}
                                        </span>
                                        <span class="text-muted cycle-label">/ bulan</span>
                                    </div>
                                    @if($lpSavePct > 0)
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill lp-save-badge d-none">
                                            Hemat {{ $lpSavePct }}%
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <ul class="list-unstyled d-grid gap-2 mb-4 small">
                                <li><i class="fa-solid fa-check text-success me-2"></i>{{ $plan->max_users ? "Hingga {$plan->max_users} akun pengguna (Admin & Kasir)" : 'Akun pengguna tanpa batas' }}</li>
                                <li><i class="fa-solid fa-check text-success me-2"></i>{{ $plan->max_menus ? "Hingga {$plan->max_menus} menu produk" : 'Jumlah menu produk tanpa batas' }}</li>
                                <li><i class="fa-solid fa-check text-success me-2"></i>Kasir POS Offline-First, Hold Order &amp; Auto Sync</li>
                                <li><i class="fa-solid fa-check text-success me-2"></i>Struk Thermal, Kirim Struk WA &amp; PDF nama toko sendiri</li>
                                <li><i class="fa-solid fa-check text-success me-2"></i>Pengeluaran Kas, Estimasi Kas Laci &amp; Laba Bersih</li>
                                @if($plan->hasFeature('qris_dynamic'))
                                    <li class="fw-semibold text-success"><i class="fa-solid fa-star text-warning me-2"></i>QRIS Dinamis + Auto-Verify Android Listener</li>
                                @else
                                    <li class="text-muted"><i class="fa-solid fa-xmark text-secondary me-2"></i>QRIS Dinamis + Auto-Verify Android Listener</li>
                                @endif
                            </ul>

                            <a href="{{ route('register') }}" class="btn {{ $plan->is_featured ? 'btn-kl-primary' : 'btn-outline-success' }} w-100 py-2 rounded-pill fw-bold mt-auto">
                                Mulai Uji Coba Gratis {{ $trialDays }} Hari
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="py-5">
        <div class="container py-3" style="max-width: 820px;">
            <div class="text-center mb-4">
                <h3 class="fw-bold">Pertanyaan yang Sering Ditanyakan</h3>
            </div>
            <div class="accordion accordion-flush border rounded-4 overflow-hidden shadow-sm" id="klFaq">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                            Apakah data penjualan saya aman jika masa langganan habis?
                        </button>
                    </h2>
                    <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#klFaq">
                        <div class="accordion-body text-muted small">
                            Ya, 100% aman. Setelah masa aktif habis, Anda mendapat masa tenggang 3 hari. Jika belum diperpanjang, akun masuk mode baca saja (Read-Only): Anda tetap bisa login untuk melihat atau mengunduh laporan lama kapan pun.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                            Apakah bisa dipakai di HP Android atau laptop biasa?
                        </button>
                    </h2>
                    <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#klFaq">
                        <div class="accordion-body text-muted small">
                            Bisa. {{ $platformName ?? 'KasirLoka' }} berbasis web PWA yang responsif dan ringan. Cukup buka lewat browser Chrome di HP, tablet, atau laptop tanpa perlu beli mesin kasir mahal.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                            Bagaimana cara memperpanjang paket langganan?
                        </button>
                    </h2>
                    <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#klFaq">
                        <div class="accordion-body text-muted small">
                            Di dalam dashboard, buka menu <strong>Langganan &amp; Paket</strong>, pilih Starter atau Pro, lalu bayar melalui QRIS Dinamis (otomatis aktif dalam hitungan detik) atau Transfer Bank dengan kode unik.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-4 bg-dark text-white-50 text-center">
        <div class="container">
            <div class="fw-bold text-white mb-1">{{ $platformName ?? 'KasirLoka' }}</div>
            @if(!empty($landingWaClean))
                <div class="mb-2">
                    <a href="https://wa.me/{{ $landingWaClean }}?text={{ urlencode($landingWaMsg) }}" target="_blank" rel="noopener noreferrer" class="text-success text-decoration-none small fw-semibold">
                        <i class="fa-brands fa-whatsapp me-1"></i>Hubungi Tim Support WhatsApp
                    </a>
                </div>
            @endif
            <small>&copy; {{ date('Y') }} {{ $platformName ?? 'KasirLoka' }}. Seluruh hak cipta dilindungi.</small>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const btnMonthly = document.getElementById('btnCycleMonthly');
        const btnYearly = document.getElementById('btnCycleYearly');
        const prices = document.querySelectorAll('.price-display');
        const labels = document.querySelectorAll('.cycle-label');

        function setCycle(cycle) {
            const isYearly = cycle === 'yearly';
            prices.forEach(el => {
                el.textContent = isYearly ? el.dataset.yearly : el.dataset.monthly;
            });
            labels.forEach(el => {
                el.textContent = isYearly ? '/ tahun' : '/ bulan';
            });
            document.querySelectorAll('.lp-save-badge').forEach(el => {
                el.classList.toggle('d-none', !isYearly);
            });
            btnMonthly.classList.toggle('btn-success', !isYearly);
            btnMonthly.classList.toggle('btn-light', isYearly);
            btnYearly.classList.toggle('btn-success', isYearly);
            btnYearly.classList.toggle('btn-light', !isYearly);
        }

        btnMonthly?.addEventListener('click', () => setCycle('monthly'));
        btnYearly?.addEventListener('click', () => setCycle('yearly'));
    </script>
</body>
</html>
