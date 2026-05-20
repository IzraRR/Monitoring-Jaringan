@extends('layouts.app')

@section('title', 'Laporan Pembayaran | SMKN 53')
@section('page_heading', 'Laporan Pembayaran')

@section('content')
<style>
    .laporan-header {
        background: #183b63;
        color: #ffffff;
        border-radius: 14px;
        padding: 18px 20px;
    }

    .laporan-header .form-control,
    .laporan-header .btn {
        border-radius: 10px;
    }

    .metric-card {
        border: 0;
        border-radius: 14px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
    }

    .metric-value {
        font-size: 2rem;
        line-height: 1.1;
        font-weight: 800;
        letter-spacing: -0.02em;
    }

    .chart-card {
        border: 0;
        border-radius: 14px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
        height: 100%;
    }

    .chart-card .card-body {
        min-height: 360px;
    }

    .chart-wrap {
        position: relative;
        height: 290px;
    }

    .table-card {
        border: 0;
        border-radius: 14px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
    }
</style>

<div class="laporan-header mb-4">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-12 col-lg-3">
            <label class="form-label fw-semibold mb-1">Laporan Pembayaran</label>
            <h4 class="fw-bold mb-0">Laporan Pembayaran</h4>
        </div>
        <div class="col-12 col-md-3 col-lg-2">
            <label for="start_date" class="form-label fw-semibold mb-1">Tanggal Mulai</label>
            <input type="date" id="start_date" name="start_date" value="{{ $startDate }}" class="form-control border-0 shadow-sm">
        </div>
        <div class="col-12 col-md-3 col-lg-2">
            <label for="end_date" class="form-label fw-semibold mb-1">Tanggal Akhir</label>
            <input type="date" id="end_date" name="end_date" value="{{ $endDate }}" class="form-control border-0 shadow-sm">
        </div>
        <div class="col-12 col-md-3 col-lg-2">
            <button type="submit" class="btn btn-light fw-semibold w-100 shadow-sm">Terapkan Filter</button>
        </div>
        <div class="col-12 col-md-3 col-lg-2">
            <a href="{{ route('laporan.cetak', request()->only('start_date', 'end_date')) }}" class="btn btn-outline-light fw-semibold w-100 shadow-sm">Cetak PDF</a>
        </div>
        <div class="col-12 col-md-3 col-lg-2">
            <a href="{{ route('laporan.index') }}" class="btn btn-outline-light fw-semibold w-100 shadow-sm">Reset Filter</a>
        </div>
    </form>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card metric-card h-100">
            <div class="card-body p-4">
                <div class="text-secondary fw-semibold mb-2">Total Pemasukan</div>
                <div class="metric-value">Rp {{ number_format($summary['total_pemasukan'], 0, ',', '.') }}</div>
                <div class="text-muted small mt-2">Akumulasi pemasukan sesuai rentang filter</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card metric-card h-100">
            <div class="card-body p-4">
                <div class="text-secondary fw-semibold mb-2">Rata-rata Pemasukan</div>
                <div class="metric-value">Rp {{ number_format($summary['rata_pemasukan'], 0, ',', '.') }}</div>
                <div class="text-muted small mt-2">Rata-rata nilai transaksi</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card metric-card h-100">
            <div class="card-body p-4">
                <div class="text-secondary fw-semibold mb-2">Tunggakan Aktif</div>
                <div class="metric-value text-danger">Rp {{ number_format($summary['tunggakan_aktif'], 0, ',', '.') }}</div>
                <div class="text-muted small mt-2">Estimasi tunggakan pelanggan aktif</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card chart-card">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="fw-bold mb-0">Pemasukan per Paket</h5>
                </div>
                <div class="chart-wrap">
                    <canvas id="pieChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card chart-card">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="fw-bold mb-0">Pemasukan Bulanan</h5>
                </div>
                <div class="chart-wrap">
                    <canvas id="barChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card table-card mb-3">
    <div class="card-body p-0">
        <div class="px-4 pt-4 pb-3 border-bottom">
            <h5 class="fw-bold mb-0">Riwayat Transaksi</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="fw-bold text-center">ID</th>
                        <th class="fw-bold">Pelanggan</th>
                        <th class="fw-bold">Paket</th>
                        <th class="fw-bold text-center">Periode</th>
                        <th class="fw-bold text-center">Tanggal Bayar</th>
                        <th class="fw-bold text-end">Nominal</th>
                        <th class="fw-bold text-center">Status Notifikasi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transaksiLaporan as $item)
                        <tr>
                            <td class="text-center">{{ $item->id_pembayaran }}</td>
                            <td>{{ $item->pelanggan->nama_pelanggan ?? '-' }}</td>
                            <td>{{ $item->pelanggan->paket->nama_paket ?? '-' }}</td>
                            <td class="text-center">{{ $item->periode_tagihan }}</td>
                            <td class="text-center">{{ optional($item->tanggal_bayar)->format('d/m/Y') ?? '-' }}</td>
                            <td class="text-end">Rp {{ number_format($item->nominal, 0, ',', '.') }}</td>
                            <td class="text-center">{{ $item->status_notifikasi }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Belum ada transaksi untuk periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-top d-flex justify-content-end">
            {{ $transaksiLaporan->links() }}
        </div>
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

    const pieCanvas = document.getElementById('pieChart');
    const barCanvas = document.getElementById('barChart');

    if (pieCanvas) {
        new Chart(pieCanvas, {
            type: 'doughnut',
            data: {
                labels: pieLabels,
                datasets: [{
                    data: pieValues,
                    backgroundColor: ['#0f172a', '#1d4ed8', '#14b8a6', '#f59e0b', '#ef4444', '#8b5cf6', '#22c55e'],
                    borderWidth: 0,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 10,
                        }
                    }
                }
            }
        });
    }

    if (barCanvas) {
        new Chart(barCanvas, {
            type: 'bar',
            data: {
                labels: barLabels,
                datasets: [{
                    label: 'Pemasukan',
                    data: barValues,
                    backgroundColor: '#183b63',
                    borderRadius: 8,
                    barThickness: 28,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(148, 163, 184, 0.25)'
                        },
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + Number(value).toLocaleString('id-ID');
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
</script>
@endpush