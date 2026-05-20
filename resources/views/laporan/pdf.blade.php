<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Pembayaran</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        .header { text-align: center; margin-bottom: 18px; }
        .header h1 { margin: 0; font-size: 20px; }
        .meta { margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #374151; padding: 8px; }
        th { background: #e5e7eb; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>LAPORAN PEMBAYARAN</h1>
        <div>Periode: {{ $periodLabel }}</div>
        <div>Cetak: {{ $printedAt->format('d/m/Y H:i') }}</div>
    </div>

    <div class="meta">
        <strong>Total Pemasukan:</strong> Rp {{ number_format($totalPemasukan, 0, ',', '.') }}
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Pelanggan</th>
                <th>Paket</th>
                <th>Periode</th>
                <th>Tanggal Bayar</th>
                <th class="text-right">Nominal</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($riwayatPembayaran as $item)
                <tr>
                    <td>{{ $item->id_pembayaran }}</td>
                    <td>{{ $item->pelanggan->nama_pelanggan ?? '-' }}</td>
                    <td>{{ $item->pelanggan->paket->nama_paket ?? '-' }}</td>
                    <td>{{ $item->periode_tagihan }}</td>
                    <td>{{ optional($item->tanggal_bayar)->format('d/m/Y') ?? '-' }}</td>
                    <td class="text-right">Rp {{ number_format($item->nominal, 0, ',', '.') }}</td>
                    <td>{{ $item->status_notifikasi }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>