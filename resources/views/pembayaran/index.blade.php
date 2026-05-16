@extends('layouts.app')

@section('title', 'Pencatatan Pembayaran | SMKN 53')
@section('page_heading', 'Dashboard | Pencatatan Pembayaran & Tagihan')

@section('content')
@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if (session('warning'))
    <div class="alert alert-warning">{{ session('warning') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<style>
    .payment-title {
        color: #19395f;
        font-size: 2.1rem;
        font-weight: 800;
        letter-spacing: 0.2px;
        margin-bottom: 12px;
        text-transform: uppercase;
    }

    .payment-card {
        background: #21476f;
        color: #0f172a;
        border-radius: 8px;
        border: 2px solid #1f2937;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08);
    }

    .payment-card .card-title {
        font-size: 1.2rem;
        line-height: 1.2;
        color: #0b1525;
        font-weight: 800;
        text-transform: uppercase;
        margin-bottom: 14px;
    }

    .payment-card label,
    .payment-card .muted-line {
        color: #0e1826;
        font-weight: 600;
    }

    .payment-card .form-control,
    .payment-card .form-select {
        border: 2px solid #1f2937;
        border-radius: 8px;
        background: #d8d8d8;
        color: #0f172a;
        box-shadow: none;
    }

    .payment-card .search-field {
        background: #d8d8d8;
        border-right: 0;
        border-radius: 10px 0 0 10px;
    }

    .payment-card .search-btn {
        border: 2px solid #1f2937;
        border-left: 0;
        background: #d8d8d8;
        border-radius: 0 10px 10px 0;
        color: #111827;
    }

    .payment-card .action-btn,
    .payment-card .secondary-btn {
        border: 2px solid #1f2937;
        border-radius: 8px;
        background: #d8d8d8;
        color: #111827;
        font-weight: 800;
    }

    .payment-table {
        border: 3px solid #1f2937;
        background: #f8fbfb;
    }

    .payment-table thead th {
        background: #c7c7c7;
        border: 3px solid #1f2937;
        color: #111827;
        font-weight: 800;
        vertical-align: middle;
        padding: 1rem 0.75rem;
    }

    .payment-table tbody td {
        border: 3px solid #1f2937;
        padding: 1.2rem 0.75rem;
        color: #111827;
        background: #f5fbfb;
    }

    .table-footer-bar {
        border: 3px solid #1f2937;
        border-top: 0;
        background: #f5fbfb;
        padding: 14px 18px;
    }

    .pagination-soft .page-link {
        background: #d3d3d3;
        color: #111827;
        border: 0;
        border-radius: 6px;
        margin: 0 3px;
        min-width: 48px;
        text-align: center;
        font-weight: 700;
    }

    .pagination-soft .page-item.active .page-link {
        background: #b8b8b8;
        color: #111827;
    }
</style>

<h1 class="payment-title">Pencatatan Pembayaran & Tagihan</h1>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card payment-card h-100">
            <div class="card-body p-3 p-md-4">
                <div class="card-title">Rekam Pembayaran Baru</div>

                <label class="form-label mb-1">Cari Pembayaran/Pelanggan</label>
                <form action="{{ route('pembayaran.index') }}" method="GET" class="input-group mb-3 w-100 w-lg-75">
                    <input type="text" name="q" value="{{ $search }}" class="form-control search-field" placeholder="Nama pelanggan, periode, status">
                    <button class="btn search-btn" type="submit"><i class="bi bi-search"></i></button>
                </form>

                <div class="muted-line mb-2">
                    <span id="client-info">Pilih pelanggan untuk melihat paket dan total tagihan</span>
                </div>

                <form action="{{ route('pembayaran.store') }}" method="POST" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label mb-1">Pelanggan :</label>
                        <select name="id_pelanggan" id="pelanggan-select" class="form-select" required>
                            <option value="">Pilih pelanggan</option>
                            @foreach($pelangganOptions as $pelanggan)
                                <option value="{{ $pelanggan->id_pelanggan }}" data-harga="{{ $pelanggan->paket->harga ?? 0 }}" data-paket="{{ $pelanggan->paket->nama_paket ?? '-' }}" @selected((string) old('id_pelanggan') === (string) $pelanggan->id_pelanggan)>
                                    {{ $pelanggan->nama_pelanggan }} ({{ $pelanggan->username_mikrotik }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1">Periode Tagihan :</label>
                        <input type="text" name="periode_tagihan" class="form-control" value="{{ old('periode_tagihan', now()->translatedFormat('F Y')) }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Tanggal Bayar :</label>
                        <input type="date" name="tanggal_bayar" class="form-control" value="{{ old('tanggal_bayar', now()->toDateString()) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1">Jumlah Nominal Bayar (Rp):</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-2 border-dark fw-bold">Rp</span>
                            <input type="number" step="0.01" min="0" id="nominal-input" name="nominal" class="form-control fw-bold" value="{{ old('nominal') }}" required>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="muted-line">Total pemasukan bulan ini: Rp {{ number_format($rekap['bulan_ini'], 0, ',', '.') }}</div>
                        <div class="muted-line">Total seluruh pemasukan: Rp {{ number_format($rekap['total_nominal'], 0, ',', '.') }}</div>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn action-btn w-100 py-2">PROSES PEMBAYARAN</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card payment-card h-100">
            <div class="card-body p-3 p-md-4 d-flex flex-column justify-content-between">
                <div>
                    <div class="card-title">Kontrol Notifikasi & Notifikasi (WA)</div>
                    <form action="{{ route('pembayaran.send-notifications') }}" method="POST" class="mb-3">
                        @csrf
                        <button type="submit" class="btn secondary-btn w-100 py-2">KIRIM NOTIFIKASI TAGIHAN</button>
                    </form>
                    <div class="muted-line">Generate & Kirim via WhatsApp</div>
                    <div class="muted-line">Log log of status</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card payment-table mb-0">
    <div class="table-responsive">
        <table class="table mb-0 text-center align-middle">
            <thead>
                <tr>
                    <th style="width: 10%">ID Pembayaran</th>
                    <th style="width: 10%">ID Pelanggan</th>
                    <th style="width: 16%">Nama Pelanggan</th>
                    <th style="width: 10%">Periode</th>
                    <th style="width: 12%">Tanggal Bayar</th>
                    <th style="width: 12%">Nominal</th>
                    <th style="width: 12%">Status Notifikasi</th>
                    <th style="width: 18%">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pembayaran as $item)
                    <tr>
                        <td>PMB_{{ str_pad((string) $item->id_pembayaran, 3, '0', STR_PAD_LEFT) }}</td>
                        <td>{{ $item->id_pelanggan }}</td>
                        <td>{{ $item->pelanggan->nama_pelanggan ?? '-' }}</td>
                        <td>{{ $item->periode_tagihan }}</td>
                        <td>{{ optional($item->tanggal_bayar)->format('d/m/y') ?? '-' }}</td>
                        <td>{{ number_format($item->nominal, 0, ',', '.') }}</td>
                        <td>{{ $item->status_notifikasi }}</td>
                        <td>
                            <a href="{{ route('pembayaran.edit', $item) }}" class="text-dark fw-semibold text-decoration-none me-2">Edit</a>
                            <form action="{{ route('pembayaran.destroy', $item) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus data pembayaran ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-link p-0 text-danger fw-semibold text-decoration-none">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-4 text-muted">Belum ada data pembayaran.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="table-footer-bar">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-center gap-3">
            <div class="fw-bold fs-5 text-dark">Menampilkan {{ $pembayaran->firstItem() ?? 0 }} - {{ $pembayaran->lastItem() ?? 0 }} dari {{ $pembayaran->total() }} rekaman</div>
            <div>{{ $pembayaran->links() }}</div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const pelangganSelect = document.getElementById('pelanggan-select');
        const nominalInput = document.getElementById('nominal-input');
        const clientInfo = document.getElementById('client-info');

        function updateClientInfo() {
            const selectedOption = pelangganSelect.options[pelangganSelect.selectedIndex];
            if (selectedOption.value === '') {
                clientInfo.textContent = 'Pilih pelanggan untuk melihat paket dan total tagihan';
                nominalInput.value = '';
            } else {
                const namaClient = selectedOption.textContent.split('(')[0].trim();
                const harga = selectedOption.dataset.harga || '0';
                const paketNama = selectedOption.dataset.paket || '-';
                clientInfo.textContent = `Client: ${namaClient} | Paket: ${paketNama} | Total Tagihan: Rp ${new Intl.NumberFormat('id-ID').format(harga)}`;
                nominalInput.value = harga;
            }
        }

        pelangganSelect.addEventListener('change', updateClientInfo);
        updateClientInfo();
    });
</script>
@endpush