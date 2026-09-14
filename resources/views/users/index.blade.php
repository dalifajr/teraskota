@extends('layouts.app')

@section('title', 'Manajemen Pengguna')
@section('page_title', 'Manajemen Pengguna')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Pengguna</li>
@endsection

@section('content')
<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="card premium-card p-3">
            <div class="d-flex align-items-center">
                <div class="card-icon-wrapper bg-mint-soft me-3">
                    <i class="fa-solid fa-users-gear text-success"></i>
                </div>
                <div>
                    <div class="stat-value">{{ $users->count() }}</div>
                    <div class="stat-label">Total Akun</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card premium-card p-3">
            <div class="d-flex align-items-center">
                <div class="card-icon-wrapper bg-primary bg-opacity-10 me-3 text-primary">
                    <i class="fa-solid fa-user-shield"></i>
                </div>
                <div>
                    <div class="stat-value text-primary">{{ $users->where('role', 'admin')->count() }}</div>
                    <div class="stat-label">Administrator</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card premium-card p-3">
            <div class="d-flex align-items-center">
                <div class="card-icon-wrapper bg-success bg-opacity-10 me-3 text-success">
                    <i class="fa-solid fa-cash-register"></i>
                </div>
                <div>
                    <div class="stat-value text-success">{{ $users->where('role', 'kasir')->count() }}</div>
                    <div class="stat-label">Kasir POS</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card premium-card">
    <div class="premium-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="m-0 fw-semibold text-dark">
            <i class="fa-solid fa-user-group text-success me-2"></i> Daftar Akun Pengguna
        </h5>
        <button type="button" class="btn btn-primary-green" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fa-solid fa-user-plus me-1"></i> Tambah Pengguna
        </button>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 60px;">No</th>
                        <th>Nama & Profil</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Peran (Role)</th>
                        <th>Dibuat Tanggal</th>
                        <th class="text-center" style="width: 140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $index => $u)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img
                                        src="https://ui-avatars.com/api/?name={{ urlencode($u->name) }}&background=11361b&color=ffffff"
                                        alt="Avatar"
                                        style="width: 36px; height: 36px; border-radius: 50%;"
                                    >
                                    <div>
                                        <div class="fw-semibold text-dark">{{ $u->name }}</div>
                                        @if($u->id === Auth::id())
                                            <span class="badge bg-info text-dark" style="font-size: 0.65rem;">Akun Anda</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td><code>{{ $u->username }}</code></td>
                            <td>{{ $u->email }}</td>
                            <td>
                                @if($u->role === 'admin')
                                    <span class="badge bg-primary px-3 py-1 rounded-pill">
                                        <i class="fa-solid fa-user-shield me-1"></i> Admin
                                    </span>
                                @else
                                    <span class="badge bg-success px-3 py-1 rounded-pill">
                                        <i class="fa-solid fa-cash-register me-1"></i> Kasir POS
                                    </span>
                                @endif
                            </td>
                            <td>{{ $u->created_at->format('d M Y') }}</td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editUserModal{{ $u->id }}"
                                        title="Edit Pengguna"
                                    >
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                    @if($u->id !== Auth::id())
                                        <form action="{{ route('users.destroy', $u->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus pengguna ini?');" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Pengguna">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editUserModal{{ $u->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog text-start">
                                        <div class="modal-content rounded-4 border-0 shadow">
                                            <form action="{{ route('users.update', $u->id) }}" method="POST">
                                                @csrf
                                                @method('PUT')
                                                <div class="modal-header bg-light">
                                                    <h5 class="modal-title fw-bold text-dark">Edit Pengguna: {{ $u->name }}</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                                </div>
                                                <div class="modal-body p-4">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold small">Nama Lengkap</label>
                                                        <input type="text" name="name" class="form-control" value="{{ $u->name }}" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold small">Username</label>
                                                        <input type="text" name="username" class="form-control" value="{{ $u->username }}" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold small">Email</label>
                                                        <input type="email" name="email" class="form-control" value="{{ $u->email }}" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold small">Peran (Role)</label>
                                                        <select name="role" class="form-select" required>
                                                            <option value="kasir" {{ $u->role === 'kasir' ? 'selected' : '' }}>Kasir POS</option>
                                                            <option value="admin" {{ $u->role === 'admin' ? 'selected' : '' }}>Administrator</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold small">Kata Sandi Baru (Kosongkan jika tidak diubah)</label>
                                                        <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter...">
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light border-0">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-primary-green">Simpan Perubahan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">Belum ada pengguna terdaftar</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah Pengguna -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <form action="{{ route('users.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-dark">
                        <i class="fa-solid fa-user-plus text-success me-2"></i> Tambah Pengguna Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Nama Lengkap</label>
                        <input type="text" name="name" class="form-control" placeholder="Contoh: Siti Rahma" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Username</label>
                        <input type="text" name="username" class="form-control" placeholder="Contoh: sitikasir" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Email</label>
                        <input type="email" name="email" class="form-control" placeholder="Contoh: siti@teraskota.local" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Peran (Role)</label>
                        <select name="role" class="form-select" required>
                            <option value="kasir" selected>Kasir POS (Hanya akses Terminal POS)</option>
                            <option value="admin">Administrator (Akses penuh sistem)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Kata Sandi</label>
                        <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter..." required>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary-green">Tambah Pengguna</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
