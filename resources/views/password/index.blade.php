@extends('layouts.app')

@section('title', 'Ubah Password | SMKN 53')
@section('page_heading', 'Ubah Password Saya')

@section('content')
<div class="row justify-content-center mt-3">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-2 text-center">
                <i class="bi bi-shield-lock-fill" style="font-size: 3rem; color: #1e293b;"></i>
                <h5 class="fw-bold mt-2" style="color: #0f172a;">Form Pembaruan Sandi</h5>
                <p class="text-muted small mb-0">Minimal 8 karakter, gunakan kombinasi huruf dan angka.</p>
            </div>
            <div class="card-body px-4 pb-4">
                <form action="{{ route('password.update') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-bold">Password Lama</label>
                        @include('partials.password-input', [
                            'id' => 'password-lama',
                            'name' => 'password_lama',
                            'value' => old('password_lama'),
                            'autocomplete' => 'current-password',
                        ])
                        @error('password_lama')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    <hr class="text-secondary my-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Password Baru</label>
                        @include('partials.password-input', [
                            'id' => 'password-baru',
                            'name' => 'password_baru',
                            'value' => old('password_baru'),
                            'autocomplete' => 'new-password',
                        ])
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Konfirmasi Password Baru</label>
                        @include('partials.password-input', [
                            'id' => 'password-konfirm',
                            'name' => 'password_baru_confirmation',
                            'value' => old('password_baru_confirmation'),
                            'autocomplete' => 'new-password',
                        ])
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn fw-bold py-2 text-white" style="background-color: #0f172a;">
                            SIMPAN PASSWORD BARU
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
