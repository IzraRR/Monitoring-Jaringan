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
        <div class="input-group" style="width: 250px;">
            <form action="{{ route('log.index') }}" method="GET" class="d-flex w-100">
                <input type="text" name="q" value="{{ $search }}" class="form-control border-dark border-2" placeholder="Cari pelanggan" style="border-radius: 8px 0 0 8px;">
                <button class="btn btn-outline-dark border-2 fw-bold" style="border-radius: 0 8px 8px 0;"><i class="bi bi-search"></i></button>
            </form>
        </div>
        <div class="d-flex align-items-center gap-2">
            <form action="{{ route('log.index') }}" method="GET" class="d-flex align-items-center gap-2">
                <input type="hidden" name="q" value="{{ $search }}">
                <label class="fw-bold fs-5 mb-0" style="color: #0f172a;">Rentang Waktu :</label>
                <input type="date" name="start_date" value="{{ $startDate }}" class="form-control border-secondary fw-medium" style="width: 165px; background-color: #cbd5e1;">
                <i class="bi bi-arrow-right fw-bold fs-5"></i>
                <input type="date" name="end_date" value="{{ $endDate }}" class="form-control border-secondary fw-medium" style="width: 165px; background-color: #e2e8f0;">
                <button class="btn fw-bold px-3 ms-2" style="background-color: #e2e8f0; color: #0f172a; border: 1px solid #cbd5e1;">TAMPILKAN LOG</button>
                <a href="{{ route('log.index') }}" class="btn fw-bold px-3" style="background-color: #fff; color: #0f172a; border: 1px solid #cbd5e1;">RESET</a>
            </form>
        </div>
    </div>
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
</div>
<div class="d-flex justify-content-end">{{ $logAktivitas->links() }}</div>
@endsection