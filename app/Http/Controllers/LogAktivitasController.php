<?php

namespace App\Http\Controllers;

use App\Models\LogAktivitas;
use App\Traits\ParsesDateRange;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\MikrotikService;
use Illuminate\Support\Facades\Log as FacadeLog;

class LogAktivitasController extends Controller
{
    use ParsesDateRange;
    public function index(Request $request, MikrotikService $mikrotikService)
    {
        $search = trim((string) $request->query('q', ''));
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $dates = $this->parseDateRange($startDate, $endDate);
        $start = $dates['start'];
        $end = $dates['end'];
        $startDate = $dates['startDate'];
        $endDate = $dates['endDate'];

        $logAktivitas = LogAktivitas::with(['pelanggan', 'paket'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function($q1) use ($search) {
                    $q1->where('ip_address', 'like', "%{$search}%")
                       ->orWhereHas('pelanggan', function ($q) use ($search) {
                           $q->where('nama_pelanggan', 'like', "%{$search}%")
                             ->orWhere('username_mikrotik', 'like', "%{$search}%");
                       });
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
            'batasAnomali' => \App\Models\Setting::get('batas_anomali_mb', 1000),
        ]);
    }

    public function updateAnomalyThreshold(Request $request)
    {
        $request->validate([
            'batas_anomali_mb' => 'required|numeric|min:1',
        ]);

        \App\Models\Setting::set('batas_anomali_mb', $request->input('batas_anomali_mb'));

        return redirect()->route('log.index')->with('success', 'Batas volume anomali berhasil diperbarui menjadi ' . $request->input('batas_anomali_mb') . ' MB.');
    }
}
