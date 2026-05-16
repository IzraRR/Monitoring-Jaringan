<?php

namespace App\Http\Controllers;

use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Services\MikrotikService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PembayaranController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $pembayaran = Pembayaran::with(['pelanggan'])
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
            'pelangganOptions' => Pelanggan::orderBy('nama_pelanggan')->get(['id_pelanggan', 'nama_pelanggan', 'username_mikrotik']),
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
            'nominal' => ['required', 'numeric', 'min:0'],
            'periode_tagihan' => ['required', 'string', 'max:30'],
            'status_notifikasi' => ['nullable', 'in:Pending,Send,Failed'],
        ]);

        $validated['id_admin'] = $adminId;
        $validated['status_notifikasi'] = $validated['status_notifikasi'] ?? 'Pending';

        $masaAktifBaru = null;
        $pelanggan = null;

        DB::transaction(function () use (&$masaAktifBaru, &$pelanggan, $validated) {
            Pembayaran::create($validated);

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
        $redirect = redirect()->route('pembayaran.index')
            ->with('success', 'Pembayaran berhasil diproses. Masa aktif diperpanjang sampai ' . $masaAktifBaru->format('d/m/Y') . '.');

        if (!$sync['success']) {
            $redirect->with('warning', $sync['message']);
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
        $updated = Pembayaran::where('status_notifikasi', 'Pending')->update([
            'status_notifikasi' => 'Send',
            'updated_at' => now(),
        ]);

        return redirect()->route('pembayaran.index')->with(
            'success',
            $updated > 0
                ? "Notifikasi tagihan berhasil diproses untuk {$updated} transaksi."
                : 'Tidak ada data notifikasi Pending untuk dikirim.'
        );
    }
}
