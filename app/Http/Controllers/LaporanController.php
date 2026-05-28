<?php

namespace App\Http\Controllers;

use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Traits\ParsesDateRange;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LaporanController extends Controller
{
    use ParsesDateRange;
    public function index(Request $request)
    {
        $dates = $this->parseDateRange(
            $request->query('start_date'),
            $request->query('end_date')
        );
        $start = $dates['start'];
        $end = $dates['end'];
        $startDate = $dates['startDate'];
        $endDate = $dates['endDate'];

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

        $transaksiLaporan = Pembayaran::with(['pelanggan', 'paket'])
            ->selectRaw('id_pelanggan, SUM(nominal) as total_nominal, COUNT(id_pembayaran) as jumlah_transaksi, MAX(tanggal_bayar) as transaksi_terakhir')
            ->when($start, function ($query) use ($start) {
                $query->whereDate('tanggal_bayar', '>=', $start->toDateString());
            })
            ->when($end, function ($query) use ($end) {
                $query->whereDate('tanggal_bayar', '<=', $end->toDateString());
            })
            ->groupBy('id_pelanggan')
            ->orderByDesc('transaksi_terakhir')
            ->paginate(10)
            ->withQueryString();

        // PENTING: Join melalui pembayaran.id_paket (paket saat transaksi)
        // bukan pelanggan.id_paket (paket saat ini)
        $pemasukanPerPaket = Pembayaran::query()
            ->join('paket_bandwidth', 'pembayaran.id_paket', '=', 'paket_bandwidth.id_paket')
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

        // 1. Current MRR (expected monthly recurring revenue from active subscriptions)
        $currentMrr = Pelanggan::where('pelanggan.status_aktif', 'Aktif')
            ->join('paket_bandwidth', 'pelanggan.id_paket', '=', 'paket_bandwidth.id_paket')
            ->sum('paket_bandwidth.harga');

        // 2. Realisasi Bulan Ini (actual payment nominal recorded in this month)
        $realisasiBulanIni = Pembayaran::whereYear('tanggal_bayar', now()->year)
            ->whereMonth('tanggal_bayar', now()->month)
            ->sum('nominal');

        // 3. Piutang Bulan Ini (unpaid packages for active customers in the current month)
        $pelangganBelumBayarBulanIni = Pelanggan::where('pelanggan.status_aktif', 'Aktif')
            ->whereDoesntHave('pembayaran', function ($query) {
                $query->whereYear('tanggal_bayar', now()->year)
                    ->whereMonth('tanggal_bayar', now()->month);
            })->with('paket')->get();

        $piutangBulanIni = $pelangganBelumBayarBulanIni->sum(function ($p) {
            return (float) optional($p->paket)->harga;
        });

        // 4. Customer counts & Churn Rate
        $activeCustomersCount = Pelanggan::where('status_aktif', 'Aktif')->count();
        $churnedCustomersCount = Pelanggan::whereIn('status_aktif', ['Nonaktif', 'Locked'])->count();
        $totalCustomersCount = $activeCustomersCount + $churnedCustomersCount;
        $churnRatePercent = $totalCustomersCount > 0 ? round(($churnedCustomersCount / $totalCustomersCount) * 100, 1) : 0;
        $newCustomersBulanIni = Pelanggan::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        // 5. 6-Month History of collections, MRR, and receivables
        $financialHistory = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = now()->subMonths($i);
            $year = $monthDate->year;
            $month = $monthDate->month;
            $monthLabel = $monthDate->translatedFormat('F Y'); // Full indonesian month name

            $endOfMonth = $monthDate->copy()->endOfMonth();

            // Realisasi
            $histRealisasi = Pembayaran::whereYear('tanggal_bayar', $year)
                ->whereMonth('tanggal_bayar', $month)
                ->sum('nominal');

            // Expected MRR
            $histMrr = Pelanggan::where(function($q) use ($endOfMonth) {
                    $q->whereNull('pelanggan.created_at')
                      ->orWhere('pelanggan.created_at', '<=', $endOfMonth);
                })
                ->where(function($q) use ($year, $month) {
                    $q->where('pelanggan.status_aktif', 'Aktif')
                      ->orWhereHas('pembayaran', function($sub) use ($year, $month) {
                          $sub->whereYear('tanggal_bayar', $year)
                              ->whereMonth('tanggal_bayar', $month);
                      });
                })
                ->join('paket_bandwidth', 'pelanggan.id_paket', '=', 'paket_bandwidth.id_paket')
                ->sum('paket_bandwidth.harga');

            // Piutang: active customers created <= endOfMonth, who didn't pay in that month
            $histPiutang = Pelanggan::where(function($q) use ($endOfMonth) {
                    $q->whereNull('pelanggan.created_at')
                      ->orWhere('pelanggan.created_at', '<=', $endOfMonth);
                })
                ->where('pelanggan.status_aktif', 'Aktif')
                ->whereDoesntHave('pembayaran', function($sub) use ($year, $month) {
                    $sub->whereYear('tanggal_bayar', $year)
                        ->whereMonth('tanggal_bayar', $month);
                })
                ->join('paket_bandwidth', 'pelanggan.id_paket', '=', 'paket_bandwidth.id_paket')
                ->sum('paket_bandwidth.harga');

            $financialHistory[] = [
                'label' => $monthLabel,
                'realisasi' => (float) $histRealisasi,
                'mrr' => (float) $histMrr,
                'piutang' => (float) $histPiutang,
            ];
        }

        $businessStats = [
            'current_mrr' => $currentMrr,
            'realisasi_bulan_ini' => $realisasiBulanIni,
            'piutang_bulan_ini' => $piutangBulanIni,
            'aktif_count' => $activeCustomersCount,
            'churn_count' => $churnedCustomersCount,
            'churn_rate' => $churnRatePercent,
            'baru_count' => $newCustomersBulanIni,
            'history' => $financialHistory,
        ];

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
            'businessStats' => $businessStats,
        ]);
    }

    public function cetakPdf(Request $request)
    {
        $dates = $this->parseDateRange(
            $request->query('start_date'),
            $request->query('end_date')
        );
        $start = $dates['start'];
        $end = $dates['end'];

        $riwayatPembayaran = Pembayaran::with(['pelanggan', 'paket', 'admin'])
            ->when($start, function ($query) use ($start) {
                $query->where('tanggal_bayar', '>=', $start->toDateString());
            })
            ->when($end, function ($query) use ($end) {
                $query->where('tanggal_bayar', '<=', $end->toDateString());
            })
            ->latest('tanggal_bayar')
            ->get();

        $totalPemasukan = $riwayatPembayaran->sum('nominal');
        $periodLabel = $start && $end
            ? $start->translatedFormat('d F Y') . ' - ' . $end->translatedFormat('d F Y')
            : ($start ? $start->translatedFormat('d F Y') : ($end ? $end->translatedFormat('d F Y') : 'Seluruh Periode'));

        $printedAt = now();

        $pdf = Pdf::loadView('laporan.pdf', [
            'riwayatPembayaran' => $riwayatPembayaran,
            'totalPemasukan' => $totalPemasukan,
            'periodLabel' => $periodLabel,
            'printedAt' => $printedAt,
        ]);

        return $pdf->download('laporan-pembayaran.pdf');
    }

    public function exportExcel(Request $request)
    {
        $dates = $this->parseDateRange(
            $request->query('start_date'),
            $request->query('end_date')
        );
        $start = $dates['start'];
        $end = $dates['end'];

        $riwayatPembayaran = Pembayaran::with(['pelanggan', 'paket', 'admin'])
            ->when($start, function ($query) use ($start) {
                $query->where('tanggal_bayar', '>=', $start->toDateString());
            })
            ->when($end, function ($query) use ($end) {
                $query->where('tanggal_bayar', '<=', $end->toDateString());
            })
            ->latest('tanggal_bayar')
            ->get();

        $fileName = 'laporan-pembayaran-' . now()->format('Y-m-d-His') . '.csv';

        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = ['No', 'Tanggal Bayar', 'Nama Pelanggan', 'Paket Bandwidth', 'Nominal (Rp)', 'Periode Tagihan', 'Status Notifikasi', 'Penerima Pembayaran (Admin)'];

        $callback = function() use($riwayatPembayaran, $columns) {
            $file = fopen('php://output', 'w');
            
            // Add UTF-8 BOM to make Excel display international characters properly
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Semicolon separator is highly compatible with Excel in Indonesian locale settings
            fputcsv($file, $columns, ';');

            $no = 1;
            foreach ($riwayatPembayaran as $row) {
                fputcsv($file, [
                    $no++,
                    $row->tanggal_bayar ? $row->tanggal_bayar->format('d-m-Y') : '-',
                    $row->pelanggan->nama_pelanggan ?? '-',
                    $row->paket->nama_paket ?? '-',
                    $row->nominal,
                    $row->periode_tagihan,
                    $row->status_notifikasi,
                    $row->admin->nama_lengkap ?? '-'
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function cetakPdfSigned(Request $request)
    {
        return $this->cetakPdf($request);
    }

    public function kirimOwner(Request $request)
    {
        $dates = $this->parseDateRange(
            $request->query('start_date'),
            $request->query('end_date')
        );
        $start = $dates['start'];
        $end = $dates['end'];

        $pembayaranQuery = Pembayaran::query()
            ->when($start, function ($query) use ($start) {
                $query->where('tanggal_bayar', '>=', $start->toDateString());
            })
            ->when($end, function ($query) use ($end) {
                $query->where('tanggal_bayar', '<=', $end->toDateString());
            });

        $totalPemasukan = $pembayaranQuery->sum('nominal');

        $periodLabel = $start && $end
            ? $start->translatedFormat('d F Y') . ' - ' . $end->translatedFormat('d F Y')
            : ($start ? $start->translatedFormat('d F Y') : ($end ? $end->translatedFormat('d F Y') : 'Seluruh Periode'));

        $whatsappConfig = config('services.whatsapp');
        $ownerNumber = $whatsappConfig['owner_number'] ?? env('OWNER_WA_NUMBER');

        if (empty($ownerNumber)) {
            return redirect()->back()->with('error', 'Nomor WhatsApp Owner belum diatur di .env (OWNER_WA_NUMBER).');
        }

        // Normalize owner number
        if (str_starts_with($ownerNumber, '0')) {
            $ownerNumber = '62' . substr($ownerNumber, 1);
        }

        // Generate signed URL valid for 7 days
        $pdfUrl = URL::temporarySignedRoute(
            'laporan.cetak.signed',
            now()->addDays(7),
            $request->only('start_date', 'end_date')
        );

        $laporanUrl = route('laporan.index');

        $pesan = "*LAPORAN KEUANGAN SMKN 53 JAKARTA*\n\n";
        $pesan .= "Halo Bapak/Ibu Owner,\n";
        $pesan .= "Berikut adalah Laporan Keuangan terbaru yang dikirimkan oleh Administrator.\n\n";
        $pesan .= "Periode: *{$periodLabel}*\n";
        $pesan .= "Total Pemasukan: *Rp " . number_format($totalPemasukan, 0, ',', '.') . "*\n\n";
        $pesan .= "Silakan klik link di bawah ini untuk mengunduh laporan PDF secara langsung (tautan valid selama 7 hari):\n";
        $pesan .= "{$pdfUrl}\n\n";
        $pesan .= "Atau akses dashboard laporan keuangan melalui tautan berikut:\n";
        $pesan .= "{$laporanUrl}\n\n";
        $pesan .= "Terima kasih.";

        if (!$whatsappConfig || !$whatsappConfig['enabled'] || empty($whatsappConfig['token']) || empty($whatsappConfig['url'])) {
            return redirect()->back()->with('error', 'Fungsi WhatsApp Gateway (Fonnte) belum diaktifkan atau dikonfigurasi di .env.');
        }

        try {
            $response = Http::asForm()
                ->withHeaders([
                    'Authorization' => $whatsappConfig['token'],
                ])->post($whatsappConfig['url'], [
                    'target' => $ownerNumber,
                    'message' => $pesan,
                ]);

            if ($response->successful() && isset($response->json()['status']) && $response->json()['status'] == 'success') {
                return redirect()->back()->with('success', 'Laporan berhasil dibuat dan dikirimkan ke WhatsApp Owner!');
            } else {
                $reason = $response->json()['reason'] ?? 'Gagal mengirim pesan via WhatsApp Gateway.';
                return redirect()->back()->with('error', 'Gagal mengirim WhatsApp: ' . $reason);
            }
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim laporan ke Owner', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Terjadi kesalahan sistem saat mengirim laporan: ' . $e->getMessage());
        }
    }
}
