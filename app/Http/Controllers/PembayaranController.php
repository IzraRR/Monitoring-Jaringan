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

        $pembayaran = Pembayaran::with(['pelanggan.paket', 'admin'])
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
            'pelangganOptions' => Pelanggan::orderBy('nama_pelanggan')->get(['id_pelanggan', 'nama_pelanggan', 'username_mikrotik', 'masa_aktif']),
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
            'status_notifikasi' => ['nullable', 'in:Pending,Send,Failed'],
        ]);

        $validated['id_admin'] = $adminId;
        $validated['status_notifikasi'] = 'Pending';
        $validated['tanggal_bayar'] = now()->toDateString();

        $pembayaran = Pembayaran::create($validated);

        $pelanggan = Pelanggan::with('paket')->find($validated['id_pelanggan']);

        if ($pelanggan) {
            $currentMasaAktif = $pelanggan->masa_aktif ? Carbon::parse($pelanggan->masa_aktif) : now();

            if ($currentMasaAktif->isPast()) {
                $newMasaAktif = now()->addMonth();
            } else {
                $newMasaAktif = $currentMasaAktif->addMonth();
            }

            $pelanggan->update([
                'masa_aktif' => $newMasaAktif->toDateString(),
                'status_aktif' => 'Aktif',
            ]);

            $tipePaket = 'Hotspot';
            if ($pelanggan->paket && stripos((string) $pelanggan->paket->nama_paket, 'pppoe') !== false) {
                $tipePaket = 'PPPoE';
            }

            $mikrotikService->setPelangganStateByType($pelanggan, true, $tipePaket);
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
                }
            } catch (\Exception $e) {
                Log::error('Gagal kirim kwitansi WA: ' . $e->getMessage());
            }
        }
        // ----------------------------------

        return redirect()->back()->with('success', 'Pembayaran berhasil dicatat dan kwitansi WA sedang dikirim otomatis!');
    }

    public function edit(Pembayaran $pembayaran): View
    {
        return view('pembayaran.edit', [
            'pembayaran' => $pembayaran,
            'pelangganOptions' => Pelanggan::orderBy('nama_pelanggan')->get(['id_pelanggan', 'nama_pelanggan', 'username_mikrotik', 'masa_aktif']),
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
            'status_notifikasi' => ['required', 'in:Pending,Send,Failed'],
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

    public function sendNotifications(): RedirectResponse
    {
        try {
            Artisan::call('app:kirim-tagihan-wa');
            $output = Artisan::output();
            $terminalLog = trim($output) !== '' ? trim($output) : 'Tidak ada antrean log tagihan baru.';

            Log::info('Manual trigger: Kirim notifikasi tagihan via WhatsApp', [
                'triggered_at' => now(),
                'output' => $terminalLog,
            ]);

            return back()
                ->with('success', 'Pengingat notifikasi tagihan selesai dijalankan, cek log untuk detail.')
                ->with('terminal_log', $terminalLog);
        } catch (\Throwable $e) {
            Log::error('Error saat menjalankan kirim tagihan command: ' . $e->getMessage());

            return back()
                ->with('danger', 'Terjadi error saat menjalankan proses: ' . $e->getMessage());
        }
    }

    public function cetakStruk($id)
    {
        $pembayaran = Pembayaran::with(['pelanggan.paket', 'admin'])
            ->findOrFail($id);

        $pdf = Pdf::loadView('pembayaran.struk', compact('pembayaran'));

        return $pdf->stream('Struk_Pembayaran_' . $pembayaran->id_pembayaran . '.pdf');
    }
}
