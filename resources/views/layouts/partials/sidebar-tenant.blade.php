<!-- Desktop Sidebar: TENANT ADMIN (Visible on >= 992px) -->
<div class="sidebar d-none d-lg-block" id="sidebar">
    <div class="sidebar-brand d-flex align-items-center gap-2 text-truncate">
        <img src="{{ $brandLogo }}" alt="{{ $brandTitle }}" class="rounded-circle shadow-sm flex-shrink-0" style="width: 38px; height: 38px; object-fit: cover; background: #fff;">
        <div class="d-flex flex-column text-truncate" style="line-height: 1.15;">
            <span class="text-truncate fw-bold text-white fs-6">{{ $brandTitle }}</span>
            <small class="text-white-50 text-truncate" style="font-size: 0.68rem; letter-spacing: 0.5px;">{{ $brandSubtitle }}</small>
        </div>
    </div>
    <ul class="sidebar-menu">
        <li class="sidebar-section-label">Operasional</li>
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
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span>Riwayat Transaksi</span>
            </a>
        </li>
        <li class="sidebar-item {{ Request::routeIs('expenses.*') ? 'active' : '' }}">
            <a href="{{ route('expenses.index') }}" class="sidebar-link d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-money-bill-transfer"></i>
                    <span>Pengeluaran Kas</span>
                </div>
                @if($currentTenant && !$currentTenant->hasFeature('expenses'))
                    <span class="badge bg-warning text-dark" style="font-size: 0.6rem;">PRO</span>
                @endif
            </a>
        </li>

        <li class="sidebar-section-label">Data Master</li>
        <li class="sidebar-item {{ Request::routeIs('menus.*') ? 'active' : '' }}">
            <a href="{{ route('menus.index') }}" class="sidebar-link">
                <i class="fa-solid fa-mug-hot"></i>
                <span>Data Menu</span>
            </a>
        </li>
        <li class="sidebar-item {{ Request::routeIs('categories.*') ? 'active' : '' }}">
            <a href="{{ route('categories.index') }}" class="sidebar-link">
                <i class="fa-solid fa-tags"></i>
                <span>Kategori Menu</span>
            </a>
        </li>

        <li class="sidebar-section-label">Laporan &amp; Analisis</li>
        <li class="sidebar-item {{ Request::routeIs('reports.*') ? 'active' : '' }}">
            <a href="{{ route('reports.index') }}" class="sidebar-link">
                <i class="fa-solid fa-file-invoice-dollar"></i>
                <span>Laporan Penjualan</span>
            </a>
        </li>

        <li class="sidebar-section-label">Usaha &amp; Langganan</li>
        <li class="sidebar-item {{ Request::routeIs('billing.*') ? 'active' : '' }}">
            <a href="{{ route('billing.index') }}" class="sidebar-link d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-crown text-warning"></i>
                    <span>Langganan &amp; Paket</span>
                </div>
                @if($subscriptionState === 'expired' || $subscriptionState === 'grace')
                    <span class="badge bg-danger rounded-pill" style="font-size: 0.65rem;">!</span>
                @elseif($subscriptionState === 'trial')
                    <span class="badge bg-warning text-dark rounded-pill" style="font-size: 0.65rem;">Trial</span>
                @endif
            </a>
        </li>
        <li class="sidebar-item {{ Request::routeIs('settings.*') ? 'active' : '' }}">
            <a href="#settingsSubmenuDesktop" data-bs-toggle="collapse" class="sidebar-link d-flex align-items-center justify-content-between {{ Request::routeIs('settings.*') ? '' : 'collapsed' }}" aria-expanded="{{ Request::routeIs('settings.*') ? 'true' : 'false' }}">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-sliders"></i>
                    <span>Pengaturan Usaha</span>
                </div>
                <i class="fa-solid fa-chevron-down sub-arrow small"></i>
            </a>
            <div class="collapse {{ Request::routeIs('settings.*') ? 'show' : '' }}" id="settingsSubmenuDesktop">
                <ul class="submenu">
                    <li class="submenu-item {{ Request::routeIs('settings.store.*') ? 'active' : '' }}">
                        <a href="{{ route('settings.store.edit') }}" class="submenu-link">
                            <i class="fa-solid fa-store me-2"></i> Profil Usaha &amp; Struk
                        </a>
                    </li>
                    <li class="submenu-item {{ Request::routeIs('settings.qris.*') ? 'active' : '' }}">
                        <a href="{{ route('settings.qris.index') }}" class="submenu-link">
                            <i class="fa-solid fa-qrcode me-2"></i> Pengaturan QRIS
                            @if($currentTenant && !$currentTenant->hasFeature('qris_dynamic'))
                                <span class="badge bg-warning text-dark ms-1" style="font-size: 0.6rem;">PRO</span>
                            @endif
                        </a>
                    </li>
                    <li class="submenu-item {{ Request::routeIs('settings.profit.*') ? 'active' : '' }}">
                        <a href="{{ route('settings.profit.edit') }}" class="submenu-link">
                            <i class="fa-solid fa-percent me-2"></i> Pengaturan Profit
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

