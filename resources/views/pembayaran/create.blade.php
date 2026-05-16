@extends('layouts.app')

@section('title', 'Tambah Pembayaran')

@section('content')
<div class="card border-0 shadow-sm rounded-3 border border-dark mt-3">
    <div class="card-header bg-white">
        <h5 class="mb-0 fw-bold">Tambah Pembayaran</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('pembayaran.store') }}" method="POST" class="row g-3">
            @csrf
            <div class="col-md-6">
                <label class="form-label">Pelanggan</label>
                <select name="id_pelanggan" class="form-select" required>
                    <option value="">Pilih Pelanggan</option>
                    @foreach($pelangganOptions as $pelanggan)
                        <option value="{{ $pelanggan->id_pelanggan }}" @selected(old('id_pelanggan') == $pelanggan->id_pelanggan)>{{ $pelanggan->nama_pelanggan }} ({{ $pelanggan->username_mikrotik }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Tanggal Bayar</label>
                <input type="date" name="tanggal_bayar" value="{{ old('tanggal_bayar', now()->toDateString()) }}" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Nominal</label>
                <input type="number" step="0.01" min="0" name="nominal" value="{{ old('nominal') }}" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Periode Tagihan</label>
                <input type="text" name="periode_tagihan" value="{{ old('periode_tagihan', now()->translatedFormat('F Y')) }}" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Status Notifikasi</label>
                <select name="status_notifikasi" class="form-select" required>
                    <option value="Pending" @selected(old('status_notifikasi', 'Pending') === 'Pending')>Pending</option>
                    <option value="Send" @selected(old('status_notifikasi') === 'Send')>Send</option>
                    <option value="Failed" @selected(old('status_notifikasi') === 'Failed')>Failed</option>
                </select>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-dark">Simpan</button>
                <a href="{{ route('pembayaran.index') }}" class="btn btn-outline-secondary">Kembali</a>
            </div>
        </form>
    </div>
</div>
@endsection
