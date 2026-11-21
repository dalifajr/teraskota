@extends('layouts.app')

@section('title', 'Pengeluaran Operasional')
@section('page_title', 'Pencatatan Pengeluaran Operasional (Kas Keluar)')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Pengeluaran Kas</li>
@endsection

@section('content')
<!-- Summary Metrics -->
<div class="row g-3 mb-4" style="font-variant-numeric: tabular-nums;">
    <div class="col-md-4">
        <div class="card premium-card border-0 bg-white shadow-sm h-100">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <small class="text-muted fw-semibold text-uppercase d-block mb-1">Total Pengeluaran (Filter)</small>
                    <h3 class="fw-bold text-danger m-0">Rp{{ number_format($totalExpenses, 0, ',', '.') }}</h3>
                    <small class="text-muted">{{ number_format($expenseCount, 0, ',', '.') }} catatan kas keluar</small>
                </div>
                <div class="card-icon-wrapper" style="background-color:#fee2e2; color:#dc2626; width: 48px; height: 48px; font-size: 1.3rem;">
                    <i class="fa-solid fa-money-bill-transfer"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card premium-card border-0 bg-white shadow-sm h-100">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <small class="text-muted fw-semibold text-uppercase d-block mb-1">Dari Kas Tunai (Laci)</small>
                    <h3 class="fw-bold text-success m-0">Rp{{ number_format($cashExpenses ?? 0, 0, ',', '.') }}</h3>
                    <small class="text-muted">Mengurangi kas fisik laci kasir</small>
                </div>
                <div class="card-icon-wrapper bg-mint-soft" style="width: 48px; height: 48px; font-size: 1.3rem;">
                    <i class="fa-solid fa-money-bill-wave"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card premium-card border-0 bg-white shadow-sm h-100">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <small class="text-muted fw-semibold text-uppercase d-block mb-1">Dari Transfer / Non-Tunai</small>
                    <h3 class="fw-bold text-primary m-0">Rp{{ number_format($nonCashExpenses ?? 0, 0, ',', '.') }}</h3>
                    <small class="text-muted">Pembayaran via rekening / e-wallet</small>
                </div>
                <div class="card-icon-wrapper bg-primary bg-opacity-10 text-primary" style="width: 48px; height: 48px; font-size: 1.3rem;">
                    <i class="fa-solid fa-building-columns"></i>
                </div>
            </div>
        </div>
    </div>
</div>

@if(isset($categoryBreakdown) && $categoryBreakdown->count() > 0)
<!-- Distribusi Pengeluaran per Kategori -->
<div class="card premium-card border-0 shadow-sm mb-4" style="font-variant-numeric: tabular-nums;">
    <div class="card-body py-3 px-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <small class="text-uppercase fw-bold text-muted"><i class="fa-solid fa-chart-pie text-danger me-1"></i>Distribusi Biaya per Kategori</small>
            <small class="text-muted">{{ $categoryBreakdown->count() }} kategori aktif</small>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @foreach($categoryBreakdown as $cb)
                <a href="{{ route('expenses.index', array_merge(request()->except('page'), ['category' => $cb->category])) }}"
                   class="text-decoration-none px-3 py-2 rounded-3 border bg-light d-inline-flex align-items-center gap-2 small">
                    <span class="fw-semibold text-dark">{{ $cb->category }}</span>
                    <span class="text-danger fw-bold">Rp{{ number_format($cb->total_amount, 0, ',', '.') }}</span>
                    <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill">{{ $cb->pct }}%</span>
                </a>
            @endforeach
        </div>
    </div>
</div>
@endif

@php
    $expAdvancedCount = 0;
    foreach (['category', 'payment_method', 'start_date', 'end_date'] as $efk) {
        if (filled(request($efk))) $expAdvancedCount++;
    }
    $hasAnyExpFilter = $expAdvancedCount > 0 || filled(request('search'));
@endphp

