@extends('layouts.app')

@section('title', 'Data Menu')
@section('page_title', 'Kelola Menu Produk')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Menu</li>
@endsection

@section('content')
@if(isset($menuLimitInfo))
<div class="card premium-card mb-3 border-0 shadow-sm">
    <div class="card-body py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-2 {{ $menuLimitInfo['allowed'] ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger' }}">
                <i class="fa-solid fa-layer-group fs-5"></i>
            </div>
            <div>
                <div class="small text-muted fw-semibold">Kuota Menu Paket Langganan</div>
                <div class="fw-bold text-dark">
                    {{ $menuLimitInfo['current'] }} / {{ $menuLimitInfo['limit'] ?? 'Unlimited (Tanpa Batas)' }} Menu Terdaftar
                </div>
            </div>
        </div>
        @if($menuLimitInfo['limit'] !== null)
            <div class="d-flex align-items-center gap-3">
                @php $pct = min(100, (int) round(($menuLimitInfo['current'] / max(1, $menuLimitInfo['limit'])) * 100)); @endphp
                <div class="progress" style="width: 140px; height: 8px;">
                    <div class="progress-bar {{ $pct >= 100 ? 'bg-danger' : ($pct >= 80 ? 'bg-warning' : 'bg-success') }}" role="progressbar" style="width: {{ $pct }}%"></div>
                </div>
                @if(!$menuLimitInfo['allowed'])
                    <a href="{{ route('billing.index') }}" class="btn btn-sm btn-warning fw-bold">Upgrade Paket</a>
                @endif
            </div>
        @endif
    </div>
</div>
@endif

<!-- Compact Filter Toolbar -->
<div class="kl-filter-toolbar p-3 mb-4">
    <form action="{{ route('menus.index') }}" method="GET" class="row g-2 align-items-center">
        <div class="col-12 col-md-4">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" name="search" id="search" class="form-control border-start-0" placeholder="Cari nama atau kode menu..." value="{{ request('search') }}">
            </div>
        </div>

        <div class="col-6 col-md-3">
            <select name="category_id" id="category_id" class="form-select form-select-sm" aria-label="Filter Kategori">
                <option value="">Semua Kategori</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-6 col-md-3">
            <select name="status" id="status" class="form-select form-select-sm" aria-label="Filter Status">
                <option value="">Semua Status</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Aktif (Tersedia)</option>
                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Habis / Nonaktif</option>
            </select>
        </div>

        <div class="col-12 col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-sm btn-primary-green flex-grow-1"><i class="fa-solid fa-search me-1"></i>Cari</button>
            @if(request()->hasAny(['search', 'category_id', 'status']) && (filled(request('search')) || filled(request('category_id')) || filled(request('status'))))
                <a href="{{ route('menus.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset"><i class="fa-solid fa-rotate-left"></i></a>
            @endif
        </div>
    </form>
</div>

