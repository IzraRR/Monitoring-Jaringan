<?php

namespace App\Http\Controllers;

use App\Models\PaketBandwidth;
use App\Models\Pelanggan;
use App\Services\MikrotikService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Str;

class PelangganController extends Controller
{
    /**
     * Deteksi tipe koneksi berdasarkan nama paket pelanggan.
     * Jika nama paket mengandung "pppoe" (case-insensitive), return "PPPoE".
     * Sebaliknya, return "Hotspot".
     * 
     * @param Pelanggan $pelanggan
     * @return string 'Hotspot' atau 'PPPoE'
     */
    private function detectTypeFromPaket(Pelanggan $pelanggan): string
    {
        $pelanggan->loadMissing('paket');
        $namaPaket = $pelanggan->paket?->nama_paket ?? '';
        
        // Convert ke lowercase terlebih dahulu untuk pendeteksian case-insensitive yang reliable
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
            $pelanggan->loadMissing('paket');
            
            // Deteksi tipe koneksi dari nama paket secara otomatis
            $tipe = $this->detectTypeFromPaket($pelanggan);
            
            // Sinkronkan ke MikroTik dengan tipe yang terdeteksi
            $sync = $mikrotikService->syncPelangganCreatedByType($pelanggan, $tipe);

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
            'password_mikrotik' => ['required', 'string', 'max:50'],
            'masa_aktif' => ['nullable', 'date'],
            'status_aktif' => ['required', 'in:Aktif,Nonaktif,Locked'],
        ]);

        try {
            $oldUsername = $pelanggan->username_mikrotik;
            $pelanggan->update($validated);
            $pelanggan->refresh();
            
            // Deteksi tipe koneksi dari nama paket secara otomatis
            $tipe = $this->detectTypeFromPaket($pelanggan);
            
            // Sinkronkan update ke MikroTik dengan tipe yang terdeteksi
            $sync = $mikrotikService->syncPelangganUpdatedByType($pelanggan, $oldUsername, $tipe);

            $redirect = redirect()->route('pelanggan.index')->with('success', 'Pelanggan berhasil diperbarui.');
            if (!$sync['success']) {
                $redirect->with('warning', $sync['message']);
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
            
            // Deteksi tipe koneksi dari nama paket sebelum data dihapus
            $tipe = $this->detectTypeFromPaket($pelanggan);
            
            $pelanggan->delete();
            
            // Hapus dari MikroTik dengan tipe koneksi yang terdeteksi
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
        $result = $mikrotikService->syncAllPelanggan();
        $flashKey = $result['success'] ? 'success' : 'warning';

        return redirect()->route('pelanggan.index')->with($flashKey, $result['message']);
    }

    public function toggleLock(Pelanggan $pelanggan, MikrotikService $mikrotikService): RedirectResponse
    {
        try {
            $isLocked = strtolower((string) $pelanggan->status_aktif) === 'locked';
            
            // Deteksi tipe koneksi dari nama paket
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
            // Deteksi tipe koneksi dari nama paket
            $tipe = $this->detectTypeFromPaket($pelanggan);
            
            // Putus sesi aktif dengan tipe koneksi yang terdeteksi
            $syncDisconnect = $mikrotikService->disconnectActiveSessionByType($pelanggan, $tipe);

            // Update status menjadi nonaktif dan disable user di MikroTik
            $pelanggan->update(['status_aktif' => 'Nonaktif']);
            $syncDisable = $mikrotikService->setPelangganStateByType($pelanggan->fresh(), false, $tipe);

            $redirect = redirect()->route('pelanggan.index')->with('success', 'User berhasil dinonaktifkan dan sesi aktif diputus.');

            if (!$syncDisconnect['success']) {
                $redirect->with('warning', $syncDisconnect['message']);
            } elseif (!$syncDisable['success']) {
                $redirect->with('warning', $syncDisable['message']);
            }

            return $redirect;
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Gagal memutuskan sesi di MikroTik: ' . $e->getMessage());
        }
    }
}
