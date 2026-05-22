<?php

namespace App\Http\Controllers;

use App\Models\PaketBandwidth;
use App\Models\Pelanggan;
use App\Services\MikrotikService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PelangganController extends Controller
{
    private function detectTypeFromPaket(Pelanggan $pelanggan): string
    {
        $pelanggan->loadMissing('paket');
        $namaPaket = $pelanggan->paket?->nama_paket ?? '';

        return Str::contains(strtolower($namaPaket), 'pppoe') ? 'PPPoE' : 'Hotspot';
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $pelanggan = Pelanggan::with('paket')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('nama_pelanggan', 'like', "%{$search}%")
                        ->orWhere('username_mikrotik', 'like', "%{$search}%")
                        ->orWhere('id_pelanggan', 'like', "%{$search}%");
                });
            })
            ->latest('id_pelanggan')
            ->paginate(10)
            ->withQueryString();

        return view('pelanggan.index', [
            'pelanggan' => $pelanggan,
            'search' => $search,
            'summary' => [
                'total' => Pelanggan::count(),
                'aktif' => Pelanggan::where('status_aktif', 'Aktif')->count(),
                'locked' => Pelanggan::where('status_aktif', 'Locked')->count(),
                'nonaktif' => Pelanggan::where('status_aktif', 'Nonaktif')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('pelanggan.create', [
            'paketOptions' => PaketBandwidth::orderBy('nama_paket')->get(),
        ]);
    }

    public function store(Request $request, MikrotikService $mikrotikService): RedirectResponse
    {
        $validated = $request->validate([
            'id_paket' => ['required', 'exists:paket_bandwidth,id_paket'],
            'nama_pelanggan' => ['required', 'string', 'max:100'],
            'no_hp' => ['required', 'string', 'max:20'],
            'username_mikrotik' => ['required', 'string', 'max:50', 'unique:pelanggan,username_mikrotik'],
            'password_mikrotik' => ['required', 'string', 'max:50'],
            'masa_aktif' => ['nullable', 'date'],
            'status_aktif' => ['required', 'in:Aktif,Nonaktif,Locked'],
        ]);

        try {
            $pelanggan = Pelanggan::create($validated);
            $pelanggan->load('paket');

            $namaPaket = strtolower(trim($pelanggan->paket->nama_paket ?? ''));
            if (stripos($namaPaket, 'pppoe') !== false) {
                $sync = $mikrotikService->tambahUserPPPoE($pelanggan);
            } else {
                $sync = $mikrotikService->tambahUserHotspot($pelanggan);
            }

            $redirect = redirect()->route('pelanggan.index')->with('success', 'Pelanggan berhasil ditambahkan.');
            if (!$sync['success']) {
                $redirect->with('warning', $sync['message']);
            }

            return $redirect;
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', 'Gagal menambahkan pelanggan: ' . $e->getMessage());
        }
    }

    public function edit(Pelanggan $pelanggan): View
    {
        return view('pelanggan.edit', [
            'pelanggan' => $pelanggan,
            'paketOptions' => PaketBandwidth::orderBy('nama_paket')->get(),
        ]);
    }

    public function update(Request $request, Pelanggan $pelanggan, MikrotikService $mikrotikService): RedirectResponse
    {
        $validated = $request->validate([
            'id_paket' => ['required', 'exists:paket_bandwidth,id_paket'],
            'nama_pelanggan' => ['required', 'string', 'max:100'],
            'no_hp' => ['required', 'string', 'max:20'],
            'username_mikrotik' => ['required', 'string', 'max:50', 'unique:pelanggan,username_mikrotik,' . $pelanggan->id_pelanggan . ',id_pelanggan'],
            'password_mikrotik' => ['nullable', 'string', 'max:50'],
            'masa_aktif' => ['nullable', 'date'],
            'status_aktif' => ['required', 'in:Aktif,Nonaktif,Locked'],
        ]);

        if (empty($validated['password_mikrotik'])) {
            unset($validated['password_mikrotik']);
        }

        try {
            // Simpan data lama SEBELUM update
            $oldUsername = $pelanggan->username_mikrotik;
            $oldIdPaket = $pelanggan->id_paket;
            
            // Deteksi tipe LAMA sebelum update
            $pelanggan->load('paket');
            $oldTipePaket = 'Hotspot';
            if ($pelanggan->paket && stripos($pelanggan->paket->nama_paket, 'pppoe') !== false) {
                $oldTipePaket = 'PPPoE';
            }

            // Update pelanggan dengan data baru
            $pelanggan->update($validated);
            $pelanggan->refresh();
            $pelanggan->load('paket');

            // Deteksi tipe BARU setelah update
            $newTipePaket = 'Hotspot';
            if ($pelanggan->paket && stripos($pelanggan->paket->nama_paket, 'pppoe') !== false) {
                $newTipePaket = 'PPPoE';
            }

            // Cek apakah ada perubahan tipe paket
            $paketChanged = ($oldIdPaket != $validated['id_paket']);
            $tipeChanged = ($oldTipePaket !== $newTipePaket);

            if ($tipeChanged) {
                // Jika tipe berubah (Hotspot <-> PPPoE), hapus dari tipe lama dan tambah ke tipe baru
                Log::info("Pelanggan {$oldUsername} mengubah tipe: {$oldTipePaket} -> {$newTipePaket}");
                
                // Hapus dari tipe lama
                $deleteSync = $mikrotikService->syncPelangganDeletedByType($oldUsername, $oldTipePaket);
                if (!$deleteSync['success']) {
                    Log::warning("Gagal hapus dari {$oldTipePaket}: " . $deleteSync['message']);
                }
                
                // Tambah ke tipe baru
                $addSync = $mikrotikService->syncPelangganCreatedByType($pelanggan, $newTipePaket);
                
                $redirect = redirect()->route('pelanggan.index')
                    ->with('success', "Pelanggan berhasil diperbarui dan dipindahkan dari {$oldTipePaket} ke {$newTipePaket}.");
                
                if (!$addSync['success']) {
                    $redirect->with('warning', $addSync['message']);
                }
            } else {
                // Jika tipe sama, hanya update data
                $sync = $mikrotikService->syncPelangganUpdatedByType($pelanggan, $oldUsername, $newTipePaket);
                
                $redirect = redirect()->route('pelanggan.index')->with('success', 'Pelanggan berhasil diperbarui.');
                if (!$sync['success']) {
                    $redirect->with('warning', $sync['message']);
                }
            }

            return $redirect;
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', 'Gagal memperbarui pelanggan: ' . $e->getMessage());
        }
    }

    public function destroy(Pelanggan $pelanggan, MikrotikService $mikrotikService): RedirectResponse
    {
        try {
            $username = $pelanggan->username_mikrotik;

            $tipe = $this->detectTypeFromPaket($pelanggan);

            $pelanggan->delete();

            $sync = $mikrotikService->syncPelangganDeletedByType($username, $tipe);

            $redirect = redirect()->route('pelanggan.index')->with('success', 'Pelanggan berhasil dihapus.');
            if (!$sync['success']) {
                $redirect->with('warning', $sync['message']);
            }

            return $redirect;
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Gagal menghapus pelanggan: ' . $e->getMessage());
        }
    }

    public function syncMikrotik(MikrotikService $mikrotikService): RedirectResponse
    {
        $pelangganList = Pelanggan::with('paket')->get();
        $berhasil = 0;
        $gagal = 0;

        foreach ($pelangganList as $p) {
            try {
                $namaPaket = strtolower(trim($p->paket->nama_paket ?? ''));

                Log::info("SYNC MIKROTIK -> User: {$p->username_mikrotik} | Paket: {$namaPaket}");

                if (stripos($namaPaket, 'pppoe') !== false) {
                    $sync = $mikrotikService->tambahUserPPPoE($p);
                } else {
                    $sync = $mikrotikService->tambahUserHotspot($p);
                }

                if ($sync['success']) {
                    $berhasil++;
                } else {
                    Log::error("GAGAL SYNC User {$p->username_mikrotik}: " . ($sync['message'] ?? 'Unknown error'));
                    $gagal++;
                }
            } catch (\Throwable $e) {
                Log::error("GAGAL SYNC User {$p->username_mikrotik}: " . $e->getMessage());
                $gagal++;
            }
        }

        return redirect()->route('pelanggan.index')->with(
            $gagal === 0 ? 'success' : 'warning',
            "Sinkronisasi selesai! Berhasil: {$berhasil}, Gagal: {$gagal}."
        );
    }

    public function toggleLock(Pelanggan $pelanggan, MikrotikService $mikrotikService): RedirectResponse
    {
        try {
            $isLocked = strtolower((string) $pelanggan->status_aktif) === 'locked';
            $tipe = $this->detectTypeFromPaket($pelanggan);

            if ($isLocked) {
                $pelanggan->update(['status_aktif' => 'Aktif']);
                $sync = $mikrotikService->setPelangganStateByType($pelanggan->fresh(), true, $tipe);
                $message = 'Pelanggan dibuka kembali.';
            } else {
                $pelanggan->update(['status_aktif' => 'Locked']);
                $sync = $mikrotikService->setPelangganStateByType($pelanggan->fresh(), false, $tipe);
                $message = 'Pelanggan berhasil dikunci.';
            }

            $redirect = redirect()->route('pelanggan.index')->with('success', $message);
            if (!$sync['success']) {
                $redirect->with('warning', $sync['message']);
            }

            return $redirect;
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Gagal mengubah status pelanggan di MikroTik: ' . $e->getMessage());
        }
    }

    public function disconnect(Pelanggan $pelanggan, MikrotikService $mikrotikService): RedirectResponse
    {
        try {
            $tipe = $this->detectTypeFromPaket($pelanggan);
            $syncDisconnect = $mikrotikService->disconnectActiveSessionByType($pelanggan, $tipe);

            $redirect = redirect()->route('pelanggan.index')->with('success', 'Sesi pelanggan berhasil diputus paksa (Kick).');

            if (!$syncDisconnect['success']) {
                $redirect->with('warning', $syncDisconnect['message']);
            }

            return $redirect;
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Gagal memutuskan sesi di MikroTik: ' . $e->getMessage());
        }
    }
}
