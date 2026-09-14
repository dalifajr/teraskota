<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk - {{ $transaction->transaction_number }}</title>
    <style>
        .receipt-card,
        .receipt-card * {
            box-sizing: border-box;
            font-family: 'Courier New', Courier, monospace !important;
        }

        .receipt-standalone-wrapper {
            background-color: #f1f5f9;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 20px;
            min-height: 100vh;
        }

        .receipt-card {
            width: 80mm;
            max-width: 100%;
            background: #ffffff;
            padding: 16px 12px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            font-size: 12px;
            color: #1e293b;
            line-height: 1.4;
            margin: 0 auto;
            text-align: left;
        }

        .receipt-header {
            text-align: center;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #94a3b8;
        }

        .receipt-header h1 {
            font-size: 15px;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 2px;
            letter-spacing: 0.5px;
            color: #0f172a;
        }

        .receipt-header p {
            font-size: 10px;
            color: #64748b;
            margin: 0;
        }

        .receipt-meta {
            margin-bottom: 10px;
            font-size: 11px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #94a3b8;
        }

        .receipt-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
        }

        .receipt-items {
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #94a3b8;
        }

        .item-row {
            margin-bottom: 6px;
        }

        .item-name {
            font-weight: 600;
            font-size: 11px;
            color: #1e293b;
        }

        .item-calc {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #475569;
        }

        .receipt-totals {
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #94a3b8;
            font-size: 11px;
        }

        .receipt-totals .receipt-row.grand-total {
            font-size: 13px;
            font-weight: 700;
            margin: 4px 0;
            padding-top: 4px;
            border-top: 1px dotted #cbd5e1;
            color: #0f172a;
        }

        .receipt-footer {
            text-align: center;
            font-size: 10px;
            color: #64748b;
            margin-top: 10px;
        }

        .no-print {
            text-align: center;
            margin-top: 15px;
        }

        .btn-print {
            background-color: #11361b;
            color: white;
            border: none;
            padding: 8px 18px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 0 4px;
        }

        .btn-close-receipt {
            background-color: #e2e8f0;
            color: #334155;
            border: none;
            padding: 8px 18px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 0 4px;
        }

        @media print {
            body {
                background: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .receipt-standalone-wrapper {
                background: none !important;
                padding: 0 !important;
                margin: 0 !important;
                min-height: auto !important;
                display: block !important;
            }

            .receipt-card {
                width: 100% !important;
                max-width: 80mm !important;
                box-shadow: none !important;
                padding: 4px !important;
                border-radius: 0 !important;
                margin: 0 !important;
            }

            .no-print {
                display: none !important;
            }

            @page {
                margin: 2mm;
                size: auto;
            }
        }
    </style>
</head>
<body>

    <div class="{{ (isset($isModal) || request()->ajax() || request()->expectsJson()) ? '' : 'receipt-standalone-wrapper' }}">
        <div class="receipt-card" id="printableReceipt">
            <!-- Header -->
            <div class="receipt-header">
                <h1>Teras Kota</h1>
                <p>Berlian Makmur</p>
                <p>Jl. Jenderal Sudirman, Palembang</p>
            </div>

            <!-- Meta Data -->
            <div class="receipt-meta">
                <div class="receipt-row">
                    <span>No. Nota:</span>
                    <strong>{{ $transaction->transaction_number }}</strong>
                </div>
                <div class="receipt-row">
                    <span>Waktu:</span>
                    <span>{{ \Carbon\Carbon::parse($transaction->transaction_date)->format('d/m/Y') }} {{ substr($transaction->transaction_time, 0, 5) }}</span>
                </div>
                <div class="receipt-row">
                    <span>Kasir:</span>
                    <span>{{ $transaction->cashier->name ?? 'Kasir' }}</span>
                </div>
                @if($transaction->customer_name)
                    <div class="receipt-row">
                        <span>Pelanggan:</span>
                        <span>{{ $transaction->customer_name }}</span>
                    </div>
                @endif
            </div>

            <!-- Items -->
            <div class="receipt-items">
                @foreach($transaction->details as $item)
                    <div class="item-row">
                        <div class="item-name">{{ $item->menu_name_snapshot }}</div>
                        <div class="item-calc">
                            <span>{{ $item->quantity }} x Rp{{ number_format($item->price_snapshot, 0, ',', '.') }}</span>
                            <strong>Rp{{ number_format($item->subtotal, 0, ',', '.') }}</strong>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Totals -->
            <div class="receipt-totals">
                <div class="receipt-row">
                    <span>Total Item:</span>
                    <span>{{ $transaction->total_quantity }} pcs</span>
                </div>
                <div class="receipt-row grand-total">
                    <span>TOTAL:</span>
                    <span>Rp{{ number_format($transaction->total_sales, 0, ',', '.') }}</span>
                </div>
                <div class="receipt-row">
                    <span>Metode:</span>
                    <span style="text-transform: uppercase;">{{ $transaction->payment_method }}</span>
                </div>
                @if($transaction->payment_method === 'tunai')
                    <div class="receipt-row">
                        <span>Tunai:</span>
                        <span>Rp{{ number_format($transaction->cash_tendered ?? $transaction->total_sales, 0, ',', '.') }}</span>
                    </div>
                    <div class="receipt-row">
                        <span>Kembali:</span>
                        <span>Rp{{ number_format($transaction->change_returned ?? 0, 0, ',', '.') }}</span>
                    </div>
                @endif
            </div>

            <!-- Footer -->
            <div class="receipt-footer">
                <p>*** LUNAS ***</p>
                <p>Terima kasih atas kunjungan Anda!</p>
                <p>Semoga harimu menyenangkan :)</p>
            </div>
        </div>

        @if(!isset($isModal) && !request()->ajax() && !request()->expectsJson())
            <div class="no-print">
                <button onclick="window.print()" class="btn-print">
                    🖨️ Cetak Struk
                </button>
                <a href="{{ route('pos.index') }}" class="btn-close-receipt">
                    ➕ Transaksi Baru
                </a>
            </div>
        @endif
    </div>

</body>
</html>
