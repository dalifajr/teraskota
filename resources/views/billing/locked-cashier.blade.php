<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akses Kasir Terkunci - {{ $tenant->name ?? ($platformName ?? 'KasirLoka') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100 p-3">
    <div class="card border-0 shadow-lg rounded-4 p-4 text-center" style="max-width: 460px;">
        <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle bg-danger bg-opacity-10 text-danger" style="width: 72px; height: 72px; font-size: 2rem;">
            <i class="fa-solid fa-lock"></i>
        </div>
        <h4 class="fw-bold text-dark">Terminal Kasir Sementara Terkunci</h4>
        <p class="text-muted small mb-4">{{ $reason }}</p>
        <p class="small text-secondary mb-4">
            Silakan hubungi <strong>Admin / Pemilik Usaha ({{ $tenant->name }})</strong> untuk memperpanjang paket langganan di menu <em>Langganan &amp; Paket</em>.
        </p>
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-outline-danger rounded-pill px-4">
                <i class="fa-solid fa-right-from-bracket me-1"></i>Keluar
            </button>
        </form>
    </div>
</body>
</html>
