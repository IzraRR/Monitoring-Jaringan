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
        <x-stat-card 
            title="Pelanggan Aktif"
            :value="number_format($stats['pelanggan_aktif'])"
            :subtitle="'dari ' . number_format($stats['total_pelanggan']) . ' pelanggan'"
            icon="bi-people-fill"
        />
    </div>
    <div class="col-md-3">
        <x-stat-card 
            title="Log Hari Ini"
            :value="number_format($stats['log_hari_ini'])"
            subtitle="aktivitas tercatat"
            icon="bi-activity"
        />
    </div>
    <div class="col-md-3">
        <x-stat-card 
            title="Pemasukan Bulan Ini"
            :value="'Rp ' . number_format($stats['pemasukan_bulan_ini'], 0, ',', '.')"
            :subtitle="'transaksi ' . now()->translatedFormat('F Y')"
            value-class="text-success"
            icon="bi-cash-stack"
        />
    </div>
    <div class="col-md-3">
        <x-stat-card 
            title="Total Paket Bandwidth"
            :value="number_format($stats['total_paket'])"
            subtitle="profil tersedia"
            icon="bi-speedometer2"
        />
    </div>
</div>

<div class="row">
    <div class="col-md-9 mb-4">
        <div class="card border-0 shadow-sm rounded-3 h-100" style="background-color: #e2e8f0;">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-secondary mb-0">Traffic Interface Real-Time (RX/TX)</h6>
                    <div style="width: 280px;">
                        <select id="select-pelanggan" class="form-select form-select-sm border-dark border-1 fw-bold" style="border-radius: 8px;">
                            <option value="">Semua (All)</option>
                            @foreach($pelangganOptions as $p)
                                @php
                                    $namaPaket = $p->paket->nama_paket ?? '';
                                    $tipe = \Illuminate\Support\Str::contains(strtolower($namaPaket), 'pppoe') ? 'PPPoE' : 'Hotspot';
                                @endphp
                                <option value="{{ $p->id_pelanggan }}">{{ $p->nama_pelanggan }} ({{ $p->username_mikrotik }} - {{ $tipe }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="flex-grow-1" style="min-height: 350px; position: relative;">
                    <canvas id="trafficChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card border-0 shadow-sm rounded-3 bg-white h-100">
            <div class="card-body">
                <h6 class="fw-bold text-dark mb-4">Analisis & Peringatan<br>(Alert)</h6>
                
                <form id="threshold-form">
                    <div class="mb-2">
                        <label class="form-label text-secondary mb-1 small fw-semibold">Threshold RX (Download) Mbps :</label>
                        <input type="number" id="input-rx-threshold" class="form-control bg-dark text-white text-center fw-bold fs-5" min="0" placeholder="0 = Off" style="border-radius: 8px;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary mb-1 small fw-semibold">Threshold TX (Upload) Mbps :</label>
                        <input type="number" id="input-tx-threshold" class="form-control bg-dark text-white text-center fw-bold fs-5" min="0" placeholder="0 = Off" style="border-radius: 8px;">
                    </div>
                    
                    <div class="d-flex gap-2 mb-4">
                        <button type="submit" class="btn btn-sm btn-danger flex-grow-1">
                            <i class="bi bi-check-circle"></i> Simpan
                        </button>
                        <button type="button" id="btn-toggle-alert-mute" class="btn btn-sm btn-outline-secondary flex-grow-1">
                            <i class="bi bi-bell-slash"></i> Silent
                        </button>
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
                <small class="text-muted d-block">Router: {{ !empty($mikrotik['host']) ? 'Terhubung (dikonfigurasi)' : 'Belum dikonfigurasi' }}</small>
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
<script src="{{ asset('vendor/js/chart.umd.min.js') }}"></script>
<script>
    // Define endpoint untuk dashboard monitor
    const realtimeEndpoint = @json(route('dashboard.realtime-stats'));
</script>
<script src="{{ asset('js/dashboard.js') }}?v={{ filemtime(public_path('js/dashboard.js')) }}"></script>
@endpush
