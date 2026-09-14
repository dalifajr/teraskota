@extends('layouts.pos')

@section('title', 'Terminal Kasir POS')

@section('styles')
<style>
    /* POS Catalog Layout */
    .pos-catalog-panel {
        flex: 1;
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
        width: 420px;
        min-width: 360px;
        display: flex;
        flex-direction: column;
        background: #ffffff;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.03);
        border: 1px solid #e2e8f0;
        overflow: hidden;
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

    /* Product Grid */
    .pos-product-grid {
        flex: 1;
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
        padding: 1.2rem;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
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

    @media (max-width: 991px) {
        .pos-catalog-panel {
            margin-right: 0;
            margin-bottom: 1rem;
            min-height: 500px;
        }
        .pos-cart-panel {
            width: 100%;
        }
    }
</style>
@endsection

@section('content')
<!-- Left Panel: Menu Catalog -->
<section class="pos-catalog-panel">
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
        <button class="pos-cat-btn active" data-category="all">
            <i class="fa-solid fa-border-all"></i> Semua Menu
        </button>
        @foreach($categories as $cat)
            <button class="pos-cat-btn" data-category="cat-{{ $cat->id }}">
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

<!-- Right Panel: Interactive Shopping Cart -->
<aside class="pos-cart-panel">
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

    // Render Cart HTML & Calculations
    function renderCart() {
        const cartList = document.getElementById('posCartList');
        const countText = document.getElementById('cartItemCountText');
        const totalQtyEl = document.getElementById('posTotalQty');
        const grandTotalEl = document.getElementById('posGrandTotal');
        const payBtn = document.getElementById('btnOpenCheckout');

        if (cart.length === 0) {
            cartList.innerHTML = `
                <div class="text-center py-5 text-muted" id="posCartEmpty">
                    <i class="fa-solid fa-basket-shopping fs-1 mb-3 text-secondary opacity-50"></i>
                    <h6 class="fw-semibold text-secondary">Keranjang Kosong</h6>
                    <p class="small text-muted mb-0">Klik menu di katalog sebelah kiri untuk menambahkan pesanan.</p>
                </div>
            `;
            countText.textContent = '0 item';
            totalQtyEl.textContent = '0 pcs';
            grandTotalEl.textContent = 'Rp0';
            payBtn.disabled = true;
            payBtn.classList.remove('has-items');
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

        cartList.innerHTML = html;
        countText.textContent = `${cart.length} item (${totalQty} pcs)`;
        totalQtyEl.textContent = `${totalQty} pcs`;
        grandTotalEl.textContent = formatRupiah(grandTotal);
        payBtn.disabled = false;
        payBtn.classList.add('has-items');
    }

    // Clear Cart
    document.getElementById('btnClearCart')?.addEventListener('click', function() {
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
            }
        });
    });

    // Category Filter Navigation
    document.querySelectorAll('.pos-cat-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.pos-cat-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            const selectedCat = this.getAttribute('data-category');
            filterCatalog();
        });
    });

    // Live Search Filter
    const searchInput = document.getElementById('posSearchInput');
    const clearSearchBtn = document.getElementById('btnClearSearch');

    searchInput?.addEventListener('input', function() {
        if (this.value.length > 0) {
            clearSearchBtn.classList.remove('d-none');
        } else {
            clearSearchBtn.classList.add('d-none');
        }
        filterCatalog();
    });

    clearSearchBtn?.addEventListener('click', function() {
        searchInput.value = '';
        this.classList.add('d-none');
        searchInput.focus();
        filterCatalog();
    });

    function filterCatalog() {
        const query = (searchInput.value || '').toLowerCase().trim();
        const activeCategory = document.querySelector('.pos-cat-btn.active')?.getAttribute('data-category') || 'all';

        document.querySelectorAll('.menu-item-element').forEach(card => {
            const name = (card.getAttribute('data-name') || '').toLowerCase();
            const code = (card.getAttribute('data-code') || '').toLowerCase();
            const itemCat = card.getAttribute('data-category');

            const matchesCategory = (activeCategory === 'all' || itemCat === activeCategory);
            const matchesQuery = (!query || name.includes(query) || code.includes(query));

            if (matchesCategory && matchesQuery) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // Open Checkout Modal
    document.getElementById('btnOpenCheckout')?.addEventListener('click', function() {
        if (cart.length === 0) return;

        const grandTotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        document.getElementById('checkoutModalTotal').textContent = formatRupiah(grandTotal);

        // Reset inputs
        document.getElementById('payMethodCash').checked = true;
        document.getElementById('sectionCashPayment').style.display = 'block';
        document.getElementById('cashTenderedInput').value = '';
        document.getElementById('changeAmountText').textContent = 'Rp0';
        document.getElementById('changeAmountText').className = 'fs-4 fw-bold text-success';
        document.getElementById('posCustomerName').value = '';
        document.getElementById('posNotes').value = '';

        checkoutModal.show();
        setTimeout(() => document.getElementById('cashTenderedInput').focus(), 400);
    });

    // Payment Method Radio Change
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

    // Quick Cash Buttons
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

    // Calculate Change
    document.getElementById('cashTenderedInput')?.addEventListener('input', calculateChange);

    function calculateChange() {
        const grandTotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        const cashTendered = parseFloat(document.getElementById('cashTenderedInput').value) || 0;
        const change = cashTendered - grandTotal;

        const changeTextEl = document.getElementById('changeAmountText');
        if (change >= 0) {
            changeTextEl.textContent = formatRupiah(change);
            changeTextEl.className = 'fs-4 fw-bold text-success';
        } else {
            changeTextEl.textContent = `Uang Kurang (${formatRupiah(Math.abs(change))})`;
            changeTextEl.className = 'fs-5 fw-bold text-danger';
        }
    }

    // Submit Payment
    document.getElementById('btnSubmitPayment')?.addEventListener('click', function() {
        const grandTotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        const paymentMethod = document.querySelector('input[name="payment_method"]:checked')?.value || 'tunai';
        const cashTendered = parseFloat(document.getElementById('cashTenderedInput').value);

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
            customer_name: document.getElementById('posCustomerName').value,
            notes: document.getElementById('posNotes').value,
        };

        const submitBtn = this;
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
            submitBtn.innerHTML = '<i class="fa-solid fa-circle-check me-2"></i> Proses Pembayaran';

            if (data.success) {
                checkoutModal.hide();
                
                // Show receipt modal with generated HTML
                document.getElementById('receiptModalBody').innerHTML = data.receipt_html;
                receiptModal.show();

                // Clear active cart
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
            submitBtn.innerHTML = '<i class="fa-solid fa-circle-check me-2"></i> Proses Pembayaran';
            
            const isConnectionErr = err.name === 'TypeError' && err.message.includes('fetch');
            Swal.fire({
                icon: 'error',
                title: isConnectionErr ? 'Error Koneksi' : 'Gagal Memproses Transaksi',
                text: isConnectionErr ? 'Gagal menghubungi server. Periksa jaringan Anda.' : err.message,
                confirmButtonColor: '#11361b'
            });
        });
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

    function resetPosForNewTransaction() {
        cart = [];
        renderCart();
        document.getElementById('posSearchInput').value = '';
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
        // F9 to open checkout
        if (e.key === 'F9') {
            e.preventDefault();
            if (cart.length > 0) {
                document.getElementById('btnOpenCheckout')?.click();
            }
        }
    });
</script>
@endsection
