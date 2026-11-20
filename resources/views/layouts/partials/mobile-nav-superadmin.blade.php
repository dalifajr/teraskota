<!-- Mobile Bottom Navigation Bar: SUPER ADMIN (Visible on < 992px) -->
<nav class="mobile-bottom-nav d-lg-none">
    <a href="{{ route('superadmin.dashboard') }}" class="mobile-nav-item {{ Request::routeIs('superadmin.dashboard') ? 'active' : '' }}">
        <i class="fa-solid fa-gauge-high"></i>
        <span>Dashboard</span>
    </a>
    <a href="{{ route('superadmin.tenants') }}" class="mobile-nav-item {{ Request::routeIs('superadmin.tenants*') ? 'active' : '' }}">
        <i class="fa-solid fa-store"></i>
        <span>Tenant</span>
    </a>
    <a href="{{ route('superadmin.invoices') }}" class="mobile-nav-item position-relative {{ Request::routeIs('superadmin.invoices*') ? 'active' : '' }}">
        <i class="fa-solid fa-file-invoice-dollar"></i>
        <span>Invoice</span>
        @if(($pendingInvoicesBadgeCount ?? 0) > 0)
            <span class="position-absolute top-0 start-50 translate-middle-y badge rounded-pill bg-warning text-dark fw-bold" style="font-size: 0.62rem; margin-left: 10px; margin-top: 8px;">{{ $pendingInvoicesBadgeCount }}</span>
        @endif
    </a>
    <a href="{{ route('superadmin.plans') }}" class="mobile-nav-item {{ Request::routeIs('superadmin.plans*') ? 'active' : '' }}">
        <i class="fa-solid fa-layer-group"></i>
        <span>Paket</span>
    </a>
    <button type="button" class="mobile-nav-item" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Menu Lengkap">
        <i class="fa-solid fa-bars"></i>
        <span>Lainnya</span>
    </button>
</nav>
