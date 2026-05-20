<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Pembayaran</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace;
            background: #fff;
            padding: 20px;
        }

        .struk-container {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            background: #fff;
            border: 2px solid #333;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .struk-header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }

        .struk-title {
            font-size: 18px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 5px;
        }

        .struk-subtitle {
            font-size: 12px;
            color: #555;
            margin-bottom: 10px;
        }

        .struk-divider {
            border-top: 1px dashed #333;
            margin: 15px 0;
        }

        .struk-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 13px;
            line-height: 1.6;
        }

        .struk-label {
            font-weight: bold;
            color: #111827;
            flex: 0 0 40%;
        }

        .struk-value {
            color: #333;
            flex: 0 1 60%;
            text-align: right;
            word-break: break-word;
        }

        .struk-section-title {
            font-weight: bold;
            color: #111827;
            margin-top: 15px;
            margin-bottom: 10px;
            font-size: 13px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }

        .struk-nominal {
            display: flex;
            justify-content: space-between;
            margin: 15px 0;
            font-size: 16px;
            font-weight: bold;
            color: #19395f;
            border-top: 1px solid #333;
            border-bottom: 1px solid #333;
            padding: 10px 0;
        }

        .struk-nominal-label {
            flex: 0 0 40%;
        }

        .struk-nominal-value {
            flex: 0 1 60%;
            text-align: right;
        }

        .struk-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px solid #333;
            font-size: 11px;
            color: #666;
        }

        .struk-footer p {
            margin: 5px 0;
        }

        @media print {
            body {
                padding: 0;
            }

            .struk-container {
                max-width: none;
                box-shadow: none;
                border: none;
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="struk-container">
        <!-- Header -->
        <div class="struk-header">
            <div class="struk-title">SMKN 53 JAKARTA</div>
            <div class="struk-subtitle">Sistem Monitoring Jaringan</div>
            <div class="struk-subtitle">Struk Pembayaran</div>
        </div>

        <!-- Divider -->
        <div class="struk-divider"></div>

        <!-- Data Transaksi -->
        <div class="struk-section-title">INFORMASI TRANSAKSI</div>

        <div class="struk-row">
            <span class="struk-label">ID Pembayaran</span>
            <span class="struk-value">PMB_{{ str_pad((string) $pembayaran->id_pembayaran, 3, '0', STR_PAD_LEFT) }}</span>
        </div>

        <div class="struk-row">
            <span class="struk-label">Tanggal</span>
            <span class="struk-value">{{ optional($pembayaran->tanggal_bayar)->format('d/m/Y') ?? '-' }}</span>
        </div>

        <div class="struk-row">
            <span class="struk-label">Admin</span>
            <span class="struk-value">{{ $pembayaran->admin->nama_lengkap ?? $pembayaran->admin->username ?? '-' }}</span>
        </div>

        <!-- Divider -->
        <div class="struk-divider"></div>

        <!-- Data Pelanggan -->
        <div class="struk-section-title">DATA PELANGGAN</div>

        <div class="struk-row">
            <span class="struk-label">ID Pelanggan</span>
            <span class="struk-value">{{ $pembayaran->id_pelanggan }}</span>
        </div>

        <div class="struk-row">
            <span class="struk-label">Nama</span>
            <span class="struk-value">{{ $pembayaran->pelanggan->nama_pelanggan ?? '-' }}</span>
        </div>

        <div class="struk-row">
            <span class="struk-label">Username</span>
            <span class="struk-value">{{ $pembayaran->pelanggan->username_mikrotik ?? '-' }}</span>
        </div>

        <div class="struk-row">
            <span class="struk-label">Paket</span>
            <span class="struk-value">{{ $pembayaran->pelanggan->paket->nama_paket ?? '-' }}</span>
        </div>

        <!-- Divider -->
        <div class="struk-divider"></div>

        <!-- Data Pembayaran -->
        <div class="struk-section-title">DETAIL PEMBAYARAN</div>

        <div class="struk-row">
            <span class="struk-label">Periode</span>
            <span class="struk-value">{{ $pembayaran->periode_tagihan }}</span>
        </div>

        <!-- Nominal -->
        <div class="struk-nominal">
            <span class="struk-nominal-label">Total Pembayaran</span>
            <span class="struk-nominal-value">Rp {{ number_format($pembayaran->nominal, 0, ',', '.') }}</span>
        </div>

        <div class="struk-row">
            <span class="struk-label">Status</span>
            <span class="struk-value">{{ $pembayaran->status_notifikasi }}</span>
        </div>

        <!-- Footer -->
        <div class="struk-footer">
            <p>Terima kasih telah melakukan pembayaran</p>
            <p>Struk ini adalah bukti pembayaran yang sah</p>
            <p>Dicetak pada: {{ now()->format('d/m/Y H:i:s') }}</p>
        </div>
    </div>
</body>
</html>
