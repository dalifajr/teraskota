<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Kasir POS') - Teras Kota Berlian Makmur</title>

    <!-- PWA -->
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="theme-color" content="#14532d">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom Style -->
    <link href="{{ asset('assets/css/custom.css') }}" rel="stylesheet">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            background-color: #f1f5f9;
            overflow-x: hidden;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Dedicated POS Navbar */
        .pos-navbar {
            background-color: var(--primary-green);
            color: #ffffff;
            padding: 0.6rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            z-index: 100;
        }

        .pos-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.25rem;
            font-weight: 700;
            color: #ffffff;
            text-decoration: none;
        }

        .pos-brand span {
            color: var(--light-accent);
        }

        .pos-clock {
            font-size: 0.9rem;
            background: rgba(255, 255, 255, 0.1);
            padding: 0.35rem 0.8rem;
            border-radius: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pos-user-badge {
            background: rgba(163, 230, 53, 0.18);
            color: var(--light-accent);
            padding: 0.35rem 0.9rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(163, 230, 53, 0.3);
        }

        .pos-btn-nav {
            background: rgba(255, 255, 255, 0.12);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }

        .pos-btn-nav:hover {
            background: rgba(255, 255, 255, 0.25);
            color: #ffffff;
            transform: translateY(-1px);
        }

        .pos-btn-logout {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .pos-btn-logout:hover {
            background: #dc2626;
            color: #ffffff;
        }

        .pos-content-wrapper {
            flex: 1;
            padding: 1rem;
            overflow: hidden;
            display: flex;
        }

        @media (max-width: 991px) {
            body {
                height: auto;
                overflow-y: auto;
            }
            .pos-content-wrapper {
                overflow: visible;
                display: block;
            }
        }

        /* Print Isolation: Only print thermal receipt if window.print is triggered directly */
        @media print {
            body {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
                height: auto !important;
                min-height: auto !important;
                overflow: visible !important;
            }

            .pos-navbar,
            .pos-content-wrapper,
            .modal-backdrop,
            .modal-header,
            .modal-footer,
            .no-print {
                display: none !important;
            }

            .modal {
                position: static !important;
                display: block !important;
                padding: 0 !important;
                overflow: visible !important;
            }

            .modal-dialog {
                margin: 0 !important;
                max-width: 80mm !important;
                transform: none !important;
            }

            .modal-content {
                border: none !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                background: transparent !important;
            }

            .modal-body {
                padding: 0 !important;
                overflow: visible !important;
                max-height: none !important;
            }

            #printableReceipt,
            #printableReceipt * {
                visibility: visible !important;
            }

            #printableReceipt {
                width: 80mm !important;
                max-width: 100% !important;
                margin: 0 auto !important;
                padding: 2mm 3mm !important;
                box-shadow: none !important;
                border: none !important;
                background: #ffffff !important;
                font-family: 'Courier New', Courier, monospace !important;
            }

            @page {
                size: 80mm auto;
                margin: 0;
            }
        }

        /* POS Orientation Warning (Mobile Portrait Mode) */
        .pos-portrait-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(17, 54, 27, 0.97);
            backdrop-filter: blur(10px);
            z-index: 99999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .pos-prompt-card {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            max-width: 380px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
        }

        .pos-rotate-phone-icon {
            font-size: 2.8rem;
            color: var(--light-accent);
            display: inline-flex;
            align-items: center;
            gap: 12px;
            animation: phoneRotateAnim 2.5s infinite ease-in-out;
        }

        @keyframes phoneRotateAnim {
            0%, 15% { transform: rotate(0deg); }
            45%, 65% { transform: rotate(90deg); }
            90%, 100% { transform: rotate(0deg); }
        }

        @media (max-width: 767px) and (orientation: portrait) {
            .pos-portrait-overlay:not(.dismissed) {
                display: flex !important;
            }
        }

        @media (orientation: landscape), (min-width: 768px) {
            .pos-portrait-overlay {
                display: none !important;
            }
        }
    </style>

    @yield('styles')
