@extends('layouts.app')

@section('title', 'Edit Pelanggan')

@section('content')
<div class="card border-0 shadow-sm rounded-3 border border-dark mt-3">
    <div class="card-header bg-white">
        <h5 class="mb-0 fw-bold">Edit Pelanggan</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('pelanggan.update', $pelanggan) }}" method="POST" class="row g-3">
            @csrf
            @method('PUT')
            <div class="col-md-6">
                <label class="form-label">Nama Pelanggan</label>
                <input type="text" name="nama_pelanggan" value="{{ old('nama_pelanggan', $pelanggan->nama_pelanggan) }}" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">No HP</label>
                <input type="text" name="no_hp" value="{{ old('no_hp', $pelanggan->no_hp) }}" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Username MikroTik</label>
                <input type="text" name="username_mikrotik" value="{{ old('username_mikrotik', $pelanggan->username_mikrotik) }}" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Password MikroTik</label>
                @include('partials.password-input', [
                    'id' => 'password-mikrotik-edit',
                    'name' => 'password_mikrotik',
                    'value' => old('password_mikrotik'),
                    'placeholder' => 'Kosongkan jika tidak ingin mengubah',
                    'required' => false,
                ])
                <small class="text-muted">Biarkan kosong untuk mempertahankan password saat ini.</small>
            </div>
            <div class="col-md-6">
                <label class="form-label">Paket</label>
                <select name="id_paket" class="form-select" required>
                    <option value="">Pilih Paket</option>
                    @foreach($paketOptions as $paket)
                        <option value="{{ $paket->id_paket }}" @selected(old('id_paket', $pelanggan->id_paket) == $paket->id_paket)>{{ $paket->nama_paket }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Masa Aktif</label>
                <input type="date" name="masa_aktif" value="{{ old('masa_aktif', optional($pelanggan->masa_aktif)->format('Y-m-d')) }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status_aktif" class="form-select" required>
                    <option value="Aktif" @selected(old('status_aktif', $pelanggan->status_aktif) === 'Aktif')>Aktif</option>
                    <option value="Nonaktif" @selected(old('status_aktif', $pelanggan->status_aktif) === 'Nonaktif')>Nonaktif</option>
                    <option value="Locked" @selected(old('status_aktif', $pelanggan->status_aktif) === 'Locked')>Locked</option>
                </select>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-dark">Perbarui</button>
                <a href="{{ route('pelanggan.index') }}" class="btn btn-outline-secondary">Kembali</a>
            </div>
        </form>
    </div>
</div>
@endsection
