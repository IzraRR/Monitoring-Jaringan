@extends('layouts.app')

@section('title', 'Dashboard | SMKN 53 Jakarta')
@section('page_heading', 'Dashboard | Monitoring Bandwidth Real-Time')

@section('content')
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
                    
                    <button type="button" class="btn btn-secondary w-100 fw-bold border-dark mb-4" style="background-color: #cbd5e1; color:#0f172a;" disabled>
                        MONITORING OTOMATIS
                    </button>
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
                    <span id="mikrotik-status-text" class="fw-bold text-dark">{{ $realtimeStats['connected'] ? 'TERHUBUNG' : 'TIDAK TERHUBUNG' }}</span>
                </div>
                <small class="text-muted d-block">Host: {{ $mikrotik['host'] !== '' ? $mikrotik['host'] : '-' }}</small>
                <small class="text-muted d-block">Identity: <span id="mikrotik-identity">{{ $realtimeStats['identity'] ?? $mikrotik['identity'] ?? '-' }}</span></small>
                <small class="text-muted d-block">Uptime: <span id="mikrotik-uptime">{{ $realtimeStats['uptime'] ?? $mikrotik['uptime'] ?? '-' }}</span></small>
                <small class="text-muted d-block mb-2">CPU Load: <span id="mikrotik-cpu-load">{{ $realtimeStats['cpu_load'] !== null ? $realtimeStats['cpu_load'] . '%' : ($mikrotik['cpu_load'] !== null ? $mikrotik['cpu_load'] . '%' : '-') }}</span></small>
                <small class="text-muted d-block">Hotspot Aktif: <span id="mikrotik-hotspot-active">{{ number_format($realtimeStats['hotspot_active'] ?? 0) }}</span></small>
                <small class="text-muted d-block">PPPoE Aktif: <span id="mikrotik-pppoe-active">{{ number_format($realtimeStats['pppoe_active'] ?? 0) }}</span></small>
                <small class="text-muted d-block mb-2">Interface: <span id="mikrotik-interface-name">{{ $realtimeStats['interface_name'] ?? '-' }}</span></small>
                @if (!$mikrotik['connected'] && $mikrotik['error'])
                    <small class="text-danger d-block">{{ $mikrotik['error'] }}</small>
                    <small class="text-muted d-block mt-1">
                        Password kosong diperbolehkan jika akun RouterOS memang tidak memakai password.
                    </small>
                    <small class="text-muted d-block mt-1">
                        Pastikan layanan API RouterOS aktif (port 8728/8729) dan user punya hak akses yang cukup.
                    </small>
                @endif

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
<script>
    const realtimeEndpoint = @json(route('dashboard.realtime-stats'));

    const ctx = document.getElementById('trafficChart').getContext('2d');
    const trafficChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                {
                    label: 'RX (bps)',
                    borderColor: '#0f172a',
                    backgroundColor: 'rgba(15, 23, 42, 0.08)',
                    borderWidth: 2,
                    data: [],
                    tension: 0.1,
                    pointRadius: 2
                },
                {
                    label: 'TX (bps)',
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
                y: { beginAtZero: true },
                x: { grid: { display: false } }
            },
            plugins: {
                legend: { position: 'top', align: 'start' }
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
        trafficChart.data.datasets[0].data.push(snapshot.rx_bps ?? 0);
        trafficChart.data.datasets[1].data.push(snapshot.tx_bps ?? 0);

        if (trafficChart.data.labels.length > 20) {
            trafficChart.data.labels.shift();
            trafficChart.data.datasets[0].data.shift();
            trafficChart.data.datasets[1].data.shift();
        }

        trafficChart.update('none');
    }

    function updateRealtimeCards(data) {
        const connected = !!data.connected;
        const statusDot = document.getElementById('mikrotik-status-dot');
        const statusText = document.getElementById('mikrotik-status-text');

        if (statusDot) {
            statusDot.classList.remove('text-success', 'text-danger');
            statusDot.classList.add(connected ? 'text-success' : 'text-danger');
        }

        if (statusText) {
            statusText.textContent = connected ? 'TERHUBUNG' : 'TIDAK TERHUBUNG';
        }

        const identityEl = document.getElementById('mikrotik-identity');
        const uptimeEl = document.getElementById('mikrotik-uptime');
        const cpuEl = document.getElementById('mikrotik-cpu-load');
        const hotspotEl = document.getElementById('mikrotik-hotspot-active');
        const pppoeEl = document.getElementById('mikrotik-pppoe-active');
        const interfaceEl = document.getElementById('mikrotik-interface-name');

        if (identityEl) identityEl.textContent = data.identity ?? '-';
        if (uptimeEl) uptimeEl.textContent = data.uptime ?? '-';
        if (cpuEl) cpuEl.textContent = data.cpu_load !== null && data.cpu_load !== undefined ? `${data.cpu_load}%` : '-';
        if (hotspotEl) hotspotEl.textContent = Number(data.hotspot_active ?? 0).toLocaleString('id-ID');
        if (pppoeEl) pppoeEl.textContent = Number(data.pppoe_active ?? 0).toLocaleString('id-ID');
        if (interfaceEl) interfaceEl.textContent = data.interface_name ?? '-';

        updateTrafficChart(data);
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
        }
    }

    loadRealtimeStats();
    setInterval(loadRealtimeStats, 3000);
</script>
@endpush    