</head>
<body>

    <!-- POS Orientation Prompt for Mobile Portrait -->
    <div id="posOrientationPrompt" class="pos-portrait-overlay">
        <div class="pos-prompt-card text-center p-4">
            <div class="pos-rotate-phone-icon mb-3">
                <i class="fa-solid fa-mobile-screen-button"></i>
                <i class="fa-solid fa-rotate-right text-warning fs-4"></i>
            </div>
            <h5 class="fw-bold text-white mb-2">Gunakan Mode Landscape / Desktop</h5>
            <p class="text-white-50 small mb-4">
                Terminal Kasir POS membutuhkan ruang horizontal untuk katalog menu dan keranjang kasir. Silakan putar ponsel ke posisi <strong>Landscape (Mendatar)</strong> atau gunakan tablet/PC untuk transaksi yang optimal.
            </p>
            <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-4" onclick="document.getElementById('posOrientationPrompt').classList.add('dismissed')">
                Tetap Lanjutkan (Abaikan)
            </button>
        </div>
    </div>

    <!-- Top POS Navbar -->
    <header class="pos-navbar">
        <!-- Brand -->
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('pos.index') }}" class="pos-brand">
                <i class="fa-solid fa-mug-hot text-warning"></i>
                <span>Teras Kota</span> <small class="text-white opacity-75 fw-normal d-none d-md-inline">POS</small>
            </a>
            <div class="pos-user-badge">
                <i class="fa-solid fa-user-tag"></i>
                <span>{{ Auth::user()->name }}</span>
            </div>
        </div>

        <!-- Middle: Digital Live Clock -->
        <div class="pos-clock d-none d-lg-flex">
            <i class="fa-regular fa-clock text-warning"></i>
            <span id="posLiveClock">--:--:--</span>
            <span class="opacity-50">|</span>
            <span id="posLiveDate">--</span>
        </div>

        <!-- Right: Actions -->
        <div class="d-flex align-items-center gap-2">
            <!-- Summary Modal Button -->
            <button type="button" class="pos-btn-nav" id="btnOpenSummary" title="Ringkasan Penjualan Kasir Hari Ini">
                <i class="fa-solid fa-chart-pie text-warning"></i>
                <span class="d-none d-sm-inline">Ringkasan Kasir</span>
            </button>

            <!-- Fullscreen Toggle -->
            <button type="button" class="pos-btn-nav d-none d-sm-inline-flex" id="btnToggleFullscreen" title="Layar Penuh (F11)">
                <i class="fa-solid fa-expand"></i>
            </button>

            <!-- If Admin, link to Admin Dashboard -->
            @if(Auth::user()->isAdmin())
                <a href="{{ route('dashboard') }}" class="pos-btn-nav" title="Kembali ke Dashboard Admin">
                    <i class="fa-solid fa-gauge-high text-info"></i>
                    <span class="d-none d-md-inline">Dashboard Admin</span>
                </a>
            @endif

            <!-- Logout -->
            <form action="{{ route('logout') }}" method="POST" id="posLogoutForm" class="d-none">
                @csrf
            </form>
            <button type="button" class="pos-btn-nav pos-btn-logout" id="btnConfirmLogout" title="Keluar dari Aplikasi">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span class="d-none d-sm-inline">Keluar</span>
            </button>
        </div>
    </header>

    <!-- Main POS Container -->
    <main class="pos-content-wrapper">
        @yield('content')
    </main>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Live Clock
        function updateClock() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('id-ID', { hour12: false });
            const dateStr = now.toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
            
            const clockEl = document.getElementById('posLiveClock');
            const dateEl = document.getElementById('posLiveDate');
            if (clockEl) clockEl.textContent = timeStr;
            if (dateEl) dateEl.textContent = dateStr;
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Fullscreen Toggle
        document.getElementById('btnToggleFullscreen')?.addEventListener('click', function() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(() => {});
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
            }
        });

        // Logout Confirmation with SweetAlert2
        document.getElementById('btnConfirmLogout')?.addEventListener('click', function() {
            Swal.fire({
                title: 'Konfirmasi Keluar',
                text: 'Apakah Anda yakin ingin keluar dari sesi kasir ini?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                confirmButtonText: '<i class="fa-solid fa-right-from-bracket me-1"></i> Ya, Keluar',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('posLogoutForm').submit();
                }
            });
        });
    </script>

    @yield('scripts')
</body>
</html>
