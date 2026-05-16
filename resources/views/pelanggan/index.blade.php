@extends('layouts.app')

@section('title', 'Kelola Pelanggan | SMKN 53 Jakarta')
@section('page_heading', 'Kelola User & Pelanggan (Hotspot/PPPoE)')

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

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-3 h-100"><div class="card-body"><p class="text-secondary mb-1">Total Pelanggan</p><h4 class="fw-bold mb-0">{{ number_format($summary['total']) }}</h4></div></div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-3 h-100"><div class="card-body"><p class="text-secondary mb-1">Status Aktif</p><h4 class="fw-bold text-success mb-0">{{ number_format($summary['aktif']) }}</h4></div></div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-3 h-100"><div class="card-body"><p class="text-secondary mb-1">Status Locked</p><h4 class="fw-bold text-warning mb-0">{{ number_format($summary['locked']) }}</h4></div></div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-3 h-100"><div class="card-body"><p class="text-secondary mb-1">Status Nonaktif</p><h4 class="fw-bold text-secondary mb-0">{{ number_format($summary['nonaktif']) }}</h4></div></div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-3 border border-dark">
    
    <div class="card-header bg-white border-bottom-0 pt-4 pb-3 d-flex justify-content-between align-items-center">
        
        <div class="input-group" style="width: 300px;">
            <form action="{{ route('pelanggan.index') }}" method="GET" class="d-flex w-100">
                <input type="text" name="q" value="{{ $search }}" class="form-control border-dark border-2" placeholder="Cari nama/username/id" style="border-radius: 8px 0 0 8px;">
                <button class="btn btn-outline-dark border-2 fw-bold" type="submit" style="border-radius: 0 8px 8px 0;">
                    <i class="bi bi-search"></i>
                </button>
            </form>
        </div>

        <div>
            <a href="{{ route('pelanggan.create') }}" class="btn fw-bold px-4 me-2" style="background-color: #e2e8f0; color: #0f172a; border: 1px solid #cbd5e1;">
                + DAFTAR PELANGGAN
            </a>
            <form action="{{ route('pelanggan.sync') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn fw-bold px-4 text-white" style="background-color: #38bdf8; border: 1px solid #0284c7;">
                    <i class="bi bi-arrow-repeat me-1"></i> SINKRONISASI KE MIKROTIK
                </button>
            </form>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0 text-center align-middle" style="border-color: #475569;">
                <thead style="background-color: #cbd5e1; color: black;">
                    <tr>
                        <th class="py-3">ID</th>
                        <th class="py-3">Nama</th>
                        <th class="py-3">Tipe<br>(Hotspot/PPPoE)</th>
                        <th class="py-3">Username MikroTik</th>
                        <th class="py-3">Paket</th>
                        <th class="py-3">Masa Aktif</th>
                        <th class="py-3">Status</th>
                        <th class="py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="fw-medium text-dark">
                    @forelse($pelanggan as $item)
                        <tr>
                            <td>{{ $item->id_pelanggan }}</td>
                            <td>{{ $item->nama_pelanggan }}</td>
                            <td>{{ strtoupper(config('services.mikrotik.sync_mode', 'hotspot')) === 'PPPOE' ? 'PPPoE' : 'Hotspot' }}</td>
                            <td>{{ $item->username_mikrotik }}</td>
                            <td>{{ $item->paket->nama_paket ?? '-' }}</td>
                            <td>{{ optional($item->masa_aktif)->format('d/m/Y') ?? '-' }}</td>
                            <td>
                                @php $status = strtolower((string) $item->status_aktif); @endphp
                                <span class="badge {{ $status === 'aktif' ? 'bg-success' : ($status === 'locked' ? 'bg-warning text-dark' : 'bg-secondary') }}">
                                    {{ $item->status_aktif }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('pelanggan.edit', $item) }}" class="text-warning me-2" title="Edit"><i class="bi bi-pencil-fill"></i></a>
                                <form action="{{ route('pelanggan.destroy', $item) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus pelanggan ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-link p-0 text-secondary" title="Hapus"><i class="bi bi-trash-fill"></i></button>
                                </form>
                                <form action="{{ route('pelanggan.toggle-lock', $item) }}" method="POST" class="d-inline" onsubmit="return confirm('Ubah status lock user ini?')">
                                    @csrf
                                    <button type="submit" class="btn btn-link p-0 text-warning ms-2" title="Kunci/Buka Kunci"><i class="bi bi-lock-fill"></i></button>
                                </form>
                                <form action="{{ route('pelanggan.disconnect', $item) }}" method="POST" class="d-inline" onsubmit="return confirm('Nonaktifkan dan putus sesi aktif user ini?')">
                                    @csrf
                                    <button type="submit" class="btn btn-link p-0 text-dark ms-2" title="Nonaktifkan"><i class="bi bi-x-lg fw-bold"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-4 text-muted">Belum ada data pelanggan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3 border-top-0">
        <span class="fw-bold fs-6">Menampilkan {{ $pelanggan->firstItem() ?? 0 }} - {{ $pelanggan->lastItem() ?? 0 }} dari {{ $pelanggan->total() }} rekaman</span>
        <div>{{ $pelanggan->links() }}</div>
    </div>
</div>
@endsection