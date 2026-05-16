@extends('layouts.app')

@section('title', 'Laporan | SMKN 53')
@section('page_heading', 'Laporan Keuangan & Pemasukan')

@section('content')
<div class="card border-0 shadow-sm mb-3" style="background-color: #dbe4ee;">
    <div class="card-body">
        <form action="{{ route('laporan.index') }}" method="GET" class="d-flex flex-wrap align-items-end gap-3">
            <div>
                <label class="form-label fw-bold mb-1">Mulai</label>
                <input type="date" name="start_date" value="{{ $startDate }}" class="form-control">
            </div>
            <div>
                <label class="form-label fw-bold mb-1">Sampai</label>
                <input type="date" name="end_date" value="{{ $endDate }}" class="form-control">
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn fw-bold" style="background-color: #1e293b; color: #fff;">Terapkan Filter</button>
                <a href="{{ route('laporan.index') }}" class="btn fw-bold" style="background-color: #fff; color: #1e293b; border: 1px solid #94a3b8;">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="row mb-4 mt-3">
    <div class="col-md-3"><div class="card border-0 shadow-sm h-100 text-center" style="background-color: #1e293b; color: white;"><div class="card-body"><small class="text-uppercase text-secondary fw-bold">Total Pemasukan</small><h4 class="fw-bold mt-2" style="color: #22c55e;">Rp {{ number_format($summary['total_pemasukan'], 0, ',', '.') }}</h4></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm h-100 text-center" style="background-color: #1e293b; color: white;"><div class="card-body"><small class="text-uppercase text-secondary fw-bold">Rata-Rata Pemasukan</small><h4 class="fw-bold mt-2" style="color: #22c55e;">Rp {{ number_format($summary['rata_pemasukan'], 0, ',', '.') }}</h4></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm h-100 text-center" style="background-color: #1e293b; color: white;"><div class="card-body"><small class="text-uppercase text-secondary fw-bold">Tunggakan Aktif</small><h4 class="fw-bold mt-2" style="color: #ef4444;">Rp {{ number_format($summary['tunggakan_aktif'], 0, ',', '.') }}</h4></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm h-100 text-center" style="background-color: #1e293b; color: white;"><div class="card-body"><small class="text-uppercase text-secondary fw-bold">Total Transaksi</small><h4 class="fw-bold mt-2 text-white">{{ number_format($summary['total_transaksi']) }} Transaksi</h4></div></div></div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100" style="background-color: #1e293b; color: white;">
            <div class="card-header bg-transparent border-0"><h6 class="fw-bold text-secondary text-uppercase mt-2">Pemasukan Per Paket</h6></div>
            <div class="card-body d-flex justify-content-center align-items-center" style="height: 250px;"><canvas id="paketPieChart"></canvas></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100" style="background-color: #1e293b; color: white;">
            <div class="card-header bg-transparent border-0"><h6 class="fw-bold text-secondary text-uppercase mt-2">Pemasukan Bulanan</h6></div>
            <div class="card-body" style="height: 250px;"><canvas id="bulananBarChart"></canvas></div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-3 border border-dark mb-2">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0 text-center align-middle" style="border-color: #475569;">
                <thead style="background-color: #cbd5e1; color: black;">
                    <tr>
                        <th class="py-3">No.</th>
                        <th class="py-3">Timestamp</th>
                        <th class="py-3">ID Pelanggan</th>
                        <th class="py-3">Nama Pelanggan</th>
                        <th class="py-3">Paket/Keterangan</th>
                        <th class="py-3">Nominal</th>
                        <th class="py-3">Admin Pencatat</th>
                        <th class="py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="fw-medium text-dark">
                    @forelse($transaksiLaporan as $index => $item)
                        <tr>
                            <td>{{ ($transaksiLaporan->firstItem() ?? 1) + $index }}</td>
                            <td>{{ optional($item->tanggal_bayar)->format('d/m/y') ?? '-' }}</td>
                            <td>{{ $item->id_pelanggan }}</td>
                            <td>{{ $item->pelanggan->nama_pelanggan ?? '-' }}</td>
                            <td>{{ $item->pelanggan->paket->nama_paket ?? '-' }}</td>
                            <td>Rp {{ number_format($item->nominal, 0, ',', '.') }}</td>
                            <td>{{ $item->admin->nama_lengkap ?? '-' }}</td>
                            <td><span class="text-dark">[Cetak PDF]</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-4 text-muted">Belum ada data transaksi pada periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3 border-top-0">
        <span class="fw-bold fs-6">Menampilkan {{ $transaksiLaporan->firstItem() ?? 0 }} - {{ $transaksiLaporan->lastItem() ?? 0 }} dari {{ $transaksiLaporan->total() }} rekaman</span>
        <div>{{ $transaksiLaporan->links() }}</div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const pieLabels = @json($pieLabels);
    const pieValues = @json($pieValues);
    const barLabels = @json($barLabels);
    const barValues = @json($barValues);

    new Chart(document.getElementById('paketPieChart').getContext('2d'), {
        type: 'pie',
        data: {
            labels: pieLabels.length ? pieLabels : ['Belum Ada Data'],
            datasets: [{
                data: pieValues.length ? pieValues : [1],
                backgroundColor: ['#3b82f6', '#8b5cf6', '#eab308', '#22c55e', '#f97316'],
                borderWidth: 0
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: true, labels: { color: '#cbd5e1' } } } }
    });

    new Chart(document.getElementById('bulananBarChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: barLabels.length ? barLabels : ['Belum Ada Data'],
            datasets: [{
                data: barValues.length ? barValues : [0],
                backgroundColor: '#3b82f6',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { grid: { color: '#334155' }, ticks: { color: '#cbd5e1' } },
                x: { grid: { display: false }, ticks: { color: '#cbd5e1' } }
            },
            plugins: { legend: { display: false } }
        }
    });
</script>
@endpush