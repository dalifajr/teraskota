@extends('layouts.app')

@section('title', 'Profil Admin')
@section('page_title', 'Profil Admin')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Profil Admin</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card premium-card">
            <div class="premium-header">
                <h5 class="m-0 fw-semibold text-dark"><i class="fa-solid fa-user-gear text-success me-2"></i> Pengaturan Akun Admin</h5>
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

                <form action="{{ route('profile.update') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label fw-semibold text-dark">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $user->name) }}" required autocomplete="name">
                        </div>
                        <div class="col-md-6">
                            <label for="username" class="form-label fw-semibold text-dark">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" id="username" class="form-control" value="{{ old('username', $user->username) }}" required autocomplete="username">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold text-dark">Alamat Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="email" class="form-control" value="{{ old('email', $user->email) }}" required autocomplete="email">
                    </div>

                    <hr class="my-4">

                    <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-lock me-1"></i> Ubah Password (Opsional)</h6>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="password" class="form-label fw-semibold text-dark">Password Baru</label>
                            <input type="password" name="password" id="password" class="form-control" placeholder="Kosongkan jika tidak ingin diubah">
                            <small class="text-muted">Minimal 6 karakter.</small>
                        </div>
                        <div class="col-md-6">
                            <label for="password_confirmation" class="form-label fw-semibold text-dark">Konfirmasi Password Baru</label>
                            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" placeholder="Ulangi password baru">
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary px-4"><i class="fa-solid fa-xmark me-1"></i> Batal</a>
                        <button type="submit" class="btn btn-primary-green px-4"><i class="fa-solid fa-save me-1"></i> Simpan Profil</button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>
@endsection
