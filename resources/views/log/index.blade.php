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
                <input type="text" id="log-q" name="q" value="{{ $search }}" class="form-control border-dark border-2" placeholder="Cari berdasarkan Pelanggan/IP" style="border-radius: 8px 0 0 8px;" autocomplete="off">
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
            <button type="button" class="btn btn-outline-danger border-2 fw-bold px-3" data-bs-toggle="modal" data-bs-target="#anomalySettingsModal" style="border-radius: 8px;">
                <i class="bi bi-gear-fill me-1"></i> SET ANOMALI
            </button>
        </div>
    </div>
    <div id="log-table-container">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 text-center align-middle" style="border-color: #475569;">
                    <thead style="background-color: #cbd5e1; color: black;">
                        <tr>
                            <th>Waktu Login</th>
                            <th>Pelanggan</th>
                            <th>IP Address</th>
                            <th>Tipe Akses</th>
                            <th>Durasi / Trafik</th>
                            <th>Status</th>
                            <th>Aksi</th>
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
                                    $isCurrentlyActiveSession = is_null($log->waktu_selesai) && $liveIp !== 'Offline';
                                @endphp
                                @if($isCurrentlyActiveSession)
                                    <span class="badge bg-info text-dark">{{ $liveIp }}</span>
                                @elseif($log->ip_address)
                                    <span class="text-secondary">{{ $log->ip_address }}</span>
                                @else
                                    <span class="text-muted small">Offline</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $namaPaket = optional($log->paket)->nama_paket ?? optional(optional($log->pelanggan)->paket)->nama_paket ?? '';
                                    $isPppoe = \Illuminate\Support\Str::contains(strtolower($namaPaket), 'pppoe');
                                    $tipeKoneksi = $isPppoe ? 'PPPoE' : 'Hotspot';

                                    // Tipe Akses berdasarkan session state & status pelanggan
                                    $statusAkses = 'Logout';
                                    $statusColor = 'bg-secondary';

                                    if (is_null($log->waktu_selesai) && $liveIp !== 'Offline') {
                                        $statusAkses = 'Login';
                                        $statusColor = 'bg-success';
                                    }

                                    if (optional($log->pelanggan)->status_aktif === 'nonaktif') {
                                        $statusAkses = 'Blocked';
                                        $statusColor = 'bg-danger';
                                    } elseif (\Illuminate\Support\Str::contains(strtolower($namaPaket), 'cbt') || \Illuminate\Support\Str::contains(strtolower($namaPaket), 'ujian')) {
                                        $statusAkses = 'CBT_Ujian';
                                        $statusColor = 'bg-warning text-dark';
                                    }
                                @endphp
                                <span class="badge {{ $statusColor }}">{{ $statusAkses }}</span>
                                <br>
                                <span class="badge {{ $isPppoe ? 'bg-info text-dark' : 'bg-primary' }} mt-1">
                                    {{ $tipeKoneksi }}
                                </span>
                                @if($namaPaket)
                                    <br><small class="text-muted">{{ $namaPaket }}</small>
                                @endif
                            </td>
                            <td>
                                @if(is_null($log->waktu_selesai) && $liveIp !== 'Offline')
                                    <span class="badge bg-warning text-dark"><i class="spinner-border spinner-border-sm me-1" role="status" style="width: 10px; height: 10px; border-width: 2px;"></i> [Wait]</span>
                                @else
                                    {{ $log->durasi_formatted }}
                                @endif
                                <br>
                                <small class="text-muted">{{ number_format($log->data_usage_mb, 2) }} MB</small>
                            </td>
                            <td>
                                @if($log->is_anomali)
                                    <span class="text-danger fw-bold"><i class="bi bi-x-circle-fill me-1"></i> Anomali</span>
                                @else
                                    <span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i> Normal</span>
                                @endif
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-dark btn-log-detail" 
                                        data-id="{{ $log->id_log }}"
                                        data-nama="{{ optional($log->pelanggan)->nama_pelanggan ?? '-' }}"
                                        data-username="{{ optional($log->pelanggan)->username_mikrotik ?? '-' }}"
                                        data-ip="{{ $liveIp !== 'Offline' ? $liveIp : ($log->ip_address ?? '-') }}"
                                        data-tipe="{{ $isPppoe ? 'PPPoE' : 'Hotspot' }}"
                                        data-paket="{{ $namaPaket ?: '-' }}"
                                        data-mulai="{{ $log->waktu_mulai->format('d M Y, H:i:s') }}"
                                        data-selesai="{{ $log->waktu_selesai ? $log->waktu_selesai->format('d M Y, H:i:s') : ($liveIp === 'Offline' ? 'Sesi Terputus (Menunggu Sync)' : 'Masih Aktif (Running)') }}"
                                        data-durasi="{{ $log->durasi_formatted }}"
                                        data-usage="{{ number_format($log->data_usage_mb, 2) }} MB"
                                        data-status="{{ $log->is_anomali ? 'Anomali' : 'Normal' }}"
                                        title="Detail Log">
                                    <i class="bi bi-file-earmark-text"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center py-3 text-muted">Belum ada aktivitas pemakaian pelanggan.</td></tr>
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

