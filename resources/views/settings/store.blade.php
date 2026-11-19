@extends('layouts.app')

@section('title', 'Profil Usaha & Struk')
@section('page_title', 'Profil Usaha & White-Label Struk')

@section('breadcrumbs')
    <li class="breadcrumb-item">Pengaturan</li>
    <li class="breadcrumb-item active">Profil Usaha</li>
@endsection

@section('content')
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold m-0 text-dark"><i class="fa-solid fa-store text-success me-2"></i>Identitas Usaha &amp; Cetakan Struk</h6>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('settings.store.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label fw-semibold small">Nama Usaha / Toko <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $tenant->name) }}" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold small">Jenis Usaha <span class="text-danger">*</span></label>
                            <input type="text" name="business_type" class="form-control" value="{{ old('business_type', $tenant->business_type) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Alamat Usaha (tampil di Struk &amp; PDF)</label>
                            <input type="text" name="address" class="form-control" value="{{ old('address', $tenant->address) }}" placeholder="Jl. Contoh No. 12, Kota">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">No. Telepon / WhatsApp Toko</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone', $tenant->phone) }}" placeholder="0812-xxxx-xxxx">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold small">Pesan Footer Struk Thermal</label>
                            <input type="text" name="receipt_footer" class="form-control" value="{{ old('receipt_footer', $tenant->receipt_footer) }}" placeholder="Terima kasih atas kunjungan Anda!">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Zona Waktu</label>
                            <select name="timezone" class="form-select">
                                <option value="Asia/Jakarta" {{ $tenant->timezone === 'Asia/Jakarta' ? 'selected' : '' }}>WIB (Asia/Jakarta)</option>
                                <option value="Asia/Makassar" {{ $tenant->timezone === 'Asia/Makassar' ? 'selected' : '' }}>WITA (Asia/Makassar)</option>
                                <option value="Asia/Jayapura" {{ $tenant->timezone === 'Asia/Jayapura' ? 'selected' : '' }}>WIT (Asia/Jayapura)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Logo Usaha (Opsional · Maks 2MB PNG/JPG/WebP)</label>
                            <div class="d-flex align-items-center gap-3">
                                <img src="{{ $tenant->logo_path ? $tenant->logoUrl() : asset('assets/images/logo.png') }}" alt="Logo" class="rounded-circle border shadow-sm" style="width: 60px; height: 60px; object-fit: cover;">
                                <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/webp">
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary-green px-4">
                            <i class="fa-solid fa-save me-2"></i>Simpan Profil Usaha
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold m-0 text-dark"><i class="fa-solid fa-receipt text-success me-2"></i>Pratinjau Kop Struk</h6>
            </div>
            <div class="card-body bg-light p-4">
                <div class="bg-white p-3 rounded-3 shadow-sm mx-auto" style="max-width: 260px; font-family: 'Courier New', monospace; font-size: 12px;">
                    <div class="text-center pb-2 mb-2" style="border-bottom: 1px dashed #94a3b8;">
                        <div class="fw-bold text-uppercase">{{ $tenant->name }}</div>
                        @if($tenant->address)<div class="text-muted" style="font-size: 10px;">{{ $tenant->address }}</div>@endif
                        @if($tenant->phone)<div class="text-muted" style="font-size: 10px;">Telp: {{ $tenant->phone }}</div>@endif
                    </div>
                    <div class="d-flex justify-content-between" style="font-size: 11px;">
                        <span>Es Kopi Susu x1</span>
                        <strong>Rp15.000</strong>
                    </div>
                    <div class="d-flex justify-content-between fw-bold pt-2 mt-2" style="border-top: 1px dashed #94a3b8;">
                        <span>TOTAL</span>
                        <span>Rp15.000</span>
                    </div>
                    <div class="text-center text-muted mt-3" style="font-size: 10px;">
                        <div>*** LUNAS ***</div>
                        <div>{{ $tenant->receipt_footer ?: 'Terima kasih atas kunjungan Anda!' }}</div>
                    </div>
                </div>
                <div class="mt-3 text-center">
                    <span class="badge bg-secondary bg-opacity-10 text-secondary">ID Tenant: {{ $tenant->slug }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