<!-- Compact Search & Collapsible Filter Toolbar -->
<div class="kl-filter-toolbar p-3 mb-4">
    <form action="{{ route('expenses.index') }}" method="GET">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm flex-grow-1" style="min-width: 220px;">
                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" name="search" id="search" class="form-control border-start-0" placeholder="Cari keterangan, bahan, listrik, atau no. bukti..." value="{{ request('search') }}">
            </div>

            <button type="button"
                    class="btn btn-sm {{ $expAdvancedCount > 0 ? 'btn-success' : 'btn-outline-secondary' }} kl-filter-toggle-btn"
                    data-bs-toggle="collapse"
                    data-bs-target="#expAdvancedFilters"
                    aria-expanded="{{ $expAdvancedCount > 0 ? 'true' : 'false' }}"
                    aria-controls="expAdvancedFilters">
                <i class="fa-solid fa-sliders"></i>
                <span>Filter</span>
                @if($expAdvancedCount > 0)
                    <span class="badge bg-white text-success rounded-pill">{{ $expAdvancedCount }}</span>
                @endif
            </button>

            <button type="submit" class="btn btn-sm btn-primary-green px-3">
                <i class="fa-solid fa-search me-1"></i>Cari
            </button>

            @if($hasAnyExpFilter)
                <a href="{{ route('expenses.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset Filter">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            @endif
        </div>

        <div class="collapse {{ $expAdvancedCount > 0 ? 'show' : '' }}" id="expAdvancedFilters">
            <div class="row g-2 g-md-3 pt-3 mt-3 border-top align-items-end">
                <div class="col-6 col-md-3">
                    <label for="category" class="form-label fw-medium text-dark small mb-1">Kategori Biaya</label>
                    <select name="category" id="category" class="form-select form-select-sm">
                        <option value="">Semua Kategori</option>
                        @foreach($categories as $key => $label)
                            <option value="{{ $key }}" {{ request('category') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-3">
                    <label for="payment_method" class="form-label fw-medium text-dark small mb-1">Sumber Dana</label>
                    <select name="payment_method" id="payment_method" class="form-select form-select-sm">
                        <option value="">Semua Sumber</option>
                        <option value="tunai" {{ request('payment_method') === 'tunai' ? 'selected' : '' }}>Kas Tunai</option>
                        <option value="non-tunai" {{ request('payment_method') === 'non-tunai' ? 'selected' : '' }}>Transfer / Non-Tunai</option>
                    </select>
                </div>

                <div class="col-6 col-md-3">
                    <label for="start_date" class="form-label fw-medium text-dark small mb-1">Dari Tanggal</label>
                    <input type="date" name="start_date" id="start_date" class="form-control form-control-sm" value="{{ request('start_date') }}">
                </div>

                <div class="col-6 col-md-3">
                    <label for="end_date" class="form-label fw-medium text-dark small mb-1">Sampai Tanggal</label>
                    <input type="date" name="end_date" id="end_date" class="form-control form-control-sm" value="{{ request('end_date') }}">
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Expenses Table -->
<div class="card premium-card">
    <div class="premium-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="m-0 fw-semibold text-dark"><i class="fa-solid fa-wallet text-danger me-2"></i> Daftar Pengeluaran Operasional</h5>
        <div class="d-flex gap-2">
            <a href="{{ route('expenses.export', request()->query()) }}" class="btn btn-outline-dark btn-sm">
                <i class="fa-solid fa-file-csv me-1"></i> Export CSV
            </a>
            <button type="button" class="btn btn-primary-green btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#createExpenseModal">
                <i class="fa-solid fa-plus me-1"></i> Catat Pengeluaran Baru
            </button>
        </div>
    </div>
    <div class="card-body p-4">
        @if($expenses->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle border mb-4" style="font-variant-numeric: tabular-nums;">
                    <thead>
                        <tr>
                            <th>No. Bukti</th>
                            <th>Tanggal</th>
                            <th>Kategori</th>
                            <th>Deskripsi Pengeluaran</th>
                            <th class="text-center">Sumber Dana</th>
                            <th class="text-end">Nominal (Rp)</th>
                            <th>Dicatat Oleh</th>
                            <th class="text-end" style="width: 72px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($expenses as $exp)
                            <tr>
                                <td class="fw-bold text-dark">{{ $exp->expense_number }}</td>
                                <td>{{ $exp->expense_date->translatedFormat('d M Y') }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $exp->category }}</span></td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $exp->description }}</div>
                                    @if($exp->notes)
                                        <small class="text-muted">{{ $exp->notes }}</small>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $exp->payment_method === 'tunai' ? 'bg-success' : 'bg-primary' }} text-uppercase">
                                        {{ $exp->payment_method }}
                                    </span>
                                </td>
                                <td class="text-end fw-bold text-danger">Rp{{ number_format($exp->amount, 0, ',', '.') }}</td>
                                <td><span class="small fw-semibold">{{ $exp->creator->name ?? 'Admin' }}</span></td>
                                <td class="text-end">
                                    <div class="dropdown kl-action-dropdown d-inline-block">
                                        <button type="button" class="btn kl-action-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menu Aksi {{ $exp->expense_number }}" title="Menu Aksi">
                                            <i class="fa-solid fa-bars"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end kl-action-menu">
                                            <li>
                                                <button
                                                    type="button"
                                                    class="dropdown-item btn-edit-expense"
                                                    data-id="{{ $exp->id }}"
                                                    data-date="{{ $exp->expense_date->format('Y-m-d') }}"
                                                    data-category="{{ $exp->category }}"
                                                    data-description="{{ $exp->description }}"
                                                    data-amount="{{ (float) $exp->amount }}"
                                                    data-method="{{ $exp->payment_method }}"
                                                    data-notes="{{ $exp->notes }}"
                                                >
                                                    <i class="fa-solid fa-pen-to-square text-primary"></i>
                                                    <span>Edit Pengeluaran</span>
                                                </button>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('expenses.destroy', $exp->id) }}" method="POST" class="m-0" data-confirm="Hapus catatan pengeluaran {{ $exp->expense_number }} ({{ $exp->description }})?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="fa-solid fa-trash"></i>
                                                        <span>Hapus Pengeluaran</span>
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <small class="text-muted">Menampilkan {{ $expenses->firstItem() }} - {{ $expenses->lastItem() }} dari {{ $expenses->total() }} pengeluaran</small>
                <div>{{ $expenses->links('pagination::bootstrap-5') }}</div>
            </div>
        @else
            <div class="text-center text-muted py-5">
                <i class="fa-solid fa-file-invoice-dollar fs-1 mb-3 text-secondary opacity-50"></i>
                <p class="m-0 fw-semibold">Belum ada catatan pengeluaran operasional</p>
                <small>Klik tombol "Catat Pengeluaran Baru" untuk mencatat pembelian bahan baku, kemasan, atau biaya operasional harian.</small>
            </div>
        @endif
    </div>
