@extends('layouts.app')

@section('title', 'Detail Transaksi')
@section('page_title', 'Detail Transaksi')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('transactions.index') }}" class="text-decoration-none text-success">Transaksi</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $transaction->transaction_number }}</li>
@endsection

@section('content')
<div class="row">
    <!-- Transaction Metadata Cards -->
    <div class="col-lg-4 mb-4">
        <div class="card premium-card h-100">
            <div class="premium-header">
                <h5 class="m-0 fw-semibold text-dark"><i class="fa-solid fa-circle-info text-success me-2"></i> Informasi Ringkasan</h5>
            </div>
            <div class="card-body p-4">
                <div class="mb-3">
                    <span class="small text-muted d-block">Nomor Transaksi</span>
                    <strong class="text-dark fs-5">{{ $transaction->transaction_number }}</strong>
                </div>
                <div class="mb-3">
                    <span class="small text-muted d-block">Waktu Transaksi</span>
                    <strong class="text-dark"><i class="fa-regular fa-calendar text-success me-1"></i> {{ $transaction->transaction_date->translatedFormat('d F Y') }}</strong>
                    <span class="text-muted ms-2">&bull; <i class="fa-regular fa-clock me-1"></i> {{ date('H:i', strtotime($transaction->transaction_time)) }}</span>
                </div>
                <div class="mb-3">
                    <span class="small text-muted d-block">Admin Penginput</span>
                    <strong class="text-dark"><i class="fa-regular fa-user text-success me-1"></i> {{ $transaction->admin->name ?? 'System' }}</strong>
                </div>
                <div class="mb-4">
                    <span class="small text-muted d-block">Catatan</span>
                    <p class="text-dark m-0 bg-light p-2.5 rounded-3 border" style="white-space: pre-wrap;">{{ $transaction->notes ?: 'Tidak ada catatan.' }}</p>
                </div>
                
                <hr>

                <div class="d-flex flex-column gap-2 mt-4">
                    <a href="{{ route('transactions.edit', $transaction->id) }}" class="btn btn-primary-green w-100"><i class="fa-solid fa-edit me-1"></i> Edit Transaksi</a>
                    
                    <form action="{{ route('transactions.destroy', $transaction->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus transaksi ini? Transaksi akan dipindahkan ke folder sampah (soft delete).')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger w-100"><i class="fa-solid fa-trash me-1"></i> Hapus Transaksi</button>
                    </form>

                    <a href="{{ route('transactions.index') }}" class="btn btn-outline-secondary w-100"><i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Riwayat</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Transaction Line Items Details -->
    <div class="col-lg-8 mb-4">
        <div class="card premium-card">
            <div class="premium-header">
                <h5 class="m-0 fw-semibold text-dark"><i class="fa-solid fa-file-lines text-success me-2"></i> Rincian Menu Terjual</h5>
            </div>
            <div class="card-body p-4">
                <div class="table-responsive mb-4">
                    <table class="table table-hover align-middle border">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>Menu</th>
                                <th>Kategori</th>
                                <th class="text-end">Harga Snapshot</th>
                                <th class="text-center">Jumlah</th>
                                <th class="text-end">Subtotal</th>
                                <th class="text-center">Profit (%)</th>
                                <th class="text-end">Keuntungan</th>
                                <th class="text-end">Modal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($transaction->details as $idx => $detail)
                                <tr>
                                    <td>{{ $idx + 1 }}</td>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ $detail->menu_name_snapshot }}</div>
                                        <small class="text-muted">ID Menu: {{ $detail->menu_id ?? 'Terhapus' }}</small>
                                    </td>
                                    <td><span class="badge bg-mint-soft text-success">{{ $detail->category_name_snapshot }}</span></td>
                                    <td class="text-end">Rp{{ number_format($detail->price_snapshot, 0, ',', '.') }}</td>
                                    <td class="text-center fw-semibold text-dark">{{ $detail->quantity }} pcs</td>
                                    <td class="text-end fw-semibold">Rp{{ number_format($detail->subtotal, 0, ',', '.') }}</td>
                                    <td class="text-center text-muted">{{ $detail->profit_percentage_snapshot }}%</td>
                                    <td class="text-end text-success fw-medium">Rp{{ number_format($detail->profit_amount, 0, ',', '.') }}</td>
                                    <td class="text-end text-danger small">Rp{{ number_format($detail->estimated_cost, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Aggregate Summaries Box -->
                <div class="bg-light p-4 rounded-4 border">
                    <div class="row g-3">
                        <div class="col-md-3 text-center border-end">
                            <span class="small text-muted d-block mb-1">Total Produk Terjual</span>
                            <h4 class="m-0 fw-bold text-dark">{{ $transaction->total_quantity }} pcs</h4>
                        </div>
                        <div class="col-md-3 text-center border-end">
                            <span class="small text-muted d-block mb-1">Total Omzet</span>
                            <h4 class="m-0 fw-bold text-primary">Rp{{ number_format($transaction->total_sales, 0, ',', '.') }}</h4>
                        </div>
                        <div class="col-md-3 text-center border-end">
                            <span class="small text-muted d-block mb-1">Total Keuntungan</span>
                            <h4 class="m-0 fw-bold text-success">Rp{{ number_format($transaction->total_profit, 0, ',', '.') }}</h4>
                        </div>
                        <div class="col-md-3 text-center">
                            <span class="small text-muted d-block mb-1">Estimasi Modal</span>
                            <h4 class="m-0 fw-bold text-danger">Rp{{ number_format($transaction->estimated_cost, 0, ',', '.') }}</h4>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
