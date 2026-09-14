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

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <i class="fa-solid fa-mug-hot"></i>
            <span>Teras Kota Berlian Makmur</span>
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
            </li>
            <li class="sidebar-item {{ Request::routeIs('settings.profit.*') ? 'active' : '' }}">
                <a href="{{ route('settings.profit.edit') }}" class="sidebar-link">
                    <i class="fa-solid fa-percent"></i>
                    <span>Pengaturan Profit</span>
                </a>
            </li>
            <li class="sidebar-item {{ Request::routeIs('users.*') ? 'active' : '' }}">
                <a href="{{ route('users.index') }}" class="sidebar-link">
                    <i class="fa-solid fa-users-gear"></i>
                    <span>Kelola Pengguna</span>
                </a>
            </li>
            <li class="sidebar-item {{ Request::routeIs('settings.update.*') ? 'active' : '' }}">
                <a href="{{ route('settings.update.index') }}" class="sidebar-link">
                    <i class="fa-brands fa-github"></i>
                    <span>Update Sistem</span>
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

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div class="d-flex align-items-center">
                <button class="btn btn-outline-secondary d-lg-none me-3" id="sidebarToggle">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <h4 class="m-0 fw-semibold text-dark">@yield('page_title', 'Teras Kota Berlian Makmur')</h4>
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <div class="text-end d-none d-sm-block">
                    <div class="fw-semibold text-dark">{{ Auth::user()?->name ?? 'Pengguna' }}</div>
                    <small class="text-muted">{{ Auth::user()?->email ?? '' }}</small>
                </div>
                <div class="nav-user">
                    <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()?->name ?? 'User') }}&background=11361b&color=ffffff" alt="Avatar">
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="container-fluid p-4">
            
            <!-- Breadcrumbs -->
            <nav aria-label="breadcrumb" class="mb-4">
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
                <small>&copy; {{ date('Y') }} Teras Kota Berlian Makmur. All Rights Reserved. Powered by Laravel.</small>
            </div>
        </footer>
    </div>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Sidebar Toggle Script -->
    <script>
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar')?.classList.toggle('active');
        });
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker
                .register('/service-worker.js')
                .then(function (registration) {
                    console.log(
                        'Service Worker terdaftar:',
                        registration.scope
                    );
                })
                .catch(function (error) {
                    console.error(
                        'Service Worker gagal didaftarkan:',
                        error
                    );
                });
        });
    }
</script>
    
    @yield('scripts')
</body>
</html>
