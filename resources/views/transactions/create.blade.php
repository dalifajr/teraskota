@extends('layouts.app')

@section('title', 'Input Transaksi')
@section('page_title', 'Input Transaksi Manual')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('transactions.index') }}" class="text-decoration-none text-success">Transaksi</a></li>
    <li class="breadcrumb-item active" aria-current="page">Input Baru</li>
@endsection

@section('content')
<div class="row">
    <!-- Left: Menu Catalog -->
    <div class="col-lg-7 col-xl-8 mb-4 mb-lg-0">
        <div class="card premium-card h-100">
            <div class="premium-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="m-0 fw-semibold text-dark"><i class="fa-solid fa-mug-hot text-success me-2"></i> Katalog Menu</h5>
                <div class="d-flex gap-2">
                    <input type="text" id="menuSearch" class="form-control form-control-sm" placeholder="Cari nama / kode menu...">
                </div>
            </div>
            <div class="card-body p-4">
                <!-- Category Filter Tabs -->
                <ul class="nav nav-pills mb-4 gap-2 border-bottom pb-3" id="categoryTabs">
                    <li class="nav-item">
                        <button type="button" class="nav-link active btn-sm" data-category="all">Semua</button>
                    </li>
                    @foreach($categories as $category)
                        <li class="nav-item">
                            <button type="button" class="nav-link btn-sm" data-category="cat-{{ $category->id }}">{{ $category->name }}</button>
                        </li>
                    @endforeach
                </ul>

                <!-- Grid of Menus -->
                <div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-3" id="menuCatalog">
                    @foreach($categories as $category)
                        @foreach($category->menus as $menu)
                            @php
                                $menuProfitPct = $menu->use_global_profit ? $globalProfitPercentage : ($menu->profit_percentage ?? $globalProfitPercentage);
                            @endphp
                            <div class="col menu-card-item" data-category-id="cat-{{ $category->id }}" data-menu-name="{{ strtolower($menu->name) }}" data-menu-code="{{ strtolower($menu->code) }}">
                                <div class="card pos-menu-card h-100 p-2 text-center js-add-menu-card"
                                     role="button"
                                     tabindex="0"
                                     data-id="{{ $menu->id }}"
                                     data-name="{{ $menu->name }}"
                                     data-price="{{ (float) $menu->price }}"
                                     data-profit="{{ (float) $menuProfitPct }}">
                                    <div class="fw-bold small text-muted mb-1">{{ $menu->code }}</div>
                                    <div class="fw-semibold text-dark text-truncate mb-2" style="font-size:0.95rem;" title="{{ $menu->name }}">{{ $menu->name }}</div>
                                    <div class="mt-auto text-success fw-bold" style="font-variant-numeric: tabular-nums;">Rp{{ number_format($menu->price, 0, ',', '.') }}</div>
                                </div>
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Right: Cart & Checkout Form -->
    <div class="col-lg-5 col-xl-4">
        <div class="card premium-card h-100">
            <div class="premium-header">
                <h5 class="m-0 fw-semibold text-dark"><i class="fa-solid fa-shopping-cart text-success me-2"></i> Keranjang Belanja</h5>
            </div>
            <div class="card-body p-4 d-flex flex-column" style="min-height: 520px;">
                <form action="{{ route('transactions.store') }}" method="POST" id="checkoutForm" class="d-flex flex-column h-100 flex-grow-1">
                    @csrf

                    <!-- Date & Time -->
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label for="transaction_date" class="form-label small fw-semibold text-muted mb-1">Tanggal</label>
                            <input type="date" name="transaction_date" id="transaction_date" class="form-control form-control-sm" value="{{ old('transaction_date', $todayDate) }}" required>
                        </div>
                        <div class="col-6">
                            <label for="transaction_time" class="form-label small fw-semibold text-muted mb-1">Waktu</label>
                            <input type="time" name="transaction_time" id="transaction_time" class="form-control form-control-sm" value="{{ old('transaction_time', $nowTime) }}" required>
                        </div>
                    </div>

                    <!-- Customer & Payment Method -->
                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label for="customer_name" class="form-label small fw-semibold text-muted mb-1">Pelanggan (Opsional)</label>
                            <input type="text" name="customer_name" id="customer_name" class="form-control form-control-sm" value="{{ old('customer_name') }}" placeholder="Nama pelanggan...">
                        </div>
                        <div class="col-5">
                            <label for="payment_method" class="form-label small fw-semibold text-muted mb-1">Metode</label>
                            <select name="payment_method" id="payment_method" class="form-select form-select-sm">
                                <option value="tunai" {{ old('payment_method', 'tunai') === 'tunai' ? 'selected' : '' }}>Tunai</option>
                                <option value="qris" {{ old('payment_method') === 'qris' ? 'selected' : '' }}>QRIS</option>
                            </select>
                        </div>
                    </div>

                    <!-- Cart List -->
                    <div class="pos-cart-list flex-grow-1 mb-3" id="cartContainer"></div>
                    <div id="hiddenInputs"></div>

                    <!-- Optional Discount -->
                    <div class="row g-2 mb-3">
                        <div class="col-5">
                            <label for="discount_type" class="form-label small fw-semibold text-muted mb-1">Diskon</label>
                            <select name="discount_type" id="discount_type" class="form-select form-select-sm">
                                <option value="none">Tanpa Diskon</option>
                                <option value="nominal">Nominal (Rp)</option>
                                <option value="percentage">Persen (%)</option>
                            </select>
                        </div>
                        <div class="col-7">
                            <label for="discount_value" class="form-label small fw-semibold text-muted mb-1">Nilai Diskon</label>
                            <input type="number" name="discount_value" id="discount_value" class="form-control form-control-sm" min="0" step="1" value="{{ old('discount_value', 0) }}" placeholder="0">
                        </div>
                    </div>

                    <!-- Summary math section -->
                    <div class="border-top pt-3 mb-3" style="font-variant-numeric: tabular-nums;">
                        <div class="d-flex justify-content-between mb-1 small text-muted">
                            <span>Total Quantity</span>
                            <span id="totalQtyVal" class="fw-semibold text-dark">0 pcs</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1 small text-muted">
                            <span>Subtotal / Diskon</span>
                            <span id="discountSummaryVal" class="fw-semibold text-danger">-Rp0</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1 small text-muted">
                            <span>Estimasi Modal</span>
                            <span id="estimatedCostVal" class="fw-semibold text-dark">Rp0</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1 small text-muted">
                            <span>Estimasi Keuntungan</span>
                            <span id="totalProfitVal" class="fw-semibold text-success">Rp0</span>
                        </div>
                        <div class="d-flex justify-content-between border-top pt-2 mt-2">
                            <span class="fw-bold text-dark">Total Omzet</span>
                            <span id="totalSalesVal" class="fw-bold text-primary fs-5">Rp0</span>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="mb-3">
                        <label for="notes" class="form-label small fw-semibold text-muted mb-1">Catatan Transaksi (Opsional)</label>
                        <textarea name="notes" id="notes" rows="2" class="form-control form-control-sm" placeholder="Catatan tambahan...">{{ old('notes') }}</textarea>
                    </div>

                    <!-- Form Buttons -->
                    <div class="row g-2">
                        <div class="col-6">
                            <button type="button" id="btnClearCart" class="btn btn-outline-secondary w-100 py-2 btn-sm fw-semibold"><i class="fa-solid fa-rotate-left me-1"></i> Reset</button>
                        </div>
                        <div class="col-6">
                            <button type="submit" class="btn btn-primary-green w-100 py-2 btn-sm fw-semibold"><i class="fa-solid fa-save me-1"></i> Simpan</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    let cart = [];

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function addToCart(menuId, name, price, profitPercentage) {
        const id = Number(menuId);
        const existing = cart.find(item => item.menuId === id);
        if (existing) {
            existing.quantity += 1;
        } else {
            cart.push({
                menuId: id,
                name: String(name || ''),
                price: parseFloat(price) || 0,
                profitPercentage: parseFloat(profitPercentage) || 0,
                quantity: 1
            });
        }
        renderCart();
    }

    function changeQty(menuId, step) {
        const item = cart.find(i => i.menuId === menuId);
        if (!item) return;
        item.quantity += step;
        if (item.quantity <= 0) {
            cart = cart.filter(i => i.menuId !== menuId);
        }
        renderCart();
    }

    function setQty(menuId, rawVal) {
        const qty = parseInt(rawVal, 10);
        if (isNaN(qty) || qty <= 0) {
            cart = cart.filter(i => i.menuId !== menuId);
        } else {
            const item = cart.find(i => i.menuId === menuId);
            if (item) item.quantity = qty;
        }
        renderCart();
    }

    function renderCart() {
        const container = document.getElementById('cartContainer');
        const hiddenInputs = document.getElementById('hiddenInputs');
        container.innerHTML = '';
        hiddenInputs.innerHTML = '';

        if (cart.length === 0) {
            container.innerHTML = `
                <div class="text-center text-muted py-5" id="cartEmptyState">
                    <i class="fa-solid fa-cart-shopping fs-1 mb-3 text-secondary"></i>
                    <p class="m-0">Keranjang masih kosong</p>
                    <small>Klik menu di katalog untuk menambahkan</small>
                </div>`;
            document.getElementById('totalQtyVal').textContent = '0 pcs';
            document.getElementById('discountSummaryVal').textContent = '-Rp0';
            document.getElementById('estimatedCostVal').textContent = 'Rp0';
            document.getElementById('totalProfitVal').textContent = 'Rp0';
            document.getElementById('totalSalesVal').textContent = 'Rp0';
            return;
        }

        let totalQty = 0;
        let subtotalAll = 0;
        let totalProfit = 0;
        let totalCost = 0;

        cart.forEach((item, index) => {
            const sub = item.price * item.quantity;
            const profit = sub * (item.profitPercentage / 100);
            const cost = sub - profit;
            totalQty += item.quantity;
            subtotalAll += sub;
            totalProfit += profit;
            totalCost += cost;

            const row = document.createElement('div');
            row.className = 'pos-cart-item d-flex justify-content-between align-items-center';
            row.innerHTML = `
                <div style="max-width: 52%;">
                    <div class="fw-semibold text-dark text-truncate">${escapeHtml(item.name)}</div>
                    <small class="text-muted">Rp${item.price.toLocaleString('id-ID')} &bull; Margin ${item.profitPercentage}%</small>
                </div>
                <div class="d-flex align-items-center gap-1">
                    <div class="input-group input-group-sm" style="width: 96px;">
                        <button type="button" class="btn btn-outline-secondary px-2 js-qty-dec" data-id="${item.menuId}">-</button>
                        <input type="text" class="form-control text-center px-1 js-qty-input" data-id="${item.menuId}" value="${item.quantity}">
                        <button type="button" class="btn btn-outline-secondary px-2 js-qty-inc" data-id="${item.menuId}">+</button>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger border-0 js-qty-del" data-id="${item.menuId}"><i class="fa-solid fa-trash"></i></button>
                </div>`;
            container.appendChild(row);

            const mInp = document.createElement('input');
            mInp.type = 'hidden';
            mInp.name = `items[${index}][menu_id]`;
            mInp.value = item.menuId;
            hiddenInputs.appendChild(mInp);

            const qInp = document.createElement('input');
            qInp.type = 'hidden';
            qInp.name = `items[${index}][quantity]`;
            qInp.value = item.quantity;
            hiddenInputs.appendChild(qInp);
        });

        const discType = document.getElementById('discount_type')?.value || 'none';
        const discVal = parseFloat(document.getElementById('discount_value')?.value || '0') || 0;
        let discAmt = 0;
        if (discType === 'percentage' && discVal > 0) {
            discAmt = Math.round(subtotalAll * (Math.min(100, discVal) / 100));
        } else if (discType === 'nominal' && discVal > 0) {
            discAmt = Math.min(subtotalAll, discVal);
        }

        const netSales = Math.max(0, subtotalAll - discAmt);
        const netProfit = totalProfit - discAmt;

        document.getElementById('totalQtyVal').textContent = totalQty + ' pcs';
        document.getElementById('discountSummaryVal').textContent = '-Rp' + Math.round(discAmt).toLocaleString('id-ID');
        document.getElementById('estimatedCostVal').textContent = 'Rp' + Math.round(totalCost).toLocaleString('id-ID');
        document.getElementById('totalProfitVal').textContent = 'Rp' + Math.round(netProfit).toLocaleString('id-ID');
        document.getElementById('totalSalesVal').textContent = 'Rp' + Math.round(netSales).toLocaleString('id-ID');
    }

    document.addEventListener('DOMContentLoaded', function () {
        renderCart();

        document.querySelectorAll('.js-add-menu-card').forEach(card => {
            const trigger = () => addToCart(card.dataset.id, card.dataset.name, card.dataset.price, card.dataset.profit);
            card.addEventListener('click', trigger);
            card.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); trigger(); } });
        });

        document.getElementById('cartContainer')?.addEventListener('click', function (e) {
            const dec = e.target.closest('.js-qty-dec');
            if (dec) return changeQty(Number(dec.dataset.id), -1);
            const inc = e.target.closest('.js-qty-inc');
            if (inc) return changeQty(Number(inc.dataset.id), 1);
            const del = e.target.closest('.js-qty-del');
            if (del) { cart = cart.filter(i => i.menuId !== Number(del.dataset.id)); renderCart(); }
        });

        document.getElementById('cartContainer')?.addEventListener('change', function (e) {
            const inp = e.target.closest('.js-qty-input');
            if (inp) setQty(Number(inp.dataset.id), inp.value);
        });

        document.getElementById('discount_type')?.addEventListener('change', renderCart);
        document.getElementById('discount_value')?.addEventListener('input', renderCart);

        document.getElementById('btnClearCart')?.addEventListener('click', async function () {
            if (cart.length === 0) return;
            const ok = window.KLConfirm
                ? await window.KLConfirm({ title: 'Kosongkan Keranjang?', text: 'Semua item yang dipilih akan dihapus dari keranjang.', isDanger: true, confirmText: 'Ya, Kosongkan' })
                : window.confirm('Apakah Anda yakin ingin mengosongkan keranjang?');
            if (ok) { cart = []; renderCart(); }
        });

        const searchInput = document.getElementById('menuSearch');
        const categoryButtons = document.querySelectorAll('#categoryTabs button');
        const menuItems = document.querySelectorAll('.menu-card-item');

        function filterMenu() {
            const term = (searchInput?.value || '').toLowerCase();
            const activeCat = document.querySelector('#categoryTabs button.active')?.getAttribute('data-category') || 'all';
            menuItems.forEach(item => {
                const matchText = (item.dataset.menuName || '').includes(term) || (item.dataset.menuCode || '').includes(term);
                const matchCat = activeCat === 'all' || item.dataset.categoryId === activeCat;
                item.style.setProperty('display', (matchText && matchCat) ? 'block' : 'none', 'important');
            });
        }

        searchInput?.addEventListener('input', filterMenu);
        categoryButtons.forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                categoryButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                filterMenu();
            });
        });

        document.getElementById('checkoutForm')?.addEventListener('submit', function (e) {
            if (cart.length === 0) {
                e.preventDefault();
                if (window.Swal) {
                    Swal.fire({ icon: 'warning', title: 'Keranjang Kosong', text: 'Silakan pilih minimal satu menu terlebih dahulu.', confirmButtonColor: '#14532d' });
                } else {
                    window.alert('Keranjang belanja Anda masih kosong! Silakan pilih menu terlebih dahulu.');
                }
            }
        });
    });
})();
</script>
@endsection
