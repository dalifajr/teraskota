@extends('layouts.pos')

@section('title', 'Terminal Kasir POS')

@section('styles')
<style>
    /* POS Catalog Layout */
    .pos-catalog-panel {
        flex: 1;
        height: 100%;
        min-height: 0;
        display: flex;
        flex-direction: column;
        background: #ffffff;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.03);
        border: 1px solid #e2e8f0;
        overflow: hidden;
        margin-right: 1rem;
    }

    .pos-cart-panel {
        width: 400px;
        min-width: 350px;
        height: 100%;
        min-height: 0;
        display: flex;
        flex-direction: column;
        background: #ffffff;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.03);
        border: 1px solid #e2e8f0;
        overflow: hidden;
        position: relative;
    }

    /* Category Navigation */
    .pos-categories-nav {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        padding: 0.8rem 1rem;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        white-space: nowrap;
        scrollbar-width: thin;
    }

    .pos-cat-btn {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        color: #475569;
        padding: 0.45rem 1rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .pos-cat-btn:hover {
        background: #e2e8f0;
        color: #1e293b;
    }

    .pos-cat-btn.active {
        background: var(--primary-green);
        color: #ffffff;
        border-color: var(--primary-green);
        box-shadow: 0 4px 10px rgba(17, 54, 27, 0.2);
    }

    .pos-cat-btn * {
        pointer-events: none;
    }

    /* Product Grid */
    .pos-product-grid {
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        padding: 1rem;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
        gap: 14px;
        align-content: start;
    }

    .pos-product-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 1rem 0.8rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
        user-select: none;
    }

    .pos-product-card:hover {
        border-color: var(--accent-green);
        box-shadow: 0 8px 25px rgba(46, 125, 50, 0.12);
        transform: translateY(-3px);
    }

    .pos-product-card:active {
        transform: scale(0.96);
    }

    .pos-product-icon {
        width: 46px;
        height: 46px;
        border-radius: 12px;
        background: var(--mint-soft);
        color: var(--accent-green);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        margin: 0 auto 0.6rem;
        transition: all 0.2s ease;
    }

    .pos-product-card:hover .pos-product-icon {
        background: var(--primary-green);
        color: var(--light-accent);
        transform: rotate(-5deg);
    }

    .pos-product-code {
        font-size: 0.72rem;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .pos-product-name {
        font-size: 0.92rem;
        font-weight: 600;
        color: #1e293b;
        margin: 0.2rem 0 0.5rem;
        line-height: 1.3;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        min-height: 2.4em;
    }

    .pos-product-price {
        font-size: 1rem;
        font-weight: 700;
        color: var(--accent-green);
    }

    /* Cart Styles */
    .pos-cart-header {
        padding: 1rem 1.2rem;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f8fafc;
    }

    .pos-cart-items-wrapper {
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        padding: 0.8rem 1rem;
    }

    .pos-cart-item {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 0.75rem 0.9rem;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        animation: fadeInSlide 0.25s ease-out;
    }

    @keyframes fadeInSlide {
        from { opacity: 0; transform: translateY(6px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .pos-item-info {
        flex: 1;
        min-width: 0;
    }

    .pos-item-title {
        font-size: 0.88rem;
        font-weight: 600;
        color: #1e293b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .pos-item-sub {
        font-size: 0.78rem;
        color: #64748b;
    }

    .pos-qty-controls {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .pos-btn-qty {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #334155;
        font-size: 0.85rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .pos-btn-qty:hover {
        background: var(--primary-green);
        color: #ffffff;
        border-color: var(--primary-green);
    }

    .pos-qty-display {
        font-size: 0.9rem;
        font-weight: 700;
        min-width: 24px;
        text-align: center;
    }

    .pos-btn-remove {
        color: #94a3b8;
        border: none;
        background: transparent;
        cursor: pointer;
        padding: 4px;
        font-size: 0.9rem;
        transition: color 0.15s ease;
    }

    .pos-btn-remove:hover {
        color: #ef4444;
    }

    /* Cart Footer */
    .pos-cart-footer {
        flex-shrink: 0;
        padding: 1.2rem;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        position: relative;
        z-index: 15;
    }

    .pos-total-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.8rem;
    }

    .pos-grand-total {
        font-size: 1.6rem;
        font-weight: 800;
        color: var(--primary-green);
    }

    .pos-btn-pay {
        background: linear-gradient(135deg, var(--primary-green) 0%, var(--accent-green) 100%);
        color: #ffffff;
        border: none;
        border-radius: 12px;
        padding: 0.9rem;
        width: 100%;
        font-size: 1.1rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        box-shadow: 0 4px 15px rgba(17, 54, 27, 0.25);
        position: relative;
        z-index: 20;
    }

    .pos-btn-pay:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(17, 54, 27, 0.35);
    }

    .pos-btn-pay:disabled {
        background: #cbd5e1;
        cursor: not-allowed;
        box-shadow: none;
    }

    .pos-btn-pay.has-items {
        animation: pulsePayBtn 2s infinite;
    }

    @keyframes pulsePayBtn {
        0% { box-shadow: 0 0 0 0 rgba(46, 125, 50, 0.5); }
        70% { box-shadow: 0 0 0 10px rgba(46, 125, 50, 0); }
        100% { box-shadow: 0 0 0 0 rgba(46, 125, 50, 0); }
    }

    /* Quick Cash Buttons */
    .btn-quick-cash {
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 0.5rem;
        font-size: 0.85rem;
        font-weight: 600;
        color: #334155;
        transition: all 0.15s ease;
    }

    .btn-quick-cash:hover {
        background: var(--mint-soft);
        border-color: var(--accent-green);
        color: var(--accent-green);
    }

    /* Mobile Floating Bottom Bar */
    .pos-floating-bar {
        position: fixed;
        bottom: 14px;
        left: 12px;
        right: 12px;
        z-index: 1025;
        animation: slideUpFloatingBar 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    @keyframes slideUpFloatingBar {
        from { transform: translateY(100px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    .pos-floating-bar-inner {
        background: #ffffff;
        border-radius: 18px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.18), 0 2px 8px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        padding: 6px;
        gap: 8px;
    }

    .pos-floating-cart-btn {
        flex: 1;
        display: flex;
        align-items: center;
        gap: 10px;
        background: var(--mint-soft);
        border: 1px solid rgba(46, 125, 50, 0.15);
        border-radius: 14px;
        padding: 8px 12px;
        color: var(--primary-green);
        cursor: pointer;
        transition: all 0.2s ease;
        text-align: left;
    }

    .pos-floating-cart-btn:active {
        transform: scale(0.97);
    }

    .pos-cart-icon-wrapper {
        position: relative;
        width: 36px;
        height: 36px;
        background: var(--primary-green);
        color: #ffffff;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.05rem;
        flex-shrink: 0;
    }

    .pos-cart-badge-dot {
        position: absolute;
        top: -6px;
        right: -6px;
        background: #ef4444;
        color: #ffffff;
        font-size: 0.72rem;
        font-weight: 800;
        min-width: 20px;
        height: 20px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 4px;
        border: 2px solid #ffffff;
        box-shadow: 0 2px 6px rgba(239, 68, 68, 0.4);
    }

    .pos-floating-qty {
        font-size: 0.72rem;
        font-weight: 600;
        color: #64748b;
        line-height: 1.2;
    }

    .pos-floating-total {
        font-size: 1.05rem;
        font-weight: 800;
        color: var(--primary-green);
        line-height: 1.1;
    }

    .pos-floating-pay-btn {
        background: linear-gradient(135deg, var(--primary-green) 0%, var(--accent-green) 100%);
        color: #ffffff;
        border: none;
        border-radius: 14px;
        padding: 10px 20px;
        font-weight: 700;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        box-shadow: 0 4px 14px rgba(17, 54, 27, 0.3);
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .pos-floating-pay-btn:active {
        transform: scale(0.96);
    }

    /* Bottom Sheet Slide Card */
    .pos-cart-bottom-sheet {
        height: auto !important;
        max-height: 85vh !important;
        border-top-left-radius: 24px !important;
        border-top-right-radius: 24px !important;
        box-shadow: none !important;
        z-index: 1055 !important;
    }

    .pos-cart-bottom-sheet.show {
        box-shadow: 0 -10px 35px rgba(0, 0, 0, 0.25) !important;
    }

    .pos-sheet-handle-wrapper {
        display: flex;
        justify-content: center;
        padding: 10px 0 6px;
        cursor: pointer;
    }

    .pos-sheet-handle {
        width: 44px;
        height: 5px;
        background: #cbd5e1;
        border-radius: 3px;
    }

    /* Dedicated Fullscreen Payment Screen */
    .pos-checkout-screen {
        display: none;
        flex: 1;
        width: 100%;
        background: #ffffff;
        border-radius: 16px;
        box-shadow: 0 4px 25px rgba(0,0,0,0.05);
        border: 1px solid #e2e8f0;
        overflow-y: auto;
        padding: 1.5rem;
        animation: fadeInScreen 0.25s ease-out;
    }

    @keyframes fadeInScreen {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 991px) {
        .pos-catalog-panel {
            height: auto;
            margin-right: 0;
            margin-bottom: 0;
            min-height: auto;
            padding-bottom: 90px; /* Room for floating cart bar */
        }
        .pos-checkout-screen {
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 2rem;
        }
        .pos-product-grid {
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 10px;
            padding: 0.75rem;
        }
    }

    /* Strict Desktop Isolation: Never show mobile floating bar or bottom sheet on desktop */
    @media (min-width: 992px) {
        .pos-floating-bar,
        #posFloatingCartBar,
        .pos-cart-bottom-sheet,
        #posCartBottomSheet {
            display: none !important;
            visibility: hidden !important;
            pointer-events: none !important;
            box-shadow: none !important;
        }
    }
</style>
@endsection

@section('content')
<!-- Left Panel: Menu Catalog -->
<section class="pos-catalog-panel" id="posCatalogSection">
    <!-- Top Search & Info Bar -->
    <div class="p-3 border-bottom d-flex align-items-center gap-3">
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0">
                <i class="fa-solid fa-magnifying-glass text-muted"></i>
            </span>
            <input
                type="text"
                id="posSearchInput"
                class="form-control border-start-0 ps-0"
                placeholder="Cari nama atau kode menu... (Tekan F2)"
                autocomplete="off"
            >
            <button class="btn btn-outline-secondary d-none" type="button" id="btnClearSearch">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <span class="badge bg-success bg-opacity-10 text-success fw-semibold px-3 py-2 rounded-pill d-none d-md-inline-block">
            <i class="fa-solid fa-store me-1"></i> Mode Kasir Aktif
        </span>
    </div>

    <!-- Category Tabs -->
    <div class="pos-categories-nav" id="categoryNav">
        <button type="button" class="pos-cat-btn active" data-category="all">
            <i class="fa-solid fa-border-all"></i> Semua Menu
        </button>
        @foreach($categories as $cat)
            <button type="button" class="pos-cat-btn" data-category="cat-{{ $cat->id }}">
                <i class="fa-solid fa-tag"></i> {{ $cat->name }}
                <span class="badge bg-white text-dark rounded-pill ms-1" style="font-size:0.7rem;">{{ $cat->menus->count() }}</span>
            </button>
        @endforeach
    </div>

    <!-- Product Grid -->
    <div class="pos-product-grid" id="posProductGrid">
        @foreach($categories as $category)
            @foreach($category->menus as $menu)
                <div
                    class="pos-product-card menu-item-element"
                    data-id="{{ $menu->id }}"
                    data-name="{{ $menu->name }}"
                    data-code="{{ $menu->code }}"
                    data-price="{{ $menu->price }}"
                    data-category="cat-{{ $category->id }}"
                    onclick="posAddToCart({{ $menu->id }}, '{{ addslashes($menu->name) }}', {{ $menu->price }}, '{{ $menu->code }}')"
                >
                    <div>
                        <div class="pos-product-icon">
                            <i class="fa-solid fa-mug-hot"></i>
                        </div>
                        <div class="pos-product-code">{{ $menu->code }}</div>
                        <div class="pos-product-name" title="{{ $menu->name }}">{{ $menu->name }}</div>
                    </div>
                    <div class="pos-product-price">
                        Rp{{ number_format($menu->price, 0, ',', '.') }}
                    </div>
                </div>
            @endforeach
        @endforeach
    </div>
</section>

<!-- Right Panel: Interactive Shopping Cart (Desktop >= 992px) -->
<aside class="pos-cart-panel d-none d-lg-flex" id="posCartAside">
    <!-- Cart Header -->
    <div class="pos-cart-header">
        <div>
            <h6 class="m-0 fw-bold text-dark">
                <i class="fa-solid fa-cash-register text-success me-1"></i> Pesanan Kasir
            </h6>
            <small class="text-muted" id="cartItemCountText">0 item</small>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger" id="btnClearCart" title="Kosongkan Keranjang">
            <i class="fa-solid fa-trash-can me-1"></i> Kosongkan
        </button>
    </div>

    <!-- Cart Items List -->
    <div class="pos-cart-items-wrapper" id="posCartList">
        <!-- Empty State Illustration -->
        <div class="text-center py-5 text-muted" id="posCartEmpty">
            <i class="fa-solid fa-basket-shopping fs-1 mb-3 text-secondary opacity-50"></i>
            <h6 class="fw-semibold text-secondary">Keranjang Kosong</h6>
            <p class="small text-muted mb-0">Klik menu di katalog sebelah kiri untuk menambahkan pesanan.</p>
        </div>
    </div>

    <!-- Cart Footer & Pay Button -->
    <div class="pos-cart-footer">
        <div class="d-flex justify-content-between text-muted small mb-1">
            <span>Total Kuantitas:</span>
            <strong id="posTotalQty" class="text-dark">0 pcs</strong>
        </div>
        <div class="pos-total-row">
            <span class="fw-bold text-dark fs-6">Total Belanja:</span>
            <span class="pos-grand-total" id="posGrandTotal">Rp0</span>
        </div>

        <button type="button" class="pos-btn-pay" id="btnOpenCheckout" disabled>
            <i class="fa-solid fa-calculator"></i>
            <span>Bayar (F9)</span>
        </button>
    </div>
</aside>

<!-- Mobile Floating Bottom Action Bar (< 992px) -->
<div class="pos-floating-bar d-lg-none" id="posFloatingCartBar" style="display: none;">
    <div class="pos-floating-bar-inner">
        <!-- Left Button: Cart Toggle with Dot Counter Badge -->
        <button type="button" class="pos-floating-cart-btn" id="btnOpenMobileCartSheet">
            <div class="pos-cart-icon-wrapper">
                <i class="fa-solid fa-basket-shopping"></i>
                <span class="pos-cart-badge-dot" id="mobileCartDot">0</span>
            </div>
            <div class="d-flex flex-column text-start">
                <div class="pos-floating-qty" id="mobileCartQtyText">0 Menu (0 pcs)</div>
                <div class="pos-floating-total" id="mobileCartTotalText">Rp0</div>
            </div>
            <i class="fa-solid fa-chevron-up ms-auto opacity-75 small"></i>
        </button>

        <!-- Right Button: Direct Pay Button -->
        <button type="button" class="pos-floating-pay-btn" id="btnMobileGoToCheckout">
            <span>Bayar</span>
            <i class="fa-solid fa-arrow-right"></i>
        </button>
    </div>
</div>

<!-- Mobile Slide Card (Bottom Sheet Offcanvas) -->
<div class="offcanvas offcanvas-bottom pos-cart-bottom-sheet d-lg-none" tabindex="-1" id="posCartBottomSheet" aria-labelledby="posCartBottomSheetLabel">
    <div class="pos-sheet-handle-wrapper" data-bs-dismiss="offcanvas">
        <div class="pos-sheet-handle"></div>
    </div>
    <div class="offcanvas-header border-bottom py-2 px-3 bg-light">
        <div class="d-flex align-items-center gap-2">
            <i class="fa-solid fa-basket-shopping text-success fs-5"></i>
            <h6 class="offcanvas-title fw-bold text-dark m-0" id="posCartBottomSheetLabel">
                Daftar Pesanan (<span id="mobileSheetCountText">0</span>)
            </h6>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2" id="btnMobileClearCart" title="Kosongkan">
                <i class="fa-solid fa-trash-can me-1"></i> Kosongkan
            </button>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
    </div>
    <div class="offcanvas-body p-3" id="mobileSheetCartList" style="max-height: 55vh; overflow-y: auto;">
        <!-- Injected dynamically via renderCart() -->
    </div>
    <div class="pos-sheet-footer border-top p-3 bg-white">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-muted small">Total Belanja:</span>
            <span class="fs-4 fw-bold text-success" id="mobileSheetGrandTotal">Rp0</span>
        </div>
        <button type="button" class="btn btn-primary-green w-100 py-3 fw-bold fs-6 rounded-3 shadow d-flex align-items-center justify-content-center gap-2" id="btnSheetProceedPayment">
            <i class="fa-solid fa-credit-card"></i>
            <span>Bayar Sekarang</span>
            <i class="fa-solid fa-arrow-right ms-auto"></i>
        </button>
    </div>
</div>

<!-- Dedicated Fullscreen Payment Screen (Laman Bayar) -->
<div class="pos-checkout-screen" id="posCheckoutScreen">
    <!-- Header Back Navigation -->
    <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom flex-wrap gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3 fw-semibold" id="btnBackToCatalog">
            <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Katalog
        </button>
        <h5 class="fw-bold text-dark m-0 d-flex align-items-center gap-2">
            <i class="fa-solid fa-cash-register text-success"></i> Pembayaran Transaksi
        </h5>
        <div class="d-none d-md-block" style="width: 120px;"></div>
    </div>

    <div class="row g-4 justify-content-center">
        <!-- Col 1: Order Summary -->
        <div class="col-lg-5">
            <div class="p-3 bg-light rounded-4 border mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-bold text-dark small text-uppercase">Ringkasan Pesanan</span>
                    <span class="badge bg-success bg-opacity-10 text-success fw-bold" id="checkoutScreenItemCount">0 item</span>
                </div>
                <div id="checkoutScreenItemsList" class="pos-checkout-items-list mb-3" style="max-height: 250px; overflow-y: auto;">
                    <!-- Items injected dynamically -->
                </div>
                <div class="border-top pt-2">
                    <div class="d-flex justify-content-between text-muted small mb-1">
                        <span>Total Kuantitas:</span>
                        <strong class="text-dark" id="checkoutScreenTotalQty">0 pcs</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <span class="fw-bold text-dark fs-6">Total Tagihan:</span>
                        <span class="fs-2 fw-bold text-success" id="checkoutScreenGrandTotal">Rp0</span>
                    </div>
                </div>
            </div>

            <!-- Notes & Customer -->
            <div class="card border rounded-4 p-3 shadow-none">
                <div class="mb-3">
                    <label for="posScreenCustomerName" class="form-label small fw-bold text-dark">Nama Pelanggan / No. Meja</label>
                    <input type="text" id="posScreenCustomerName" class="form-control" placeholder="Opsional (contoh: Meja 4 / Bpk. Budi)">
                </div>
                <div>
                    <label for="posScreenNotes" class="form-label small fw-bold text-dark">Catatan Pesanan</label>
                    <input type="text" id="posScreenNotes" class="form-control" placeholder="Opsional (contoh: Less ice, manis sedang)">
                </div>
            </div>
        </div>

        <!-- Col 2: Payment Method, Cash Input, & Submit -->
        <div class="col-lg-6">
            <div class="card border rounded-4 p-3 shadow-none h-100 d-flex flex-column justify-content-between">
                <div>
                    <!-- Payment Methods -->
                    <label class="form-label fw-bold text-dark small mb-2">Pilih Metode Pembayaran</label>
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <input type="radio" class="btn-check" name="screen_payment_method" id="screenPayCash" value="tunai" checked>
                            <label class="btn btn-outline-success w-100 py-3 fw-bold small text-center" for="screenPayCash">
                                <i class="fa-solid fa-money-bill-wave d-block mb-1 fs-4"></i> Tunai
                            </label>
                        </div>
                        <div class="col-4">
                            <input type="radio" class="btn-check" name="screen_payment_method" id="screenPayQris" value="qris">
                            <label class="btn btn-outline-success w-100 py-3 fw-bold small text-center" for="screenPayQris">
                                <i class="fa-solid fa-qrcode d-block mb-1 fs-4"></i> QRIS
                            </label>
                        </div>
                        <div class="col-4">
                            <input type="radio" class="btn-check" name="screen_payment_method" id="screenPayTransfer" value="transfer">
                            <label class="btn btn-outline-success w-100 py-3 fw-bold small text-center" for="screenPayTransfer">
                                <i class="fa-solid fa-building-columns d-block mb-1 fs-4"></i> Transfer
                            </label>
                        </div>
                    </div>

                    <!-- Cash Section -->
                    <div id="screenSectionCash">
                        <label class="form-label fw-bold text-dark small mb-1">Pecahan Cepat Uang Diterima</label>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <button type="button" class="btn btn-quick-cash flex-fill" onclick="setScreenQuickCash('pas')">Uang Pas</button>
                            <button type="button" class="btn btn-quick-cash flex-fill" onclick="setScreenQuickCash(10000)">10.000</button>
                            <button type="button" class="btn btn-quick-cash flex-fill" onclick="setScreenQuickCash(20000)">20.000</button>
                            <button type="button" class="btn btn-quick-cash flex-fill" onclick="setScreenQuickCash(50000)">50.000</button>
                            <button type="button" class="btn btn-quick-cash flex-fill" onclick="setScreenQuickCash(100000)">100.000</button>
                            <button type="button" class="btn btn-quick-cash flex-fill" onclick="setScreenQuickCash(200000)">200.000</button>
                        </div>

                        <div class="mb-3">
                            <label for="screenCashInput" class="form-label small fw-bold text-dark">Jumlah Uang Diterima (Rp)</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text fw-bold bg-light">Rp</span>
                                <input
                                    type="number"
                                    id="screenCashInput"
                                    class="form-control form-control-lg fw-bold text-dark"
                                    placeholder="0"
                                    min="0"
                                >
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center p-3 rounded-3 border mb-4" id="screenChangeContainer" style="background: #f8fafc;">
                            <span class="fw-bold text-muted fs-6">Kembalian:</span>
                            <span class="fs-3 fw-bold text-success" id="screenChangeText">Rp0</span>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div>
                    <button type="button" class="btn btn-primary-green w-100 py-3 fw-bold fs-5 rounded-3 shadow d-flex align-items-center justify-content-center gap-2" id="btnSubmitScreenPayment">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>Proses Pembayaran & Cetak Struk</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal 1: Checkout & Pembayaran POS -->
<div class="modal fade" id="posCheckoutModal" tabindex="-1" aria-labelledby="posCheckoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white border-0 py-3" style="background-color: var(--primary-green) !important;">
                <h5 class="modal-title fw-bold" id="posCheckoutModalLabel">
                    <i class="fa-solid fa-cash-register text-warning me-2"></i> Pembayaran Transaksi
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            
            <div class="modal-body p-4">
                <!-- Grand Total Display -->
                <div class="text-center p-3 mb-4 rounded-3" style="background: var(--mint-soft);">
                    <small class="text-muted fw-semibold text-uppercase">Total Yang Harus Dibayar</small>
                    <div class="fs-1 fw-bold text-success" id="checkoutModalTotal">Rp0</div>
                </div>

                <!-- Metode Pembayaran -->
                <div class="mb-3">
                    <label class="form-label fw-bold text-dark small">Metode Pembayaran</label>
                    <div class="row g-2">
                        <div class="col-4">
                            <input type="radio" class="btn-check" name="payment_method" id="payMethodCash" value="tunai" checked>
                            <label class="btn btn-outline-success w-100 py-2 fw-semibold small" for="payMethodCash">
                                <i class="fa-solid fa-money-bill-wave d-block mb-1 fs-5"></i> Tunai
                            </label>
                        </div>
                        <div class="col-4">
                            <input type="radio" class="btn-check" name="payment_method" id="payMethodQris" value="qris">
                            <label class="btn btn-outline-success w-100 py-2 fw-semibold small" for="payMethodQris">
                                <i class="fa-solid fa-qrcode d-block mb-1 fs-5"></i> QRIS
                            </label>
                        </div>
                        <div class="col-4">
                            <input type="radio" class="btn-check" name="payment_method" id="payMethodTransfer" value="transfer">
                            <label class="btn btn-outline-success w-100 py-2 fw-semibold small" for="payMethodTransfer">
                                <i class="fa-solid fa-building-columns d-block mb-1 fs-5"></i> Transfer
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Section Tunai: Uang Diterima & Kembalian -->
                <div id="sectionCashPayment">
                    <!-- Quick Nominal Cash Buttons -->
                    <label class="form-label fw-bold text-dark small mb-1">Pecahan Cepat (Uang Diterima)</label>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button type="button" class="btn btn-quick-cash flex-fill" onclick="setQuickCash('pas')">Uang Pas</button>
                        <button type="button" class="btn btn-quick-cash flex-fill" onclick="setQuickCash(10000)">10.000</button>
                        <button type="button" class="btn btn-quick-cash flex-fill" onclick="setQuickCash(20000)">20.000</button>
                        <button type="button" class="btn btn-quick-cash flex-fill" onclick="setQuickCash(50000)">50.000</button>
                        <button type="button" class="btn btn-quick-cash flex-fill" onclick="setQuickCash(100000)">100.000</button>
                        <button type="button" class="btn btn-quick-cash flex-fill" onclick="setQuickCash(200000)">200.000</button>
                    </div>

                    <!-- Input Nominal Uang Diterima -->
                    <div class="mb-3">
                        <label for="cashTenderedInput" class="form-label fw-semibold small text-muted">Jumlah Uang Tunai Diterima (Rp)</label>
                        <div class="input-group">
                            <span class="input-group-text fw-bold">Rp</span>
                            <input
                                type="number"
                                id="cashTenderedInput"
                                class="form-control form-control-lg fw-bold text-dark"
                                placeholder="0"
                                min="0"
                            >
                        </div>
                    </div>

                    <!-- Kembalian Display -->
                    <div class="d-flex justify-content-between align-items-center p-3 rounded-3 border mb-3" id="changeContainer" style="background: #f8fafc;">
                        <span class="fw-bold text-muted">Kembalian:</span>
                        <span class="fs-4 fw-bold text-success" id="changeAmountText">Rp0</span>
                    </div>
                </div>

                <!-- Customer & Notes -->
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label for="posCustomerName" class="form-label small fw-semibold text-muted">Nama Pelanggan / No. Meja</label>
                        <input type="text" id="posCustomerName" class="form-control form-control-sm" placeholder="Opsional...">
                    </div>
                    <div class="col-6">
                        <label for="posNotes" class="form-label small fw-semibold text-muted">Catatan Pesanan</label>
                        <input type="text" id="posNotes" class="form-control form-control-sm" placeholder="Contoh: Less ice...">
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light border-0 p-3">
                <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary-green px-4 fw-bold" id="btnSubmitPayment">
                    <i class="fa-solid fa-circle-check me-2"></i> Proses Pembayaran
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal 2: Popup Struk Pembayaran -->
<div class="modal fade" id="posReceiptModal" tabindex="-1" aria-labelledby="posReceiptModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 380px;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-success text-white py-2">
                <h6 class="modal-title fw-bold" id="posReceiptModalLabel">
                    <i class="fa-solid fa-receipt me-2"></i> Struk Transaksi Kasir
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup" onclick="resetPosForNewTransaction()"></button>
            </div>
            <div class="modal-body p-3 text-center" id="receiptModalBody" style="max-height: 75vh; overflow-y: auto;">
                <!-- Receipt content injected dynamically -->
            </div>
            <div class="modal-footer bg-light border-0 p-3 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" onclick="resetPosForNewTransaction()">
                    ➕ Transaksi Baru
                </button>
                <button type="button" class="btn btn-primary-green" id="btnPrintReceiptBtn">
                    <i class="fa-solid fa-print me-1"></i> Cetak Struk
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal 3: Ringkasan Kasir Hari Ini -->
<div class="modal fade" id="posSummaryModal" tabindex="-1" aria-labelledby="posSummaryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white py-3" style="background-color: var(--primary-green) !important;">
                <h5 class="modal-title fw-bold" id="posSummaryModalLabel">
                    <i class="fa-solid fa-chart-pie text-warning me-2"></i> Rekap Shift Kasir Hari Ini
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-4" id="summaryModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-success" role="status"></div>
                    <p class="mt-2 text-muted small">Memuat ringkasan kasir...</p>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 p-3">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Iframe for Direct Isolated Thermal Receipt Printing -->
<iframe id="receiptPrintFrame" style="position:fixed; right:100%; bottom:100%; width:0; height:0; border:0; visibility:hidden;"></iframe>
@endsection

@section('scripts')
<script>
    // State Keranjang
    let cart = [];
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // Modals
    const checkoutModal = new bootstrap.Modal(document.getElementById('posCheckoutModal'));
    const receiptModal = new bootstrap.Modal(document.getElementById('posReceiptModal'));
    const summaryModal = new bootstrap.Modal(document.getElementById('posSummaryModal'));

    // Format Rupiah Helper
    function formatRupiah(number) {
        return 'Rp' + new Intl.NumberFormat('id-ID').format(number);
    }

    // Add to Cart Function
    function posAddToCart(id, name, price, code) {
        const existing = cart.find(item => item.id === id);
        if (existing) {
            existing.qty += 1;
        } else {
            cart.push({
                id: id,
                name: name,
                price: parseFloat(price),
                code: code,
                qty: 1
            });
        }
        renderCart();
    }

    // Update Quantity
    function updateCartQty(id, delta) {
        const item = cart.find(i => i.id === id);
        if (!item) return;

        item.qty += delta;
        if (item.qty <= 0) {
            cart = cart.filter(i => i.id !== id);
        }
        renderCart();
    }

    // Remove from Cart
    function removeCartItem(id) {
        cart = cart.filter(i => i.id !== id);
        renderCart();
    }

    // Offcanvas Bottom Sheet Instance
    const cartBottomSheetEl = document.getElementById('posCartBottomSheet');
    const cartBottomSheet = cartBottomSheetEl ? new bootstrap.Offcanvas(cartBottomSheetEl) : null;

    // Offcanvas Bottom Sheet Lifecycle: Sembunyikan floating bar saat sheet terbuka agar tidak tertutup backdrop
    cartBottomSheetEl?.addEventListener('show.bs.offcanvas', function() {
        const floatingBar = document.getElementById('posFloatingCartBar');
        if (floatingBar) floatingBar.style.display = 'none';
    });

    cartBottomSheetEl?.addEventListener('hidden.bs.offcanvas', function() {
        const screen = document.getElementById('posCheckoutScreen');
        const isScreenOpen = screen && screen.style.display === 'block';
        const isMobile = window.innerWidth < 992;
        const floatingBar = document.getElementById('posFloatingCartBar');
        if (floatingBar && isMobile && cart.length > 0 && !isScreenOpen) {
            floatingBar.style.display = 'block';
        }
    });

    // Render Cart HTML & Calculations (Desktop + Mobile Floating Bar + Slide Card)
    function renderCart() {
        const cartList = document.getElementById('posCartList');
        const countText = document.getElementById('cartItemCountText');
        const totalQtyEl = document.getElementById('posTotalQty');
        const grandTotalEl = document.getElementById('posGrandTotal');
        const payBtn = document.getElementById('btnOpenCheckout');

        // Mobile Floating Bar Elements
        const floatingBar = document.getElementById('posFloatingCartBar');
        const mobileDot = document.getElementById('mobileCartDot');
        const mobileQtyText = document.getElementById('mobileCartQtyText');
        const mobileTotalText = document.getElementById('mobileCartTotalText');

        // Mobile Sheet Elements
        const sheetList = document.getElementById('mobileSheetCartList');
        const sheetCountText = document.getElementById('mobileSheetCountText');
        const sheetGrandTotal = document.getElementById('mobileSheetGrandTotal');

        if (cart.length === 0) {
            // Desktop Aside
            if (cartList) {
                cartList.innerHTML = `
                    <div class="text-center py-5 text-muted" id="posCartEmpty">
                        <i class="fa-solid fa-basket-shopping fs-1 mb-3 text-secondary opacity-50"></i>
                        <h6 class="fw-semibold text-secondary">Keranjang Kosong</h6>
                        <p class="small text-muted mb-0">Klik menu di katalog sebelah kiri untuk menambahkan pesanan.</p>
                    </div>
                `;
            }
            if (countText) countText.textContent = '0 item';
            if (totalQtyEl) totalQtyEl.textContent = '0 pcs';
            if (grandTotalEl) grandTotalEl.textContent = 'Rp0';
            if (payBtn) {
                payBtn.disabled = true;
                payBtn.classList.remove('has-items');
            }

            // Mobile Floating Bar (Hide when empty)
            if (floatingBar) floatingBar.style.display = 'none';

            // Mobile Bottom Sheet
            if (sheetList) {
                sheetList.innerHTML = `
                    <div class="text-center py-5 text-muted">
                        <i class="fa-solid fa-basket-shopping fs-1 mb-3 text-secondary opacity-50"></i>
                        <h6 class="fw-semibold text-secondary">Keranjang Kosong</h6>
                        <p class="small text-muted mb-0">Pilih menu dari katalog untuk menambahkan pesanan.</p>
                    </div>
                `;
            }
            if (sheetCountText) sheetCountText.textContent = '0';
            if (sheetGrandTotal) sheetGrandTotal.textContent = 'Rp0';

            // Close checkout screen if open with empty cart
            const checkoutScreen = document.getElementById('posCheckoutScreen');
            if (checkoutScreen && checkoutScreen.style.display === 'block') {
                showCatalogView();
            }
            return;
        }

        let totalQty = 0;
        let grandTotal = 0;
        let html = '';

        cart.forEach(item => {
            const subtotal = item.price * item.qty;
            totalQty += item.qty;
            grandTotal += subtotal;

            html += `
                <div class="pos-cart-item">
                    <div class="pos-item-info">
                        <div class="pos-item-title" title="${item.name}">${item.name}</div>
                        <div class="pos-item-sub">${formatRupiah(item.price)} &times; ${item.qty} = <strong class="text-success">${formatRupiah(subtotal)}</strong></div>
                    </div>
                    <div class="pos-qty-controls">
                        <button type="button" class="pos-btn-qty" onclick="updateCartQty(${item.id}, -1)">-</button>
                        <span class="pos-qty-display">${item.qty}</span>
                        <button type="button" class="pos-btn-qty" onclick="updateCartQty(${item.id}, 1)">+</button>
                    </div>
                    <button type="button" class="pos-btn-remove" onclick="removeCartItem(${item.id})" title="Hapus">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            `;
        });

        // Desktop aside updates
        if (cartList) cartList.innerHTML = html;
        if (countText) countText.textContent = `${cart.length} item (${totalQty} pcs)`;
        if (totalQtyEl) totalQtyEl.textContent = `${totalQty} pcs`;
        if (grandTotalEl) grandTotalEl.textContent = formatRupiah(grandTotal);
        if (payBtn) {
            payBtn.disabled = false;
            payBtn.classList.add('has-items');
        }

        // Mobile Floating Bar updates (Show when cart has items on mobile only and sheet is not open)
        if (floatingBar) {
            const isMobile = window.innerWidth < 992;
            const screen = document.getElementById('posCheckoutScreen');
            const isScreenOpen = screen && screen.style.display === 'block';
            const isSheetOpen = cartBottomSheetEl && cartBottomSheetEl.classList.contains('show');

            if (isMobile && cart.length > 0 && !isScreenOpen && !isSheetOpen) {
                floatingBar.style.display = 'block';
            } else {
                floatingBar.style.display = 'none';
            }
        }
        if (mobileDot) mobileDot.textContent = totalQty > 99 ? '99+' : totalQty;
        if (mobileQtyText) mobileQtyText.textContent = `${cart.length} Menu (${totalQty} pcs)`;
        if (mobileTotalText) mobileTotalText.textContent = formatRupiah(grandTotal);

        // Mobile Bottom Sheet updates
        if (sheetList) sheetList.innerHTML = html;
        if (sheetCountText) sheetCountText.textContent = cart.length;
        if (sheetGrandTotal) sheetGrandTotal.textContent = formatRupiah(grandTotal);

        // Update Checkout screen if currently visible
        const screen = document.getElementById('posCheckoutScreen');
        if (screen && screen.style.display === 'block') {
            populateCheckoutScreen();
        }
    }

    // Window Resize Sync for Mobile Floating Bar
    window.addEventListener('resize', function() {
        const floatingBar = document.getElementById('posFloatingCartBar');
        if (floatingBar) {
            const isMobile = window.innerWidth < 992;
            const screen = document.getElementById('posCheckoutScreen');
            const isScreenOpen = screen && screen.style.display === 'block';
            const isSheetOpen = cartBottomSheetEl && cartBottomSheetEl.classList.contains('show');
            if (isMobile && cart.length > 0 && !isScreenOpen && !isSheetOpen) {
                floatingBar.style.display = 'block';
            } else {
                floatingBar.style.display = 'none';
            }
        }
    });

    // Clear Cart Helper
    function executeClearCart() {
        if (cart.length === 0) return;
        Swal.fire({
            title: 'Kosongkan Keranjang?',
            text: 'Seluruh item yang dipilih akan dihapus.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Kosongkan',
            cancelButtonText: 'Batal'
        }).then((res) => {
            if (res.isConfirmed) {
                cart = [];
                renderCart();
                if (cartBottomSheet) cartBottomSheet.hide();
            }
        });
    }

    document.getElementById('btnClearCart')?.addEventListener('click', executeClearCart);
    document.getElementById('btnMobileClearCart')?.addEventListener('click', executeClearCart);

    // Open Mobile Slide Card (Bottom Sheet)
    document.getElementById('btnOpenMobileCartSheet')?.addEventListener('click', function() {
        if (cartBottomSheet) cartBottomSheet.show();
    });

    // View Switching: Catalog vs Dedicated Payment Screen (Laman Bayar)
    function showCheckoutView() {
        if (cart.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Keranjang Kosong',
                text: 'Silakan pilih minimal 1 menu sebelum melakukan pembayaran.',
                confirmButtonColor: '#11361b'
            });
            return;
        }

        // Hide Mobile Bottom Sheet if open and safely clean up lingering backdrops
        if (cartBottomSheet) cartBottomSheet.hide();
        document.querySelectorAll('.offcanvas-backdrop, .modal-backdrop').forEach(b => b.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');

        // Hide Catalog and Desktop Aside
        const catSection = document.getElementById('posCatalogSection');
        if (catSection) catSection.style.display = 'none';
        const cartAside = document.getElementById('posCartAside');
        if (cartAside) cartAside.style.setProperty('display', 'none', 'important');

        // Hide Mobile Floating Bar
        const floatingBar = document.getElementById('posFloatingCartBar');
        if (floatingBar) floatingBar.style.display = 'none';

        // Show Fullscreen Checkout Screen
        const screen = document.getElementById('posCheckoutScreen');
        if (screen) {
            screen.style.display = 'block';
            populateCheckoutScreen();
        }

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function showCatalogView() {
        const catSection = document.getElementById('posCatalogSection');
        if (catSection) catSection.style.display = '';
        const cartAside = document.getElementById('posCartAside');
        if (cartAside) cartAside.style.removeProperty('display');

        const screen = document.getElementById('posCheckoutScreen');
        if (screen) screen.style.display = 'none';

        const floatingBar = document.getElementById('posFloatingCartBar');
        if (floatingBar) {
            const isMobile = window.innerWidth < 992;
            if (isMobile && cart.length > 0) {
                floatingBar.style.display = 'block';
            } else {
                floatingBar.style.display = 'none';
            }
        }

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Populate Data in Dedicated Checkout Screen
    function populateCheckoutScreen() {
        const grandTotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        const totalQty = cart.reduce((sum, item) => sum + item.qty, 0);

        document.getElementById('checkoutScreenGrandTotal').textContent = formatRupiah(grandTotal);
        document.getElementById('checkoutScreenTotalQty').textContent = `${totalQty} pcs`;
        document.getElementById('checkoutScreenItemCount').textContent = `${cart.length} menu`;

        let itemsHtml = '';
        cart.forEach(item => {
            const subtotal = item.price * item.qty;
            itemsHtml += `
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div class="text-truncate me-2" style="max-width: 65%;">
                        <div class="fw-semibold text-dark text-truncate">${item.name}</div>
                        <small class="text-muted">${formatRupiah(item.price)} &times; ${item.qty}</small>
                    </div>
                    <div class="fw-bold text-success">${formatRupiah(subtotal)}</div>
                </div>
            `;
        });
        document.getElementById('checkoutScreenItemsList').innerHTML = itemsHtml;

        // Auto calculate change
        calculateScreenChange();
    }

    // Desktop Checkout: Open Focused Popup Modal
    function openCheckoutModal() {
        if (cart.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Keranjang Kosong',
                text: 'Silakan pilih minimal 1 menu sebelum melakukan pembayaran.',
                confirmButtonColor: '#11361b'
            });
            return;
        }

        const grandTotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        document.getElementById('checkoutModalTotal').textContent = formatRupiah(grandTotal);

        // Reset modal inputs
        document.getElementById('cashTenderedInput').value = '';
        document.getElementById('posCustomerName').value = '';
        document.getElementById('posNotes').value = '';
        document.getElementById('payMethodCash').checked = true;
        document.getElementById('sectionCashPayment').style.display = 'block';

        calculateChange();
        checkoutModal.show();

        setTimeout(() => {
            document.getElementById('cashTenderedInput')?.focus();
        }, 400);
    }

    // Modal Quick Cash Helper
    function setQuickCash(val) {
        const grandTotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        const cashInput = document.getElementById('cashTenderedInput');
        if (val === 'pas') {
            cashInput.value = grandTotal;
        } else {
            cashInput.value = val;
        }
        calculateChange();
    }

    // Modal Change Calculator
    function calculateChange() {
        const grandTotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        const cashInput = document.getElementById('cashTenderedInput');
        const cashTendered = parseFloat(cashInput?.value) || 0;
        const change = cashTendered - grandTotal;

        const changeTextEl = document.getElementById('changeAmountText');
        if (!changeTextEl) return;

        if (change >= 0) {
            changeTextEl.textContent = formatRupiah(change);
            changeTextEl.className = 'fs-4 fw-bold text-success';
        } else {
            changeTextEl.textContent = `Uang Kurang (${formatRupiah(Math.abs(change))})`;
            changeTextEl.className = 'fs-5 fw-bold text-danger';
        }
    }
    document.getElementById('cashTenderedInput')?.addEventListener('input', calculateChange);

    // Modal Payment Method Radio Change
    document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const cashSection = document.getElementById('sectionCashPayment');
            if (this.value === 'tunai') {
                cashSection.style.display = 'block';
                document.getElementById('cashTenderedInput').focus();
            } else {
                cashSection.style.display = 'none';
            }
        });
    });

    // Wire Up Checkout Buttons
    document.getElementById('btnMobileGoToCheckout')?.addEventListener('click', showCheckoutView);
    document.getElementById('btnSheetProceedPayment')?.addEventListener('click', showCheckoutView);
    document.getElementById('btnOpenCheckout')?.addEventListener('click', function() {
        if (window.innerWidth >= 992) {
            openCheckoutModal();
        } else {
            showCheckoutView();
        }
    });
    document.getElementById('btnBackToCatalog')?.addEventListener('click', showCatalogView);

    // Screen Payment Method Radio Change
    document.querySelectorAll('input[name="screen_payment_method"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const cashSec = document.getElementById('screenSectionCash');
            if (this.value === 'tunai') {
                cashSec.style.display = 'block';
                document.getElementById('screenCashInput').focus();
            } else {
                cashSec.style.display = 'none';
            }
        });
    });

    // Screen Quick Cash
    function setScreenQuickCash(val) {
        const grandTotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        const input = document.getElementById('screenCashInput');
        if (val === 'pas') {
            input.value = grandTotal;
        } else {
            input.value = val;
        }
        calculateScreenChange();
    }

    function calculateScreenChange() {
        const grandTotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        const cashInput = document.getElementById('screenCashInput');
        const cashTendered = parseFloat(cashInput?.value) || 0;
        const change = cashTendered - grandTotal;

        const changeText = document.getElementById('screenChangeText');
        if (!changeText) return;

        if (change >= 0) {
            changeText.textContent = formatRupiah(change);
            changeText.className = 'fs-3 fw-bold text-success';
        } else {
            changeText.textContent = `Kurang (${formatRupiah(Math.abs(change))})`;
            changeText.className = 'fs-4 fw-bold text-danger';
        }
    }
    document.getElementById('screenCashInput')?.addEventListener('input', calculateScreenChange);

    // Shared Process Payment Checkout Function
    function executePaymentSubmission(paymentMethod, cashTendered, customerName, notes, submitBtn) {
        const grandTotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);

        if (paymentMethod === 'tunai') {
            if (isNaN(cashTendered) || cashTendered < grandTotal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Uang Tunai Kurang',
                    text: `Jumlah uang diterima minimal ${formatRupiah(grandTotal)}`,
                    confirmButtonColor: '#11361b'
                });
                return;
            }
        }

        const payload = {
            items: cart.map(i => ({ menu_id: i.id, quantity: i.qty })),
            payment_method: paymentMethod,
            cash_tendered: paymentMethod === 'tunai' ? cashTendered : grandTotal,
            customer_name: customerName,
            notes: notes,
        };

        const originalBtnHtml = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses...';

        fetch("{{ route('pos.checkout') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(async res => {
            if (!res.ok) {
                if (res.status === 419) {
                    throw new Error('Sesi atau token CSRF telah kedaluwarsa. Silakan muat ulang (refresh) halaman.');
                }
                const errData = await res.json().catch(() => null);
                if (errData) {
                    let errMsg = errData.message || 'Gagal memproses transaksi.';
                    if (errData.errors) {
                        const errList = Object.values(errData.errors).flat();
                        if (errList.length > 0) errMsg = errList[0];
                    }
                    throw new Error(errMsg);
                }
                throw new Error(`Server mengembalikan respon error (Status ${res.status}).`);
            }
            return res.json();
        })
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnHtml;

            if (data.success) {
                // Return screen back to catalog view
                showCatalogView();
                checkoutModal.hide();

                // Show receipt modal with generated HTML
                document.getElementById('receiptModalBody').innerHTML = data.receipt_html;
                receiptModal.show();

                // Clear active cart safely
                cart = [];
                renderCart();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal Memproses Transaksi',
                    text: data.message || 'Terjadi kesalahan pada sistem.',
                    confirmButtonColor: '#11361b'
                });
            }
        })
        .catch(err => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnHtml;

            const isConnectionErr = err.name === 'TypeError' && err.message.includes('fetch');
            Swal.fire({
                icon: 'error',
                title: isConnectionErr ? 'Error Koneksi' : 'Gagal Memproses Transaksi',
                text: isConnectionErr ? 'Gagal menghubungi server. Periksa jaringan Anda.' : err.message,
                confirmButtonColor: '#11361b'
            });
        });
    }

    // Submit Payment from Dedicated Screen
    document.getElementById('btnSubmitScreenPayment')?.addEventListener('click', function() {
        const paymentMethod = document.querySelector('input[name="screen_payment_method"]:checked')?.value || 'tunai';
        const cashTendered = parseFloat(document.getElementById('screenCashInput').value);
        const customerName = document.getElementById('posScreenCustomerName').value;
        const notes = document.getElementById('posScreenNotes').value;

        executePaymentSubmission(paymentMethod, cashTendered, customerName, notes, this);
    });

    // Submit Payment from Modal (for modal fallback if used)
    document.getElementById('btnSubmitPayment')?.addEventListener('click', function() {
        const paymentMethod = document.querySelector('input[name="payment_method"]:checked')?.value || 'tunai';
        const cashTendered = parseFloat(document.getElementById('cashTenderedInput').value);
        const customerName = document.getElementById('posCustomerName').value;
        const notes = document.getElementById('posNotes').value;

        executePaymentSubmission(paymentMethod, cashTendered, customerName, notes, this);
    });

    // Print Receipt Button in Modal (Isolated Hidden Iframe Method)
    document.getElementById('btnPrintReceiptBtn')?.addEventListener('click', function() {
        const receiptCard = document.getElementById('printableReceipt');
        if (!receiptCard) {
            window.print();
            return;
        }

        const printFrame = document.getElementById('receiptPrintFrame');
        if (!printFrame) {
            window.print();
            return;
        }

        try {
            const frameDoc = printFrame.contentDocument || printFrame.contentWindow.document;
            frameDoc.open();
            frameDoc.write(`
                <!DOCTYPE html>
                <html lang="id">
                <head>
                    <meta charset="UTF-8">
                    <title>Struk Pembayaran</title>
                    <style>
                        * {
                            margin: 0;
                            padding: 0;
                            box-sizing: border-box;
                            font-family: 'Courier New', Courier, monospace !important;
                            font-size: 12px;
                            color: #000000;
                        }
                        body {
                            width: 80mm;
                            margin: 0 auto;
                            padding: 2mm 3mm;
                            background: #ffffff;
                        }
                        .receipt-card {
                            width: 100% !important;
                            max-width: 80mm !important;
                            background: #ffffff;
                            padding: 0 !important;
                            box-shadow: none !important;
                            border: none !important;
                        }
                        .receipt-header {
                            text-align: center;
                            margin-bottom: 8px;
                            padding-bottom: 6px;
                            border-bottom: 1px dashed #000000;
                        }
                        .receipt-header h1 {
                            font-size: 14px;
                            font-weight: bold;
                            text-transform: uppercase;
                            margin-bottom: 2px;
                        }
                        .receipt-header p {
                            font-size: 10px;
                            margin: 0;
                        }
                        .receipt-meta {
                            margin-bottom: 8px;
                            font-size: 11px;
                            padding-bottom: 6px;
                            border-bottom: 1px dashed #000000;
                        }
                        .receipt-row {
                            display: flex;
                            justify-content: space-between;
                            margin-bottom: 2px;
                        }
                        .receipt-items {
                            margin-bottom: 8px;
                            padding-bottom: 6px;
                            border-bottom: 1px dashed #000000;
                        }
                        .item-row {
                            margin-bottom: 4px;
                        }
                        .item-name {
                            font-weight: bold;
                            font-size: 11px;
                        }
                        .item-calc {
                            display: flex;
                            justify-content: space-between;
                            font-size: 11px;
                        }
                        .receipt-totals {
                            margin-bottom: 8px;
                            padding-bottom: 6px;
                            border-bottom: 1px dashed #000000;
                            font-size: 11px;
                        }
                        .receipt-totals .receipt-row.grand-total {
                            font-size: 13px;
                            font-weight: bold;
                            margin: 3px 0;
                            padding-top: 3px;
                            border-top: 1px dotted #000000;
                        }
                        .receipt-footer {
                            text-align: center;
                            font-size: 10px;
                            margin-top: 8px;
                        }
                        .no-print {
                            display: none !important;
                        }
                        @page {
                            size: 80mm auto;
                            margin: 0;
                        }
                    </style>
                </head>
                <body>
                    ${receiptCard.outerHTML}
                </body>
                </html>
            `);
            frameDoc.close();

            setTimeout(() => {
                printFrame.contentWindow.focus();
                printFrame.contentWindow.print();
            }, 250);
        } catch (e) {
            console.error('Iframe printing error:', e);
            window.print();
        }
    });

    // ==========================================
    // POS Catalog Filtering (Category & Search)
    // ==========================================
    let currentCategory = 'all';

    function filterCatalog() {
        const searchInput = document.getElementById('posSearchInput');
        const btnClearSearch = document.getElementById('btnClearSearch');
        const query = (searchInput?.value || '').trim().toLowerCase();

        if (btnClearSearch) {
            if (query.length > 0) {
                btnClearSearch.classList.remove('d-none');
            } else {
                btnClearSearch.classList.add('d-none');
            }
        }

        const items = document.querySelectorAll('#posProductGrid .menu-item-element');
        let visibleCount = 0;

        items.forEach(item => {
            const itemCategory = item.getAttribute('data-category');
            const itemName = (item.getAttribute('data-name') || '').toLowerCase();
            const itemCode = (item.getAttribute('data-code') || '').toLowerCase();

            const matchCategory = (currentCategory === 'all') || (itemCategory === currentCategory);
            const matchSearch = !query || itemName.includes(query) || itemCode.includes(query);

            if (matchCategory && matchSearch) {
                item.style.display = '';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });

        // Handle empty state inside posProductGrid
        let emptyStateEl = document.getElementById('posEmptyFilterState');
        if (visibleCount === 0) {
            if (!emptyStateEl) {
                emptyStateEl = document.createElement('div');
                emptyStateEl.id = 'posEmptyFilterState';
                emptyStateEl.className = 'w-100 text-center py-5 text-muted';
                emptyStateEl.style.gridColumn = '1 / -1';
                emptyStateEl.innerHTML = `
                    <div class="mb-3">
                        <i class="fa-solid fa-utensils fs-1 text-secondary opacity-50"></i>
                    </div>
                    <h6 class="fw-bold">Tidak ada menu yang sesuai</h6>
                    <p class="small text-muted mb-0">Coba ubah kata kunci pencarian atau pilih kategori lain.</p>
                `;
                document.getElementById('posProductGrid')?.appendChild(emptyStateEl);
            } else {
                emptyStateEl.style.display = 'block';
            }
        } else if (emptyStateEl) {
            emptyStateEl.style.display = 'none';
        }
    }

    // Category Nav Buttons Event Handlers
    document.querySelectorAll('#categoryNav .pos-cat-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('#categoryNav .pos-cat-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentCategory = this.getAttribute('data-category') || 'all';
            
            // Smoothly center the active category button in scroll view on mobile/small viewports
            this.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });

            filterCatalog();
        });
    });

    // Search Input Event Handlers
    const posSearchInputEl = document.getElementById('posSearchInput');
    posSearchInputEl?.addEventListener('input', function() {
        filterCatalog();
    });

    document.getElementById('btnClearSearch')?.addEventListener('click', function() {
        if (posSearchInputEl) {
            posSearchInputEl.value = '';
            posSearchInputEl.focus();
        }
        filterCatalog();
    });

    function resetPosForNewTransaction() {
        cart = [];
        renderCart();
        currentCategory = 'all';
        document.querySelectorAll('#categoryNav .pos-cat-btn').forEach(b => {
            if (b.getAttribute('data-category') === 'all') {
                b.classList.add('active');
            } else {
                b.classList.remove('active');
            }
        });
        const searchInput = document.getElementById('posSearchInput');
        if (searchInput) searchInput.value = '';
        filterCatalog();
    }

    // Cashier Summary Modal
    document.getElementById('btnOpenSummary')?.addEventListener('click', function() {
        summaryModal.show();
        const bodyEl = document.getElementById('summaryModalBody');
        bodyEl.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-success" role="status"></div>
                <p class="mt-2 text-muted small">Memuat ringkasan kasir...</p>
            </div>
        `;

        fetch("{{ route('pos.summary') }}", {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            let trHtml = '';
            if (data.transactions.length === 0) {
                trHtml = '<tr><td colspan="4" class="text-center text-muted py-3">Belum ada transaksi hari ini</td></tr>';
            } else {
                data.transactions.slice(0, 5).forEach(t => {
                    trHtml += `
                        <tr>
                            <td><small class="fw-bold">${t.transaction_number}</small></td>
                            <td><small>${t.transaction_time.substring(0, 5)}</small></td>
                            <td><span class="badge bg-secondary text-uppercase" style="font-size:0.7rem;">${t.payment_method || 'tunai'}</span></td>
                            <td class="text-end fw-bold text-success">${formatRupiah(t.total_sales)}</td>
                        </tr>
                    `;
                });
            }

            bodyEl.innerHTML = `
                <div class="mb-3 text-center">
                    <h6 class="fw-bold text-dark mb-0">${data.cashier_name}</h6>
                    <small class="text-muted">${data.date}</small>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-3 text-center border">
                            <small class="text-muted">Total Transaksi</small>
                            <h4 class="fw-bold text-dark m-0">${data.total_transactions}</h4>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-3 text-center border">
                            <small class="text-muted">Produk Terjual</small>
                            <h4 class="fw-bold text-dark m-0">${data.total_qty} pcs</h4>
                        </div>
                    </div>
                </div>

                <div class="p-3 rounded-3 text-white mb-3" style="background: var(--primary-green);">
                    <small class="opacity-75">Total Penjualan Kasir Hari Ini</small>
                    <h3 class="fw-bold m-0" style="color: var(--light-accent);">${formatRupiah(data.total_sales)}</h3>
                    <div class="d-flex justify-content-between mt-2 pt-2 border-top border-white border-opacity-25 small opacity-90">
                        <span>Tunai: ${formatRupiah(data.total_tunai)}</span>
                        <span>Non-Tunai: ${formatRupiah(data.total_non_tunai)}</span>
                    </div>
                </div>

                <h6 class="fw-bold text-dark small mb-2">5 Transaksi Terakhir:</h6>
                <div class="table-responsive border rounded-3">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr class="table-light">
                                <th>No. Nota</th>
                                <th>Jam</th>
                                <th>Metode</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>${trHtml}</tbody>
                    </table>
                </div>
            `;
        })
        .catch(() => {
            bodyEl.innerHTML = '<div class="alert alert-danger mb-0">Gagal memuat data ringkasan.</div>';
        });
    });

    // Keyboard Shortcuts
    document.addEventListener('keydown', function(e) {
        // F2 to focus search
        if (e.key === 'F2') {
            e.preventDefault();
            document.getElementById('posSearchInput')?.focus();
        }
        // F9 to open checkout (Modal on desktop, Dedicated Screen on mobile)
        if (e.key === 'F9') {
            e.preventDefault();
            if (cart.length > 0) {
                if (window.innerWidth >= 992) {
                    openCheckoutModal();
                } else {
                    showCheckoutView();
                }
            }
        }
    });
</script>
@endsection
