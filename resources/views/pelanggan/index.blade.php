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
                <form action="{{ route('pelanggan.sync') }}" method="POST" class="d-inline form-sync">
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
                            <td>
                                <span class="badge {{ Str::contains(strtolower($item->paket->nama_paket ?? ''), 'pppoe') ? 'bg-info' : 'bg-primary' }}">
                                    {{ Str::contains(strtolower($item->paket->nama_paket ?? ''), 'pppoe') ? 'PPPoE' : 'Hotspot' }}
                                </span>
                            </td>
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
                                <!-- Tombol 1: Edit -->
                                <a href="{{ route('pelanggan.edit', $item) }}" class="btn btn-sm btn-outline-primary me-1" title="Edit Pelanggan">
                                    <i class="bi bi-pencil-fill"></i>
                                </a>
                                
                                <!-- Tombol 2: Hapus (Form DELETE) -->
                                <form action="{{ route('pelanggan.destroy', $item) }}" method="POST" class="d-inline form-delete">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger me-1" title="Hapus Pelanggan">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </form>
                                
                                <!-- Tombol 3: Lock/Unlock (Form POST) -->
                                <form action="{{ route('pelanggan.toggle-lock', $item) }}" method="POST" class="d-inline form-lock">
                                    @csrf
                                    @php $isLocked = strtolower((string) $item->status_aktif) === 'locked'; @endphp
                                    <button type="submit" class="btn btn-sm btn-outline-warning me-1" title="{{ $isLocked ? 'Buka Kunci Pelanggan' : 'Kunci Pelanggan' }}">
                                        <i class="bi bi-{{ $isLocked ? 'unlock-fill' : 'lock-fill' }}"></i>
                                    </button>
                                </form>
                                
                                <!-- Tombol 4: Kick/Disconnect (Form POST) -->
                                <form action="{{ route('pelanggan.disconnect', $item) }}" method="POST" class="d-inline form-kick">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Putus Sesi (Kick)">
                                        <i class="bi bi-x-circle-fill"></i>
                                    </button>
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

<!-- CDN SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // Konfirmasi Hapus dengan SweetAlert2
    document.querySelectorAll('.form-delete').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: '⚠️ PERINGATAN BUKAN MAIN!',
                html: 'Apakah Anda <strong>YAKIN</strong> ingin <strong>MENGHAPUS</strong> pelanggan ini secara <strong>PERMANEN</strong>?<br><br>Akun siswa di <strong>MikroTik</strong> juga akan ikut <strong>MUSNAH</strong>.<br><br><span class="text-danger fw-bold">Tindakan ini TIDAK bisa dibatalkan!</span>',
                icon: 'warning',
                iconColor: '#dc3545',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Hapus Sekarang!',
                cancelButtonText: 'Batalkan',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });

    // Konfirmasi Lock/Unlock dengan SweetAlert2
    document.querySelectorAll('.form-lock').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const isLocked = this.querySelector('.btn').title.includes('Buka Kunci');
            const aksiTeks = isLocked ? 'DIBUKA KUNCI' : 'DIKUNCI';
            
            Swal.fire({
                title: 'Ubah Status Pelanggan',
                html: `Apakah Anda <strong>YAKIN</strong> ingin <strong>MENGUBAH STATUS</strong> (Lock / Aktif) untuk pelanggan ini?<br><br>User akan <strong>${aksiTeks}</strong>.`,
                icon: 'question',
                iconColor: '#ffc107',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Ubah Status!',
                cancelButtonText: 'Batalkan',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });

    // Konfirmasi Kick/Disconnect dengan SweetAlert2
    document.querySelectorAll('.form-kick').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Putus Sesi Pelanggan',
                html: 'Apakah Anda <strong>YAKIN</strong> ingin <strong>MEMUTUS PAKSA (Kick)</strong> koneksi siswa ini?<br><br>Siswa akan <strong>ter-disconnect</strong> dari internet <strong>sesaat</strong>.',
                icon: 'warning',
                iconColor: '#0d6efd',
                showCancelButton: true,
                confirmButtonColor: '#0d6efd',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Kick Sekarang!',
                cancelButtonText: 'Batalkan',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });

        // Konfirmasi Sinkronisasi Massal dengan SweetAlert2
        document.querySelectorAll('.form-sync').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: '🔄 SINKRONISASI MASSAL',
                    html: 'Proses ini akan <strong>mencocokkan seluruh data</strong> database lokal dengan router <strong>MikroTik</strong>.<br><br>Waktu tunggu: beberapa detik hingga menit tergantung jumlah pelanggan.<br><br>Lanjutkan?',
                    icon: 'question',
                    iconColor: '#38bdf8',
                    showCancelButton: true,
                    confirmButtonColor: '#38bdf8',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, Sinkronisasi Sekarang!',
                    cancelButtonText: 'Batalkan',
                    reverseButtons: true,
                    didOpen: (modal) => {
                        // Store the button reference for disabling later
                        modal.dataset.confirmBtn = Swal.getConfirmButton().id;
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Disable button and show loading
                        Swal.showLoading();
                        form.submit();
                    }
                });
            });
        });
</script>
@endsection