@extends('layouts.app')

@section('title', 'Kategori Menu')
@section('page_title', 'Kelola Kategori Menu')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Kategori</li>
@endsection

@section('content')
<div class="row">
    <!-- Left Column: Kategori List -->
    <div class="col-lg-7 mb-4 mb-lg-0">
        <div class="card premium-card">
            <div class="premium-header">
                <h5 class="m-0 fw-semibold text-dark"><i class="fa-solid fa-tags text-success me-2"></i> Daftar Kategori</h5>
            </div>
            <div class="card-body p-4">
                @if($categories->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover align-middle border">
                            <thead>
                                <tr>
                                    <th>Nama Kategori</th>
                                    <th>Slug</th>
                                    <th class="text-center">Jumlah Menu</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center" style="width: 150px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($categories as $cat)
                                    <tr>
                                        <td class="fw-semibold text-dark">{{ $cat->name }}</td>
                                        <td class="text-muted small">{{ $cat->slug }}</td>
                                        <td class="text-center"><span class="badge bg-secondary">{{ $cat->menus_count }} menu</span></td>
                                        <td class="text-center">
                                            @if($cat->status)
                                                <span class="badge bg-success">Aktif</span>
                                            @else
                                                <span class="badge bg-danger">Nonaktif</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button type="button" class="btn btn-outline-primary" onclick="editCategory({{ $cat->id }}, '{{ addslashes($cat->name) }}', {{ $cat->status ? 1 : 0 }})" title="Edit"><i class="fa-solid fa-edit"></i></button>
                                                
                                                <form action="{{ route('categories.destroy', $cat->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus kategori {{ $cat->name }}? Kategori yang memiliki menu di dalamnya tidak dapat dihapus.')">
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
                @else
                    <div class="text-center text-muted py-5">
                        <i class="fa-solid fa-folder-open fs-1 mb-3 text-secondary"></i>
                        <p class="m-0">Tidak ada kategori ditemukan</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Right Column: Interactive Create/Edit Form -->
    <div class="col-lg-5">
        <div class="card premium-card" id="formCard">
            <div class="premium-header">
                <h5 class="m-0 fw-semibold text-dark" id="formTitle"><i class="fa-solid fa-plus-circle text-success me-2"></i> Tambah Kategori Baru</h5>
            </div>
            <div class="card-body p-4">
                
                @if ($errors->any())
                    <div class="alert alert-danger border-0 rounded-3 mb-4">
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form id="categoryForm" action="{{ route('categories.store') }}" method="POST">
                    @csrf
                    <div id="methodPlaceholder"></div>

                    <div class="mb-3">
                        <label for="name" class="form-label fw-medium text-dark">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control" placeholder="Contoh: Varian Tea" value="{{ old('name') }}" required>
                    </div>

                    <div class="mb-4" id="statusCheckboxContainer" style="display: none;">
                        <div class="form-check">
                            <input type="checkbox" name="status" id="status" class="form-check-input" value="1" checked>
                            <label class="form-check-label fw-medium text-dark" for="status">
                                Kategori Aktif
                            </label>
                            <div class="text-muted small">Jika tidak aktif, menu dalam kategori ini juga akan terpengaruh.</div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary-green flex-grow-1" id="submitBtn"><i class="fa-solid fa-save me-1"></i> Simpan Kategori</button>
                        <button type="button" class="btn btn-outline-secondary" id="cancelBtn" style="display: none;" onclick="resetForm()"><i class="fa-solid fa-xmark me-1"></i> Batal</button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const formCard = document.getElementById('formCard');
    const formTitle = document.getElementById('formTitle');
    const categoryForm = document.getElementById('categoryForm');
    const methodPlaceholder = document.getElementById('methodPlaceholder');
    const nameInput = document.getElementById('name');
    const statusContainer = document.getElementById('statusCheckboxContainer');
    const statusCheckbox = document.getElementById('status');
    const submitBtn = document.getElementById('submitBtn');
    const cancelBtn = document.getElementById('cancelBtn');

    function editCategory(id, name, status) {
        // Switch form layout to Edit
        formCard.classList.add('border-primary-subtle');
        formTitle.innerHTML = `<i class="fa-solid fa-edit text-primary me-2"></i> Edit Kategori: ${name}`;
        categoryForm.action = `/categories/${id}`;
        methodPlaceholder.innerHTML = `<input type="hidden" name="_method" value="PUT">`;
        nameInput.value = name;
        
        statusContainer.style.display = 'block';
        statusCheckbox.checked = status === 1;

        submitBtn.className = 'btn btn-primary flex-grow-1';
        submitBtn.innerHTML = '<i class="fa-solid fa-save me-1"></i> Simpan Perubahan';
        cancelBtn.style.display = 'block';

        nameInput.focus();
    }

    function resetForm() {
        // Restore layout to Create
        formCard.classList.remove('border-primary-subtle');
        formTitle.innerHTML = '<i class="fa-solid fa-plus-circle text-success me-2"></i> Tambah Kategori Baru';
        categoryForm.action = '{{ route("categories.store") }}';
        methodPlaceholder.innerHTML = '';
        nameInput.value = '';
        
        statusContainer.style.display = 'none';
        statusCheckbox.checked = true;

        submitBtn.className = 'btn btn-primary-green flex-grow-1';
        submitBtn.innerHTML = '<i class="fa-solid fa-save me-1"></i> Simpan Kategori';
        cancelBtn.style.display = 'none';
    }
</script>
@endsection
