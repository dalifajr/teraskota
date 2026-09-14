@extends('layouts.app')

@section('title', 'Data Menu')
@section('page_title', 'Kelola Menu Minuman')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Menu</li>
@endsection

@section('content')
<!-- Filter Panel -->
<div class="card premium-card mb-4">
    <div class="card-body p-4">
        <form action="{{ route('menus.index') }}" method="GET" class="row g-3 align-items-end">
            <!-- Search Menu -->
            <div class="col-md-4">
                <label for="search" class="form-label fw-medium text-dark">Cari Nama atau Kode Menu</label>
                <input type="text" name="search" id="search" class="form-control" placeholder="Contoh: Jasmine Tea atau TEA-001..." value="{{ request('search') }}">
            </div>
            
            <!-- Category Filter -->
            <div class="col-md-4">
                <label for="category_id" class="form-label fw-medium text-dark">Kategori</label>
                <select name="category_id" id="category_id" class="form-select">
                    <option value="">-- Semua Kategori --</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Buttons -->
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary-green flex-grow-1"><i class="fa-solid fa-search"></i> Cari</button>
                <a href="{{ route('menus.index') }}" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Menus Table List -->
<div class="card premium-card">
    <div class="premium-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="m-0 fw-semibold text-dark"><i class="fa-solid fa-coffee text-success me-2"></i> Daftar Menu Aktif & Nonaktif</h5>
        <a href="{{ route('menus.create') }}" class="btn btn-primary-green btn-sm shadow-sm"><i class="fa-solid fa-plus me-1"></i> Tambah Menu Baru</a>
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
                            <th class="text-center" style="width: 150px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($menus as $menu)
                            <tr>
                                <td class="fw-bold text-success">{{ $menu->code }}</td>
                                <td class="fw-semibold text-dark">{{ $menu->name }}</td>
                                <td><span class="badge bg-mint-soft text-success">{{ $menu->category->name }}</span></td>
                                <td class="text-end fw-bold text-dark">Rp{{ number_format($menu->price, 0, ',', '.') }}</td>
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
                                    @if($menu->status)
                                        <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i> Aktif</span>
                                    @else
                                        <span class="badge bg-danger"><i class="fa-solid fa-circle-xmark me-1"></i> Nonaktif</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="{{ route('menus.edit', $menu->id) }}" class="btn btn-outline-primary" title="Edit"><i class="fa-solid fa-edit"></i></a>
                                        
                                        <form action="{{ route('menus.destroy', $menu->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus menu {{ $menu->name }}? Menu yang memiliki riwayat transaksi akan dinonaktifkan (soft delete).')">
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
@endsection
