@extends('layouts.app')

@section('title', 'Log Aktivitas | SMKN 53')
@section('page_heading', 'Log Aktivitas Jaringan (Intra-Net)')

@section('content')
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-secondary small">Total Log Hari Ini</div>
                <div class="fs-4 fw-bold">{{ number_format($summary['total_hari_ini'] ?? 0) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-secondary small">Anomali Hari Ini</div>
                <div class="fs-4 fw-bold text-warning">{{ number_format($summary['anomali_hari_ini'] ?? 0) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-secondary small">Sedang Berjalan</div>
                <div class="fs-4 fw-bold text-info">{{ number_format($summary['sedang_berjalan'] ?? 0) }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-3 border border-dark mb-4 mt-3">
    <div class="card-header bg-white border-bottom-0 pt-4 pb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex flex-column" style="width: 250px;">
            <form id="log-search-form" action="{{ route('log.index') }}" method="GET" class="d-flex w-100">
                <input type="text" id="log-q" name="q" value="{{ $search }}" class="form-control border-dark border-2" placeholder="Cari pelanggan" style="border-radius: 8px 0 0 8px;" autocomplete="off">
                <button class="btn btn-outline-dark border-2 fw-bold" type="submit" style="border-radius: 0 8px 8px 0;"><i class="bi bi-search"></i></button>
            </form>
            <small id="log-search-indicator" class="text-muted mt-1 d-none"><span class="spinner-border spinner-border-sm me-1" role="status" style="width: 12px; height: 12px;"></span>Mencari...</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <form id="log-date-form" action="{{ route('log.index') }}" method="GET" class="d-flex align-items-center gap-2">
                <input type="hidden" id="log-q-hidden" name="q" value="{{ $search }}">
                <label class="fw-bold fs-5 mb-0" style="color: #0f172a;">Rentang Waktu :</label>
                <input type="date" id="log-start-date" name="start_date" value="{{ $startDate }}" class="form-control border-secondary fw-medium" style="width: 165px; background-color: #cbd5e1;">
                <i class="bi bi-arrow-right fw-bold fs-5"></i>
                <input type="date" id="log-end-date" name="end_date" value="{{ $endDate }}" class="form-control border-secondary fw-medium" style="width: 165px; background-color: #e2e8f0;">
                <button class="btn fw-bold px-3 ms-2" type="submit" style="background-color: #e2e8f0; color: #0f172a; border: 1px solid #cbd5e1;">TAMPILKAN LOG</button>
                <a href="{{ route('log.index') }}" id="log-reset-btn" class="btn fw-bold px-3" style="background-color: #fff; color: #0f172a; border: 1px solid #cbd5e1;">RESET</a>
            </form>
        </div>
    </div>
    <div id="log-table-container">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 text-center align-middle" style="border-color: #475569;">
                    <thead style="background-color: #cbd5e1; color: black;">
                        <tr>
                            <th>Timestamp</th>
                            <th>Pelanggan</th>
                            <th>IP Address</th>
                            <th>Tipe Akses</th>
                            <th>Durasi / Trafik</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody class="fw-medium text-dark">
                        @forelse($logAktivitas as $log)
                        <tr>
                            <td>{{ $log->waktu_mulai->format('d M Y, H:i') }}</td>
                            <td>{{ optional($log->pelanggan)->nama_pelanggan ?? '-' }}</td>
                            <td>
                                @php
                                    $uname = optional($log->pelanggan)->username_mikrotik;
                                    $liveIp = $uname && isset($activeIps[$uname]) ? $activeIps[$uname] : 'Offline';
                                @endphp
                                @if($liveIp === 'Offline')
                                    <span class="text-muted small">Offline</span>
                                @else
                                    <span class="badge bg-info text-dark">{{ $liveIp }}</span>
                                @endif
                            </td>
                            <td><span class="badge bg-secondary">{{ optional(optional($log->pelanggan)->paket)->nama_paket ?? '-' }}</span></td>
                            <td>{{ $log->durasi_menit ?? 0 }} Menit <br> <small class="text-muted">{{ number_format($log->data_usage_mb, 2) }} MB</small></td>
                            <td>
                                @if($log->is_anomali)
                                    <span class="badge bg-danger">Anomali</span>
                                @else
                                    <span class="badge bg-success">Normal</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center py-3 text-muted">Belum ada aktivitas pemakaian pelanggan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white py-3 border-top-0 d-flex justify-content-between align-items-center">
            <span class="fw-bold">Menampilkan {{ $logAktivitas->firstItem() ?? 0 }} - {{ $logAktivitas->lastItem() ?? 0 }} dari {{ $logAktivitas->total() }} rekaman</span>
            <div>{{ $logAktivitas->links() }}</div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchForm = document.getElementById('log-search-form');
        const searchInput = document.getElementById('log-q');
        const searchIndicator = document.getElementById('log-search-indicator');
        const logTableContainer = document.getElementById('log-table-container');
        
        const dateForm = document.getElementById('log-date-form');
        const hiddenQInput = document.getElementById('log-q-hidden');
        const startDateInput = document.getElementById('log-start-date');
        const endDateInput = document.getElementById('log-end-date');
        const resetBtn = document.getElementById('log-reset-btn');

        function setSearchIndicatorLoading(isLoading) {
            if (searchIndicator) {
                searchIndicator.classList.toggle('d-none', !isLoading);
            }
        }

        function getFilteredUrl(actionUrl) {
            const url = new URL(actionUrl || searchForm.action, window.location.origin);
            
            const qVal = (searchInput?.value || '').trim();
            if (qVal) {
                url.searchParams.set('q', qVal);
            } else {
                url.searchParams.delete('q');
            }

            const startDateVal = startDateInput?.value || '';
            if (startDateVal) {
                url.searchParams.set('start_date', startDateVal);
            } else {
                url.searchParams.delete('start_date');
            }

            const endDateVal = endDateInput?.value || '';
            if (endDateVal) {
                url.searchParams.set('end_date', endDateVal);
            } else {
                url.searchParams.delete('end_date');
            }

            return url;
        }

        async function refreshLogTableByUrl(url) {
            if (!logTableContainer) return;

            logTableContainer.classList.add('opacity-50');
            setSearchIndicatorLoading(true);

            try {
                const response = await fetch(url.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const html = await response.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const nextTableContainer = doc.getElementById('log-table-container');

                if (nextTableContainer) {
                    logTableContainer.innerHTML = nextTableContainer.innerHTML;
                    window.history.replaceState({}, '', url.pathname + url.search);
                }
            } catch (error) {
                console.error('Gagal memuat data log:', error);
            } finally {
                logTableContainer.classList.remove('opacity-50');
                setSearchIndicatorLoading(false);
            }
        }

        if (logTableContainer) {
            // Pagination AJAX clicks
            logTableContainer.addEventListener('click', function (event) {
                const paginationLink = event.target.closest('.pagination a');
                if (!paginationLink) return;

                event.preventDefault();

                const url = getFilteredUrl(paginationLink.href);
                refreshLogTableByUrl(url);
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
                    
                    if (hiddenQInput) {
                        hiddenQInput.value = currentValue;
                    }

                    const url = getFilteredUrl();
                    url.searchParams.delete('page');
                    refreshLogTableByUrl(url);
                }, 350);
            });
        }

        // Intercept date form submit
        if (dateForm) {
            dateForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const url = getFilteredUrl(dateForm.action);
                url.searchParams.delete('page');
                refreshLogTableByUrl(url);
            });
        }

        // Intercept reset button click
        if (resetBtn) {
            resetBtn.addEventListener('click', function (e) {
                e.preventDefault();
                
                if (searchInput) searchInput.value = '';
                if (hiddenQInput) hiddenQInput.value = '';
                if (startDateInput) startDateInput.value = '';
                if (endDateInput) endDateInput.value = '';
                
                const url = new URL(resetBtn.href, window.location.origin);
                refreshLogTableByUrl(url);
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