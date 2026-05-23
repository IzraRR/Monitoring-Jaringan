@extends('layouts.app')

@section('title', 'Kelola Pelanggan | SMKN 53 Jakarta')
@section('page_heading', 'Kelola User & Pelanggan (Hotspot/PPPoE)')

@section('content')
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
        <div class="d-flex flex-column" style="width: 300px;">
            <form id="pelanggan-search-form" action="{{ route('pelanggan.index') }}" method="GET" class="d-flex w-100">
                <input type="text" id="pelanggan-q" name="q" value="{{ $search }}" class="form-control border-dark border-2" placeholder="Cari nama/username/id" style="border-radius: 8px 0 0 8px;" autocomplete="off">
                <button class="btn btn-outline-dark border-2 fw-bold" type="submit" style="border-radius: 0 8px 8px 0;"><i class="bi bi-search"></i></button>
            </form>
            <small id="pelanggan-search-indicator" class="text-muted mt-1 d-none"><span class="spinner-border spinner-border-sm me-1" role="status" style="width: 12px; height: 12px;"></span>Mencari...</small>
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

    <div id="pelanggan-table-container">
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
                                    <span class="badge {{ \Illuminate\Support\Str::contains(strtolower($item->paket->nama_paket ?? ''), 'pppoe') ? 'bg-info' : 'bg-primary' }}">
                                        {{ \Illuminate\Support\Str::contains(strtolower($item->paket->nama_paket ?? ''), 'pppoe') ? 'PPPoE' : 'Hotspot' }}
                                    </span>
                                </td>
                                <td>{{ $item->username_mikrotik }}</td>
                                <td>{{ $item->paket->nama_paket ?? '-' }}</td>
                                <td>{{ optional($item->masa_aktif)->format('d/m/Y') ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ strtolower($item->status_aktif) === 'aktif' ? 'bg-success' : (strtolower($item->status_aktif) === 'locked' ? 'bg-warning text-dark' : 'bg-secondary') }}">
                                        {{ $item->status_aktif }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('pelanggan.edit', $item) }}" class="btn btn-sm btn-outline-primary me-1" title="Edit Pelanggan"><i class="bi bi-pencil-fill"></i></a>
                                    <form action="{{ route('pelanggan.destroy', $item) }}" method="POST" class="d-inline form-delete">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger me-1" title="Hapus Pelanggan"><i class="bi bi-trash-fill"></i></button>
                                    </form>
                                    <form action="{{ route('pelanggan.toggle-lock', $item) }}" method="POST" class="d-inline form-lock">
                                        @csrf
                                        @php $isLocked = strtolower((string) $item->status_aktif) === 'locked'; @endphp
                                        <button type="submit" class="btn btn-sm btn-outline-warning me-1" title="{{ $isLocked ? 'Buka Kunci Pelanggan' : 'Kunci Pelanggan' }}">
                                            <i class="bi {{ $isLocked ? 'bi-unlock-fill' : 'bi-lock-fill' }}"></i>
                                        </button>
                                    </form>
                                    <form action="{{ route('pelanggan.disconnect', $item) }}" method="POST" class="d-inline form-kick">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="Putus Sesi Pelanggan"><i class="bi bi-plug-fill"></i></button>
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
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchForm = document.getElementById('pelanggan-search-form');
        const searchInput = document.getElementById('pelanggan-q');
        const searchIndicator = document.getElementById('pelanggan-search-indicator');
        const pelangganTableContainer = document.getElementById('pelanggan-table-container');

        function setSearchIndicatorLoading(isLoading) {
            if (searchIndicator) {
                searchIndicator.classList.toggle('d-none', !isLoading);
            }
        }

        async function refreshPelangganTableByUrl(url) {
            if (!searchForm || !pelangganTableContainer) return;

            pelangganTableContainer.classList.add('opacity-50');
            setSearchIndicatorLoading(true);

            try {
                const response = await fetch(url.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const html = await response.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const nextTableContainer = doc.getElementById('pelanggan-table-container');

                if (nextTableContainer) {
                    pelangganTableContainer.innerHTML = nextTableContainer.innerHTML;
                    window.history.replaceState({}, '', url.pathname + url.search);
                }
            } catch (error) {
                console.error('Gagal memuat data pelanggan:', error);
            } finally {
                pelangganTableContainer.classList.remove('opacity-50');
                setSearchIndicatorLoading(false);
            }
        }

        async function refreshPelangganTable(query) {
            const url = new URL(searchForm.action, window.location.origin);
            const cleanQuery = query.trim();

            if (cleanQuery !== '') {
                url.searchParams.set('q', cleanQuery);
            }
            url.searchParams.delete('page');

            await refreshPelangganTableByUrl(url);
        }

        if (pelangganTableContainer) {
            // Pagination AJAX clicks
            pelangganTableContainer.addEventListener('click', function (event) {
                const paginationLink = event.target.closest('.pagination a');
                if (!paginationLink) return;

                event.preventDefault();

                const url = new URL(paginationLink.href, window.location.origin);
                const cleanQuery = (searchInput?.value || '').trim();
                if (cleanQuery !== '') {
                    url.searchParams.set('q', cleanQuery);
                }

                refreshPelangganTableByUrl(url);
            });

            // Event delegation for table action forms (delete, lock, kick)
            pelangganTableContainer.addEventListener('submit', function(e) {
                const form = e.target;
                if (form.classList.contains('form-delete')) {
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
                } else if (form.classList.contains('form-lock')) {
                    e.preventDefault();
                    const isLocked = form.querySelector('.btn').title.includes('Buka Kunci');
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
                } else if (form.classList.contains('form-kick')) {
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
                }
            });
        }

        if (searchInput) {
            let searchDebounceTimer = null;
            let lastSubmittedValue = searchInput.value;

            searchInput.addEventListener('input', function () {
                clearTimeout(searchDebounceTimer);
                setSearchIndicatorLoading(true);

                searchDebounceTimer = setTimeout(function () {
                    const currentValue = searchInput.value;
                    if (currentValue === lastSubmittedValue) {
                        setSearchIndicatorLoading(false);
                        return;
                    }

                    lastSubmittedValue = currentValue;
                    refreshPelangganTable(currentValue);
                }, 350);
            });
        }

        // Prevent submit on enter key press for search form
        if (searchForm) {
            searchForm.addEventListener('submit', function(e) {
                e.preventDefault();
            });
        }

        // Keep static form-sync listener outside the table container
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
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.showLoading();
                        form.submit();
                    }
                });
            });
        });
    });
</script>
@endpush

@endsection