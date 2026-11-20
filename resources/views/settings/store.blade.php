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
                            <label class="form-label fw-semibold small" for="storeNameInput">Nama Usaha / Toko <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="storeNameInput" class="form-control" value="{{ old('name', $tenant->name) }}" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold small" for="storeBusinessType">Jenis Usaha <span class="text-danger">*</span></label>
                            <input type="text" name="business_type" id="storeBusinessType" list="storeBusinessPresets" class="form-control" value="{{ old('business_type', $tenant->business_type) }}" required>
                            <datalist id="storeBusinessPresets">
                                <option value="Kedai Minuman / Kopi">
                                <option value="Warung Makan / Resto">
                                <option value="Bakery / Kue">
                                <option value="Toko Kelontong / Retail">
                            </datalist>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small" for="storeAddressInput">Alamat Usaha (tampil di Struk &amp; PDF)</label>
                            <input type="text" name="address" id="storeAddressInput" class="form-control" value="{{ old('address', $tenant->address) }}" placeholder="Jl. Contoh No. 12, Kota">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small" for="storePhoneInput">No. Telepon / WhatsApp Toko</label>
                            <input type="tel" name="phone" id="storePhoneInput" inputmode="tel" class="form-control" value="{{ old('phone', $tenant->phone) }}" placeholder="0812-xxxx-xxxx">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold small" for="storeFooterInput">Pesan Footer Struk Thermal</label>
                            <input type="text" name="receipt_footer" id="storeFooterInput" class="form-control" value="{{ old('receipt_footer', $tenant->receipt_footer) }}" placeholder="Terima kasih atas kunjungan Anda!">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small" for="storeTimezone">Zona Waktu</label>
                            <select name="timezone" id="storeTimezone" class="form-select">
                                <option value="Asia/Jakarta" {{ $tenant->timezone === 'Asia/Jakarta' ? 'selected' : '' }}>WIB (Asia/Jakarta)</option>
                                <option value="Asia/Makassar" {{ $tenant->timezone === 'Asia/Makassar' ? 'selected' : '' }}>WITA (Asia/Makassar)</option>
                                <option value="Asia/Jayapura" {{ $tenant->timezone === 'Asia/Jayapura' ? 'selected' : '' }}>WIT (Asia/Jayapura)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small" for="storeLogoInput">Logo Usaha (Opsional · Maks 2MB PNG/JPG/WebP)</label>
                            <div class="d-flex align-items-center gap-3">
                                <img id="storeLogoPreview" src="{{ $tenant->logo_path ? $tenant->logoUrl() : asset('assets/images/logo.png') }}" alt="Logo Usaha" class="rounded-circle border shadow-sm" style="width: 60px; height: 60px; object-fit: cover;">
                                <input type="file" name="logo" id="storeLogoInput" class="form-control" accept="image/png,image/jpeg,image/webp">
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
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold m-0 text-dark"><i class="fa-solid fa-receipt text-success me-2"></i>Pratinjau Kop Struk</h6>
                <span class="badge bg-success bg-opacity-10 text-success">Live Preview</span>
            </div>
            <div class="card-body bg-light p-4">
                <div class="bg-white p-3 rounded-3 shadow-sm mx-auto" style="max-width: 260px; font-family: 'Courier New', monospace; font-size: 12px;">
                    <div class="text-center pb-2 mb-2" style="border-bottom: 1px dashed #94a3b8;">
                        <div class="fw-bold text-uppercase" id="previewStoreName">{{ $tenant->name }}</div>
                        <div class="text-muted {{ $tenant->address ? '' : 'd-none' }}" id="previewStoreAddress" style="font-size: 10px;">{{ $tenant->address }}</div>
                        <div class="text-muted {{ $tenant->phone ? '' : 'd-none' }}" id="previewStorePhone" style="font-size: 10px;">{{ $tenant->phone ? 'Telp: ' . $tenant->phone : '' }}</div>
                    </div>
                    <div class="d-flex justify-content-between" style="font-size: 11px;">
                        <span>Produk Contoh x1</span>
                        <strong>Rp15.000</strong>
                    </div>
                    <div class="d-flex justify-content-between fw-bold pt-2 mt-2" style="border-top: 1px dashed #94a3b8;">
                        <span>TOTAL</span>
                        <span>Rp15.000</span>
                    </div>
                    <div class="text-center text-muted mt-3" style="font-size: 10px;">
                        <div>*** LUNAS ***</div>
                        <div id="previewStoreFooter">{{ $tenant->receipt_footer ?: 'Terima kasih atas kunjungan Anda!' }}</div>
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

@section('scripts')
<script>
    (function () {
        const nameInput = document.getElementById('storeNameInput');
        const addressInput = document.getElementById('storeAddressInput');
        const phoneInput = document.getElementById('storePhoneInput');
        const footerInput = document.getElementById('storeFooterInput');
        const logoInput = document.getElementById('storeLogoInput');
        const logoPreview = document.getElementById('storeLogoPreview');

        const pName = document.getElementById('previewStoreName');
        const pAddress = document.getElementById('previewStoreAddress');
        const pPhone = document.getElementById('previewStorePhone');
        const pFooter = document.getElementById('previewStoreFooter');

        function syncReceiptPreview() {
            if (pName && nameInput) pName.textContent = nameInput.value.trim() || 'NAMA USAHA';
            if (pAddress && addressInput) {
                const v = addressInput.value.trim();
                pAddress.textContent = v;
                pAddress.classList.toggle('d-none', v === '');
            }
            if (pPhone && phoneInput) {
                const v = phoneInput.value.trim();
                pPhone.textContent = v ? 'Telp: ' + v : '';
                pPhone.classList.toggle('d-none', v === '');
            }
            if (pFooter && footerInput) {
                pFooter.textContent = footerInput.value.trim() || 'Terima kasih atas kunjungan Anda!';
            }
        }

        [nameInput, addressInput, phoneInput, footerInput].forEach(el => {
            el?.addEventListener('input', syncReceiptPreview);
        });

        logoInput?.addEventListener('change', function (e) {
            const file = e.target.files?.[0];
            if (file && logoPreview) {
                const objUrl = URL.createObjectURL(file);
                logoPreview.src = objUrl;
                logoPreview.onload = () => URL.revokeObjectURL(objUrl);
            }
        });
    })();
</script>
@endsection
