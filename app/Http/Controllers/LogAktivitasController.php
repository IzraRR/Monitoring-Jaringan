<?php

namespace App\Http\Controllers;

use App\Models\LogAktivitas;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\MikrotikService;
use Illuminate\Support\Facades\Log as FacadeLog;

class LogAktivitasController extends Controller
{
    public function index(Request $request, MikrotikService $mikrotikService)
    {
        $search = trim((string) $request->query('q', ''));
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

        $logAktivitas = LogAktivitas::with(['pelanggan', 'paket'])
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('pelanggan', function ($q) use ($search) {
                    $q->where('nama_pelanggan', 'like', "%{$search}%")
                      ->orWhere('username_mikrotik', 'like', "%{$search}%");
                });
            })
            ->when($start, function ($query) use ($start) { $query->where('waktu_mulai', '>=', $start); })
            ->when($end, function ($query) use ($end) { $query->where('waktu_mulai', '<=', $end); })
            ->latest('waktu_mulai')
            ->paginate(15)
            ->withQueryString();

        $today = now()->toDateString();

        $summary = [
            'total_hari_ini' => LogAktivitas::whereDate('waktu_mulai', $today)->count(),
            'anomali_hari_ini' => LogAktivitas::whereDate('waktu_mulai', $today)->where('is_anomali', true)->count(),
            'sedang_berjalan' => LogAktivitas::whereNull('waktu_selesai')->count(),
        ];

        // tarik IP live dari MikroTik (Hotspot + PPPoE) untuk mapping username => ip
        $activeIps = [];
        try {
            $hotspotUsers = $mikrotikService->comm('/ip/hotspot/active/print');
            if (is_array($hotspotUsers)) {
                foreach ($hotspotUsers as $user) {
                    if (isset($user['user']) && isset($user['address'])) {
                        $activeIps[$user['user']] = $user['address'];
                    }
                }
            }

            $pppoeUsers = $mikrotikService->comm('/ppp/active/print');
            if (is_array($pppoeUsers)) {
                foreach ($pppoeUsers as $user) {
                    if (isset($user['name']) && isset($user['address'])) {
                        $activeIps[$user['name']] = $user['address'];
                    }
                }
            }
        } catch (\Throwable $e) {
            FacadeLog::warning('Gagal tarik IP live untuk log: ' . $e->getMessage());
        }

        return view('log.index', [
            'logAktivitas' => $logAktivitas,
            'search' => $search,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'summary' => $summary,
            'activeIps' => $activeIps,
        ]);
    }
}
