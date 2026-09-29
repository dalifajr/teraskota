<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Teras Kota Berlian Makmur') - Dashboard Penjualan</title>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<meta name="theme-color" content="#14532d">
<link
    rel="apple-touch-icon"
    href="{{ asset('icons/icon-192.png') }}"
>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom Style -->
    <link href="{{ asset('assets/css/custom.css') }}" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    @yield('styles')
</head>
<body>

    <!-- Desktop Sidebar (Visible on >= 992px) -->
    <div class="sidebar d-none d-lg-block" id="sidebar">
        <div class="sidebar-brand d-flex align-items-center gap-2 text-truncate">
            <img src="{{ asset('assets/images/logo.png') }}" alt="Teras Kota Logo" class="rounded-circle shadow-sm flex-shrink-0" style="width: 38px; height: 38px; object-fit: cover; background: #fff;">
            <div class="d-flex flex-column text-truncate" style="line-height: 1.15;">
                <span class="text-truncate fw-bold text-white fs-6">Teras Kota</span>
                <small class="text-white-50" style="font-size: 0.7rem; letter-spacing: 0.5px;">INDONESIA</small>
            </div>
        </div>
        <ul class="sidebar-menu">
            <li class="sidebar-item {{ Request::routeIs('dashboard') ? 'active' : '' }}">
                <a href="{{ route('dashboard') }}" class="sidebar-link">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="sidebar-item mb-3">
                <a href="{{ route('pos.index') }}" class="sidebar-link btn-light-accent text-dark fw-bold py-2 shadow-sm" target="_blank">
                    <i class="fa-solid fa-cash-register text-success"></i>
                    <span>Buka Kasir POS <i class="fa-solid fa-arrow-up-right-from-square small ms-1"></i></span>
                </a>
            </li>
            <li class="sidebar-item {{ Request::routeIs('transactions.index') || Request::routeIs('transactions.show') || Request::routeIs('transactions.edit') ? 'active' : '' }}">
                <a href="{{ route('transactions.index') }}" class="sidebar-link">
                    <i class="fa-solid fa-history"></i>
                    <span>Riwayat Transaksi</span>
                </a>
            </li>
            <li class="sidebar-item {{ Request::routeIs('menus.*') ? 'active' : '' }}">
                <a href="{{ route('menus.index') }}" class="sidebar-link">
                    <i class="fa-solid fa-coffee"></i>
                    <span>Data Menu</span>
                </a>
            </li>
            <li class="sidebar-item {{ Request::routeIs('categories.*') ? 'active' : '' }}">
                <a href="{{ route('categories.index') }}" class="sidebar-link">
                    <i class="fa-solid fa-tags"></i>
                    <span>Kategori</span>
                </a>
            </li>
            <li class="sidebar-item {{ Request::routeIs('reports.*') ? 'active' : '' }}">
                <a href="{{ route('reports.index') }}" class="sidebar-link">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                    <span>Laporan</span>
                </a>
            <!-- Grouped Settings Menu -->
            <li class="sidebar-item {{ Request::routeIs('settings.*') ? 'active' : '' }}">
                <a href="#settingsSubmenuDesktop" data-bs-toggle="collapse" class="sidebar-link d-flex align-items-center justify-content-between {{ Request::routeIs('settings.*') ? '' : 'collapsed' }}" aria-expanded="{{ Request::routeIs('settings.*') ? 'true' : 'false' }}">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-sliders"></i>
                        <span>Pengaturan</span>
                    </div>
                    <i class="fa-solid fa-chevron-down sub-arrow small"></i>
                </a>
                <div class="collapse {{ Request::routeIs('settings.*') ? 'show' : '' }}" id="settingsSubmenuDesktop">
                    <ul class="submenu">
                        <li class="submenu-item {{ Request::routeIs('settings.qris.*') ? 'active' : '' }}">
                            <a href="{{ route('settings.qris.index') }}" class="submenu-link">
                                <i class="fa-solid fa-qrcode me-2"></i> Pengaturan QRIS
                            </a>
                        </li>
                        <li class="submenu-item {{ Request::routeIs('settings.profit.*') ? 'active' : '' }}">
                            <a href="{{ route('settings.profit.edit') }}" class="submenu-link">
                                <i class="fa-solid fa-percent me-2"></i> Pengaturan Profit
                            </a>
                        </li>
                        <li class="submenu-item {{ Request::routeIs('settings.update.*') ? 'active' : '' }}">
                            <a href="{{ route('settings.update.index') }}" class="submenu-link">
                                <i class="fa-brands fa-github me-2"></i> Update Sistem
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="sidebar-item {{ Request::routeIs('users.*') ? 'active' : '' }}">
                <a href="{{ route('users.index') }}" class="sidebar-link">
                    <i class="fa-solid fa-users-gear"></i>
                    <span>Kelola Pengguna</span>
                </a>
            </li>
            <li class="sidebar-item {{ Request::routeIs('profile.edit') ? 'active' : '' }}">
                <a href="{{ route('profile.edit') }}" class="sidebar-link">
                    <i class="fa-solid fa-user-gear"></i>
                    <span>Profil Akun</span>
                </a>
            </li>
            <li class="sidebar-item mt-4">
                <form action="{{ route('logout') }}" method="POST" id="logout-form" class="d-none">
                    @csrf
                </form>
                <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="sidebar-link text-danger">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Mobile Offcanvas Drawer (< 992px) -->
    <div class="offcanvas offcanvas-start offcanvas-sidebar d-lg-none" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
        <div class="offcanvas-header d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2 text-white text-truncate">
                <img src="{{ asset('assets/images/logo.png') }}" alt="Teras Kota Logo" class="rounded-circle shadow-sm flex-shrink-0" style="width: 34px; height: 34px; object-fit: cover; background: #fff;">
                <div class="d-flex flex-column text-truncate" style="line-height: 1.15;">
                    <span class="fw-bold fs-6 text-white text-truncate" id="sidebarOffcanvasLabel">Teras Kota</span>
                    <small class="text-white-50" style="font-size: 0.68rem; letter-spacing: 0.5px;">INDONESIA</small>
                </div>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
        </div>
        <div class="offcanvas-body">
            <!-- Full Navigation Menu -->
            <ul class="sidebar-menu mb-0">
                <li class="sidebar-item {{ Request::routeIs('dashboard') ? 'active' : '' }}">
                    <a href="{{ route('dashboard') }}" class="sidebar-link">
                        <i class="fa-solid fa-chart-line"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="sidebar-item mb-2">
                    <a href="{{ route('pos.index') }}" class="sidebar-link btn-light-accent text-dark fw-bold py-2 shadow-sm" target="_blank">
                        <i class="fa-solid fa-cash-register text-success"></i>
                        <span>Buka Kasir POS <i class="fa-solid fa-arrow-up-right-from-square small ms-1"></i></span>
                    </a>
                </li>
                <li class="sidebar-item {{ Request::routeIs('transactions.index') || Request::routeIs('transactions.show') || Request::routeIs('transactions.edit') ? 'active' : '' }}">
                    <a href="{{ route('transactions.index') }}" class="sidebar-link">
                        <i class="fa-solid fa-history"></i>
                        <span>Riwayat Transaksi</span>
                    </a>
                </li>
                <li class="sidebar-item {{ Request::routeIs('menus.*') ? 'active' : '' }}">
                    <a href="{{ route('menus.index') }}" class="sidebar-link">
                        <i class="fa-solid fa-coffee"></i>
                        <span>Data Menu</span>
                    </a>
                </li>
                <li class="sidebar-item {{ Request::routeIs('categories.*') ? 'active' : '' }}">
                    <a href="{{ route('categories.index') }}" class="sidebar-link">
                        <i class="fa-solid fa-tags"></i>
                        <span>Kategori</span>
                    </a>
                </li>
                <li class="sidebar-item {{ Request::routeIs('reports.*') ? 'active' : '' }}">
                    <a href="{{ route('reports.index') }}" class="sidebar-link">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                        <span>Laporan</span>
                    </a>
                </li>
                <!-- Grouped Settings Menu (Mobile) -->
                <li class="sidebar-item {{ Request::routeIs('settings.*') ? 'active' : '' }}">
                    <a href="#settingsSubmenuMobile" data-bs-toggle="collapse" class="sidebar-link d-flex align-items-center justify-content-between {{ Request::routeIs('settings.*') ? '' : 'collapsed' }}" aria-expanded="{{ Request::routeIs('settings.*') ? 'true' : 'false' }}">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-sliders"></i>
                            <span>Pengaturan</span>
                        </div>
                        <i class="fa-solid fa-chevron-down sub-arrow small"></i>
                    </a>
                    <div class="collapse {{ Request::routeIs('settings.*') ? 'show' : '' }}" id="settingsSubmenuMobile">
                        <ul class="submenu">
                            <li class="submenu-item {{ Request::routeIs('settings.qris.*') ? 'active' : '' }}">
                                <a href="{{ route('settings.qris.index') }}" class="submenu-link">
                                    <i class="fa-solid fa-qrcode me-2"></i> Pengaturan QRIS
                                </a>
                            </li>
                            <li class="submenu-item {{ Request::routeIs('settings.profit.*') ? 'active' : '' }}">
                                <a href="{{ route('settings.profit.edit') }}" class="submenu-link">
                                    <i class="fa-solid fa-percent me-2"></i> Pengaturan Profit
                                </a>
                            </li>
                            <li class="submenu-item {{ Request::routeIs('settings.update.*') ? 'active' : '' }}">
                                <a href="{{ route('settings.update.index') }}" class="submenu-link">
                                    <i class="fa-brands fa-github me-2"></i> Update Sistem
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                <li class="sidebar-item {{ Request::routeIs('users.*') ? 'active' : '' }}">
                    <a href="{{ route('users.index') }}" class="sidebar-link">
                        <i class="fa-solid fa-users-gear"></i>
                        <span>Kelola Pengguna</span>
                    </a>
                </li>
                <li class="sidebar-item {{ Request::routeIs('profile.edit') ? 'active' : '' }}">
                    <a href="{{ route('profile.edit') }}" class="sidebar-link">
                        <i class="fa-solid fa-user-gear"></i>
                        <span>Profil Akun</span>
                    </a>
                </li>
                <li class="sidebar-item mt-3 pt-3 border-top border-white border-opacity-10">
                    <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="sidebar-link text-danger">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        
        <!-- Top Sticky Navbar -->
        <div class="top-navbar">
            <div class="d-flex align-items-center">
                <button class="btn btn-outline-secondary d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Buka Menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="d-flex align-items-center gap-2">
                    <img src="{{ asset('assets/images/logo.png') }}" alt="Logo" class="d-lg-none rounded-circle" style="width: 28px; height: 28px; object-fit: cover;">
                    <h4 class="m-0 fw-semibold text-dark text-truncate">@yield('page_title', 'Teras Kota Berlian Makmur')</h4>
                </div>
            </div>
            
            <div class="d-flex align-items-center gap-2 gap-sm-3">
                @if(Auth::user()?->isAdmin())
                    <!-- Admin Browser Push Notification Toggle -->
                    <button type="button" class="btn btn-light rounded-circle position-relative p-2" id="btnToggleDesktopNotification" title="Notifikasi Desktop Pembayaran Masuk" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa-solid fa-bell text-secondary fs-5" id="bellIcon"></i>
                        <span class="position-absolute top-0 start-100 translate-middle p-1 bg-secondary border border-light rounded-circle" id="bellStatusDot" style="margin-top: 8px; margin-left: -8px;">
                            <span class="visually-hidden">Status Notifikasi</span>
                        </span>
                    </button>
                @endif

                <div class="text-end d-none d-sm-block">
                    <div class="fw-semibold text-dark">{{ Auth::user()?->name ?? 'Pengguna' }}</div>
                    <small class="text-muted">{{ Auth::user()?->email ?? '' }}</small>
                </div>
                <a href="{{ route('profile.edit') }}" class="nav-user text-decoration-none" title="Profil Akun">
                    <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()?->name ?? 'User') }}&background=11361b&color=ffffff" alt="Avatar">
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="container-fluid p-3 p-md-4">
            
            <!-- Breadcrumbs -->
            <nav aria-label="breadcrumb" class="mb-3 mb-md-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-success"><i class="fa-solid fa-home"></i> Home</a></li>
                    @yield('breadcrumbs')
                </ol>
            </nav>

            <!-- Flash Alert Notifications -->
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm border-0 border-start border-5 border-success mb-4" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fa-solid fa-circle-check fs-4 me-3 text-success"></i>
                        <div>{{ session('success') }}</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm border-0 border-start border-5 border-danger mb-4" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fa-solid fa-triangle-exclamation fs-4 me-3 text-danger"></i>
                        <div>{{ session('error') }}</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @yield('content')
        </div>

        <!-- Footer -->
        <footer class="mt-auto py-3 bg-white border-top text-center text-muted">
            <div class="container">
                <small>&copy; {{ date('Y') }} Teras Kota Berlian Makmur. All Rights Reserved.</small>
            </div>
        </footer>

        <!-- Mobile Bottom Navigation Bar (Visible on < 992px) -->
        <nav class="mobile-bottom-nav d-lg-none">
            <a href="{{ route('dashboard') }}" class="mobile-nav-item {{ Request::routeIs('dashboard') ? 'active' : '' }}">
                <i class="fa-solid fa-chart-line"></i>
                <span>Dashboard</span>
            </a>
            <a href="{{ route('menus.index') }}" class="mobile-nav-item {{ Request::routeIs('menus.*') ? 'active' : '' }}">
                <i class="fa-solid fa-coffee"></i>
                <span>Menu</span>
            </a>
            <a href="{{ route('pos.index') }}" class="mobile-nav-item mobile-nav-pos" target="_blank" title="Buka Kasir POS">
                <div class="pos-icon-circle">
                    <i class="fa-solid fa-cash-register"></i>
                </div>
                <span>Kasir</span>
            </a>
            <a href="{{ route('transactions.index') }}" class="mobile-nav-item {{ Request::routeIs('transactions.*') ? 'active' : '' }}">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span>Transaksi</span>
            </a>
            <button type="button" class="mobile-nav-item" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Menu Lengkap">
                <i class="fa-solid fa-bars"></i>
                <span>Lainnya</span>
            </button>
        </nav>
    </div>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker
                    .register("{{ asset('service-worker.js') }}")
                    .then(function (registration) {
                        console.log('Service Worker terdaftar:', registration.scope);
                    })
                    .catch(function (error) {
                        console.error('Service Worker gagal didaftarkan:', error);
                    });
            });
        }
    </script>

    @if(Auth::user()?->isAdmin())
    <!-- Desktop Notification & Audio Synthesizer Engine -->
    <script>
        (function() {
            const bellBtn = document.getElementById('btnToggleDesktopNotification');
            const bellIcon = document.getElementById('bellIcon');
            const bellDot = document.getElementById('bellStatusDot');

            function updateBellUI() {
                if (!('Notification' in window)) {
                    if (bellBtn) bellBtn.style.display = 'none';
                    return;
                }
                if (Notification.permission === 'granted') {
                    if (bellIcon) bellIcon.className = 'fa-solid fa-bell text-success fs-5';
                    if (bellDot) bellDot.className = 'position-absolute top-0 start-100 translate-middle p-1 bg-success border border-light rounded-circle';
                    if (bellBtn) bellBtn.setAttribute('title', 'Notifikasi Desktop Aktif (Klik untuk tes suara)');
                } else if (Notification.permission === 'denied') {
                    if (bellIcon) bellIcon.className = 'fa-solid fa-bell-slash text-danger fs-5';
                    if (bellDot) bellDot.className = 'position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle';
                    if (bellBtn) bellBtn.setAttribute('title', 'Notifikasi Desktop Diblokir oleh Browser');
                } else {
                    if (bellIcon) bellIcon.className = 'fa-solid fa-bell text-warning fs-5';
                    if (bellDot) bellDot.className = 'position-absolute top-0 start-100 translate-middle p-1 bg-warning border border-light rounded-circle';
                    if (bellBtn) bellBtn.setAttribute('title', 'Klik untuk mengaktifkan notifikasi pembayaran');
                }
            }

            // Web Audio Synthesizer Chime (Zero MP3 files, crisp & pleasant)
            function playChime() {
                try {
                    const ctx = new (window.AudioContext || window.webkitAudioContext)();
                    if (ctx.state === 'suspended') {
                        ctx.resume();
                    }
                    const now = ctx.currentTime;

                    const osc1 = ctx.createOscillator();
                    const gain1 = ctx.createGain();
                    osc1.type = 'triangle';
                    osc1.frequency.setValueAtTime(880, now); // A5
                    gain1.gain.setValueAtTime(0.15, now);
                    gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
                    osc1.connect(gain1);
                    gain1.connect(ctx.destination);
                    osc1.start(now);
                    osc1.stop(now + 0.35);

                    const osc2 = ctx.createOscillator();
                    const gain2 = ctx.createGain();
                    osc2.type = 'sine';
                    osc2.frequency.setValueAtTime(1318.51, now + 0.12); // E6
                    gain2.gain.setValueAtTime(0.25, now + 0.12);
                    gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.6);
                    osc2.connect(gain2);
                    gain2.connect(ctx.destination);
                    osc2.start(now + 0.12);
                    osc2.stop(now + 0.6);
                } catch(e) {
                    console.warn('Audio synthesis note:', e);
                }
            }

            if (bellBtn) {
                bellBtn.addEventListener('click', function() {
                    if (!('Notification' in window)) {
                        alert('Browser Anda tidak mendukung Web Notification API.');
                        return;
                    }

                    if (Notification.permission === 'default') {
                        Notification.requestPermission().then(function(permission) {
                            updateBellUI();
                            if (permission === 'granted') {
                                playChime();
                                new Notification('Teras Kota - Notifikasi Aktif', {
                                    body: 'Anda akan menerima notifikasi setiap ada transaksi berhasil.',
                                    icon: "{{ asset('assets/images/logo.png') }}"
                                });
                            }
                        });
                    } else if (Notification.permission === 'granted') {
                        playChime();
                        new Notification('Tes Notifikasi Teras Kota', {
                            body: 'Audio chime dan notifikasi desktop berfungsi normal!',
                            icon: "{{ asset('assets/images/logo.png') }}"
                        });
                    } else {
                        alert('Izin notifikasi diblokir di browser Anda. Silakan klik ikon gembok di sebelah URL browser dan izinkan "Notifications".');
                    }
                });
            }

            updateBellUI();

            // Background poller every 8 seconds
            let isPolling = false;
            async function checkUnreadTransactions() {
                if (isPolling) return;
                isPolling = true;

                try {
                    const response = await fetch("{{ route('notifications.unread') }}", {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (response.ok) {
                        const data = await response.json();
                        if (data.count > 0 && Array.isArray(data.items)) {
                            playChime();
                            data.items.forEach(function(item) {
                                const formattedNominal = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(item.total_sales);
                                
                                if (Notification.permission === 'granted') {
                                    const notif = new Notification(`Pembayaran Masuk! - ${item.transaction_number}`, {
                                        body: `${formattedNominal} via ${item.payment_method} (${item.customer_name})`,
                                        icon: "{{ asset('assets/images/logo.png') }}",
                                        tag: 'tx-' + item.id
                                    });
                                    notif.onclick = function() {
                                        window.focus();
                                        window.location.href = item.url;
                                    };
                                }
                            });
                        }
                    }
                } catch (err) {
                    // Silent catch for background poll
                } finally {
                    isPolling = false;
                }
            }

            setInterval(checkUnreadTransactions, 8000);
        })();
    </script>
    @endif
    
    @yield('scripts')
</body>
</html>
