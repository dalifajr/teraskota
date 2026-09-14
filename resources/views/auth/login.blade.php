<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Login - Teras Kota Berlian Makmur</title>

    <!-- PWA -->
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="theme-color" content="#14532d">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">

    <!-- Bootstrap 5 CSS -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- FontAwesome Icons -->
    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        rel="stylesheet"
    >

    <!-- Custom Style -->
    <link
        href="{{ asset('assets/css/custom.css') }}"
        rel="stylesheet"
    >

    <style>
        .login-brand-icon {
            width: 64px;
            height: 64px;
            background: rgba(163, 230, 53, 0.15);
            color: var(--light-accent);
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 1rem;
            border: 1px solid rgba(163, 230, 53, 0.3);
        }
        .form-control:focus {
            border-color: var(--accent-green);
            box-shadow: 0 0 0 0.25rem rgba(46, 125, 50, 0.15);
        }
        .input-group-text {
            background-color: #f8fafc;
            border-color: #e2e8f0;
        }
    </style>
</head>
<body class="login-bg">

    <div class="login-card">
        <!-- Brand Header -->
        <div class="text-center mb-4">
            <div class="login-brand-icon">
                <i class="fa-solid fa-mug-hot"></i>
            </div>
            <h3 class="fw-bold text-dark mb-1">Teras Kota</h3>
            <span class="badge bg-success bg-opacity-10 text-success fw-medium px-3 py-1 rounded-pill mb-2">
                Berlian Makmur
            </span>
            <p class="text-muted small mb-0">Masuk untuk mengakses sistem penjualan & POS kasir</p>
        </div>

        <!-- Flash Alerts -->
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 border-start border-4 border-success shadow-sm py-2 px-3 mb-3 small" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fa-solid fa-circle-check text-success me-2 fs-6"></i>
                    <div>{{ session('success') }}</div>
                </div>
                <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Tutup"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 border-start border-4 border-danger shadow-sm py-2 px-3 mb-3 small" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fa-solid fa-triangle-exclamation text-danger me-2 fs-6"></i>
                    <div>{{ session('error') }}</div>
                </div>
                <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Tutup"></button>
            </div>
        @endif

        @if (isset($errors) && $errors->any())
            <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 border-start border-4 border-danger shadow-sm py-2 px-3 mb-3 small" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fa-solid fa-circle-xmark text-danger me-2 fs-6"></i>
                    <div>{{ $errors->first() }}</div>
                </div>
                <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Tutup"></button>
            </div>
        @endif

        <!-- Login Form -->
        <form action="{{ route('login') }}" method="POST">
            @csrf

            <!-- Username / Email -->
            <div class="mb-3">
                <label for="username" class="form-label fw-semibold text-dark small mb-1">
                    Username atau Email
                </label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fa-solid fa-user text-muted"></i>
                    </span>
                    <input
                        type="text"
                        name="username"
                        id="username"
                        class="form-control @error('username') is-invalid @enderror"
                        placeholder="Masukkan username atau email"
                        value="{{ old('username') }}"
                        required
                        autofocus
                        autocomplete="username"
                    >
                </div>
            </div>

            <!-- Password -->
            <div class="mb-3">
                <label for="password" class="form-label fw-semibold text-dark small mb-1">
                    Kata Sandi
                </label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fa-solid fa-lock text-muted"></i>
                    </span>
                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="form-control @error('password') is-invalid @enderror"
                        placeholder="••••••••"
                        required
                        autocomplete="current-password"
                    >
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        id="togglePassword"
                        tabindex="-1"
                        aria-label="Tampilkan atau sembunyikan kata sandi"
                    >
                        <i class="fa-solid fa-eye" id="togglePasswordIcon"></i>
                    </button>
                </div>
                @error('password')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <!-- Remember Me -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="remember"
                        id="remember"
                        {{ old('remember') ? 'checked' : '' }}
                    >
                    <label class="form-check-label text-muted small user-select-none" for="remember">
                        Ingat saya di perangkat ini
                    </label>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-primary-green w-100 py-2 fs-6 shadow-sm">
                <i class="fa-solid fa-right-to-bracket me-2"></i> Masuk
            </button>
        </form>

        <!-- Card Footer -->
        <div class="text-center mt-4 pt-2 border-top">
            <small class="text-muted">
                &copy; {{ date('Y') }} Teras Kota Berlian Makmur. All rights reserved.
            </small>
        </div>
    </div>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Password Visibility Toggle & Service Worker -->
    <script>
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const togglePasswordIcon = document.getElementById('togglePasswordIcon');

        togglePassword?.addEventListener('click', function () {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            togglePasswordIcon.classList.toggle('fa-eye', !isPassword);
            togglePasswordIcon.classList.toggle('fa-eye-slash', isPassword);
        });

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register("{{ asset('service-worker.js') }}").catch(function () {});
            });
        }
    </script>
</body>
</html>