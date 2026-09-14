@extends('layouts.app')

@section('title', 'Tambah Menu')
@section('page_title', 'Tambah Menu Baru')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('menus.index') }}" class="text-decoration-none text-success">Menu</a></li>
    <li class="breadcrumb-item active" aria-current="page">Tambah Baru</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card premium-card">
            <div class="premium-header">
                <h5 class="m-0 fw-semibold text-dark"><i class="fa-solid fa-plus-circle text-success me-2"></i> Form Menu Baru</h5>
            </div>
            <div class="card-body p-4">
                
                <!-- Validation Errors -->
                @if ($errors->any())
                    <div class="alert alert-danger border-0 rounded-3 mb-4">
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('menus.store') }}" method="POST">
                    @csrf

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="code" class="form-label fw-medium text-dark">Kode Menu <span class="text-danger">*</span></label>
                            <input type="text" name="code" id="code" class="form-control" placeholder="Contoh: TEA-001" value="{{ old('code') }}" required>
                            <small class="text-muted">Harus unik dan diawali dengan kode singkatan.</small>
                        </div>
                        <div class="col-md-6">
                            <label for="name" class="form-label fw-medium text-dark">Nama Menu <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control" placeholder="Contoh: Green Tea Original" value="{{ old('name') }}" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="category_id" class="form-label fw-medium text-dark">Kategori <span class="text-danger">*</span></label>
                            <select name="category_id" id="category_id" class="form-select" required>
                                <option value="">-- Pilih Kategori --</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="price" class="form-label fw-medium text-dark">Harga Jual (Rp) <span class="text-danger">*</span></label>
                            <input type="number" name="price" id="price" min="0" step="100" class="form-control" placeholder="Contoh: 11000" value="{{ old('price') }}" required>
                        </div>
                    </div>

                    <div class="card p-3 bg-light border mb-4">
                        <h6 class="fw-semibold text-dark mb-3"><i class="fa-solid fa-percent text-success me-1"></i> Pengaturan Keuntungan</h6>
                        
                        <div class="form-check mb-3">
                            <input type="checkbox" name="use_global_profit" id="use_global_profit" class="form-check-input" value="1" {{ old('use_global_profit', '1') == '1' ? 'checked' : '' }}>
                            <label class="form-check-label fw-medium text-dark" for="use_global_profit">
                                Gunakan Persentase Keuntungan Global (Default 30%)
                            </label>
                            <div class="text-muted small">Jika dicentang, menu ini akan mengikuti persentase keuntungan global.</div>
                        </div>

                        <div class="mb-2" id="customProfitContainer">
                            <label for="profit_percentage" class="form-label fw-medium text-dark">Persentase Keuntungan Khusus (%)</label>
                            <div class="input-group" style="max-width: 200px;">
                                <input type="number" name="profit_percentage" id="profit_percentage" min="0" max="100" step="0.1" class="form-control" placeholder="Contoh: 35" value="{{ old('profit_percentage') }}" {{ old('use_global_profit', '1') == '1' ? 'disabled' : '' }}>
                                <span class="input-group-text">%</span>
                            </div>
                            <small class="text-muted">Masukkan nilai antara 0 - 100.</small>
                        </div>
                    </div>

                    <div class="form-check mb-4">
                        <input type="checkbox" name="status" id="status" class="form-check-input" value="1" {{ old('status', '1') == '1' ? 'checked' : '' }}>
                        <label class="form-check-label fw-medium text-dark" for="status">
                            Menu Aktif (Tampilkan di Kasir)
                        </label>
                        <div class="text-muted small">Jika dinonaktifkan, menu tidak akan muncul di POS kasir transaksi.</div>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="{{ route('menus.index') }}" class="btn btn-outline-secondary px-4"><i class="fa-solid fa-xmark me-1"></i> Batal</a>
                        <button type="submit" class="btn btn-primary-green px-4"><i class="fa-solid fa-save me-1"></i> Simpan Menu</button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const useGlobalCheckbox = document.getElementById('use_global_profit');
        const profitInput = document.getElementById('profit_percentage');

        useGlobalCheckbox.addEventListener('change', function () {
            profitInput.disabled = this.checked;
            if (this.checked) {
                profitInput.value = '';
            } else {
                profitInput.focus();
            }
        });
    });
</script>
@endsection
