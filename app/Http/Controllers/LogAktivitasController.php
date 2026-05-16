<?php

namespace App\Http\Controllers;

use App\Models\LogAktivitas;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LogAktivitasController extends Controller
{
    public function index(Request $request)
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

        $logAktivitas = LogAktivitas::with('pelanggan')
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('pelanggan', function ($pelangganQuery) use ($search) {
                    $pelangganQuery->where('nama_pelanggan', 'like', "%{$search}%")
                        ->orWhere('username_mikrotik', 'like', "%{$search}%");
                });
            })
            ->when($start, function ($query) use ($start) {
                $query->where('waktu_mulai', '>=', $start);
            })
            ->when($end, function ($query) use ($end) {
                $query->where('waktu_mulai', '<=', $end);
            })
            ->latest('waktu_mulai')
            ->paginate(10)
            ->withQueryString();

        $today = now()->toDateString();

        return view('log.index', [
            'logAktivitas' => $logAktivitas,
            'search' => $search,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'summary' => [
                'total_hari_ini' => LogAktivitas::whereDate('waktu_mulai', $today)->count(),
                'anomali_hari_ini' => LogAktivitas::whereDate('waktu_mulai', $today)->where('is_anomali', true)->count(),
                'sedang_berjalan' => LogAktivitas::whereNull('waktu_selesai')->count(),
            ],
        ]);
    }
}
