<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="theme-color" content="#14532d">
    <title>Daftar Usaha Baru - {{ $platformName ?? 'KasirLoka' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="{{ asset('assets/css/custom.css') }}?v={{ filemtime(public_path('assets/css/custom.css')) }}" rel="stylesheet">
    <style>
        .register-card {
            background: #ffffff;
            border-radius: 22px;
            padding: 2rem;
            width: 100%;
            max-width: 560px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
        }
    </style>
</head>
<body class="login-bg py-4 px-3">

    <div class="register-card">
        <div class="text-center mb-4">
            <a href="{{ route('landing') }}" class="text-decoration-none d-inline-flex align-items-center justify-content-center rounded-4 shadow-sm mb-2" style="width: 54px; height: 54px; background: linear-gradient(135deg, #11361b 0%, #166534 100%); color: #a3e635; font-size: 1.4rem;">
                <i class="fa-solid fa-store"></i>
            </a>
            <h4 class="fw-bold text-dark mb-1">Daftarkan Usaha Anda</h4>
            <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1">
                Gratis Uji Coba {{ $trialDays }} Hari Penuh · {{ $platformName ?? 'KasirLoka' }}
            </span>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger rounded-3 py-2 px-3 small mb-3">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(isset($registrationEnabled) && !$registrationEnabled)
            @php
                $regWaClean = preg_replace('/[^0-9]/', '', (string) ($supportWhatsapp ?? ''));
                if (str_starts_with($regWaClean, '0')) {
                    $regWaClean = '62' . substr($regWaClean, 1);
                }
            @endphp
            <div class="text-center py-4">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-warning bg-opacity-10 text-warning mb-3" style="width: 64px; height: 64px; font-size: 1.8rem;">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">Pendaftaran Usaha Baru Ditutup Sementara</h5>
                <p class="text-muted small mb-4">
                    Pengelola {{ $platformName ?? 'KasirLoka' }} sedang menutup sementara pendaftaran mandiri. Hubungi tim support kami untuk aktivasi akun baru.
                </p>
                <div class="d-flex justify-content-center flex-wrap gap-2">
                    @if($regWaClean !== '')
                        <a href="https://wa.me/{{ $regWaClean }}?text={{ urlencode('Halo Admin ' . ($platformName ?? 'KasirLoka') . ', saya ingin mendaftarkan usaha baru.') }}" target="_blank" rel="noopener noreferrer" class="btn btn-success rounded-pill px-4 fw-semibold">
                            <i class="fa-brands fa-whatsapp me-2"></i>Hubungi Support WA
                        </a>
                    @endif
                    <a href="{{ route('login') }}" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold">Kembali ke Login</a>
                </div>
            </div>
        @else
        <form action="{{ route('register') }}" method="POST">
            @csrf
            <div class="row g-3">
                <div class="col-12">
                    <small class="text-uppercase fw-bold text-success d-block border-bottom pb-1">1. Informasi Usaha</small>
                </div>
                <div class="col-md-7">
                    <label for="regStoreName" class="form-label small fw-semibold mb-1">Nama Usaha / Toko <span class="text-danger">*</span></label>
                    <input type="text" name="store_name" id="regStoreName" class="form-control" placeholder="cth: Kedai Kopi Senja" value="{{ old('store_name') }}" autocomplete="organization" required>
                </div>
                <div class="col-md-5">
                    <label for="regBusinessType" class="form-label small fw-semibold mb-1">Jenis Usaha <span class="text-danger">*</span></label>
                    <select name="business_type" id="regBusinessType" class="form-select" required>
                        @foreach(['Kedai Minuman / Kopi', 'Warung Makan / Resto', 'Bakery / Kue', 'Toko Kelontong / Retail', 'Booth / Gerobak F&B', 'Lainnya'] as $type)
                            <option value="{{ $type }}" {{ old('business_type') === $type ? 'selected' : '' }}>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="regPhone" class="form-label small fw-semibold mb-1">No. WhatsApp / Telepon</label>
                    <input type="tel" name="phone" id="regPhone" class="form-control" inputmode="tel" autocomplete="tel" placeholder="08xxxxxxxxxx" value="{{ old('phone') }}">
                </div>
                <div class="col-md-6">
                    <label for="regAddress" class="form-label small fw-semibold mb-1">Kota / Alamat Singkat</label>
                    <input type="text" name="address" id="regAddress" class="form-control" autocomplete="street-address" placeholder="cth: Jl. Merdeka No. 10" value="{{ old('address') }}">
                </div>

                <div class="col-12 mt-3">
                    <small class="text-uppercase fw-bold text-success d-block border-bottom pb-1">2. Akun Pemilik (Admin)</small>
                </div>
                <div class="col-md-6">
                    <label for="regOwnerName" class="form-label small fw-semibold mb-1">Nama Lengkap Pemilik <span class="text-danger">*</span></label>
                    <input type="text" name="owner_name" id="regOwnerName" class="form-control" autocomplete="name" placeholder="Nama Anda" value="{{ old('owner_name') }}" required>
                </div>
                <div class="col-md-6">
                    <label for="regUsername" class="form-label small fw-semibold mb-1">Username Login <span class="text-danger">*</span></label>
                    <input type="text" name="username" id="regUsername" class="form-control" autocomplete="username" placeholder="cth: kopisenja" value="{{ old('username') }}" required>
                </div>
                <div class="col-12">
                    <label for="regEmail" class="form-label small fw-semibold mb-1">Email Aktif <span class="text-danger">*</span></label>
                    <input type="email" name="email" id="regEmail" class="form-control" autocomplete="email" placeholder="pemilik@usaha.com" value="{{ old('email') }}" required>
                </div>
                <div class="col-md-6">
                    <label for="regPassword" class="form-label small fw-semibold mb-1">Kata Sandi <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" name="password" id="regPassword" class="form-control" autocomplete="new-password" placeholder="Minimal 6 karakter" minlength="6" required>
                        <button type="button" class="btn btn-outline-secondary js-reg-toggle-pwd" data-target="regPassword" aria-label="Tampilkan atau sembunyikan kata sandi">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="regPasswordConf" class="form-label small fw-semibold mb-1">Ulangi Kata Sandi <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" name="password_confirmation" id="regPasswordConf" class="form-control" autocomplete="new-password" placeholder="Ketik ulang kata sandi" minlength="6" required>
                        <button type="button" class="btn btn-outline-secondary js-reg-toggle-pwd" data-target="regPasswordConf" aria-label="Tampilkan atau sembunyikan konfirmasi kata sandi">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="col-12">
                    <div class="form-check bg-light rounded-3 p-2 ps-5 border">
                        <input class="form-check-input" type="checkbox" name="seed_sample" value="1" id="seedSampleCheck" checked>
                        <label class="form-check-label small text-dark" for="seedSampleCheck">
                            <strong>Isi contoh kategori &amp; menu awal sesuai jenis usaha</strong> agar langsung siap mencoba kasir POS.
                        </label>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary-green w-100 py-2 mt-4 fs-6 shadow-sm">
                <i class="fa-solid fa-rocket me-2"></i>Buat Usaha &amp; Mulai Trial {{ $trialDays }} Hari
            </button>
        </form>
        @endif

        <div class="text-center mt-3 pt-3 border-top">
            <small class="text-muted">
                Sudah punya akun? <a href="{{ route('login') }}" class="fw-bold text-success text-decoration-none">Masuk di sini</a>
                &middot; <a href="{{ route('landing') }}" class="text-muted text-decoration-none">Beranda</a>
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('assets/js/ui-transitions.js') }}?v={{ filemtime(public_path('assets/js/ui-transitions.js')) }}"></script>
    <script>
        (function () {
            // Smart auto-suggest username from store name unless user manually edited it
            const storeInput = document.getElementById('regStoreName');
            const userInput = document.getElementById('regUsername');
            let userTouchedUsername = Boolean(userInput && userInput.value.trim() !== '');

            userInput?.addEventListener('input', function () {
                userTouchedUsername = this.value.trim() !== '';
            });

            storeInput?.addEventListener('input', function () {
                if (userTouchedUsername || !userInput) return;
                const slug = this.value
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '')
                    .slice(0, 24);
                userInput.value = slug;
            });

            // Password visibility toggles
            document.querySelectorAll('.js-reg-toggle-pwd').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const target = document.getElementById(btn.getAttribute('data-target'));
                    const icon = btn.querySelector('i');
                    if (!target || !icon) return;
                    const isPwd = target.type === 'password';
                    target.type = isPwd ? 'text' : 'password';
                    icon.classList.toggle('fa-eye', !isPwd);
                    icon.classList.toggle('fa-eye-slash', isPwd);
                });
            });
        })();
    </script>
</body>
</html>
