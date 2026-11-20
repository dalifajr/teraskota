<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $authUser = Auth::user();
        $isSuperAdminPanel = $authUser?->isSuperAdmin() && !($isImpersonatingTenant ?? false);
        $isKasirOnly = $authUser?->isKasir() && !($isImpersonatingTenant ?? false);
        $brandTitle = $isSuperAdminPanel ? ($platformName ?? 'KasirLoka') : ($currentTenant?->name ?? ($platformName ?? 'KasirLoka'));
        $brandSubtitle = $isSuperAdminPanel ? 'PLATFORM HQ' : strtoupper($currentTenant?->business_type ?? ($platformName ?? 'KASIRLOKA'));
        $brandLogo = (!$isSuperAdminPanel && $currentTenant && $currentTenant->logo_path)
            ? $currentTenant->logoUrl()
            : asset('assets/images/logo.png');
        $klStorePayload = [
            'name' => $brandTitle,
            'address' => $currentTenant?->address ?? '',
            'phone' => $currentTenant?->phone ?? '',
            'footer' => $currentTenant?->receipt_footer ?? 'Terima kasih atas kunjungan Anda!',
        ];
    @endphp
    <title>@yield('title', $brandTitle) - {{ $platformName ?? 'KasirLoka' }}</title>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="theme-color" content="{{ $isSuperAdminPanel ? '#0f172a' : '#14532d' }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom Style -->
    <link href="{{ asset('assets/css/custom.css') }}?v={{ filemtime(public_path('assets/css/custom.css')) }}" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* Distinct Deep Slate Navy Theme for Super Admin Platform HQ */
        .sidebar-superadmin {
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%) !important;
        }
        .sidebar-superadmin .sidebar-brand {
            background: rgba(15, 23, 42, 0.65) !important;
            border-bottom: 1px solid rgba(148, 163, 184, 0.16) !important;
        }
        .sidebar-superadmin .sidebar-item.active > .sidebar-link,
        .sidebar-superadmin .sidebar-link:hover {
            background: rgba(56, 189, 248, 0.16) !important;
            color: #38bdf8 !important;
            border-left-color: #38bdf8 !important;
        }
    </style>

    @yield('styles')
