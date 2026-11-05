@extends('layouts.app')

@section('title', 'Pengeluaran Operasional')
@section('page_title', 'Pencatatan Pengeluaran Operasional (Kas Keluar)')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Pengeluaran Kas</li>
@endsection

@section('content')
<!-- Summary Metrics -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card premium-card border-0 bg-white shadow-sm h-100">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <small class="text-muted fw-semibold text-uppercase d-block mb-1">Total Pengeluaran (Sesuai Filter)</small>
                    <h2 class="fw-bold text-danger m-0">Rp{{ number_format($totalExpenses, 0, ',', '.') }}</h2>
                    <small class="text-muted">Mengurangi laba kotor pada perhitungan Laba Bersih</small>
                </div>
                <div class="card-icon-wrapper" style="background-color:#fee2e2; color:#dc2626; width: 52px; height: 52px; font-size: 1.4rem;">
                    <i class="fa-solid fa-money-bill-transfer"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card premium-card border-0 bg-white shadow-sm h-100">
            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                <div>
                    <small class="text-muted fw-semibold text-uppercase d-block mb-1">Frekuensi Pengeluaran</small>
                    <h2 class="fw-bold text-dark m-0">{{ number_format($expenseCount, 0, ',', '.') }} Catatan</h2>
                    <small class="text-muted">Total transaksi kas keluar tercatat</small>
                </div>
                <div class="card-icon-wrapper bg-mint-soft" style="width: 52px; height: 52px; font-size: 1.4rem;">
                    <i class="fa-solid fa-receipt"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Panel -->
<div class="card premium-card mb-4">
    <div class="card-body p-4">
        <form action="{{ route('expenses.index') }}" method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="search" class="form-label fw-medium text-dark">Cari Keterangan / No. Bukti</label>
                <input type="text" name="search" id="search" class="form-control" placeholder="Contoh: Es batu, cup, listrik..." value="{{ request('search') }}">
            </div>

            <div class="col-md-3">
                <label for="category" class="form-label fw-medium text-dark">Kategori Biaya</label>
                <select name="category" id="category" class="form-select">
                    <option value="">-- Semua Kategori --</option>
                    @foreach($categories as $key => $label)
                        <option value="{{ $key }}" {{ request('category') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label for="start_date" class="form-label fw-medium text-dark">Dari Tanggal</label>
                <input type="date" name="start_date" id="start_date" class="form-control" value="{{ request('start_date') }}">
            </div>

            <div class="col-md-2">
                <label for="end_date" class="form-label fw-medium text-dark">Sampai Tanggal</label>
                <input type="date" name="end_date" id="end_date" class="form-control" value="{{ request('end_date') }}">
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary-green flex-grow-1"><i class="fa-solid fa-filter me-1"></i> Filter</button>
                <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary" title="Reset"><i class="fa-solid fa-rotate-left"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Expenses Table -->
<div class="card premium-card">
    <div class="premium-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="m-0 fw-semibold text-dark"><i class="fa-solid fa-wallet text-danger me-2"></i> Daftar Pengeluaran Operasional</h5>
        <button type="button" class="btn btn-primary-green btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#createExpenseModal">
            <i class="fa-solid fa-plus me-1"></i> Catat Pengeluaran Baru
        </button>
    </div>
    <div class="card-body p-4">
        @if($expenses->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle border mb-4">
                    <thead>
                        <tr>
                            <th>No. Bukti</th>
                            <th>Tanggal</th>
                            <th>Kategori</th>
                            <th>Deskripsi Pengeluaran</th>
                            <th class="text-center">Sumber Dana</th>
                            <th class="text-end">Nominal (Rp)</th>
                            <th>Dicatat Oleh</th>
                            <th class="text-center" style="width: 120px;">Aksi</th>
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
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button
                                            type="button"
                                            class="btn btn-outline-primary btn-edit-expense"
                                            data-id="{{ $exp->id }}"
                                            data-date="{{ $exp->expense_date->format('Y-m-d') }}"
                                            data-category="{{ $exp->category }}"
                                            data-description="{{ $exp->description }}"
                                            data-amount="{{ (float) $exp->amount }}"
                                            data-method="{{ $exp->payment_method }}"
                                            data-notes="{{ $exp->notes }}"
                                            title="Edit"
                                        >
                                            <i class="fa-solid fa-edit"></i>
                                        </button>
                                        <form action="{{ route('expenses.destroy', $exp->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus catatan pengeluaran {{ $exp->expense_number }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="Hapus"><i class="fa-solid fa-trash"></i></button>
                                        </form>
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
                <small>Klik tombol "Catat Pengeluaran Baru" untuk mencatat pembelian bahan, es batu, cup, atau biaya harian.</small>
            </div>
        @endif
    </div>
</div>

<!-- Modal Create Expense -->
<div class="modal fade" id="createExpenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="{{ route('expenses.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-dark text-white border-0" style="background-color: var(--primary-green) !important;">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-plus-circle me-2"></i> Catat Pengeluaran Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Tanggal Pengeluaran</label>
                            <input type="date" name="expense_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Sumber Dana</label>
                            <select name="payment_method" class="form-select" required>
                                <option value="tunai">Kas Tunai</option>
                                <option value="non-tunai">Transfer / Non-Tunai</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold text-dark">Kategori Biaya</label>
                            <select name="category" class="form-select" required>
                                @foreach($categories as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold text-dark">Deskripsi / Nama Pengeluaran</label>
                            <input type="text" name="description" class="form-control" placeholder="Contoh: Beli Es Batu 5 Bal & Cup 16oz" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold text-dark">Nominal Pengeluaran (Rp)</label>
                            <div class="input-group">
                                <span class="input-group-text fw-bold">Rp</span>
                                <input type="number" name="amount" class="form-control fw-bold" min="1" step="1" placeholder="50000" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold text-dark">Catatan Tambahan (Opsional)</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Nama toko / supplier atau keterangan tambahan..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary-green fw-bold"><i class="fa-solid fa-save me-1"></i> Simpan Pengeluaran</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Expense -->
<div class="modal fade" id="editExpenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form id="editExpenseForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header bg-dark text-white border-0" style="background-color: var(--primary-green) !important;">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-to-square me-2"></i> Edit Pengeluaran</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Tanggal Pengeluaran</label>
                            <input type="date" name="expense_date" id="edit_expense_date" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Sumber Dana</label>
                            <select name="payment_method" id="edit_payment_method" class="form-select" required>
                                <option value="tunai">Kas Tunai</option>
                                <option value="non-tunai">Transfer / Non-Tunai</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold text-dark">Kategori Biaya</label>
                            <select name="category" id="edit_category" class="form-select" required>
                                @foreach($categories as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold text-dark">Deskripsi / Nama Pengeluaran</label>
                            <input type="text" name="description" id="edit_description" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold text-dark">Nominal Pengeluaran (Rp)</label>
                            <div class="input-group">
                                <span class="input-group-text fw-bold">Rp</span>
                                <input type="number" name="amount" id="edit_amount" class="form-control fw-bold" min="1" step="1" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold text-dark">Catatan Tambahan (Opsional)</label>
                            <textarea name="notes" id="edit_notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary-green fw-bold"><i class="fa-solid fa-save me-1"></i> Perbarui</button>
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