<!-- Menus Table List -->
<div class="card premium-card">
    <div class="premium-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="m-0 fw-semibold text-dark"><i class="fa-solid fa-coffee text-success me-2"></i> Daftar Menu Aktif &amp; Nonaktif</h5>
        <div class="kl-action-group">
            <div class="dropdown kl-action-dropdown">
                <button type="button" class="btn btn-sm btn-outline-secondary fw-semibold d-inline-flex align-items-center gap-1" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa-solid fa-file-import"></i>
                    <span>Import / Export</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end kl-action-menu">
                    <li>
                        <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#importMenuModal">
                            <i class="fa-solid fa-upload text-success"></i>
                            <span>Import Menu (CSV)</span>
                        </button>
                    </li>
                    <li>
                        <a href="{{ route('menus.template') }}" class="dropdown-item">
                            <i class="fa-solid fa-file-arrow-down text-primary"></i>
                            <span>Unduh Template CSV</span>
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a href="{{ route('menus.export') }}" class="dropdown-item">
                            <i class="fa-solid fa-file-csv text-dark"></i>
                            <span>Export Katalog CSV</span>
                        </a>
                    </li>
                </ul>
            </div>
            <a href="{{ route('menus.create') }}" class="btn btn-primary-green btn-sm shadow-sm"><i class="fa-solid fa-plus me-1"></i> Tambah Menu</a>
        </div>
    </div>
    <div class="card-body p-4">
        @if($menus->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle border mb-4">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama Menu</th>
                            <th>Kategori</th>
                            <th class="text-end">Harga Jual</th>
                            <th class="text-center">Tipe Margin</th>
                            <th class="text-center">Margin Keuntungan</th>
                            <th class="text-center">Status</th>
                            <th class="text-end" style="width: 72px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($menus as $menu)
                            <tr>
                                <td class="fw-bold text-success">{{ $menu->code }}</td>
                                <td class="fw-semibold text-dark">{{ $menu->name }}</td>
                                <td><span class="badge bg-mint-soft text-success">{{ $menu->category->name }}</span></td>
                                <td class="text-end fw-bold text-dark" style="font-variant-numeric: tabular-nums;">Rp{{ number_format($menu->price, 0, ',', '.') }}</td>
                                <td class="text-center">
                                    @if($menu->use_global_profit)
                                        <span class="badge bg-secondary">Global</span>
                                    @else
                                        <span class="badge bg-primary">Khusus</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($menu->use_global_profit)
                                        <span class="text-muted">Menggunakan Global</span>
                                    @else
                                        <span class="fw-medium text-dark">{{ $menu->profit_percentage }}%</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <form action="{{ route('menus.toggle-status', $menu->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        @if($menu->status)
                                            <button type="submit" class="btn btn-sm btn-success rounded-pill px-3 py-1 fw-semibold" title="Klik untuk tandai Habis / Nonaktif">
                                                <i class="fa-solid fa-circle-check me-1"></i> Aktif
                                            </button>
                                        @else
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1 fw-semibold" title="Klik untuk Aktifkan kembali di Kasir POS">
                                                <i class="fa-solid fa-circle-xmark me-1"></i> Habis / Nonaktif
                                            </button>
                                        @endif
                                    </form>
                                </td>
                                <td class="text-end">
                                    <div class="dropdown kl-action-dropdown d-inline-block">
                                        <button type="button" class="btn kl-action-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menu Aksi {{ $menu->name }}" title="Menu Aksi">
                                            <i class="fa-solid fa-bars"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end kl-action-menu">
                                            <li>
                                                <a href="{{ route('menus.edit', $menu->id) }}" class="dropdown-item">
                                                    <i class="fa-solid fa-pen-to-square text-primary"></i>
                                                    <span>Edit Menu</span>
                                                </a>
                                            </li>
                                            <li>
                                                <form action="{{ route('menus.duplicate', $menu->id) }}" method="POST" class="m-0">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item">
                                                        <i class="fa-regular fa-copy text-info"></i>
                                                        <span>Duplikat Menu</span>
                                                    </button>
                                                </form>
                                            </li>
                                            <li>
                                                <form action="{{ route('menus.toggle-status', $menu->id) }}" method="POST" class="m-0">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="dropdown-item">
                                                        <i class="fa-solid {{ $menu->status ? 'fa-toggle-off text-warning' : 'fa-toggle-on text-success' }}"></i>
                                                        <span>{{ $menu->status ? 'Tandai Habis / Nonaktif' : 'Aktifkan Menu' }}</span>
                                                    </button>
                                                </form>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('menus.destroy', $menu->id) }}" method="POST" class="m-0" data-confirm="Apakah Anda yakin ingin menghapus menu {{ $menu->name }}? Menu yang memiliki riwayat transaksi akan dinonaktifkan (soft delete).">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="fa-solid fa-trash"></i>
                                                        <span>Hapus Menu</span>
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
            
            <!-- Pagination -->
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <small class="text-muted">Menampilkan {{ $menus->firstItem() }} sampai {{ $menus->lastItem() }} dari {{ $menus->total() }} menu</small>
                <div>
                    {{ $menus->links('pagination::bootstrap-5') }}
                </div>
            </div>
        @else
            <div class="text-center text-muted py-5">
                <i class="fa-solid fa-folder-open fs-1 mb-3 text-secondary"></i>
                <p class="m-0">Tidak ada menu yang ditemukan</p>
            </div>
        @endif
    </div>
</div>

<!-- Modal Import Menu Massal CSV -->
<div class="modal fade" id="importMenuModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('menus.import') }}" method="POST" enctype="multipart/form-data" class="modal-content">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="fa-solid fa-file-import text-success me-2"></i>Import Massal Katalog Menu (CSV)</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light border small mb-3">
                    <div class="fw-semibold text-dark mb-1">Format Kolom CSV:</div>
                    <code class="d-block mb-2">Kode, Nama Menu, Kategori, Harga Jual, Margin Khusus (%), Status</code>
                    <div class="text-muted">Kategori yang belum ada akan dibuat otomatis. Kosongkan kolom Margin Khusus untuk memakai margin global.</div>
                    <a href="{{ route('menus.template') }}" class="btn btn-sm btn-outline-primary mt-2">
                        <i class="fa-solid fa-download me-1"></i>Unduh Contoh Template CSV
                    </a>
                </div>
                <div class="mb-0">
                    <label for="menuCsvFile" class="form-label small fw-semibold">Pilih File CSV <span class="text-danger">*</span></label>
                    <input type="file" name="csv_file" id="menuCsvFile" class="form-control" accept=".csv,.txt" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary-green"><i class="fa-solid fa-upload me-1"></i>Mulai Import</button>
            </div>
        </form>
    </div>
</div>
@endsection
