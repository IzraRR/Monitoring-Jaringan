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
    .bg-primary-subtle {
        background-color: rgba(13, 110, 253, 0.1) !important;
    }
    .bg-success-subtle {
        background-color: rgba(25, 135, 84, 0.1) !important;
    }
    .bg-danger-subtle {
        background-color: rgba(220, 53, 69, 0.1) !important;
    }
    .bg-warning-subtle {
        background-color: rgba(255, 193, 7, 0.1) !important;
    }
    .bg-secondary-subtle {
        background-color: rgba(108, 117, 125, 0.1) !important;
    }
    .bg-info-subtle {
        background-color: rgba(13, 202, 240, 0.1) !important;
    }
</style>

<div class="laporan-header mb-4">
    <form method="GET" class="row g-2 align-items-center">
        <div class="col-12 col-lg-2">
            <h4 class="fw-bold mb-0">Laporan Keuangan</h4>
        </div>
        <div class="col-12 col-md-3 col-lg-2">
            <input type="date" id="start_date" name="start_date" value="{{ $startDate }}" class="form-control border-0 shadow-sm">
        </div>
        <div class="col-12 col-md-3 col-lg-2">
            <input type="date" id="end_date" name="end_date" value="{{ $endDate }}" class="form-control border-0 shadow-sm">
        </div>
        <div class="col-12 col-md-3 col-lg-1">
            <button type="submit" class="btn btn-light fw-semibold w-100 shadow-sm">Filter</button>
        </div>
        <div class="col-12 col-md-3 col-lg-2">
            <a href="{{ route('laporan.cetak', request()->only('start_date', 'end_date')) }}" class="btn btn-outline-light fw-semibold w-100 shadow-sm">Cetak PDF</a>
        </div>
        <div class="col-12 col-md-3 col-lg-1">
            <a href="{{ route('laporan.export-excel', request()->only('start_date', 'end_date')) }}" class="btn btn-outline-light fw-semibold w-100 shadow-sm" title="Export Excel">Excel</a>
        </div>
        @if (session('admin_role') === 'Admin')
        <div class="col-12 col-md-3 col-lg-1">
            <button type="button" id="btn-kirim-owner" class="btn btn-success fw-semibold w-100 shadow-sm" title="Kirim Laporan via WA ke Owner">
                <i class="bi bi-whatsapp"></i> WA
            </button>
        </div>
        @endif
        <div class="col-12 col-md-3 {{ session('admin_role') === 'Admin' ? 'col-lg-1' : 'col-lg-2' }}">
            <a href="{{ route('laporan.index') }}" class="btn btn-outline-light fw-semibold w-100 shadow-sm">Reset</a>
        </div>
    </form>
</div>

@if (session('admin_role') === 'Admin')
<form id="form-kirim-owner" action="{{ route('laporan.kirim-owner', request()->only('start_date', 'end_date')) }}" method="POST" class="d-none">
    @csrf
</form>
@endif

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

