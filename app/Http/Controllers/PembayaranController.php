<?php

namespace App\Http\Controllers;

use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Services\MikrotikService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PembayaranController extends Controller
{
    private function normalizeWhatsAppNumber(?string $rawNumber): string
    {
        $digits = preg_replace('/\D+/', '', (string) $rawNumber) ?? '';

        if ($digits === '') {
            return '';
        }

        // Konversi format lokal 08xxxx menjadi 628xxxx.
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        }

        // Jika masih tanpa prefix negara, default ke Indonesia.
        if (!str_starts_with($digits, '62')) {
            $digits = '62' . ltrim($digits, '0');
        }

        return $digits;
    }

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
            'pelangganOptions' => Pelanggan::with('paket')->orderBy('nama_pelanggan')->get(['id_pelanggan', 'nama_pelanggan', 'username_mikrotik', 'id_paket']),
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
            'pelangganOptions' => Pelanggan::orderBy('nama_pelanggan')->get(),
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
            'tanggal_bayar' => ['required', 'date'],
            'nominal' => ['required', 'numeric', 'min:100'],
            'periode_tagihan' => ['required', 'string', 'max:30'],
            'status_notifikasi' => ['nullable', 'in:Pending,Send,Failed'],
        ]);

        $validated['id_admin'] = $adminId;
        $validated['status_notifikasi'] = $validated['status_notifikasi'] ?? 'Pending';

        $masaAktifBaru = null;
        $pelanggan = null;
        $pembayaranBaru = null;

        DB::transaction(function () use (&$masaAktifBaru, &$pelanggan, &$pembayaranBaru, $validated) {
            $pembayaranBaru = Pembayaran::create($validated);

            $pelanggan = Pelanggan::findOrFail($validated['id_pelanggan']);

            $today = now()->startOfDay();
            $masaAktifLama = $pelanggan->masa_aktif ? $pelanggan->masa_aktif->copy()->startOfDay() : null;

            if (!$masaAktifLama || $masaAktifLama->lt($today)) {
                $masaAktifBaru = $today->copy()->addDays(30);
            } else {
                $masaAktifBaru = $masaAktifLama->copy()->addDays(30);
            }

            $pelanggan->update([
                'masa_aktif' => $masaAktifBaru->toDateString(),
                'status_aktif' => 'Aktif',
            ]);
        });

        $sync = $mikrotikService->setPelangganState($pelanggan->fresh(), true);
        $notifikasiStatus = 'Failed';
        $notifikasiMessage = null;

        try {
            if ($pembayaranBaru) {
                $waEnabled = (bool) config('services.whatsapp.enabled', true);
                $apiUrl = (string) config('services.whatsapp.url', 'https://api.fonnte.com/send');
                $apiToken = (string) config('services.whatsapp.token', '');
                $countryCode = (string) config('services.whatsapp.country_code', '62');
                $targetNumber = $this->normalizeWhatsAppNumber($pelanggan->no_hp ?? '');

                if (!$waEnabled) {
                    $notifikasiStatus = 'Failed';
                    $notifikasiMessage = 'WA gateway dinonaktifkan melalui konfigurasi.';
                } elseif ($targetNumber === '') {
                    $notifikasiStatus = 'Failed';
                    $notifikasiMessage = 'Nomor WhatsApp pelanggan kosong / tidak valid.';
                } elseif ($apiToken === '') {
                    $notifikasiStatus = 'Failed';
                    $notifikasiMessage = 'Token WhatsApp API belum diatur.';
                } else {
                    $pesanResi = "Halo {$pelanggan->nama_pelanggan}, pembayaran Anda telah diterima.\n"
                        . "Periode: {$pembayaranBaru->periode_tagihan}\n"
                        . 'Nominal: Rp ' . number_format((float) $pembayaranBaru->nominal, 0, ',', '.') . "\n"
                        . 'Tanggal: ' . optional($pembayaranBaru->tanggal_bayar)->format('d/m/Y') . "\n"
                        . "Status: LUNAS";

                    $response = Http::asForm()
                        ->timeout(20)
                        ->withHeaders([
                            // Fonnte membutuhkan token langsung di header Authorization (bukan Bearer).
                            'Authorization' => $apiToken,
                            'Accept' => 'application/json',
                        ])
                        ->post($apiUrl, [
                            'target' => $targetNumber,
                            'message' => $pesanResi,
                            'countryCode' => $countryCode,
                        ]);

                    if ($response->successful()) {
                        $payload = $response->json();
                        $apiStatus = $payload['status'] ?? $payload['success'] ?? true;
                        $notifikasiStatus = ($apiStatus === true || $apiStatus === 'true' || $apiStatus === 1 || $apiStatus === '1')
                            ? 'Send'
                            : 'Failed';
                        $notifikasiMessage = is_array($payload)
                            ? (string) ($payload['reason'] ?? $payload['message'] ?? $response->body())
                            : $response->body();
                    } else {
                        $notifikasiStatus = 'Failed';
                        $notifikasiMessage = 'HTTP ' . $response->status() . ': ' . $response->body();
                    }
                }

                if ($notifikasiStatus !== 'Send') {
                    Log::warning('WhatsApp receipt notification failed', [
                        'id_pembayaran' => $pembayaranBaru?->id_pembayaran,
                        'id_pelanggan' => $pelanggan?->id_pelanggan,
                        'target' => $targetNumber,
                        'reason' => $notifikasiMessage,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('WhatsApp receipt notification failed', [
                'id_pembayaran' => $pembayaranBaru?->id_pembayaran,
                'id_pelanggan' => $pelanggan?->id_pelanggan,
                'error' => $e->getMessage(),
            ]);
            $notifikasiStatus = 'Failed';
            $notifikasiMessage = $e->getMessage();
        }

        if ($pembayaranBaru) {
            $pembayaranBaru->update([
                'status_notifikasi' => $notifikasiStatus,
            ]);
        }

        $redirect = redirect()->route('pembayaran.index')
            ->with('success', 'Pembayaran berhasil diproses. Masa aktif diperpanjang sampai ' . $masaAktifBaru->format('d/m/Y') . '.');

        if (!$sync['success']) {
            $redirect->with('warning', $sync['message']);
        }

        if ($notifikasiStatus === 'Send') {
            $redirect->with('success', 'Pembayaran berhasil diproses dan resi WhatsApp terkirim. Masa aktif diperpanjang sampai ' . $masaAktifBaru->format('d/m/Y') . '.');
        } else {
            $redirect->with('warning', 'Pembayaran tersimpan, tetapi resi WhatsApp gagal dikirim. Status notifikasi: Failed. ' . ($notifikasiMessage ? 'Detail: ' . $notifikasiMessage : ''));
        }

        return $redirect;
    }

    public function edit(Pembayaran $pembayaran): View
    {
        return view('pembayaran.edit', [
            'pembayaran' => $pembayaran,
            'pelangganOptions' => Pelanggan::orderBy('nama_pelanggan')->get(),
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
            // Jalankan Artisan command yang sudah teruji
            Artisan::call('app:kirim-tagihan-wa');
            $output = Artisan::output();

            Log::info('Manual trigger: Kirim notifikasi tagihan via WhatsApp', [
                'triggered_at' => now(),
                'output' => $output,
            ]);

            return redirect()->route('pembayaran.index')->with(
                'success',
                'Proses sinkronisasi tagihan selesai dijalankan. Cek log untuk detail.'
            );
        } catch (\Exception $e) {
            Log::error('Error saat menjalankan kirim tagihan command: ' . $e->getMessage());

            return redirect()->route('pembayaran.index')->with(
                'danger',
                'Terjadi error saat menjalankan proses: ' . $e->getMessage()
            );
        }
    }

    /**
     * Cetak Struk/Kwitansi PDF per transaksi pembayaran
     */
    public function cetakStruk($id)
    {
        $pembayaran = Pembayaran::with(['pelanggan.paket', 'admin'])
            ->findOrFail($id);

        $pdf = Pdf::loadView('pembayaran.struk', compact('pembayaran'));
        return $pdf->stream('Struk_Pembayaran_' . $pembayaran->id_pembayaran . '.pdf');
    }
}
