<?php
namespace App\Http\Controllers;

use App\Models\LogAktivitas;
use App\Models\PaketBandwidth;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Services\MikrotikService;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    protected $cacheService;

    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    public function index(MikrotikService $mikrotikService)
    {
        // Cache dashboard stats for 5 minutes
        $stats = $this->cacheService->cacheDashboardStats(function () {
            $today = now()->toDateString();

            return [
                'pelanggan_aktif' => Pelanggan::where('status_aktif', 'Aktif')->count(),
                'total_pelanggan' => Pelanggan::count(),
                'log_hari_ini' => LogAktivitas::whereDate('waktu_mulai', $today)->count(),
                'total_paket' => PaketBandwidth::count(),
                'pemasukan_bulan_ini' => Pembayaran::whereYear('tanggal_bayar', now()->year)
                    ->whereMonth('tanggal_bayar', now()->month)
                    ->sum('nominal'),
                'total_pembayaran' => Pembayaran::sum('nominal'),
                'anomali_hari_ini' => LogAktivitas::whereDate('waktu_mulai', $today)
                    ->where('is_anomali', true)
                    ->count(),
            ];
        });

        // Cache traffic series for 5 minutes
        $trafficSeries = $this->cacheService->remember(
            'dashboard:traffic_series',
            CacheService::CACHE_SHORT,
            function () {
                return LogAktivitas::query()
                    ->selectRaw('DATE(waktu_mulai) as tanggal, COALESCE(SUM(data_usage_mb), 0) as usage_mb')
                    ->whereDate('waktu_mulai', '>=', now()->subDays(6)->toDateString())
                    ->groupBy('tanggal')
                    ->orderBy('tanggal')
                    ->get();
            }
        );

        // Cache MikroTik summary for 30 minutes
        $mikrotik = $this->cacheService->cacheMikrotikSummary(function () use ($mikrotikService) {
            return $mikrotikService->getSystemSummary();
        });

        // Cache recent payments for 5 minutes with eager loading
        $recentPayments = $this->cacheService->remember(
            'dashboard:recent_payments',
            CacheService::CACHE_SHORT,
            function () {
                return Pembayaran::with(['pelanggan' => function ($query) {
                    $query->select('id_pelanggan', 'nama_pelanggan');
                }])
                    ->select('id_pembayaran', 'id_pelanggan', 'tanggal_bayar', 'nominal', 'periode_tagihan')
                    ->latest('tanggal_bayar')
                    ->limit(5)
                    ->get();
            }
        );

        $pelangganOptions = Pelanggan::with('paket')->orderBy('nama_pelanggan')->get(['id_pelanggan', 'nama_pelanggan', 'username_mikrotik', 'id_paket']);

        return view('dashboard', [
            'stats' => $stats,
            'trafficLabels' => $trafficSeries->pluck('tanggal')->map(fn ($value) => date('d M', strtotime($value)))->values(),
            'trafficData' => $trafficSeries->pluck('usage_mb')->map(fn ($value) => round((float) $value, 2))->values(),
            'anomaliHariIni' => $stats['anomali_hari_ini'],
            'mikrotik' => $mikrotik,
            'recentPayments' => $recentPayments,
            'pelangganOptions' => $pelangganOptions,
        ]);
    }

    public function getRealtimeStats(MikrotikService $mikrotikService): JsonResponse
    {
        session()->reflash();
        try {
            $idPelanggan = request()->query('id_pelanggan');

            if (!empty($idPelanggan)) {
                $stats = $mikrotikService->getPelangganRealtimeStats($idPelanggan);
                return response()->json($stats);
            }

            // Cache realtime stats for 1 minute only
            $stats = $this->cacheService->cacheMikrotikRealtimeStats(function () use ($mikrotikService) {
                return $mikrotikService->getRealtimeStats();
            });

            return response()->json($stats);
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