</head>
<body>

    <!-- Role-Segregated Navigation Sidebars -->
    @if($isSuperAdminPanel)
        @include('layouts.partials.sidebar-superadmin')
    @elseif(!$isKasirOnly)
        @include('layouts.partials.sidebar-tenant')
    @endif

    <!-- Main Wrapper -->
    <div class="main-wrapper" @if($isKasirOnly) style="margin-left: 0 !important;" @endif>

        <!-- Top Sticky Navbar -->
        <div class="top-navbar">
            <div class="d-flex align-items-center">
                @if(!$isKasirOnly)
                    <button class="btn btn-outline-secondary d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Buka Menu">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                @endif
                <div class="d-flex align-items-center gap-2">
                    <img src="{{ $brandLogo }}" alt="Logo" class="d-lg-none rounded-circle" style="width: 28px; height: 28px; object-fit: cover;">
                    <h4 class="m-0 fw-semibold text-dark text-truncate">@yield('page_title', $brandTitle)</h4>
                    @if($isSuperAdminPanel)
                        <span class="badge bg-dark text-info border border-info border-opacity-25 rounded-pill d-none d-sm-inline-block">SUPER ADMIN</span>
                    @endif
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 gap-sm-3">
                <!-- Live Realtime WIB Clock -->
                <div class="admin-live-clock d-none d-md-flex align-items-center gap-2 px-3 py-1 rounded-pill" title="Zona Waktu Operasional: Waktu Indonesia Barat (WIB)">
                    <i class="fa-regular fa-clock text-success"></i>
                    <span id="adminLiveClock" class="fw-bold text-dark font-monospace">--:--:--</span>
                    <span class="badge badge-wib rounded-pill">WIB</span>
                    <span class="text-muted small border-start ps-2 d-none d-xl-inline" id="adminLiveDate">--</span>
                </div>

                @if($isKasirOnly)
                    <a href="{{ route('pos.index') }}" class="btn btn-sm btn-primary-green fw-bold px-3 d-inline-flex align-items-center gap-2 shadow-sm">
                        <i class="fa-solid fa-arrow-left"></i>
                        <span>Kembali ke Kasir POS</span>
                    </a>
                @elseif(!$isSuperAdminPanel)
                    <!-- Quick POS Terminal Link for Tenant Admin -->
                    <a href="{{ route('pos.index') }}" target="_blank" class="btn btn-sm btn-light-accent fw-bold px-3 d-none d-sm-inline-flex align-items-center gap-2 shadow-sm" title="Buka Terminal Kasir POS di Tab Baru">
                        <i class="fa-solid fa-cash-register text-success"></i>
                        <span>Kasir POS</span>
                        <i class="fa-solid fa-arrow-up-right-from-square small text-muted"></i>
                    </a>
                @endif

                @if(!$isSuperAdminPanel && !$isKasirOnly)
                    <!-- Tenant Admin Browser Push Notification Toggle -->
                    <button type="button" class="btn btn-light rounded-circle position-relative p-2" id="btnToggleDesktopNotification" title="Notifikasi Desktop Pembayaran Masuk" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa-solid fa-bell text-secondary fs-5" id="bellIcon"></i>
                        <span class="position-absolute top-0 start-100 translate-middle p-1 bg-secondary border border-light rounded-circle" id="bellStatusDot" style="margin-top: 8px; margin-left: -8px;">
                            <span class="visually-hidden">Status Notifikasi</span>
                        </span>
                    </button>
                @endif

                <div class="text-end d-none d-sm-block">
                    <div class="fw-semibold text-dark text-truncate" style="max-width: 140px;">{{ $authUser?->name ?? 'Pengguna' }}</div>
                    <small class="text-muted d-block text-truncate" style="font-size: 0.72rem; max-width: 140px;">
                        {{ $authUser?->isSuperAdmin() ? 'Super Admin' : ($authUser?->isAdmin() ? 'Admin Usaha' : 'Kasir') }}
                    </small>
                </div>
                <a href="{{ route('profile.edit') }}" class="nav-user text-decoration-none" title="Profil Akun">
                    <img src="https://ui-avatars.com/api/?name={{ urlencode($authUser?->name ?? 'User') }}&background={{ $isSuperAdminPanel ? '0f172a' : '11361b' }}&color=ffffff" alt="Avatar">
                </a>
            </div>
        </div>

        <!-- Super Admin Impersonation Banner -->
        @if($isImpersonatingTenant ?? false)
            <div class="bg-primary text-white py-2 px-3 px-md-4 d-flex flex-wrap justify-content-between align-items-center gap-2 shadow-sm">
                <div class="small fw-semibold">
                    <i class="fa-solid fa-user-secret me-2"></i>
                    Mode Support Super Admin: Anda sedang melihat panel usaha <strong>{{ $currentTenant?->name }}</strong>
                </div>
                <form action="{{ route('superadmin.stop-impersonate') }}" method="POST" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-light fw-bold py-0 px-3">
                        <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Super Admin
                    </button>
                </form>
            </div>
        @endif

        <!-- Global Platform Broadcast Announcement Banner (Tenant Admin Only) -->
        @if(!$isSuperAdminPanel && !$isKasirOnly && !empty($platformAnnouncement))
            @php
                $annType = $platformAnnouncement['type'] ?? 'info';
                $annBg = match($annType) {
                    'warning' => 'bg-warning text-dark',
                    'danger' => 'bg-danger text-white',
                    'success' => 'bg-success text-white',
                    default => 'bg-primary text-white',
                };
                $annIcon = match($annType) {
                    'warning' => 'fa-triangle-exclamation',
                    'danger' => 'fa-circle-exclamation',
                    'success' => 'fa-bullhorn',
                    default => 'fa-circle-info',
                };
            @endphp
            <div class="{{ $annBg }} py-2 px-3 px-md-4 d-flex align-items-center justify-content-between gap-2 small shadow-sm" role="status">
                <div class="fw-semibold">
                    <i class="fa-solid {{ $annIcon }} me-2"></i>
                    <span>{{ $platformAnnouncement['message'] }}</span>
                </div>
            </div>
        @endif

        <!-- Subscription Status Banner (Grace / Expired / Trial <= 5 days) -->
        @if(!$isSuperAdminPanel && !$isKasirOnly && $currentTenant && !Request::routeIs('billing.*'))
            @php $daysLeft = $currentTenant->daysRemaining(); @endphp
            @if($subscriptionState === 'expired' || $subscriptionState === 'suspended')
                <div class="bg-danger text-white py-2 px-3 px-md-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="small fw-semibold">
                        <i class="fa-solid fa-lock me-2"></i>
                        {{ $subscriptionState === 'suspended' ? 'Akun usaha dinonaktifkan sementara.' : 'Masa aktif langganan telah berakhir (Mode Baca Saja / Read-Only). Kasir POS terkunci hingga paket diperpanjang.' }}
                    </div>
                    <a href="{{ route('billing.index') }}" class="btn btn-sm btn-warning text-dark fw-bold py-0 px-3">Perpanjang Sekarang</a>
                </div>
            @elseif($subscriptionState === 'grace')
                <div class="bg-warning text-dark py-2 px-3 px-md-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="small fw-semibold">
                        <i class="fa-solid fa-clock me-2"></i>
                        Masa aktif langganan habis! Anda berada dalam <strong>Masa Tenggang (Grace Period)</strong> sebelum akses kasir dikunci otomatis.
                    </div>
                    <a href="{{ route('billing.index') }}" class="btn btn-sm btn-dark fw-bold py-0 px-3">Bayar Perpanjangan</a>
                </div>
            @elseif($subscriptionState === 'trial' && $daysLeft !== null && $daysLeft <= 5)
                <div class="bg-success bg-opacity-10 border-bottom border-success border-opacity-25 text-success py-2 px-3 px-md-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="small fw-semibold">
                        <i class="fa-solid fa-sparkles me-2"></i>
                        Masa uji coba gratis tersisa <strong>{{ max(0, $daysLeft) }} hari lagi</strong>. Pilih paket Starter atau Pro agar operasional toko tetap lancar.
                    </div>
                    <a href="{{ route('billing.index') }}" class="btn btn-sm btn-success fw-bold py-0 px-3">Lihat Paket</a>
                </div>
            @endif
        @endif

        <!-- Main Content -->
        <div class="container-fluid p-3 p-md-4">

            <!-- Breadcrumbs -->
            <nav aria-label="breadcrumb" class="mb-3 mb-md-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="{{ $isSuperAdminPanel ? route('superadmin.dashboard') : ($isKasirOnly ? route('pos.index') : route('dashboard')) }}" class="text-decoration-none text-success">
                            <i class="fa-solid fa-home"></i> Home
                        </a>
                    </li>
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

            @if(session('info'))
                <div class="alert alert-info alert-dismissible fade show rounded-3 shadow-sm border-0 border-start border-5 border-info mb-4" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fa-solid fa-circle-info fs-4 me-3 text-info"></i>
                        <div>{{ session('info') }}</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @yield('content')
        </div>

        <!-- Footer -->
        <footer class="mt-auto py-3 bg-white border-top text-center text-muted">
            <div class="container">
                <small>&copy; {{ date('Y') }} {{ $brandTitle }} · Powered by <strong>{{ $platformName ?? 'KasirLoka' }}</strong>.</small>
            </div>
        </footer>

        <!-- Role-Segregated Mobile Bottom Navigation Bar -->
        @if($isSuperAdminPanel)
            @include('layouts.partials.mobile-nav-superadmin')
        @elseif(!$isKasirOnly)
            @include('layouts.partials.mobile-nav-tenant')
        @endif
    </div>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 Unified Dialog Engine -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        window.KL_STORE_INFO = @json($klStorePayload);
    </script>

    <!-- UI Transitions & Progress Bar Engine -->
    <script src="{{ asset('assets/js/ui-transitions.js') }}?v={{ filemtime(public_path('assets/js/ui-transitions.js')) }}"></script>

    <!-- Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker
                    .register("{{ asset('service-worker.js') }}")
                    .catch(function () {});
            });
        }
    </script>

    @if(!$isSuperAdminPanel && !$isKasirOnly)
    <!-- Desktop Notification & Audio Synthesizer Engine (Tenant Admin Only) -->
    <script>
        (function() {
            const bellBtn = document.getElementById('btnToggleDesktopNotification');
            const bellIcon = document.getElementById('bellIcon');
            const bellDot = document.getElementById('bellStatusDot');
            const storeTitle = @json($brandTitle);

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
                    osc1.frequency.setValueAtTime(880, now);
                    gain1.gain.setValueAtTime(0.15, now);
                    gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
                    osc1.connect(gain1);
                    gain1.connect(ctx.destination);
                    osc1.start(now);
                    osc1.stop(now + 0.35);

                    const osc2 = ctx.createOscillator();
                    const gain2 = ctx.createGain();
                    osc2.type = 'sine';
                    osc2.frequency.setValueAtTime(1318.51, now + 0.12);
                    gain2.gain.setValueAtTime(0.25, now + 0.12);
                    gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.6);
                    osc2.connect(gain2);
                    gain2.connect(ctx.destination);
                    osc2.start(now + 0.12);
                    osc2.stop(now + 0.6);
                } catch(e) {}
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
                                new Notification(storeTitle + ' - Notifikasi Aktif', {
                                    body: 'Anda akan menerima notifikasi setiap ada transaksi berhasil.',
                                    icon: "{{ asset('assets/images/logo.png') }}"
                                });
                            }
                        });
                    } else if (Notification.permission === 'granted') {
                        playChime();
                        new Notification('Tes Notifikasi ' + storeTitle, {
                            body: 'Audio chime dan notifikasi desktop berfungsi normal!',
                            icon: "{{ asset('assets/images/logo.png') }}"
                        });
                    } else {
                        alert('Izin notifikasi diblokir di browser Anda. Silakan klik ikon gembok di sebelah URL browser dan izinkan "Notifications".');
                    }
                });
            }

            updateBellUI();

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
                } finally {
                    isPolling = false;
                }
            }

            setInterval(checkUnreadTransactions, 8000);
        })();
    </script>
    @endif

    <!-- Live Realtime WIB Clock Script -->
    <script>
        (function() {
            function updateAdminClock() {
                try {
                    const now = new Date();
                    const optionsTime = { timeZone: 'Asia/Jakarta', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false };
                    const optionsDate = { timeZone: 'Asia/Jakarta', weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' };

                    const timeStr = new Intl.DateTimeFormat('id-ID', optionsTime).format(now);
                    const dateStr = new Intl.DateTimeFormat('id-ID', optionsDate).format(now);

                    const clockEl = document.getElementById('adminLiveClock');
                    const dateEl = document.getElementById('adminLiveDate');
                    if (clockEl) clockEl.textContent = timeStr;
                    if (dateEl) dateEl.textContent = dateStr;
                } catch(e) {}
            }
            setInterval(updateAdminClock, 1000);
            updateAdminClock();
        })();
    </script>

    @yield('scripts')
</body>
</html>
