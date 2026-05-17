<?php
namespace App\Http\Controllers;

use App\Models\LogAktivitas;
use App\Models\PaketBandwidth;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Services\MikrotikService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();

        $stats = [
            'pelanggan_aktif' => Pelanggan::where('status_aktif', 'Aktif')->count(),
            'total_pelanggan' => Pelanggan::count(),
            'log_hari_ini' => LogAktivitas::whereDate('waktu_mulai', $today)->count(),
            'total_paket' => PaketBandwidth::count(),
            'pemasukan_bulan_ini' => Pembayaran::whereYear('tanggal_bayar', now()->year)
                ->whereMonth('tanggal_bayar', now()->month)
                ->sum('nominal'),
            'total_pembayaran' => Pembayaran::sum('nominal'),
        ];

        $trafficSeries = LogAktivitas::query()
            ->selectRaw('DATE(waktu_mulai) as tanggal, COALESCE(SUM(data_usage_mb), 0) as usage_mb')
            ->whereDate('waktu_mulai', '>=', now()->subDays(6)->toDateString())
            ->groupBy('tanggal')
            ->orderBy('tanggal')
            ->get();

        $anomaliHariIni = LogAktivitas::whereDate('waktu_mulai', $today)
            ->where('is_anomali', true)
            ->count();

        return view('dashboard', [
            'stats' => $stats,
            'trafficLabels' => $trafficSeries->pluck('tanggal')->map(fn ($value) => date('d M', strtotime($value)))->values(),
            'trafficData' => $trafficSeries->pluck('usage_mb')->map(fn ($value) => round((float) $value, 2))->values(),
            'anomaliHariIni' => $anomaliHariIni,
            'recentPayments' => Pembayaran::with('pelanggan')
                ->latest('tanggal_bayar')
                ->limit(5)
                ->get(),
        ]);
    }

    public function getRealtimeStats(MikrotikService $mikrotikService): JsonResponse
    {
        try {
            return response()->json($mikrotikService->getRealtimeStats());
        } catch (\Throwable $e) {
            Log::warning('MikroTik realtime stats unavailable', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'offline',
                'message' => 'Router tidak dapat dihubungi.',
                'connected' => false,
                'identity' => null,
                'uptime' => 'Offline',
                'cpu_load' => null,
                'hotspot_active' => 0,
                'pppoe_active' => 0,
                'interface_name' => null,
                'rx_bps' => 0,
                'tx_bps' => 0,
            ]);
        }
    }
}