<!-- Advanced Business Analytics Row -->
<div class="row g-3 mb-4">
    <!-- Card 1: Proyeksi Keuangan (MRR vs Realisasi vs Piutang) -->
    <div class="col-lg-6">
        <div class="card chart-card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
                    <h5 class="fw-bold mb-0 text-slate-800">
                        <i class="bi bi-graph-up-arrow text-primary me-2"></i>Proyeksi Keuangan (MRR)
                    </h5>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1.5 rounded-pill small">
                            MRR: Rp {{ number_format($businessStats['current_mrr'], 0, ',', '.') }}
                        </span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1.5 rounded-pill small">
                            Realisasi: Rp {{ number_format($businessStats['realisasi_bulan_ini'], 0, ',', '.') }}
                        </span>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1.5 rounded-pill small">
                            Piutang: Rp {{ number_format($businessStats['piutang_bulan_ini'], 0, ',', '.') }}
                        </span>
                    </div>
                </div>
                <div class="text-muted small mb-3">Tren pemasukan riil dibandingkan estimasi piutang dan target bulanan (MRR) 6 bulan terakhir.</div>
                <div class="chart-wrap">
                    <canvas id="financialChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 2: Status & Pertumbuhan Pelanggan -->
    <div class="col-lg-6">
        <div class="card chart-card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
                    <h5 class="fw-bold mb-0 text-slate-800">
                        <i class="bi bi-people text-info me-2"></i>Status & Tren Pelanggan
                    </h5>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1.5 rounded-pill small">
                            Aktif: {{ $businessStats['aktif_count'] }}
                        </span>
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1.5 rounded-pill small">
                            Churn: {{ $businessStats['churn_count'] }}
                        </span>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1.5 rounded-pill small">
                            Rate: {{ $businessStats['churn_rate'] }}%
                        </span>
                        <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1.5 rounded-pill small">
                            Baru: +{{ $businessStats['baru_count'] }}
                        </span>
                    </div>
                </div>
                <div class="text-muted small mb-3">Proporsi perbandingan status pelanggan aktif terhadap pelanggan yang berhenti berlangganan (churn/expired).</div>
                <div class="chart-wrap">
                    <canvas id="customerChart"></canvas>
                </div>
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
                <thead class="table-dark">
                    <tr>
                        <th width="5%">No</th>
                        <th>Nama Pelanggan</th>
                        <th>Paket Bandwidth</th>
                        <th class="text-center">Jml Transaksi</th>
                        <th>Transaksi Terakhir</th>
                        <th class="text-center">Total Nominal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transaksiLaporan as $key => $item)
                    <tr>
                        <td>{{ $transaksiLaporan->firstItem() + $key }}</td>
                        <td class="fw-bold">{{ optional($item->pelanggan)->nama_pelanggan ?? 'Pelanggan Dihapus' }}</td>
                        <td>{{ optional(optional($item->pelanggan)->paket)->nama_paket ?? '-' }}</td>
                        <td class="text-center"><span class="badge bg-secondary">{{ $item->jumlah_transaksi }}x</span></td>
                        <td>{{ \Carbon\Carbon::parse($item->transaksi_terakhir)->format('d M Y') }}</td>
                        <td class="text-center fw-bold text-success">Rp {{ number_format($item->total_nominal, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-3 text-muted">Belum ada data transaksi pada periode ini.</td>
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
<script src="{{ asset('vendor/js/chart.umd.min.js') }}"></script>
<script>
    const pieLabels = @json($pieLabels);
    const pieValues = @json($pieValues);
    const barLabels = @json($barLabels);
    const barValues = @json($barValues);
    const businessStats = @json($businessStats);

    const pieCanvas = document.getElementById('pieChart');
    const barCanvas = document.getElementById('barChart');
    const financialCanvas = document.getElementById('financialChart');
    const customerCanvas = document.getElementById('customerChart');

    if (financialCanvas && businessStats && businessStats.history) {
        const histLabels = businessStats.history.map(item => item.label);
        const histRealisasi = businessStats.history.map(item => item.realisasi);
        const histMrr = businessStats.history.map(item => item.mrr);
        const histPiutang = businessStats.history.map(item => item.piutang);

        new Chart(financialCanvas, {
            type: 'bar',
            data: {
                labels: histLabels,
                datasets: [
                    {
                        label: 'Proyeksi Target (MRR)',
                        data: histMrr,
                        type: 'line',
                        borderColor: '#0f172a',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        fill: false,
                        pointBackgroundColor: '#0f172a',
                        tension: 0.1,
                        order: 1
                    },
                    {
                        label: 'Realisasi Pendapatan',
                        data: histRealisasi,
                        backgroundColor: '#10b981',
                        borderRadius: 6,
                        order: 2
                    },
                    {
                        label: 'Estimasi Piutang',
                        data: histPiutang,
                        backgroundColor: '#ef4444',
                        borderRadius: 6,
                        order: 3
                    }
                ]
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
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 8
                        }
                    }
                }
            }
        });
    }

    if (customerCanvas && businessStats) {
        new Chart(customerCanvas, {
            type: 'doughnut',
            data: {
                labels: ['Aktif', 'Churn/Expired'],
                datasets: [{
                    data: [businessStats.aktif_count, businessStats.churn_count],
                    backgroundColor: ['#10b981', '#64748b'],
                    borderWidth: 0
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
                            boxWidth: 10
                        }
                    }
                },
                cutout: '65%'
            }
        });
    }

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

    const btnKirimOwner = document.getElementById('btn-kirim-owner');
    if (btnKirimOwner) {
        btnKirimOwner.addEventListener('click', function() {
            Swal.fire({
                title: 'Kirim Laporan ke Owner?',
                html: 'Sistem akan **mengenerate tautan PDF terenkripsi** (berlaku 7 hari) dan mengirimkan ringkasan laporan beserta link unduh langsung ke **WhatsApp Owner**.<br><br>Lanjutkan?',
                icon: 'question',
                iconColor: '#25d366',
                showCancelButton: true,
                confirmButtonColor: '#25d366',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Kirim Sekarang!',
                cancelButtonText: 'Batalkan',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.showLoading();
                    document.getElementById('form-kirim-owner').submit();
                }
            });
        });
    }
</script>
@endpush