@extends('layouts.app')

@section('title', 'Edit Transaksi')
@section('page_title', 'Edit Transaksi ' . $transaction->transaction_number)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('transactions.index') }}" class="text-decoration-none text-success">Transaksi</a></li>
    <li class="breadcrumb-item"><a href="{{ route('transactions.show', $transaction->id) }}" class="text-decoration-none text-success">{{ $transaction->transaction_number }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit</li>
@endsection

@section('content')
<div class="row">
    <!-- Left: Menu Catalog -->
    <div class="col-lg-7 col-xl-8 mb-4 mb-lg-0">
        <div class="card premium-card h-100">
            <div class="premium-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="m-0 fw-semibold text-dark"><i class="fa-solid fa-mug-hot text-success me-2"></i> Katalog Menu</h5>
                <div class="d-flex gap-2">
                    <input type="text" id="menuSearch" class="form-control form-control-sm" placeholder="Cari menu...">
                </div>
            </div>
            <div class="card-body p-4">
                
                <!-- Category Filter Tabs -->
                <ul class="nav nav-pills mb-4 gap-2 border-bottom pb-3" id="categoryTabs">
                    <li class="nav-item">
                        <button class="nav-link active btn-sm" data-category="all">Semua</button>
                    </li>
                    @foreach($categories as $category)
                        <li class="nav-item">
                            <button class="nav-link btn-sm" data-category="cat-{{ $category->id }}">{{ $category->name }}</button>
                        </li>
                    @endforeach
                </ul>

                <!-- Grid of Menus -->
                <div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-3" id="menuCatalog">
                    @foreach($categories as $category)
                        @foreach($category->menus as $menu)
                            <div class="col menu-card-item" data-category-id="cat-{{ $category->id }}" data-menu-name="{{ strtolower($menu->name) }}" data-menu-code="{{ strtolower($menu->code) }}">
                                <div class="card pos-menu-card h-100 p-2 text-center" onclick="addToCart({{ $menu->id }}, '{{ addslashes($menu->name) }}', {{ $menu->price }}, {{ $menu->use_global_profit ? $globalProfitPercentage : ($menu->profit_percentage ?? $globalProfitPercentage) }})">
                                    <div class="fw-bold small text-muted mb-1">{{ $menu->code }}</div>
                                    <div class="fw-semibold text-dark text-truncate mb-2" style="font-size:0.95rem;" title="{{ $menu->name }}">{{ $menu->name }}</div>
                                    <div class="mt-auto text-success fw-bold">Rp{{ number_format($menu->price, 0, ',', '.') }}</div>
                                </div>
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Right: Cart & Update Form -->
    <div class="col-lg-5 col-xl-4">
        <div class="card premium-card h-100">
            <div class="premium-header">
                <h5 class="m-0 fw-semibold text-dark"><i class="fa-solid fa-edit text-success me-2"></i> Edit Keranjang</h5>
            </div>
            <div class="card-body p-4 d-flex flex-column" style="min-height: 500px;">
                
                <form action="{{ route('transactions.update', $transaction->id) }}" method="POST" id="checkoutForm" class="d-flex flex-column h-100 flex-grow-1">
                    @csrf
                    @method('PUT')
                    
                    <!-- Date & Time Settings -->
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="transaction_date" class="form-label small fw-semibold text-muted">Tanggal Transaksi</label>
                            <input type="date" name="transaction_date" id="transaction_date" class="form-control form-control-sm" value="{{ $transaction->transaction_date->format('Y-m-d') }}" required>
                        </div>
                        <div class="col-6">
                            <label for="transaction_time" class="form-label small fw-semibold text-muted">Waktu Transaksi</label>
                            <input type="time" name="transaction_time" id="transaction_time" class="form-control form-control-sm" value="{{ date('H:i', strtotime($transaction->transaction_time)) }}" required>
                        </div>
                    </div>

                    <!-- Cart List -->
                    <div class="pos-cart-list flex-grow-1 mb-4" id="cartContainer">
                        <!-- Empty State placeholder -->
                        <div class="text-center text-muted py-5" id="cartEmptyState" style="display:none;">
                            <i class="fa-solid fa-cart-shopping fs-1 mb-3 text-secondary"></i>
                            <p class="m-0">Keranjang masih kosong</p>
                        </div>
                    </div>

                    <!-- Hidden Inputs Container for PUT Array -->
                    <div id="hiddenInputs"></div>

                    <!-- Summary math section -->
                    <div class="border-top pt-3 mb-3">
                        <div class="d-flex justify-content-between mb-1 small text-muted">
                            <span>Total Quantity</span>
                            <span id="totalQtyVal" class="fw-semibold text-dark">0 pcs</span>
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
                        <label for="notes" class="form-label small fw-semibold text-muted">Catatan Transaksi (Opsional)</label>
                        <textarea name="notes" id="notes" rows="2" class="form-control form-control-sm" placeholder="Catatan tambahan...">{{ $transaction->notes }}</textarea>
                    </div>

                    <!-- Form Buttons -->
                    <div class="row g-2">
                        <div class="col-6">
                            <a href="{{ route('transactions.show', $transaction->id) }}" class="btn btn-outline-secondary w-100 py-2 btn-sm fw-semibold"><i class="fa-solid fa-xmark me-1"></i> Batal</a>
                        </div>
                        <div class="col-6">
                            <button type="submit" class="btn btn-primary-green w-100 py-2 btn-sm fw-semibold"><i class="fa-solid fa-save me-1"></i> Simpan Perubahan</button>
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
    // Cart pre-populated with database data
    let cart = [
        @foreach($transaction->details as $detail)
        {
            menuId: {{ $detail->menu_id ?? 'null' }},
            name: '{{ addslashes($detail->menu_name_snapshot) }}',
            price: {{ (float) $detail->price_snapshot }},
            profitPercentage: {{ (float) $detail->profit_percentage_snapshot }},
            quantity: {{ $detail->quantity }}
        },
        @endforeach
    ];

    // Filter out rows where menu_id is null (e.g. menu was hard deleted, but keeping snapshots)
    cart = cart.filter(item => item.menuId !== null);

    function addToCart(menuId, name, price, profitPercentage) {
        let existItem = cart.find(item => item.menuId === menuId);
        
        if (existItem) {
            existItem.quantity += 1;
        } else {
            cart.push({
                menuId: menuId,
                name: name,
                price: parseFloat(price),
                profitPercentage: parseFloat(profitPercentage),
                quantity: 1
            });
        }
        
        renderCart();
    }

    function updateQuantity(menuId, qty) {
        let item = cart.find(item => item.menuId === menuId);
        if (item) {
            item.quantity = parseInt(qty);
            if (item.quantity <= 0 || isNaN(item.quantity)) {
                removeFromCart(menuId);
                return;
            }
        }
        renderCart();
    }

    function changeQtyByStep(menuId, step) {
        let item = cart.find(item => item.menuId === menuId);
        if (item) {
            item.quantity += step;
            if (item.quantity <= 0) {
                removeFromCart(menuId);
                return;
            }
        }
        renderCart();
    }

    function removeFromCart(menuId) {
        cart = cart.filter(item => item.menuId !== menuId);
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
                </div>
            `;
            document.getElementById('totalQtyVal').innerText = '0 pcs';
            document.getElementById('estimatedCostVal').innerText = 'Rp0';
            document.getElementById('totalProfitVal').innerText = 'Rp0';
            document.getElementById('totalSalesVal').innerText = 'Rp0';
            return;
        }

        let totalQty = 0;
        let totalSales = 0;
        let totalProfit = 0;
        let totalCost = 0;

        cart.forEach((item, index) => {
            const subtotal = item.price * item.quantity;
            const profit = subtotal * (item.profitPercentage / 100);
            const cost = subtotal - profit;

            totalQty += item.quantity;
            totalSales += subtotal;
            totalProfit += profit;
            totalCost += cost;

            const cartRow = document.createElement('div');
            cartRow.className = 'pos-cart-item d-flex justify-content-between align-items-center';
            cartRow.innerHTML = `
                <div style="max-width: 50%;">
                    <div class="fw-semibold text-dark text-truncate">${item.name}</div>
                    <small class="text-muted">Rp${item.price.toLocaleString('id-ID')} &bull; Profit ${item.profitPercentage}%</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="input-group input-group-sm" style="width: 100px;">
                        <button type="button" class="btn btn-outline-secondary px-2" onclick="changeQtyByStep(${item.menuId}, -1)">-</button>
                        <input type="text" class="form-control text-center px-1" value="${item.quantity}" onchange="updateQuantity(${item.menuId}, this.value)">
                        <button type="button" class="btn btn-outline-secondary px-2" onclick="changeQtyByStep(${item.menuId}, 1)">+</button>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger border-0 ms-1" onclick="removeFromCart(${item.menuId})"><i class="fa-solid fa-trash"></i></button>
                </div>
            `;
            container.appendChild(cartRow);

            const menuInput = document.createElement('input');
            menuInput.type = 'hidden';
            menuInput.name = `items[${index}][menu_id]`;
            menuInput.value = item.menuId;
            hiddenInputs.appendChild(menuInput);

            const qtyInput = document.createElement('input');
            qtyInput.type = 'hidden';
            qtyInput.name = `items[${index}][quantity]`;
            qtyInput.value = item.quantity;
            hiddenInputs.appendChild(qtyInput);
        });

        document.getElementById('totalQtyVal').innerText = totalQty + ' pcs';
        document.getElementById('estimatedCostVal').innerText = 'Rp' + Math.round(totalCost).toLocaleString('id-ID');
        document.getElementById('totalProfitVal').innerText = 'Rp' + Math.round(totalProfit).toLocaleString('id-ID');
        document.getElementById('totalSalesVal').innerText = 'Rp' + Math.round(totalSales).toLocaleString('id-ID');
    }

    document.addEventListener('DOMContentLoaded', function () {
        renderCart(); // Render initial pre-loaded cart

        const searchInput = document.getElementById('menuSearch');
        const categoryButtons = document.querySelectorAll('#categoryTabs button');
        const menuItems = document.querySelectorAll('.menu-card-item');

        function filterMenu() {
            const searchTerm = searchInput.value.toLowerCase();
            const activeCategory = document.querySelector('#categoryTabs button.active').getAttribute('data-category');

            menuItems.forEach(item => {
                const name = item.getAttribute('data-menu-name');
                const code = item.getAttribute('data-menu-code');
                const catId = item.getAttribute('data-category-id');

                const matchesSearch = name.includes(searchTerm) || code.includes(searchTerm);
                const matchesCategory = activeCategory === 'all' || catId === activeCategory;

                if (matchesSearch && matchesCategory) {
                    item.style.setProperty('display', 'block', 'important');
                } else {
                    item.style.setProperty('display', 'none', 'important');
                }
            });
        }

        searchInput.addEventListener('input', filterMenu);

        categoryButtons.forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                categoryButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                filterMenu();
            });
        });

        document.getElementById('checkoutForm').addEventListener('submit', function (e) {
            if (cart.length === 0) {
                e.preventDefault();
                alert('Keranjang belanja Anda kosong! Silakan pilih menu.');
            }
        });
    });
</script>
@endsection