<!-- Modal Pengaturan Anomali -->
<div class="modal fade" id="anomalySettingsModal" tabindex="-1" aria-labelledby="anomalySettingsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow" style="border-radius: 12px; overflow: hidden;">
      <div class="modal-header text-white" style="background-color: #17395f;">
        <h5 class="modal-title fw-bold" id="anomalySettingsModalLabel"><i class="bi bi-gear-fill me-2"></i>Pengaturan Batas Anomali</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="{{ route('log.update-threshold') }}" method="POST">
        @csrf
        <div class="modal-body p-4 text-start">
          <p class="text-secondary small mb-3">
            Sesi pemakaian data pelanggan yang melebihi batas ini saat sinkronisasi otomatis akan ditandai dengan status <span class="badge bg-danger">Gagal</span> (Anomali).
          </p>
          <div class="mb-3">
            <label for="batas_anomali_mb" class="form-label fw-bold text-dark">Batas Volume Data</label>
            <div class="input-group">
              <input type="number" name="batas_anomali_mb" id="batas_anomali_mb" class="form-control border-dark border-2 fw-semibold" value="{{ $batasAnomali }}" min="1" required style="border-radius: 8px 0 0 8px;">
              <span class="input-group-text border-dark border-2 bg-light fw-bold" style="border-radius: 0 8px 8px 0;">MB</span>
            </div>
            <small class="text-muted mt-1 d-block">Default: 1000 MB (1 GB)</small>
          </div>
        </div>
        <div class="modal-footer bg-light border-0">
          <button type="button" class="btn btn-secondary fw-semibold" data-bs-dismiss="modal" style="border-radius: 8px;">Batal</button>
          <button type="submit" class="btn text-white fw-bold px-4" style="background-color: #17395f; border-radius: 8px;">Simpan Perubahan</button>
        </div>
      </form>
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

        // Event listener detail button
        if (logTableContainer) {
            logTableContainer.addEventListener('click', function(e) {
                const btn = e.target.closest('.btn-log-detail');
                if (!btn) return;
                
                const d = btn.dataset;
                const statusHtml = d.status === 'Anomali' 
                    ? '<span class="badge bg-danger">Anomali</span>' 
                    : '<span class="badge bg-success">Normal</span>';

                Swal.fire({
                    title: '<strong>Detail Aktivitas Jaringan</strong>',
                    icon: 'info',
                    html: `
                        <div class="text-start">
                            <table class="table table-sm table-bordered mt-2" style="font-size: 0.95rem;">
                                <tbody>
                                    <tr><th style="width: 35%; background-color: #f1f5f9;">ID Sesi Log</th><td>#${d.id}</td></tr>
                                    <tr><th style="background-color: #f1f5f9;">Nama Pelanggan</th><td><strong>${d.nama}</strong> (${d.username})</td></tr>
                                    <tr><th style="background-color: #f1f5f9;">IP Address</th><td><span class="badge bg-dark">${d.ip}</span></td></tr>
                                    <tr><th style="background-color: #f1f5f9;">Tipe Akses</th><td><span class="badge ${d.tipe === 'PPPoE' ? 'bg-info text-dark' : 'bg-primary'}">${d.tipe}</span></td></tr>
                                    <tr><th style="background-color: #f1f5f9;">Paket Bandwidth</th><td>${d.paket}</td></tr>
                                    <tr><th style="background-color: #f1f5f9;">Waktu Mulai</th><td>${d.mulai}</td></tr>
                                    <tr><th style="background-color: #f1f5f9;">Waktu Selesai</th><td>${d.selesai}</td></tr>
                                    <tr><th style="background-color: #f1f5f9;">Durasi Koneksi</th><td>${d.durasi}</td></tr>
                                    <tr><th style="background-color: #f1f5f9;">Volume Data</th><td>${d.usage}</td></tr>
                                    <tr><th style="background-color: #f1f5f9;">Status</th><td>${statusHtml}</td></tr>
                                </tbody>
                            </table>
                        </div>
                    `,
                    showCloseButton: true,
                    confirmButtonColor: '#17395f',
                    confirmButtonText: 'Tutup'
                });
            });
        }
    });
</script>
@endpush
@endsection