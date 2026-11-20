<!-- Desktop Sidebar: SUPER ADMIN (Visible on >= 992px) -->
<div class="sidebar sidebar-superadmin d-none d-lg-block" id="sidebar">
    <div class="sidebar-brand d-flex align-items-center gap-2 text-truncate">
        <div class="d-flex align-items-center justify-content-center rounded-circle shadow-sm flex-shrink-0" style="width: 38px; height: 38px; background: linear-gradient(135deg, #38bdf8, #6366f1); color: #fff;">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <div class="d-flex flex-column text-truncate" style="line-height: 1.15;">
            <span class="text-truncate fw-bold text-white fs-6">{{ $platformName ?? 'KasirLoka' }}</span>
            <small class="text-info fw-semibold" style="font-size: 0.65rem; letter-spacing: 0.8px;">PLATFORM HQ</small>
        </div>
    </div>
    <ul class="sidebar-menu">
        <li class="sidebar-section-label">Pusat Kontrol SaaS</li>
        <li class="sidebar-item {{ Request::routeIs('superadmin.dashboard') ? 'active' : '' }}">
            <a href="{{ route('superadmin.dashboard') }}" class="sidebar-link">
                <i class="fa-solid fa-gauge-high"></i>
                <span>Dashboard SaaS</span>
            </a>
        </li>
        <li class="sidebar-item {{ Request::routeIs('superadmin.tenants*') ? 'active' : '' }}">
            <a href="{{ route('superadmin.tenants') }}" class="sidebar-link">
                <i class="fa-solid fa-store"></i>
                <span>Daftar Usaha (Tenant)</span>
            </a>
        </li>
        <li class="sidebar-item {{ Request::routeIs('superadmin.invoices*') ? 'active' : '' }}">
            <a href="{{ route('superadmin.invoices') }}" class="sidebar-link">
                <i class="fa-solid fa-file-invoice-dollar"></i>
                <span>Tagihan &amp; Pembayaran</span>
            </a>
        </li>

        <li class="sidebar-section-label">Monetisasi &amp; Sistem</li>
        <li class="sidebar-item {{ Request::routeIs('superadmin.plans*') ? 'active' : '' }}">
            <a href="{{ route('superadmin.plans') }}" class="sidebar-link">
                <i class="fa-solid fa-layer-group"></i>
                <span>Paket &amp; Harga</span>
            </a>
        </li>
        <li class="sidebar-item {{ Request::routeIs('superadmin.settings*') ? 'active' : '' }}">
            <a href="{{ route('superadmin.settings') }}" class="sidebar-link">
                <i class="fa-solid fa-sliders"></i>
                <span>Pengaturan Platform</span>
            </a>
        </li>
        <li class="sidebar-item {{ Request::routeIs('superadmin.update.*') ? 'active' : '' }}">
            <a href="{{ route('superadmin.update.index') }}" class="sidebar-link">
                <i class="fa-brands fa-github"></i>
                <span>Update Sistem</span>
            </a>
        </li>
        <li class="sidebar-item {{ Request::routeIs('profile.edit') ? 'active' : '' }}">
            <a href="{{ route('profile.edit') }}" class="sidebar-link">
                <i class="fa-solid fa-user-shield"></i>
                <span>Profil Super Admin</span>
            </a>
        </li>

        <li class="sidebar-item mt-4 pt-2 border-top border-white border-opacity-10">
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

<!-- Mobile Offcanvas Drawer: SUPER ADMIN (< 992px) -->
<div class="offcanvas offcanvas-start offcanvas-sidebar sidebar-superadmin d-lg-none" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
    <div class="offcanvas-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2 text-white text-truncate">
            <div class="d-flex align-items-center justify-content-center rounded-circle shadow-sm flex-shrink-0" style="width: 34px; height: 34px; background: linear-gradient(135deg, #38bdf8, #6366f1); color: #fff;">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <div class="d-flex flex-column text-truncate" style="line-height: 1.15;">
                <span class="fw-bold fs-6 text-white text-truncate" id="sidebarOffcanvasLabel">{{ $platformName ?? 'KasirLoka' }}</span>
                <small class="text-info fw-semibold" style="font-size: 0.65rem; letter-spacing: 0.8px;">PLATFORM HQ</small>
            </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
    </div>
    <div class="offcanvas-body">
        <ul class="sidebar-menu mb-0">
            <li class="sidebar-section-label">Pusat Kontrol SaaS</li>
            <li class="sidebar-item {{ Request::routeIs('superadmin.dashboard') ? 'active' : '' }}">
                <a href="{{ route('superadmin.dashboard') }}" class="sidebar-link"><i class="fa-solid fa-gauge-high"></i><span>Dashboard SaaS</span></a>
            </li>
            <li class="sidebar-item {{ Request::routeIs('superadmin.tenants*') ? 'active' : '' }}">
                <a href="{{ route('superadmin.tenants') }}" class="sidebar-link"><i class="fa-solid fa-store"></i><span>Daftar Usaha (Tenant)</span></a>
            </li>
            <li class="sidebar-item {{ Request::routeIs('superadmin.invoices*') ? 'active' : '' }}">
                <a href="{{ route('superadmin.invoices') }}" class="sidebar-link"><i class="fa-solid fa-file-invoice-dollar"></i><span>Tagihan &amp; Pembayaran</span></a>
            </li>
            <li class="sidebar-section-label">Monetisasi &amp; Sistem</li>
            <li class="sidebar-item {{ Request::routeIs('superadmin.plans*') ? 'active' : '' }}">
                <a href="{{ route('superadmin.plans') }}" class="sidebar-link"><i class="fa-solid fa-layer-group"></i><span>Paket &amp; Harga</span></a>
            </li>
            <li class="sidebar-item {{ Request::routeIs('superadmin.settings*') ? 'active' : '' }}">
                <a href="{{ route('superadmin.settings') }}" class="sidebar-link"><i class="fa-solid fa-sliders"></i><span>Pengaturan Platform</span></a>
            </li>
            <li class="sidebar-item {{ Request::routeIs('superadmin.update.*') ? 'active' : '' }}">
                <a href="{{ route('superadmin.update.index') }}" class="sidebar-link"><i class="fa-brands fa-github"></i><span>Update Sistem</span></a>
            </li>
            <li class="sidebar-item {{ Request::routeIs('profile.edit') ? 'active' : '' }}">
                <a href="{{ route('profile.edit') }}" class="sidebar-link"><i class="fa-solid fa-user-shield"></i><span>Profil Super Admin</span></a>
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
