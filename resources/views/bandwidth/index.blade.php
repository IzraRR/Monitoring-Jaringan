@extends('layouts.app')

@section('title', 'Manajemen Bandwidth | SMKN 53')
@section('page_heading', 'Manajemen Bandwidth (Queue)')

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
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-3 h-100"><div class="card-body"><p class="text-secondary mb-1">Total Profil</p><h4 class="fw-bold mb-0">{{ number_format($summary['total_paket']) }}</h4></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-3 h-100"><div class="card-body"><p class="text-secondary mb-1">Pelanggan Aktif</p><h4 class="fw-bold text-success mb-0">{{ number_format($summary['total_pelanggan_aktif']) }}</h4></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-3 h-100"><div class="card-body"><p class="text-secondary mb-1">Pelanggan Locked</p><h4 class="fw-bold text-warning mb-0">{{ number_format($summary['total_pelanggan_locked']) }}</h4></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-3 h-100"><div class="card-body"><p class="text-secondary mb-1">Rata-rata Harga Paket</p><h4 class="fw-bold mb-0">Rp {{ number_format($summary['rata_harga'], 0, ',', '.') }}</h4></div></div></div>
</div>

<div class="card border-0 shadow-sm rounded-3 border border-dark">
    <div class="card-header bg-white border-bottom-0 pt-4 pb-3 d-flex justify-content-between align-items-center">
        <a href="{{ route('bandwidth.create') }}" class="btn fw-bold px-4" style="background-color: #e2e8f0; color: #0f172a; border: 1px solid #cbd5e1;">+ TAMBAH PROFIL / QUEUE</a>
        <div class="input-group" style="width: 300px;">
            <form action="{{ route('bandwidth.index') }}" method="GET" class="d-flex w-100">
                <input type="text" name="q" value="{{ $search }}" class="form-control border-dark border-2" placeholder="Cari paket" style="border-radius: 8px 0 0 8px;">
                <button class="btn btn-outline-dark border-2 fw-bold" type="submit" style="border-radius: 0 8px 8px 0;"><i class="bi bi-search"></i></button>
            </form>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0 text-center align-middle" style="border-color: #475569;">
                <thead style="background-color: #cbd5e1; color: black;">
                    <tr>
                        <th class="py-3">ID</th><th class="py-3">Nama Profil</th><th class="py-3">Tipe</th><th class="py-3">Limit Download</th><th class="py-3">Limit Upload</th><th class="py-3">Status</th><th class="py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="fw-medium text-dark">
                    @forelse($paket as $item)
                        <tr>
                            <td>{{ $item->id_paket }}</td>
                            <td>{{ $item->nama_paket }}</td>
                            <td>{{ strtoupper(config('services.mikrotik.sync_mode', 'hotspot')) === 'PPPOE' ? 'PPPoE' : 'Hotspot' }}</td>
                            <td>{{ $item->limit_download }}</td>
                            <td>{{ $item->limit_upload }}</td>
                            <td>
                                @if($item->pelanggan_aktif_count > 0)
                                    <span class="badge bg-success">Aktif</span>
                                @elseif($item->pelanggan_count > 0)
                                    <span class="badge bg-warning text-dark">Locked/Nonaktif</span>
                                @else
                                    <span class="badge bg-secondary">Kosong</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('bandwidth.edit', $item) }}" class="text-warning me-2" title="Edit"><i class="bi bi-pencil-fill"></i></a>
                                <form action="{{ route('bandwidth.destroy', $item) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus paket bandwidth ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-link p-0 text-secondary" title="Hapus"><i class="bi bi-trash-fill"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-4 text-muted">Belum ada data paket bandwidth.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white py-3 border-top-0 d-flex justify-content-between align-items-center">
        <span class="fw-bold">Menampilkan {{ $paket->firstItem() ?? 0 }} - {{ $paket->lastItem() ?? 0 }} dari {{ $paket->total() }} rekaman</span>
        <div>{{ $paket->links() }}</div>
    </div>
</div>
@endsection