<!-- Mobile Offcanvas Drawer: TENANT ADMIN (< 992px) -->
<div class="offcanvas offcanvas-start offcanvas-sidebar d-lg-none" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
    <div class="offcanvas-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2 text-white text-truncate">
            <img src="{{ $brandLogo }}" alt="{{ $brandTitle }}" class="rounded-circle shadow-sm flex-shrink-0" style="width: 34px; height: 34px; object-fit: cover; background: #fff;">
            <div class="d-flex flex-column text-truncate" style="line-height: 1.15;">
                <span class="fw-bold fs-6 text-white text-truncate" id="sidebarOffcanvasLabel">{{ $brandTitle }}</span>
                <small class="text-white-50 text-truncate" style="font-size: 0.65rem; letter-spacing: 0.5px;">{{ $brandSubtitle }}</small>
            </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
    </div>
    <div class="offcanvas-body">
        <ul class="sidebar-menu mb-0">
            <li class="sidebar-section-label">Operasional</li>
            <li class="sidebar-item {{ Request::routeIs('dashboard') ? 'active' : '' }}">
                <a href="{{ route('dashboard') }}" class="sidebar-link"><i class="fa-solid fa-chart-line"></i><span>Dashboard</span></a>
            </li>
            <li class="sidebar-item mb-2">
                <a href="{{ route('pos.index') }}" class="sidebar-link btn-light-accent text-dark fw-bold py-2 shadow-sm" target="_blank">
                    <i class="fa-solid fa-cash-register text-success"></i><span>Buka Kasir POS</span>
                </a>
            </li>
            <li class="sidebar-item {{ Request::routeIs('transactions.*') ? 'active' : '' }}">
                <a href="{{ route('transactions.index') }}" class="sidebar-link"><i class="fa-solid fa-clock-rotate-left"></i><span>Riwayat Transaksi</span></a>
            </li>
            <li class="sidebar-item {{ Request::routeIs('expenses.*') ? 'active' : '' }}">
                <a href="{{ route('expenses.index') }}" class="sidebar-link"><i class="fa-solid fa-money-bill-transfer"></i><span>Pengeluaran Kas</span></a>
            </li>
            <li class="sidebar-section-label">Data Master</li>
            <li class="sidebar-item {{ Request::routeIs('menus.*') ? 'active' : '' }}">
                <a href="{{ route('menus.index') }}" class="sidebar-link"><i class="fa-solid fa-mug-hot"></i><span>Data Menu</span></a>
            </li>
            <li class="sidebar-item {{ Request::routeIs('categories.*') ? 'active' : '' }}">
                <a href="{{ route('categories.index') }}" class="sidebar-link"><i class="fa-solid fa-tags"></i><span>Kategori Menu</span></a>
            </li>
            <li class="sidebar-section-label">Laporan &amp; Usaha</li>
            <li class="sidebar-item {{ Request::routeIs('reports.*') ? 'active' : '' }}">
                <a href="{{ route('reports.index') }}" class="sidebar-link"><i class="fa-solid fa-file-invoice-dollar"></i><span>Laporan Penjualan</span></a>
            </li>
            <li class="sidebar-item {{ Request::routeIs('billing.*') ? 'active' : '' }}">
                <a href="{{ route('billing.index') }}" class="sidebar-link"><i class="fa-solid fa-crown text-warning"></i><span>Langganan &amp; Paket</span></a>
            </li>
            <li class="sidebar-item {{ Request::routeIs('settings.store.*') ? 'active' : '' }}">
                <a href="{{ route('settings.store.edit') }}" class="sidebar-link"><i class="fa-solid fa-store"></i><span>Profil Usaha &amp; Struk</span></a>
            </li>
            <li class="sidebar-item {{ Request::routeIs('settings.qris.*') ? 'active' : '' }}">
                <a href="{{ route('settings.qris.index') }}" class="sidebar-link"><i class="fa-solid fa-qrcode"></i><span>Pengaturan QRIS</span></a>
            </li>
            <li class="sidebar-item {{ Request::routeIs('settings.profit.*') ? 'active' : '' }}">
                <a href="{{ route('settings.profit.edit') }}" class="sidebar-link"><i class="fa-solid fa-percent"></i><span>Pengaturan Profit</span></a>
            </li>
            <li class="sidebar-item {{ Request::routeIs('users.*') ? 'active' : '' }}">
                <a href="{{ route('users.index') }}" class="sidebar-link"><i class="fa-solid fa-users-gear"></i><span>Kelola Pengguna</span></a>
            </li>
            <li class="sidebar-item {{ Request::routeIs('profile.edit') ? 'active' : '' }}">
                <a href="{{ route('profile.edit') }}" class="sidebar-link"><i class="fa-solid fa-user-gear"></i><span>Profil Akun</span></a>
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
