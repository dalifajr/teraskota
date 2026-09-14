@extends('layouts.app')

@section('title', 'Pembaruan Sistem')
@section('page_title', 'Pembaruan Sistem (GitHub Sync)')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-success">Dashboard</a></li>
    <li class="breadcrumb-item active" aria-current="page">Pembaruan Sistem</li>
@endsection

@section('content')
<!-- System Info Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="card premium-card p-3">
            <div class="d-flex align-items-center">
                <div class="card-icon-wrapper bg-mint-soft me-3">
                    <i class="fa-brands fa-github text-success"></i>
                </div>
                <div>
                    <div class="stat-value fs-5 text-dark text-truncate" style="max-width: 170px;" title="{{ $remoteUrl }}">
                        {{ $currentBranch ?: 'main' }}
                    </div>
                    <div class="stat-label">Branch Aktif</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card premium-card p-3">
            <div class="d-flex align-items-center">
                <div class="card-icon-wrapper bg-primary bg-opacity-10 me-3 text-primary">
                    <i class="fa-solid fa-code-commit"></i>
                </div>
                <div>
                    <div class="stat-value fs-5 text-primary"><code>{{ $lastCommitHash ?: '-' }}</code></div>
                    <div class="stat-label">Commit Terakhir</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card premium-card p-3">
            <div class="d-flex align-items-center">
                <div class="card-icon-wrapper bg-info bg-opacity-10 me-3 text-info">
                    <i class="fa-brands fa-laravel"></i>
                </div>
                <div>
                    <div class="stat-value fs-5 text-info">v{{ $laravelVersion }}</div>
                    <div class="stat-label">Laravel Framework</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card premium-card p-3">
            <div class="d-flex align-items-center">
                <div class="card-icon-wrapper bg-warning bg-opacity-10 me-3 text-warning">
                    <i class="fa-brands fa-php"></i>
                </div>
                <div>
                    <div class="stat-value fs-5 text-dark">v{{ $phpVersion }}</div>
                    <div class="stat-label">PHP Engine</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Update Control Card -->
<div class="card premium-card mb-4">
    <div class="premium-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="m-0 fw-semibold text-dark">
                <i class="fa-solid fa-arrows-rotate text-success me-2"></i> Sinkronisasi & Pembaruan Sistem
            </h5>
            <small class="text-muted">Kelola versi aplikasi terintegrasi langsung dengan repositori GitHub</small>
        </div>
        <div class="d-flex gap-2">
            <!-- Form Cek Pembaruan -->
            <form action="{{ route('settings.update.check') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-success">
                    <i class="fa-solid fa-magnifying-glass me-1"></i> Periksa Pembaruan
                </button>
            </form>

            <!-- Form Bersihkan Cache -->
            <form action="{{ route('settings.update.clear-cache') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-secondary" title="Bersihkan cache view, route & config">
                    <i class="fa-solid fa-broom me-1"></i> Bersihkan Cache
                </button>
            </form>
        </div>
    </div>

    <div class="card-body p-4">
        <!-- Remote Information -->
        <div class="p-3 bg-light rounded-3 border mb-4">
            <div class="row g-3 align-items-center">
                <div class="col-md-7">
                    <div class="small text-muted mb-1">Repositori Remote GitHub:</div>
                    <div class="fw-bold text-dark text-break">
                        <i class="fa-brands fa-github me-1 text-secondary"></i>
                        <a href="{{ $remoteUrl }}" target="_blank" class="text-decoration-none text-success">
                            {{ $remoteUrl ?: 'https://github.com/dalifajr/teraskota.git' }}
                        </a>
                    </div>
                </div>
                <div class="col-md-5 text-md-end">
                    <span class="small text-muted d-block mb-1">Pesan Commit Terakhir:</span>
                    <span class="badge bg-secondary text-wrap" style="font-size: 0.8rem;">
                        {{ $lastCommitMsg ?: 'Belum ada commit' }}
                    </span>
                    <div class="small text-muted mt-1">{{ $lastCommitDate }} oleh {{ $lastCommitAuthor }}</div>
                </div>
            </div>
        </div>

        <!-- Alert If Update Available -->
        @if(session('update_available'))
            @php $upInfo = session('update_available'); @endphp
            <div class="alert alert-warning border-0 border-start border-5 border-warning shadow-sm rounded-3 p-4 mb-4" role="alert">
                <div class="d-flex align-items-start">
                    <i class="fa-solid fa-bell fs-2 text-warning me-3 mt-1"></i>
                    <div class="flex-grow-1">
                        <h5 class="fw-bold text-dark mb-1">Tersedia {{ $upInfo['count'] }} Pembaruan Baru di GitHub!</h5>
                        <p class="mb-2 text-muted small">Versi terbaru di repositori: commit <code>{{ $upInfo['remote_hash'] }}</code></p>
                        
                        <div class="p-2 bg-white rounded border small mb-3">
                            <div class="fw-bold text-dark mb-1">Daftar Commit Terbaru:</div>
                            <pre class="m-0 text-muted" style="font-size: 0.8rem;">{{ $upInfo['commits'] }}</pre>
                        </div>

                        <form action="{{ route('settings.update.execute') }}" method="POST" id="formExecuteUpdate" onsubmit="return confirmUpdate();">
                            @csrf
                            <button type="submit" class="btn btn-primary-green px-4 py-2 fw-bold" id="btnUpdateNow">
                                <i class="fa-solid fa-cloud-arrow-down me-2"></i> Perbarui Sistem Sekarang
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @else
            <!-- Action Box if no update triggered yet -->
            <div class="p-4 rounded-3 border text-center mb-4" style="background: #fafafa;">
                <i class="fa-solid fa-shield-halved fs-1 text-success mb-3"></i>
                <h5 class="fw-bold text-dark">Alur Pembaruan Sistem Aman (5 Tahap Atomik)</h5>
                <p class="text-muted small mx-auto mb-3" style="max-width: 600px;">
                    Sistem pembaruan ini menjalankan pipeline otomatis: mengaktifkan mode pemeliharaan, menarik perubahan dari GitHub, menjalankan migrasi basis data, membersihkan seluruh cache, dan mengaktifkan kembali aplikasi tanpa risiko desinkronisasi.
                </p>

                <form action="{{ route('settings.update.execute') }}" method="POST" id="formExecuteManual" onsubmit="return confirmUpdate();">
                    @csrf
                    <button type="submit" class="btn btn-primary-green px-4 py-2" id="btnManualSync">
                        <i class="fa-solid fa-rotate me-2"></i> Jalankan Sinkronisasi GitHub (Pull & Migrate)
                    </button>
                </form>
            </div>
        @endif

        <!-- Log Output Terminal (If Available) -->
        @if(session('update_log'))
            <div class="mt-4">
                <h6 class="fw-bold text-dark mb-2">
                    <i class="fa-solid fa-terminal text-muted me-1"></i> Riwayat Eksekusi Log Pembaruan:
                </h6>
                <div class="p-3 rounded-3 text-light" style="background-color: #0f172a; font-family: 'Courier New', monospace; font-size: 0.85rem; max-height: 350px; overflow-y: auto;">
                    <pre class="m-0" style="color: #38bdf8;">{{ session('update_log') }}</pre>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
    function confirmUpdate() {
        return confirm("Apakah Anda yakin ingin memperbarui sistem sekarang?\nAplikasi akan memasuki mode pemeliharaan selama beberapa detik untuk menarik kode dan menjalankan migrasi.");
    }
</script>
@endsection
