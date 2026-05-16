@extends('layouts.app')

@section('title', 'Ubah Password | SMKN 53')
@section('page_heading', 'Ubah Password Saya')

@section('content')
<div class="row justify-content-center mt-5">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-3 border border-dark">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-2 text-center">
                <i class="bi bi-shield-lock-fill" style="font-size: 3rem; color: #1e293b;"></i>
                <h5 class="fw-bold mt-2" style="color: #0f172a;">Form Pembaruan Sandi</h5>
            </div>
            <div class="card-body px-4 pb-4">
                <form action="#" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-bold" style="color: #1e293b;">Password Lama</label>
                        <input type="password" name="pass_lama" class="form-control border-secondary bg-light" required>
                    </div>
                    <hr class="text-secondary my-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold" style="color: #1e293b;">Password Baru</label>
                        <input type="password" name="pass_baru" class="form-control border-dark" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold" style="color: #1e293b;">Konfirmasi Password Baru</label>
                        <input type="password" name="pass_konfirm" class="form-control border-dark" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn fw-bold py-2 text-white shadow-sm" style="background-color: #0f172a;">SIMPAN PASSWORD BARU</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection