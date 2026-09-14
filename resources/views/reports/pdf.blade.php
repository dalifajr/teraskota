<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Penjualan Teras Kota Berlian Makmur</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11pt;
            color: #333333;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #11361b;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .header h1 {
            color: #11361b;
            margin: 0 0 5px 0;
            font-size: 22pt;
            font-weight: bold;
        }
        .header p {
            margin: 0;
            font-size: 10pt;
            color: #666666;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 25px;
            border-collapse: collapse;
        }
        .meta-table td {
            padding: 4px 0;
            font-size: 9.5pt;
        }
        .meta-label {
            font-weight: bold;
            color: #555555;
            width: 150px;
        }
        .summary-box {
            background-color: #f4f6f3;
            border-left: 4px solid #11361b;
            padding: 15px;
            margin-bottom: 25px;
        }
        .summary-title {
            font-weight: bold;
            color: #11361b;
            font-size: 12pt;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .summary-grid {
            width: 100%;
        }
        .summary-grid td {
            width: 33.33%;
            padding: 8px 5px;
            vertical-align: top;
        }
        .summary-value {
            font-size: 14pt;
            font-weight: bold;
            color: #11361b;
            margin-top: 3px;
        }
        .summary-value.danger {
            color: #c0392b;
        }
        .summary-label {
            font-size: 8.5pt;
            color: #666666;
            text-transform: uppercase;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .report-table th {
            background-color: #11361b;
            color: #ffffff;
            font-weight: bold;
            text-align: left;
            padding: 8px 10px;
            font-size: 9.5pt;
        }
        .report-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #dddddd;
            font-size: 9.5pt;
            vertical-align: middle;
        }
        .report-table tr:nth-child(even) {
            background-color: #f9fbf9;
        }
        .text-center {
            text-align: center;
        }
        .text-end {
            text-align: right;
        }
        .fw-bold {
            font-weight: bold;
        }
        .badge {
            display: inline-block;
            padding: 3px 6px;
            font-size: 7.5pt;
            font-weight: bold;
            color: #11361b;
            background-color: #e8f5e9;
            border-radius: 4px;
        }
    </style>
</head>
<body>

    <!-- Header / Kop Laporan -->
    <div class="header">
        <h1>TERAS KOTA BERLIAN MAKMUR</h1>
        <p>Laporan Kinerja Keuangan & Penjualan Produk</p>
    </div>

    <!-- Metadata Laporan -->
    <table class="meta-table">
        <tr>
            <td class="meta-label">Judul Laporan</td>
            <td>: Laporan Penjualan Penjualan Minuman</td>
            <td class="meta-label" style="text-align: right; width: 100px;">Tanggal Cetak</td>
            <td style="text-align: right;">: {{ now()->translatedFormat('d F Y H:i') }}</td>
        </tr>
        <tr>
            <td class="meta-label">Periode Laporan</td>
            <td>: {{ $date_range_label }}</td>
            <td class="meta-label" style="text-align: right; width: 100px;">Nama Admin</td>
            <td style="text-align: right;">: {{ $admin_name }}</td>
        </tr>
    </table>

    <!-- Ringkasan Finansial -->
    <div class="summary-box">
        <div class="summary-title">Ringkasan Finansial</div>
        <table class="summary-grid">
            <tr>
                <td>
                    <div class="summary-label">Total Omzet (Sales)</div>
                    <div class="summary-value">Rp{{ number_format($total_sales, 0, ',', '.') }}</div>
                </td>
                <td>
                    <div class="summary-label">Total Keuntungan (Profit)</div>
                    <div class="summary-value" style="color: #27ae60;">Rp{{ number_format($total_profit, 0, ',', '.') }}</div>
                </td>
                <td>
                    <div class="summary-label">Estimasi Modal (Cost)</div>
                    <div class="summary-value danger">Rp{{ number_format($estimated_cost, 0, ',', '.') }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="summary-label">Jumlah Transaksi</div>
                    <div class="summary-value" style="color: #2c3e50;">{{ number_format($total_transactions, 0, ',', '.') }}</div>
                </td>
                <td>
                    <div class="summary-label">Produk Terjual (Volume)</div>
                    <div class="summary-value" style="color: #8e44ad;">{{ number_format($total_quantity, 0, ',', '.') }} pcs</div>
                </td>
                <td>
                    <div class="summary-label">Rata-rata Omzet / Hari</div>
                    <div class="summary-value" style="color: #d35400;">Rp{{ number_format($average_sales_per_day, 0, ',', '.') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Rincian Penjualan per Menu -->
    <div style="font-weight: bold; font-size: 11pt; color: #11361b; margin-top: 15px; margin-bottom: 5px; text-transform: uppercase;">
        Rincian Penjualan per Menu
    </div>
    
    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 35px;" class="text-center">No</th>
                <th>Menu Minuman</th>
                <th>Kategori</th>
                <th class="text-center" style="width: 80px;">Jumlah</th>
                <th class="text-end" style="width: 100px;">Omzet</th>
                <th class="text-end" style="width: 100px;">Keuntungan</th>
                <th class="text-end" style="width: 100px;">Estimasi Modal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $idx => $item)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td class="fw-bold">{{ $item->menu_name }}</td>
                    <td><span class="badge">{{ $item->category_name }}</span></td>
                    <td class="text-center fw-bold">{{ $item->total_quantity }} pcs</td>
                    <td class="text-end">Rp{{ number_format($item->total_sales, 0, ',', '.') }}</td>
                    <td class="text-end fw-bold" style="color: #27ae60;">Rp{{ number_format($item->total_profit, 0, ',', '.') }}</td>
                    <td class="text-end" style="color: #c0392b;">Rp{{ number_format($item->total_estimated_cost, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

</body>
</html>
