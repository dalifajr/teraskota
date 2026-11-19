<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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

        <form action="{{ route('register') }}" method="POST">
            @csrf
            <div class="row g-3">
                <div class="col-12">
                    <small class="text-uppercase fw-bold text-success d-block border-bottom pb-1">1. Informasi Usaha</small>
                </div>
                <div class="col-md-7">
                    <label class="form-label small fw-semibold mb-1">Nama Usaha / Toko <span class="text-danger">*</span></label>
                    <input type="text" name="store_name" class="form-control" placeholder="cth: Kedai Kopi Senja" value="{{ old('store_name') }}" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-semibold mb-1">Jenis Usaha <span class="text-danger">*</span></label>
                    <select name="business_type" class="form-select" required>
                        @foreach(['Kedai Minuman / Kopi', 'Warung Makan / Resto', 'Booth / Gerobak F&B', 'Toko Kelontong / Retail', 'Bakery / Kue', 'Lainnya'] as $type)
                            <option value="{{ $type }}" {{ old('business_type') === $type ? 'selected' : '' }}>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold mb-1">No. WhatsApp / Telepon</label>
                    <input type="text" name="phone" class="form-control" placeholder="08xxxxxxxxxx" value="{{ old('phone') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold mb-1">Kota / Alamat Singkat</label>
                    <input type="text" name="address" class="form-control" placeholder="cth: Jl. Merdeka No. 10" value="{{ old('address') }}">
                </div>

                <div class="col-12 mt-3">
                    <small class="text-uppercase fw-bold text-success d-block border-bottom pb-1">2. Akun Pemilik (Admin)</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold mb-1">Nama Lengkap Pemilik <span class="text-danger">*</span></label>
                    <input type="text" name="owner_name" class="form-control" placeholder="Nama Anda" value="{{ old('owner_name') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold mb-1">Username Login <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control" placeholder="cth: kopisenja" value="{{ old('username') }}" required>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold mb-1">Email Aktif <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" placeholder="pemilik@usaha.com" value="{{ old('email') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold mb-1">Kata Sandi <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold mb-1">Ulangi Kata Sandi <span class="text-danger">*</span></label>
                    <input type="password" name="password_confirmation" class="form-control" placeholder="Ketik ulang kata sandi" required>
                </div>

                <div class="col-12">
                    <div class="form-check bg-light rounded-3 p-2 ps-5 border">
                        <input class="form-check-input" type="checkbox" name="seed_sample" value="1" id="seedSampleCheck" checked>
                        <label class="form-check-label small text-dark" for="seedSampleCheck">
                            <strong>Isi contoh kategori &amp; menu awal</strong> agar saya bisa langsung mencoba terminal kasir POS.
                        </label>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary-green w-100 py-2 mt-4 fs-6 shadow-sm">
                <i class="fa-solid fa-rocket me-2"></i>Buat Usaha &amp; Mulai Trial {{ $trialDays }} Hari
            </button>
        </form>

        <div class="text-center mt-3 pt-3 border-top">
            <small class="text-muted">
                Sudah punya akun? <a href="{{ route('login') }}" class="fw-bold text-success text-decoration-none">Masuk di sini</a>
                &middot; <a href="{{ route('landing') }}" class="text-muted text-decoration-none">Beranda</a>
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
