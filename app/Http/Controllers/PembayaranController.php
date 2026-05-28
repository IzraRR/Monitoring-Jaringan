<?php

namespace App\Http\Controllers;

use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Services\MikrotikService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PembayaranController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $pembayaran = Pembayaran::with(['pelanggan', 'paket', 'admin'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('periode_tagihan', 'like', "%{$search}%")
                        ->orWhere('status_notifikasi', 'like', "%{$search}%")
                        ->orWhereHas('pelanggan', function ($pelangganQuery) use ($search) {
                            $pelangganQuery->where('nama_pelanggan', 'like', "%{$search}%")
                                ->orWhere('username_mikrotik', 'like', "%{$search}%")
                                ->orWhere('id_pelanggan', 'like', "%{$search}%");
                        });
                    });
            })
            ->latest('tanggal_bayar')
            ->latest('id_pembayaran')
            ->paginate(10)
            ->withQueryString();

        return view('pembayaran.index', [
            'pembayaran' => $pembayaran,
            'search' => $search,
            'pelangganOptions' => Pelanggan::with('paket')->orderBy('nama_pelanggan')->get(['id_pelanggan', 'nama_pelanggan', 'username_mikrotik', 'id_paket', 'masa_aktif']),
            'rekap' => [
                'total_nominal' => Pembayaran::sum('nominal'),
                'bulan_ini' => Pembayaran::whereYear('tanggal_bayar', now()->year)
                    ->whereMonth('tanggal_bayar', now()->month)
                    ->sum('nominal'),
                'total_transaksi' => Pembayaran::count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('pembayaran.create', [
            'pelangganOptions' => Pelanggan::with('paket')->orderBy('nama_pelanggan')->get(['id_pelanggan', 'nama_pelanggan', 'username_mikrotik', 'masa_aktif', 'id_paket']),
        ]);
    }

    public function store(Request $request, MikrotikService $mikrotikService): RedirectResponse
    {
        $adminId = (int) $request->session()->get('admin_id');
        if ($adminId <= 0) {
            return redirect()->route('login.form')->with('error', 'Sesi admin tidak valid, silakan login ulang.');
        }

        $validated = $request->validate([
            'id_pelanggan' => ['required', 'exists:pelanggan,id_pelanggan'],
            'tanggal_bayar' => ['nullable', 'date'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'periode_tagihan' => ['required', 'string', 'max:30'],
            'status_notifikasi' => ['nullable', 'in:Pending,Terkirim,Gagal'],
        ]);

        $validated['id_admin'] = $adminId;
        $validated['status_notifikasi'] = 'Pending';
        $validated['tanggal_bayar'] = now()->toDateString();

        // --- PROTEKSI DOUBLE SUBMIT ---
        $recentPayment = \App\Models\Pembayaran::where('id_pelanggan', $validated['id_pelanggan'])
            ->where('periode_tagihan', $validated['periode_tagihan'])
            ->where('nominal', $validated['nominal'])
            ->where('created_at', '>=', now()->subMinutes(2))
            ->first();

        if ($recentPayment) {
            return redirect()->back()->with('error', 'Transaksi ditolak: Pembayaran untuk periode dan nominal yang sama baru saja diproses beberapa detik yang lalu. Mohon tunggu sejenak.');
        }
        // ------------------------------

        // Ambil data pelanggan dengan paket
        $pelanggan = Pelanggan::with('paket')->find($validated['id_pelanggan']);

        if (!$pelanggan) {
            return redirect()->back()->withInput()->with('error', 'Pelanggan tidak ditemukan.');
        }

        // Validasi harga paket SEBELUM membuat record (cegah division by zero)
        $hargaPaket = (float) ($pelanggan->paket->harga ?? 0);

        if ($hargaPaket <= 0) {
            return redirect()->back()->withInput()->with('error', 'Harga paket tidak valid (Rp 0). Pastikan paket pelanggan memiliki harga.');
        }

        $jumlahBulan = (int) floor((float) $request->nominal / $hargaPaket);

        if ($jumlahBulan < 1) {
            return redirect()->back()->withInput()->with('error', 'Nominal uang tidak mencukupi untuk harga paket ini.');
        }

        // Simpan id_paket saat transaksi untuk history yang akurat
        $validated['id_paket'] = $pelanggan->id_paket;

        // Gunakan DB transaction untuk konsistensi data
        $pembayaran = null;
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $pelanggan, $jumlahBulan, &$pembayaran) {
                $pembayaran = Pembayaran::create($validated);

                $currentMasaAktif = $pelanggan->masa_aktif ? Carbon::parse($pelanggan->masa_aktif) : now();

                if ($currentMasaAktif->isPast()) {
                    $newMasaAktif = now()->addMonths($jumlahBulan);
                } else {
                    $newMasaAktif = $currentMasaAktif->addMonths($jumlahBulan);
                }

                $pelanggan->update([
                    'masa_aktif' => $newMasaAktif->toDateString(),
                    'status_aktif' => 'Aktif',
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Gagal menyimpan pembayaran', ['error' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', 'Terjadi kesalahan saat menyimpan pembayaran. Silakan coba lagi.');
        }

        // MikroTik sync (di luar transaction — non-critical)
        $tipePaket = 'Hotspot';
        if ($pelanggan->paket && stripos((string) $pelanggan->paket->nama_paket, 'pppoe') !== false) {
            $tipePaket = 'PPPoE';
        }

        try {
            $mikrotikService->setPelangganStateByType($pelanggan, true, $tipePaket);
        } catch (\Throwable $e) {
            Log::warning('MikroTik sync gagal setelah pembayaran', ['error' => $e->getMessage()]);
        }

        // --- KIRIM KWITANSI WA LANGSUNG ---
        if ($pelanggan && !empty($pelanggan->no_hp)) {
            $noHp = $pelanggan->no_hp;
            if (str_starts_with($noHp, '0')) {
                $noHp = '62' . substr($noHp, 1);
            }

            $nominalFormat = number_format($pembayaran->nominal, 0, ',', '.');
            $masaAktifFormat = Carbon::parse($pelanggan->masa_aktif)->translatedFormat('d F Y');

            $pesan = "*KWITANSI PEMBAYARAN*\n\n";
            $pesan .= "Yth. {$pelanggan->nama_pelanggan},\n";
            $pesan .= "Terima kasih, pembayaran internet Anda telah kami terima.\n\n";
            $pesan .= "Nominal: Rp {$nominalFormat}\n";
            $pesan .= "Paket: " . ($pelanggan->paket->nama_paket ?? '-') . "\n";
            $pesan .= "Masa Aktif s/d: {$masaAktifFormat}\n\n";
            $pesan .= "Status internet Anda sudah AKTIF. Terima kasih!";

            try {
                $response = Http::asForm()
                    ->withHeaders([
                        'Authorization' => config('services.whatsapp.token'),
                    ])->post(config('services.whatsapp.url'), [
                        'target' => $noHp,
                        'message' => $pesan,
                    ]);

                if ($response->successful() && isset($response->json()['status']) && $response->json()['status'] == 'success') {
                    $pembayaran->update(['status_notifikasi' => 'Terkirim']);
                } else {
                    $pembayaran->update(['status_notifikasi' => 'Gagal']);
                }
            } catch (\Exception $e) {
                Log::error('Gagal kirim kwitansi WA: ' . $e->getMessage());
                $pembayaran->update(['status_notifikasi' => 'Gagal']);
            }
        }
        // ----------------------------------

        // Audit trail removed — logging handled by traffic log (log_aktivitas) per class diagram

        return redirect()->back()->with('success', 'Pembayaran berhasil dicatat dan kwitansi WA sedang dikirim otomatis!');
    }

    public function edit(Pembayaran $pembayaran): View
    {
        return view('pembayaran.edit', [
            'pembayaran' => $pembayaran,
            'pelangganOptions' => Pelanggan::with('paket')->orderBy('nama_pelanggan')->get(['id_pelanggan', 'nama_pelanggan', 'username_mikrotik', 'masa_aktif', 'id_paket']),
        ]);
    }

    public function update(Request $request, Pembayaran $pembayaran): RedirectResponse
    {
        $adminId = (int) $request->session()->get('admin_id');
        if ($adminId <= 0) {
            return redirect()->route('login.form')->with('error', 'Sesi admin tidak valid, silakan login ulang.');
        }

        $validated = $request->validate([
            'id_pelanggan' => ['required', 'exists:pelanggan,id_pelanggan'],
            'tanggal_bayar' => ['required', 'date'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'periode_tagihan' => ['required', 'string', 'max:30'],
            'status_notifikasi' => ['required', 'in:Pending,Terkirim,Gagal'],
        ]);

        $validated['id_admin'] = $adminId;

        $pembayaran->update($validated);

        return redirect()->route('pembayaran.index')->with('success', 'Pembayaran berhasil diperbarui.');
    }

    public function destroy(Pembayaran $pembayaran): RedirectResponse
    {
        $pembayaran->delete();

        return redirect()->route('pembayaran.index')->with('success', 'Pembayaran berhasil dihapus.');
    }

    public function sendNotifications(Request $request)
    {
        if ($request->ajax() || $request->expectsJson()) {
            session()->reflash();
        }
        try {
            Artisan::call('app:kirim-tagihan-wa');
            $output = Artisan::output();
            $terminalLog = trim($output) !== '' ? trim($output) : 'Tidak ada antrean log tagihan baru.';

            Log::info('Manual trigger: Kirim notifikasi tagihan via WhatsApp', [
                'triggered_at' => now(),
                'output' => $terminalLog,
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Pengingat notifikasi tagihan selesai dijalankan.',
                    'terminal_log' => $terminalLog,
                ]);
            }

            return back()
                ->with('success', 'Pengingat notifikasi tagihan selesai dijalankan, cek log untuk detail.')
                ->with('terminal_log', $terminalLog);
        } catch (\Throwable $e) {
            Log::error('Error saat menjalankan kirim tagihan command: ' . $e->getMessage());

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Terjadi error saat menjalankan proses notifikasi.',
                    'terminal_log' => 'Error: Terjadi kesalahan saat menjalankan proses notifikasi. Silakan cek log server untuk detail.',
                ], 500);
            }

            return back()
                ->with('error', 'Terjadi error saat menjalankan proses notifikasi. Silakan cek log untuk detail.');
        }
    }

    public function cetakStruk($id)
    {
        $pembayaran = Pembayaran::with(['pelanggan', 'paket', 'admin'])
            ->findOrFail($id);

        $pdf = Pdf::loadView('pembayaran.struk', compact('pembayaran'));

        return $pdf->stream('Struk_Pembayaran_' . $pembayaran->id_pembayaran . '.pdf');
    }
}
