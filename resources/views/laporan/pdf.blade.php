<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Keuangan SMKN 53 Jakarta</title>
    <style>
        @page {
            margin: 28px 28px 32px 28px;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #111827;
            margin: 0;
            padding: 0;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #111827;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }

        .header h1 {
            margin: 0;
            font-size: 18px;
            letter-spacing: 1px;
        }

        .header h2 {
            margin: 4px 0 0;
            font-size: 14px;
            font-weight: normal;
        }

        .header p {
            margin: 6px 0 0;
            font-size: 10px;
        }

        .meta {
            width: 100%;
            margin-bottom: 14px;
            font-size: 10px;
        }

        .meta td {
            padding: 2px 0;
            vertical-align: top;
        }

        .summary {
            margin: 10px 0 16px;
            padding: 10px 12px;
            border: 1px solid #111827;
            background: #f9fafb;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }

        .summary-table td {
            padding: 3px 0;
            font-size: 11px;
        }

        .summary-table td:last-child {
            text-align: right;
            font-weight: bold;
        }

        .section-title {
            margin: 16px 0 8px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        table.report {
            width: 100%;
            border-collapse: collapse;
        }

        table.report th,
        table.report td {
            border: 1px solid #111827;
            padding: 6px 5px;
            vertical-align: top;
        }

        table.report th {
            background: #e5e7eb;
            text-align: center;
            font-size: 10px;
        }

        table.report td {
            font-size: 10px;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .muted {
            color: #4b5563;
        }

        .footer {
            margin-top: 18px;
            font-size: 9px;
            text-align: right;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>SMKN 53 JAKARTA</h1>
        <h2>Laporan Keuangan</h2>
        <p>Riwayat pembayaran yang status notifikasinya terkirim / sukses</p>
    </div>

    <table class="meta">
        <tr>
            <td width="18%"><strong>Periode</strong></td>
            <td width="2%">:</td>
            <td>{{ $periodLabel }}</td>
            <td width="25%" class="text-right"><strong>Tanggal Cetak</strong></td>
            <td width="2%">:</td>
            <td width="18%" class="text-right">{{ $printedAt->format('d/m/Y H:i') }}</td>
        </tr>
    </table>

    <div class="summary">
        <table class="summary-table">
            <tr>
                <td>Total Pemasukan</td>
                <td>Rp {{ number_format($totalPemasukan, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Total Transaksi</td>
                <td>{{ $riwayatPembayaran->count() }} transaksi</td>
            </tr>
        </table>
    </div>

    <div class="section-title">Riwayat Pembayaran</div>

    <table class="report">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="13%">Tanggal</th>
                <th width="13%">ID Pelanggan</th>
                <th width="20%">Nama Pelanggan</th>
                <th width="18%">Paket</th>
                <th width="12%">Nominal</th>
                <th width="14%">Admin</th>
                <th width="5%">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($riwayatPembayaran as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="text-center">{{ optional($item->tanggal_bayar)->format('d/m/Y') ?? '-' }}</td>
                    <td class="text-center">{{ $item->id_pelanggan }}</td>
                    <td>{{ $item->pelanggan->nama_pelanggan ?? '-' }}</td>
                    <td>{{ $item->pelanggan->paket->nama_paket ?? '-' }}</td>
                    <td class="text-right">Rp {{ number_format($item->nominal, 0, ',', '.') }}</td>
                    <td>{{ $item->admin->nama_lengkap ?? '-' }}</td>
                    <td class="text-center">{{ $item->status_notifikasi ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center muted" style="padding: 16px 8px;">Tidak ada data pembayaran pada periode ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Dokumen ini dibuat otomatis oleh sistem monitoring jaringan.
    </div>
</body>
</html>
