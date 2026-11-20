<!-- Mobile Bottom Navigation Bar: TENANT ADMIN (Visible on < 992px) -->
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
