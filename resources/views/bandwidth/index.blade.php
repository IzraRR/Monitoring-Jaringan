@extends('layouts.app')

@section('title', 'Manajemen Bandwidth | SMKN 53')
@section('page_heading', 'Manajemen Bandwidth (Queue)')

@section('content')
<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-3 h-100"><div class="card-body"><p class="text-secondary mb-1">Total Profil</p><h4 class="fw-bold mb-0">{{ number_format($summary['total_paket']) }}</h4></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-3 h-100"><div class="card-body"><p class="text-secondary mb-1">Pelanggan Aktif</p><h4 class="fw-bold text-success mb-0">{{ number_format($summary['total_pelanggan_aktif']) }}</h4></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-3 h-100"><div class="card-body"><p class="text-secondary mb-1">Pelanggan Locked</p><h4 class="fw-bold text-warning mb-0">{{ number_format($summary['total_pelanggan_locked']) }}</h4></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-3 h-100"><div class="card-body"><p class="text-secondary mb-1">Rata-rata Harga Paket</p><h4 class="fw-bold mb-0">Rp {{ number_format($summary['rata_harga'], 0, ',', '.') }}</h4></div></div></div>
</div>

<div class="card border-0 shadow-sm rounded-3 border border-dark">
    <div class="card-header bg-white border-bottom-0 pt-4 pb-3 d-flex justify-content-between align-items-center">
        <a href="{{ route('bandwidth.create') }}" class="btn fw-bold px-4" style="background-color: #e2e8f0; color: #0f172a; border: 1px solid #cbd5e1;">+ TAMBAH PROFIL / QUEUE</a>
        <div class="d-flex flex-column" style="width: 300px;">
            <form id="bandwidth-search-form" action="{{ route('bandwidth.index') }}" method="GET" class="d-flex w-100">
                <input type="text" id="bandwidth-q" name="q" value="{{ $search }}" class="form-control border-dark border-2" placeholder="Cari paket" style="border-radius: 8px 0 0 8px;" autocomplete="off">
                <button class="btn btn-outline-dark border-2 fw-bold" type="submit" style="border-radius: 0 8px 8px 0;"><i class="bi bi-search"></i></button>
            </form>
            <small id="bandwidth-search-indicator" class="text-muted mt-1 d-none"><span class="spinner-border spinner-border-sm me-1" role="status" style="width: 12px; height: 12px;"></span>Mencari...</small>
        </div>
    </div>
    <div id="bandwidth-table-container">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 text-center align-middle" style="border-color: #475569;">
                    <thead style="background-color: #cbd5e1; color: black;">
                        <tr>
                            <th class="py-3">ID</th><th class="py-3">Nama Profil</th><th class="py-3">Tipe</th><th class="py-3">Limit Download</th><th class="py-3">Limit Upload</th><th class="py-3">Harga</th><th class="py-3">Status</th><th class="py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="fw-medium text-dark">
                        @forelse($paket as $item)
                            <tr>
                                <td>{{ $item->id_paket }}</td>
                                <td>{{ $item->nama_paket }}</td>
                                <td>
                                    <span class="badge {{ \Illuminate\Support\Str::contains(strtolower($item->nama_paket), 'pppoe') ? 'bg-info' : 'bg-primary' }}">
                                        {{ \Illuminate\Support\Str::contains(strtolower($item->nama_paket), 'pppoe') ? 'PPPoE' : 'Hotspot' }}
                                    </span>
                                </td>
                                <td>{{ $item->limit_download }}</td>
                                <td>{{ $item->limit_upload }}</td>
                                <td>Rp {{ number_format($item->harga, 0, ',', '.') }}</td>
                                <td>
                                    @if($item->pelanggan_aktif_count > 0)
                                        <span class="badge bg-success">{{ $item->pelanggan_aktif_count }} Aktif</span>
                                    @elseif($item->pelanggan_count > 0)
                                        <span class="badge bg-warning text-dark">{{ $item->pelanggan_count }} Locked/Nonaktif</span>
                                    @else
                                        <span class="badge bg-secondary">Kosong</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('bandwidth.edit', $item) }}" class="btn btn-sm btn-outline-primary me-1" title="Edit Profil"><i class="bi bi-pencil-fill"></i></a>
                                    <form action="{{ route('bandwidth.destroy', $item) }}" method="POST" class="d-inline form-delete-bandwidth">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Profil">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-4 text-muted">Belum ada data paket bandwidth.</td>
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
</div>


@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchForm = document.getElementById('bandwidth-search-form');
        const searchInput = document.getElementById('bandwidth-q');
        const searchIndicator = document.getElementById('bandwidth-search-indicator');
        const bandwidthTableContainer = document.getElementById('bandwidth-table-container');

        function setSearchIndicatorLoading(isLoading) {
            if (searchIndicator) {
                searchIndicator.classList.toggle('d-none', !isLoading);
            }
        }

        async function refreshBandwidthTableByUrl(url) {
            if (!searchForm || !bandwidthTableContainer) return;

            bandwidthTableContainer.classList.add('opacity-50');
            setSearchIndicatorLoading(true);

            try {
                const response = await fetch(url.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const html = await response.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const nextTableContainer = doc.getElementById('bandwidth-table-container');

                if (nextTableContainer) {
                    bandwidthTableContainer.innerHTML = nextTableContainer.innerHTML;
                    window.history.replaceState({}, '', url.pathname + url.search);
                }
            } catch (error) {
                console.error('Gagal memuat data bandwidth:', error);
            } finally {
                bandwidthTableContainer.classList.remove('opacity-50');
                setSearchIndicatorLoading(false);
            }
        }

        async function refreshBandwidthTable(query) {
            const url = new URL(searchForm.action, window.location.origin);
            const cleanQuery = query.trim();

            if (cleanQuery !== '') {
                url.searchParams.set('q', cleanQuery);
            }
            url.searchParams.delete('page');

            await refreshBandwidthTableByUrl(url);
        }

        if (bandwidthTableContainer) {
            // Pagination AJAX clicks
            bandwidthTableContainer.addEventListener('click', function (event) {
                const paginationLink = event.target.closest('.pagination a');
                if (!paginationLink) return;

                event.preventDefault();

                const url = new URL(paginationLink.href, window.location.origin);
                const cleanQuery = (searchInput?.value || '').trim();
                if (cleanQuery !== '') {
                    url.searchParams.set('q', cleanQuery);
                }

                refreshBandwidthTableByUrl(url);
            });

            // Event delegation for delete form
            bandwidthTableContainer.addEventListener('submit', function(e) {
                const form = e.target;
                if (form.classList.contains('form-delete-bandwidth')) {
                    e.preventDefault();
                    Swal.fire({
                        title: '⚠️ PERINGATAN!',
                        html: 'Apakah Anda <strong>YAKIN</strong> ingin <strong>MENGHAPUS</strong> profil bandwidth ini?<br><br>Pelanggan yang menggunakan profil ini mungkin akan mengalami gangguan.<br><br><span class="text-danger fw-bold">Tindakan ini tidak bisa dibatalkan!</span>',
                        icon: 'warning',
                        iconColor: '#dc3545',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Ya, Hapus!',
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
                    refreshBandwidthTable(currentValue);
                }, 350);
            });
        }

        // Prevent submit on enter key press for search form
        if (searchForm) {
            searchForm.addEventListener('submit', function(e) {
                e.preventDefault();
            });
        }
    });
</script>
@endpush
@endsection