</div>

<!-- Modal Create Expense -->
<div class="modal fade" id="createExpenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('expenses.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-plus-circle text-success me-2"></i>Catat Pengeluaran Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Tanggal Pengeluaran</label>
                            <input type="date" name="expense_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Sumber Dana</label>
                            <select name="payment_method" class="form-select" required>
                                <option value="tunai">Kas Tunai</option>
                                <option value="non-tunai">Transfer / Non-Tunai</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Kategori Biaya</label>
                            <select name="category" class="form-select" required>
                                @foreach($categories as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Deskripsi / Nama Pengeluaran</label>
                            <input type="text" name="description" class="form-control" placeholder="Contoh: Beli bahan baku & kemasan harian" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Nominal Pengeluaran (Rp)</label>
                            <div class="input-group">
                                <span class="input-group-text fw-bold">Rp</span>
                                <input type="number" name="amount" class="form-control fw-bold" inputmode="numeric" min="1" step="1" placeholder="50000" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Catatan Tambahan (Opsional)</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Nama toko / supplier atau keterangan tambahan..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary-green fw-bold"><i class="fa-solid fa-save me-1"></i>Simpan Pengeluaran</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Expense -->
<div class="modal fade" id="editExpenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="editExpenseForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-pen-to-square text-primary me-2"></i>Edit Pengeluaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Tanggal Pengeluaran</label>
                            <input type="date" name="expense_date" id="edit_expense_date" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Sumber Dana</label>
                            <select name="payment_method" id="edit_payment_method" class="form-select" required>
                                <option value="tunai">Kas Tunai</option>
                                <option value="non-tunai">Transfer / Non-Tunai</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Kategori Biaya</label>
                            <select name="category" id="edit_category" class="form-select" required>
                                @foreach($categories as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Deskripsi / Nama Pengeluaran</label>
                            <input type="text" name="description" id="edit_description" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Nominal Pengeluaran (Rp)</label>
                            <div class="input-group">
                                <span class="input-group-text fw-bold">Rp</span>
                                <input type="number" name="amount" id="edit_amount" class="form-control fw-bold" inputmode="numeric" min="1" step="1" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Catatan Tambahan (Opsional)</label>
                            <textarea name="notes" id="edit_notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary-green fw-bold"><i class="fa-solid fa-save me-1"></i>Perbarui</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const editModalEl = document.getElementById('editExpenseModal');
        const editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;
        const editForm = document.getElementById('editExpenseForm');

        document.querySelectorAll('.btn-edit-expense').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                editForm.action = `/expenses/${id}`;
                document.getElementById('edit_expense_date').value = this.getAttribute('data-date');
                document.getElementById('edit_category').value = this.getAttribute('data-category');
                document.getElementById('edit_description').value = this.getAttribute('data-description');
                document.getElementById('edit_amount').value = this.getAttribute('data-amount');
                document.getElementById('edit_payment_method').value = this.getAttribute('data-method');
                document.getElementById('edit_notes').value = this.getAttribute('data-notes') || '';
                if (editModal) editModal.show();
            });
        });
    });
</script>
@endsection
