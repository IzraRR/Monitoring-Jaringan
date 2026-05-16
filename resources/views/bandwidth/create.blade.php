@extends('layouts.app')

@section('title', 'Tambah Paket Bandwidth')

@section('content')
<div class="card border-0 shadow-sm rounded-3 border border-dark mt-3">
    <div class="card-header bg-white">
        <h5 class="mb-0 fw-bold">Tambah Paket Bandwidth</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('bandwidth.store') }}" method="POST" class="row g-3">
            @csrf
            <div class="col-md-6">
                <label class="form-label">Nama Paket</label>
                <input type="text" name="nama_paket" value="{{ old('nama_paket') }}" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Limit Download</label>
                <input type="text" name="limit_download" value="{{ old('limit_download') }}" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Limit Upload</label>
                <input type="text" name="limit_upload" value="{{ old('limit_upload') }}" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Harga</label>
                <input type="number" step="0.01" min="0" name="harga" value="{{ old('harga') }}" class="form-control" required>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-dark">Simpan</button>
                <a href="{{ route('bandwidth.index') }}" class="btn btn-outline-secondary">Kembali</a>
            </div>
        </form>
    </div>
</div>
@endsection
