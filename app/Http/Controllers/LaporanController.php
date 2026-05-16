<?php

namespace App\Http\Controllers;

use App\Models\Pelanggan;
use App\Models\Pembayaran;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LaporanController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $start = null;
        $end = null;

        if (is_string($startDate) && $startDate !== '') {
            try {
                $start = Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
            } catch (\Throwable $e) {
                $start = null;
                $startDate = null;
            }
        }

        if (is_string($endDate) && $endDate !== '') {
            try {
                $end = Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay();
            } catch (\Throwable $e) {
                $end = null;
                $endDate = null;
            }
        }

        if ($start && $end && $start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
            [$startDate, $endDate] = [$start->toDateString(), $end->toDateString()];
        }

        $pembayaranQuery = Pembayaran::query()
            ->when($start, function ($query) use ($start) {
                $query->where('tanggal_bayar', '>=', $start->toDateString());
            })
            ->when($end, function ($query) use ($end) {
                $query->where('tanggal_bayar', '<=', $end->toDateString());
            });

        $totalPemasukan = (clone $pembayaranQuery)->sum('nominal');
        $totalTransaksi = (clone $pembayaranQuery)->count();
        $rataPemasukan = $totalTransaksi > 0 ? ($totalPemasukan / $totalTransaksi) : 0;

        $acuanTunggakan = $end ? $end->copy() : now();
        $pelangganBelumBayarBulanIni = Pelanggan::whereDoesntHave('pembayaran', function ($query) use ($acuanTunggakan) {
            $query->whereYear('tanggal_bayar', $acuanTunggakan->year)
                ->whereMonth('tanggal_bayar', $acuanTunggakan->month);
        })->with('paket')->get();

        $tunggakanAktif = $pelangganBelumBayarBulanIni->sum(function ($pelanggan) {
            return (float) optional($pelanggan->paket)->harga;
        });

        $pemasukanPerPaket = Pembayaran::query()
            ->join('pelanggan', 'pembayaran.id_pelanggan', '=', 'pelanggan.id_pelanggan')
            ->join('paket_bandwidth', 'pelanggan.id_paket', '=', 'paket_bandwidth.id_paket')
            ->selectRaw('paket_bandwidth.nama_paket as nama_paket, SUM(pembayaran.nominal) as total_nominal')
            ->when($start, function ($query) use ($start) {
                $query->where('pembayaran.tanggal_bayar', '>=', $start->toDateString());
            })
            ->when($end, function ($query) use ($end) {
                $query->where('pembayaran.tanggal_bayar', '<=', $end->toDateString());
            })
            ->groupBy('paket_bandwidth.nama_paket')
            ->orderByDesc('total_nominal')
            ->get();

        $pemasukanBulanan = Pembayaran::query()
            ->selectRaw('DATE_FORMAT(tanggal_bayar, "%Y-%m") as bulan_key, SUM(nominal) as total_nominal')
            ->when($start || $end, function ($query) use ($start, $end) {
                if ($start) {
                    $query->whereDate('tanggal_bayar', '>=', $start->toDateString());
                }

                if ($end) {
                    $query->whereDate('tanggal_bayar', '<=', $end->toDateString());
                }
            }, function ($query) {
                $query->whereDate('tanggal_bayar', '>=', now()->startOfMonth()->subMonths(5)->toDateString());
            })
            ->groupBy('bulan_key')
            ->orderBy('bulan_key')
            ->get();

        $transaksiLaporan = Pembayaran::with(['pelanggan', 'admin'])
            ->when($start, function ($query) use ($start) {
                $query->whereDate('tanggal_bayar', '>=', $start->toDateString());
            })
            ->when($end, function ($query) use ($end) {
                $query->whereDate('tanggal_bayar', '<=', $end->toDateString());
            })
            ->latest('tanggal_bayar')
            ->latest('id_pembayaran')
            ->paginate(10)
            ->withQueryString();

        return view('laporan.index', [
            'summary' => [
                'total_pemasukan' => $totalPemasukan,
                'rata_pemasukan' => $rataPemasukan,
                'tunggakan_aktif' => $tunggakanAktif,
                'total_transaksi' => $totalTransaksi,
            ],
            'pieLabels' => $pemasukanPerPaket->pluck('nama_paket')->values(),
            'pieValues' => $pemasukanPerPaket->pluck('total_nominal')->map(fn ($value) => round((float) $value, 2))->values(),
            'barLabels' => $pemasukanBulanan->pluck('bulan_key')->map(fn ($value) => date('M y', strtotime($value . '-01')))->values(),
            'barValues' => $pemasukanBulanan->pluck('total_nominal')->map(fn ($value) => round((float) $value, 2))->values(),
            'startDate' => $startDate,
            'endDate' => $endDate,
            'transaksiLaporan' => $transaksiLaporan,
        ]);
    }
}
