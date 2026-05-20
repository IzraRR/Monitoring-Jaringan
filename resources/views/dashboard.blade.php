@extends('layouts.app')

@section('title', 'Dashboard | SMKN 53 Jakarta')
@section('page_heading', 'Dashboard | Monitoring Bandwidth Real-Time')

@section('content')
@php
    $mikrotik = $mikrotik ?? [
        'host' => config('services.mikrotik.host', '-'),
        'identity' => null,
        'uptime' => null,
        'cpu_load' => null,
    ];
    $realtimeStats = $realtimeStats ?? [
        'status' => 'offline',
        'connected' => false,
        'identity' => null,
        'uptime' => 'Offline',
        'cpu_load' => null,
        'hotspot_active' => 0,
        'pppoe_active' => 0,
        'interface_name' => '-',
        'rx_bps' => 0,
        'tx_bps' => 0,
    ];
@endphp
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-3 bg-white h-100">
            <div class="card-body">
                <p class="text-secondary mb-1">Pelanggan Aktif</p>
                <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['pelanggan_aktif']) }}</h3>
                <small class="text-muted">dari {{ number_format($stats['total_pelanggan']) }} pelanggan</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-3 bg-white h-100">
            <div class="card-body">
                <p class="text-secondary mb-1">Log Hari Ini</p>
                <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['log_hari_ini']) }}</h3>
                <small class="text-muted">aktivitas tercatat</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-3 bg-white h-100">
            <div class="card-body">
                <p class="text-secondary mb-1">Pemasukan Bulan Ini</p>
                <h3 class="fw-bold text-success mb-0">Rp {{ number_format($stats['pemasukan_bulan_ini'], 0, ',', '.') }}</h3>
                <small class="text-muted">transaksi {{ now()->translatedFormat('F Y') }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-3 bg-white h-100">
            <div class="card-body">
                <p class="text-secondary mb-1">Total Paket Bandwidth</p>
                <h3 class="fw-bold text-dark mb-0">{{ number_format($stats['total_paket']) }}</h3>
                <small class="text-muted">profil tersedia</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-9 mb-4">
        <div class="card border-0 shadow-sm rounded-3" style="background-color: #e2e8f0;">
            <div class="card-body">
                <h6 class="fw-bold text-secondary mb-3">Traffic Interface Real-Time (RX/TX)</h6>
                <div style="height: 300px;">
                    <canvas id="trafficChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card border-0 shadow-sm rounded-3 bg-white h-100">
            <div class="card-body">
                <h6 class="fw-bold text-dark mb-4">Analisis & Peringatan<br>(Alert)</h6>
                
                <form>
                    <label class="form-label text-secondary mb-1">Threshold Penuh :</label>
                    <input type="number" class="form-control bg-dark text-white text-center fw-bold fs-4 mb-3" value="100" style="border-radius: 8px;" disabled>
                    
                    <div class="d-flex gap-2 mb-4">
                        <button type="button" id="btn-set-threshold" class="btn btn-sm btn-outline-danger flex-grow-1">
                            <i class="bi bi-speedometer2"></i> Set Alert
                        </button>
                        <span id="current-threshold-info" class="badge bg-secondary align-self-center small">Off</span>
                    </div>
                </form>

                <p class="text-secondary mb-1 mt-2">Status Peringatan:</p>
                <div class="d-flex align-items-center">
                    <i class="bi bi-circle-fill {{ $anomaliHariIni > 0 ? 'text-danger' : 'text-success' }} fs-5 me-2"></i>
                    <span class="fw-bold text-dark">{{ $anomaliHariIni > 0 ? 'ANOMALI TERDETEKSI' : 'NORMAL' }}</span>
                </div>
                <small class="text-muted">{{ $anomaliHariIni }} alert hari ini</small>

                <hr>

                <p class="text-secondary mb-1">Status Koneksi MikroTik</p>
                <div class="d-flex align-items-center mb-2">
                    <i id="mikrotik-status-dot" class="bi bi-circle-fill {{ $realtimeStats['connected'] ? 'text-success' : 'text-danger' }} fs-6 me-2"></i>
                    <span id="mikrotik-status-text" class="fw-bold text-dark">{{ $realtimeStats['connected'] ? 'TERHUBUNG' : 'OFFLINE' }}</span>
                </div>
                <small class="text-muted d-block">Host: {{ $mikrotik['host'] !== '' ? $mikrotik['host'] : '-' }}</small>
                <small class="text-muted d-block">Identity: <span id="mikrotik-identity">{{ $realtimeStats['identity'] ?? $mikrotik['identity'] ?? '-' }}</span></small>
                <small class="text-muted d-block">Uptime: <span id="mikrotik-uptime">{{ $realtimeStats['uptime'] ?? $mikrotik['uptime'] ?? 'Offline' }}</span></small>
                <small class="text-muted d-block mb-2">CPU Load: <span id="mikrotik-cpu-load">{{ $realtimeStats['cpu_load'] !== null ? $realtimeStats['cpu_load'] . '%' : ($mikrotik['cpu_load'] !== null ? $mikrotik['cpu_load'] . '%' : '-') }}</span></small>
                <small class="text-muted d-block">Hotspot Aktif: <span id="mikrotik-hotspot-active">{{ number_format($realtimeStats['hotspot_active'] ?? 0) }}</span></small>
                <small class="text-muted d-block">PPPoE Aktif: <span id="mikrotik-pppoe-active">{{ number_format($realtimeStats['pppoe_active'] ?? 0) }}</span></small>
                <small class="text-muted d-block mb-2">Interface: <span id="mikrotik-interface-name">{{ $realtimeStats['interface_name'] ?? '-' }}</span></small>
                <small id="mikrotik-offline-message" class="text-danger d-block {{ ($realtimeStats['status'] ?? '') === 'offline' ? '' : 'd-none' }}">Koneksi ke Router terputus.</small>

                <hr>

                <p class="text-secondary mb-2">Pembayaran Terbaru</p>
                @forelse($recentPayments as $item)
                    <div class="small mb-2">
                        <strong>{{ $item->pelanggan->nama_pelanggan ?? '-' }}</strong><br>
                        Rp {{ number_format($item->nominal, 0, ',', '.') }} - {{ optional($item->tanggal_bayar)->format('d/m/Y') }}
                    </div>
                @empty
                    <small class="text-muted">Belum ada data pembayaran.</small>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const realtimeEndpoint = @json(route('dashboard.realtime-stats'));

    const ctx = document.getElementById('trafficChart').getContext('2d');
    const trafficChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                {
                    label: 'RX (Mbps)',
                    borderColor: '#0f172a',
                    backgroundColor: 'rgba(15, 23, 42, 0.08)',
                    borderWidth: 2,
                    data: [],
                    tension: 0.1,
                    pointRadius: 2
                },
                {
                    label: 'TX (Mbps)',
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.08)',
                    borderWidth: 2,
                    data: [],
                    tension: 0.1,
                    pointRadius: 3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true,
                    ticks: {
                        callback: function(value){
                            if (value >= 1000) return value.toFixed(2) + ' Gbps';
                            return Number(value).toFixed(2) + ' Mbps';
                        }
                    }
                },
                x: { grid: { display: false } }
            },
            plugins: {
                legend: { position: 'top', align: 'start' },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const v = context.raw;
                            return context.dataset.label + ': ' + Number(v).toFixed(2) + ' Mbps';
                        }
                    }
                }
            }
        }
    });

    function formatTrafficValue(value) {
        if (!Number.isFinite(value)) {
            return '0';
        }

        if (value >= 1000000) {
            return (value / 1000000).toFixed(2) + ' Mbps';
        }

        if (value >= 1000) {
            return (value / 1000).toFixed(2) + ' Kbps';
        }

        return Math.round(value) + ' bps';
    }

    function updateTrafficChart(snapshot) {
        const timeLabel = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        trafficChart.data.labels.push(timeLabel);
        // convert bps to Mbps for chart display
        trafficChart.data.datasets[0].data.push((snapshot.rx_bps ?? 0) / 1000000);
        trafficChart.data.datasets[1].data.push((snapshot.tx_bps ?? 0) / 1000000);

        if (trafficChart.data.labels.length > 20) {
            trafficChart.data.labels.shift();
            trafficChart.data.datasets[0].data.shift();
            trafficChart.data.datasets[1].data.shift();
        }

        trafficChart.update('none');
    }

    function updateRealtimeCards(data) {
        const isOffline = data.status === 'offline';
        const connected = !!data.connected;
        const statusDot = document.getElementById('mikrotik-status-dot');
        const statusText = document.getElementById('mikrotik-status-text');
        const offlineMessage = document.getElementById('mikrotik-offline-message');

        if (statusDot) {
            statusDot.classList.remove('text-success', 'text-danger');
            statusDot.classList.add(connected && !isOffline ? 'text-success' : 'text-danger');
        }

        if (statusText) {
            statusText.textContent = connected && !isOffline ? 'TERHUBUNG' : 'OFFLINE';
        }

        const identityEl = document.getElementById('mikrotik-identity');
        const uptimeEl = document.getElementById('mikrotik-uptime');
        const cpuEl = document.getElementById('mikrotik-cpu-load');
        const hotspotEl = document.getElementById('mikrotik-hotspot-active');
        const pppoeEl = document.getElementById('mikrotik-pppoe-active');
        const interfaceEl = document.getElementById('mikrotik-interface-name');

        if (identityEl) identityEl.textContent = isOffline ? 'Offline' : (data.identity ?? '-');
        if (uptimeEl) uptimeEl.textContent = isOffline ? 'Offline' : (data.uptime ?? '-');
        if (cpuEl) cpuEl.textContent = isOffline ? 'Offline' : (data.cpu_load !== null && data.cpu_load !== undefined ? `${data.cpu_load}%` : '-');
        if (hotspotEl) hotspotEl.textContent = isOffline ? '0' : Number(data.hotspot_active ?? 0).toLocaleString('id-ID');
        if (pppoeEl) pppoeEl.textContent = isOffline ? '0' : Number(data.pppoe_active ?? 0).toLocaleString('id-ID');
        if (interfaceEl) interfaceEl.textContent = isOffline ? '-' : (data.interface_name ?? '-');
        if (offlineMessage) offlineMessage.classList.toggle('d-none', !isOffline);

        if (!isOffline) {
            updateTrafficChart(data);
        }
                // ===== THRESHOLD CHECK & ALERT =====
        handleTrafficThresholdAlert(data, isOffline);
    }

    async function loadRealtimeStats() {
        try {
            const response = await fetch(realtimeEndpoint, {
                headers: {
                    'Accept': 'application/json'
                }
            });

            const payload = await response.json();
            updateRealtimeCards(payload);
        } catch (error) {
            console.error('Gagal memuat statistik realtime dashboard:', error);
            updateRealtimeCards({
                status: 'offline',
                connected: false,
                identity: 'Offline',
                uptime: 'Offline',
                cpu_load: null,
                hotspot_active: 0,
                pppoe_active: 0,
                interface_name: '-',
                rx_bps: 0,
                tx_bps: 0,
            });
        }
    }

    // ===== THRESHOLD MANAGEMENT =====
    let maxRxBps = 0; // 0 berarti alert mati
    let maxTxBps = 0;
    let lastAlertTime = 0; // Debounce flag
    let isModalOpen = false;
    const ALERT_DEBOUNCE_MS = 5000; // Jeda 5 detik antar alert
    const ALERT_PERSIST_MS = 10 * 60 * 1000; // Persist alert across pages (10 minutes)

    const THRESHOLD_STORAGE_KEY = 'trafficThresholds';

    function saveThresholdsToLocalStorage() {
        try {
            const payload = { maxRxBps: Number(maxRxBps) || 0, maxTxBps: Number(maxTxBps) || 0 };
            localStorage.setItem(THRESHOLD_STORAGE_KEY, JSON.stringify(payload));
        } catch (e) {
            console.warn('Gagal menyimpan thresholds ke localStorage', e);
        }
    }

    function loadThresholdsFromLocalStorage() {
        try {
            const raw = localStorage.getItem(THRESHOLD_STORAGE_KEY);
            if (!raw) return;
            const parsed = JSON.parse(raw);
            if (parsed) {
                maxRxBps = Number(parsed.maxRxBps) || 0;
                maxTxBps = Number(parsed.maxTxBps) || 0;
            }
        } catch (e) {
            console.warn('Gagal membaca thresholds dari localStorage', e);
        }
    }

    function handleTrafficThresholdAlert(data, isOffline) {
        if (isOffline || (maxRxBps <= 0 && maxTxBps <= 0)) {
            return;
        }

        const currentTime = Date.now();
        const rxExceed = maxRxBps > 0 && data.rx_bps > maxRxBps;
        const txExceed = maxTxBps > 0 && data.tx_bps > maxTxBps;

        if (!(rxExceed || txExceed) || (currentTime - lastAlertTime) <= ALERT_DEBOUNCE_MS) {
            return;
        }

        const alertMsg = buildTrafficAlertMessage(data, rxExceed, txExceed);
        const alertData = {
            time: currentTime,
            rxExceed: !!rxExceed,
            txExceed: !!txExceed,
            rx_bps: data.rx_bps || 0,
            tx_bps: data.tx_bps || 0,
            maxRxBps: maxRxBps || 0,
            maxTxBps: maxTxBps || 0,
            message: alertMsg,
            expiresAt: currentTime + ALERT_PERSIST_MS,
        };

        persistTrafficAlert(alertData);
        showTrafficWarningToast(data, rxExceed, txExceed);
        lastAlertTime = currentTime;
    }

    function buildTrafficAlertMessage(data, rxExceed, txExceed) {
        let alertMsg = '⚠️ Traffic Alert!\n\n';

        if (rxExceed) {
            alertMsg += `RX: ${formatTrafficValue(data.rx_bps)} (Threshold: ${(maxRxBps / 1000000).toFixed(0)} Mbps)\n`;
        }

        if (txExceed) {
            alertMsg += `TX: ${formatTrafficValue(data.tx_bps)} (Threshold: ${(maxTxBps / 1000000).toFixed(0)} Mbps)`;
        }

        return alertMsg;
    }

    function persistTrafficAlert(alertData) {
        try {
            localStorage.setItem('trafficAlert', JSON.stringify(alertData));
        } catch (e) {
            console.warn('Gagal menyimpan alert ke localStorage', e);
        }
    }

    function showTrafficWarningToast(data, rxExceed, txExceed) {
        try {
            const isMuted = localStorage.getItem('trafficAlertMuted') === '1';
            if (isMuted || isModalOpen) {
                return;
            }

            Swal.fire({
                icon: 'warning',
                title: 'Traffic Berlebih!',
                text: (rxExceed ? `RX: ${formatTrafficValue(data.rx_bps)}` : '') + (txExceed ? (rxExceed ? ' / ' : '') + `TX: ${formatTrafficValue(data.tx_bps)}` : ''),
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                showCloseButton: true,
                timer: 5000,
                timerProgressBar: true,
                didOpen: () => {
                    try {
                        const cb = Swal.getCloseButton();
                        if (cb) {
                            cb.addEventListener('click', function () {
                                try {
                                    localStorage.removeItem('trafficAlert');
                                } catch (e) {}
                            });
                        }
                    } catch (e) {}
                }
            });
        } catch (e) {
            console.warn('Error showing toast', e);
        }
    }

    

    // Event listener untuk tombol Set Alert Threshold
    document.getElementById('btn-set-threshold')?.addEventListener('click', function() {
        isModalOpen = true; // Kunci menyala

        Swal.fire({
            title: 'Atur Alert Threshold Traffic',
            icon: 'info',
            html: `
                <div style="text-align: left;">
                    <label class="form-label fw-bold mb-2 d-block">RX (Download) Threshold (Mbps):</label>
                    <input type="number" id="swal-rx-threshold" class="form-control mb-3" placeholder="0 = Nonaktif" min="0" step="1">
                    
                    <label class="form-label fw-bold mb-2 d-block">TX (Upload) Threshold (Mbps):</label>
                    <input type="number" id="swal-tx-threshold" class="form-control" placeholder="0 = Nonaktif" min="0" step="1">
                    
                    <small class="text-muted d-block mt-3">
                        <strong>Tips:</strong> Masukkan 0 untuk menonaktifkan threshold. Alert akan muncul jika traffic melebihi batas yang ditetapkan.
                    </small>
                </div>
            `,
            confirmButtonText: 'Simpan',
            cancelButtonText: 'Batal',
            showCancelButton: true,
            didClose: () => {
                isModalOpen = false; // Kunci dilepas saat form tertutup (save/cancel)
            },
            didOpen: () => {
                // Set nilai saat ini ke input field
                document.getElementById('swal-rx-threshold').value = maxRxBps > 0 ? maxRxBps / 1000000 : '';
                document.getElementById('swal-tx-threshold').value = maxTxBps > 0 ? maxTxBps / 1000000 : '';
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const rxMbps = parseFloat(document.getElementById('swal-rx-threshold').value) || 0;
                const txMbps = parseFloat(document.getElementById('swal-tx-threshold').value) || 0;
                
                // Konversi Mbps ke bps (kalikan 1.000.000)
                maxRxBps = rxMbps > 0 ? rxMbps * 1000000 : 0;
                maxTxBps = txMbps > 0 ? txMbps * 1000000 : 0;
                
                // Update tampilan current threshold
                updateThresholdDisplay();
                // Persist thresholds so they survive page navigation
                saveThresholdsToLocalStorage();
                
                // Tampilkan toast konfirmasi
                Swal.fire({
                    icon: 'success',
                    title: 'Threshold berhasil diatur',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000,
                    timerProgressBar: true
                });
            }
        });
    });

    function updateThresholdDisplay() {
        const infoEl = document.getElementById('current-threshold-info');
        if (!infoEl) return;
        
        if (maxRxBps === 0 && maxTxBps === 0) {
            infoEl.textContent = 'Off';
            infoEl.className = 'badge bg-secondary align-self-center small';
        } else {
            let displayText = '';
            if (maxRxBps > 0) displayText += `RX: ${(maxRxBps / 1000000).toFixed(0)}M`;
            if (maxTxBps > 0) displayText += (displayText ? ' / ' : '') + `TX: ${(maxTxBps / 1000000).toFixed(0)}M`;
            infoEl.textContent = displayText;
            infoEl.className = 'badge bg-danger align-self-center small';
        }
    }

    // Load persisted thresholds and inisialisasi tampilan
    loadThresholdsFromLocalStorage();
    updateThresholdDisplay();

    loadRealtimeStats();
    setInterval(loadRealtimeStats, 1000);
</script>
@endpush    