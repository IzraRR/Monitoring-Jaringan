@extends('layouts.app')

@section('title', 'Edit Pembayaran')

@section('content')
<div class="card border-0 shadow-sm rounded-3 border border-dark mt-3">
    <div class="card-header bg-white">
        <h5 class="mb-0 fw-bold">Edit Pembayaran</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('pembayaran.update', $pembayaran) }}" method="POST" class="row g-3">
            @csrf
            @method('PUT')
            <div class="col-md-6">
                <label class="form-label">Pelanggan</label>
                <select name="id_pelanggan" class="form-select" required>
                    <option value="">Pilih Pelanggan</option>
                    @foreach($pelangganOptions as $pelanggan)
                        @php
                            $namaPaket = $pelanggan->paket->nama_paket ?? '';
                            $tipe = \Illuminate\Support\Str::contains(strtolower($namaPaket), 'pppoe') ? 'PPPoE' : 'Hotspot';
                        @endphp
                        <option value="{{ $pelanggan->id_pelanggan }}" @selected(old('id_pelanggan', $pembayaran->id_pelanggan) == $pelanggan->id_pelanggan)>{{ $pelanggan->nama_pelanggan }} ({{ $pelanggan->username_mikrotik }} - {{ $tipe }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Tanggal Bayar</label>
                <input type="date" name="tanggal_bayar" value="{{ old('tanggal_bayar', optional($pembayaran->tanggal_bayar)->format('Y-m-d')) }}" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Nominal</label>
                <input type="number" step="0.01" min="0" name="nominal" value="{{ old('nominal', $pembayaran->nominal) }}" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Periode Tagihan</label>
                <input type="text" name="periode_tagihan" value="{{ old('periode_tagihan', $pembayaran->periode_tagihan) }}" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Status Notifikasi</label>
                <select name="status_notifikasi" class="form-select" required>
                    <option value="Pending" @selected(old('status_notifikasi', $pembayaran->status_notifikasi) === 'Pending')>Pending</option>
                    <option value="Terkirim" @selected(old('status_notifikasi', $pembayaran->status_notifikasi) === 'Terkirim')>Terkirim</option>
                    <option value="Gagal" @selected(old('status_notifikasi', $pembayaran->status_notifikasi) === 'Gagal')>Gagal</option>
                </select>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-dark">Perbarui</button>
                <a href="{{ route('pembayaran.index') }}" class="btn btn-outline-secondary">Kembali</a>
            </div>
        </form>
    </div>
</div>
@endsection