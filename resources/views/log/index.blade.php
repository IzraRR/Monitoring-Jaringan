@extends('layouts.app')

@section('title', 'Log Aktivitas | SMKN 53')
@section('page_heading', 'Log Aktivitas Jaringan (Intra-Net)')

@section('content')
<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card border-0 shadow-sm rounded-3 h-100"><div class="card-body"><p class="text-secondary mb-1">Total Log Hari Ini</p><h4 class="fw-bold mb-0">{{ number_format($summary['total_hari_ini']) }}</h4></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm rounded-3 h-100"><div class="card-body"><p class="text-secondary mb-1">Anomali Hari Ini</p><h4 class="fw-bold text-danger mb-0">{{ number_format($summary['anomali_hari_ini']) }}</h4></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm rounded-3 h-100"><div class="card-body"><p class="text-secondary mb-1">Sesi Sedang Berjalan</p><h4 class="fw-bold text-success mb-0">{{ number_format($summary['sedang_berjalan']) }}</h4></div></div></div>
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
                    <tr><th>Timestamp</th><th>Pelanggan</th><th>IP Address</th><th>Tipe Akses</th><th>Durasi/Trafik</th><th>Status</th><th>Aksi</th></tr>
                </thead>
                <tbody class="fw-medium text-dark">
                    @forelse($logAktivitas as $item)
                        <tr>
                            <td>{{ optional($item->waktu_mulai)->format('d/m/y H:i') ?? '-' }}</td>
                            <td>{{ $item->pelanggan->nama_pelanggan ?? '-' }}</td>
                            <td>{{ $item->pelanggan->username_mikrotik ?? '-' }}</td>
                            <td>{{ strtoupper(config('services.mikrotik.sync_mode', 'hotspot')) === 'PPPOE' ? 'PPPoE' : 'Hotspot' }}</td>
                            <td>
                                {{ $item->durasi_menit ? $item->durasi_menit . ' menit' : '-' }}
                                / {{ $item->data_usage_mb ? number_format($item->data_usage_mb, 2, ',', '.') . ' MB' : '-' }}
                            </td>
                            <td>
                                @if($item->is_anomali)
                                    <span class="badge bg-danger">Gagal/Anomali</span>
                                @elseif($item->waktu_selesai)
                                    <span class="badge bg-success">Sukses</span>
                                @else
                                    <span class="badge bg-warning text-dark">Berjalan</span>
                                @endif
                            </td>
                            <td><i class="bi bi-folder-fill fs-4 text-dark"></i></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-4 text-muted">Belum ada log aktivitas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="d-flex justify-content-end">{{ $logAktivitas->links() }}</div>
@endsection