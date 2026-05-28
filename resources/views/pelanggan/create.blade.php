@extends('layouts.app')

@section('title', 'Tambah Pelanggan')

@section('content')
<div class="card border-0 shadow-sm rounded-3 border border-dark mt-3">
    <div class="card-header bg-white">
        <h5 class="mb-0 fw-bold">Tambah Pelanggan</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('pelanggan.store') }}" method="POST" class="row g-3">
            @csrf
            <div class="col-md-6">
                <label class="form-label">Nama Pelanggan</label>
                <input type="text" name="nama_pelanggan" value="{{ old('nama_pelanggan') }}" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">No HP</label>
                <input type="text" name="no_hp" value="{{ old('no_hp') }}" class="form-control" required>
            </div>
            <div class="col-md-12">
                <label class="form-label">Alamat Pelanggan</label>
                <input type="text" name="alamat" value="{{ old('alamat') }}" class="form-control" placeholder="Masukkan alamat lengkap pelanggan">
            </div>
            <div class="col-md-6">
                <label class="form-label">Username MikroTik</label>
                <input type="text" name="username_mikrotik" value="{{ old('username_mikrotik') }}" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Password MikroTik</label>
                @include('partials.password-input', [
                    'id' => 'password-mikrotik-create',
                    'name' => 'password_mikrotik',
                    'value' => old('password_mikrotik'),
                    'placeholder' => 'Password untuk user di router',
                ])
            </div>
            <div class="col-md-6">
                <label class="form-label">Paket</label>
                <select name="id_paket" class="form-select" required>
                    <option value="">Pilih Paket</option>
                    @foreach($paketOptions as $paket)
                        <option value="{{ $paket->id_paket }}" @selected(old('id_paket') == $paket->id_paket)>{{ $paket->nama_paket }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Masa Aktif</label>
                <input type="date" name="masa_aktif" value="{{ old('masa_aktif') }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status_aktif" class="form-select" required>
                    <option value="Aktif" @selected(old('status_aktif', 'Aktif') === 'Aktif')>Aktif</option>
                    <option value="Nonaktif" @selected(old('status_aktif') === 'Nonaktif')>Nonaktif</option>
                    <option value="Locked" @selected(old('status_aktif') === 'Locked')>Locked</option>
                </select>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-dark">Simpan</button>
                <a href="{{ route('pelanggan.index') }}" class="btn btn-outline-secondary">Kembali</a>
            </div>
        </form>
    </div>
</div>
@endsection
