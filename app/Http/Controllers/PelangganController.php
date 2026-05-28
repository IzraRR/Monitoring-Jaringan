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
            'alamat' => ['nullable', 'string', 'max:255'],
            'no_hp' => ['required', 'string', 'max:20'],
            'username_mikrotik' => ['required', 'string', 'max:50', 'unique:pelanggan,username_mikrotik'],
            'password_mikrotik' => ['required', 'string', 'max:50'],
            'masa_aktif' => ['nullable', 'date'],
            'status_aktif' => ['required', 'in:Aktif,Nonaktif,Locked'],
        ]);

        try {
            return \DB::transaction(function () use ($validated, $mikrotikService) {
                $pelanggan = Pelanggan::create($validated);
                $pelanggan->load('paket');

                $namaPaket = strtolower(trim($pelanggan->paket->nama_paket ?? ''));
                if (stripos($namaPaket, 'pppoe') !== false) {
                    $sync = $mikrotikService->tambahUserPPPoE($pelanggan);
                } else {
                    $sync = $mikrotikService->tambahUserHotspot($pelanggan);
                }

                if (!$sync['success']) {
                    throw new \RuntimeException($sync['message']);
                }

                return redirect()->route('pelanggan.index')->with('success', "Pelanggan {$pelanggan->nama_pelanggan} berhasil ditambahkan.");
            });
        } catch (\Throwable $e) {
            Log::error('Gagal menambahkan pelanggan', ['error' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', 'Gagal menambahkan pelanggan. Detail: ' . $this->getCleanErrorMessage($e));
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
            'alamat' => ['nullable', 'string', 'max:255'],
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
            return \DB::transaction(function () use ($validated, $pelanggan, $mikrotikService) {
                // Simpan data lama SEBELUM update untuk mendeteksi perubahan
                $oldData = $pelanggan->only(['nama_pelanggan', 'alamat', 'no_hp', 'username_mikrotik', 'status_aktif', 'id_paket']);
                $oldUsername = $pelanggan->username_mikrotik;
                
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

                // Deteksi field apa saja yang berubah untuk dicantumkan di notifikasi sukses
                $changedFields = [];
                foreach ($oldData as $key => $oldValue) {
                    $newValue = $pelanggan->getAttribute($key);
                    if ($oldValue != $newValue) {
                        $fieldName = str_replace('_', ' ', $key);
                        $fieldName = ucwords($fieldName);
                        if ($key === 'id_paket') $fieldName = 'Paket';
                        if ($key === 'status_aktif') $fieldName = 'Status';
                        if ($key === 'no_hp') $fieldName = 'No HP';
                        $changedFields[] = $fieldName;
                    }
                }

                $changesDesc = '';
                if (!empty($changedFields)) {
                    $changesDesc = ' (' . implode(', ', $changedFields) . ' diperbarui)';
                }

                // Cek apakah ada perubahan tipe paket
                $tipeChanged = ($oldTipePaket !== $newTipePaket);

                if ($tipeChanged) {
                    // Hapus dari tipe lama
                    $deleteSync = $mikrotikService->syncPelangganDeletedByType($oldUsername, $oldTipePaket);
                    if (!$deleteSync['success']) {
                        throw new \RuntimeException("Gagal menghapus sesi lama di MikroTik: " . $deleteSync['message']);
                    }
                    
                    // Tambah ke tipe baru
                    $addSync = $mikrotikService->syncPelangganCreatedByType($pelanggan, $newTipePaket);
                    if (!$addSync['success']) {
                        throw new \RuntimeException("Gagal menambahkan sesi baru di MikroTik: " . $addSync['message']);
                    }
                    
                    $message = "Pelanggan {$pelanggan->nama_pelanggan} berhasil diperbarui{$changesDesc} dan dipindahkan dari {$oldTipePaket} ke {$newTipePaket}.";
                } else {
                    // Jika tipe sama, hanya update data
                    $sync = $mikrotikService->syncPelangganUpdatedByType($pelanggan, $oldUsername, $newTipePaket);
                    if (!$sync['success']) {
                        throw new \RuntimeException($sync['message']);
                    }
                    $message = "Pelanggan {$pelanggan->nama_pelanggan} berhasil diperbarui{$changesDesc}.";
                }

                return redirect()->route('pelanggan.index')->with('success', $message);
            });
        } catch (\Throwable $e) {
            Log::error('Gagal memperbarui pelanggan', ['error' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', 'Gagal memperbarui pelanggan. Detail: ' . $this->getCleanErrorMessage($e));
        }
    }

    public function destroy(Pelanggan $pelanggan, MikrotikService $mikrotikService): RedirectResponse
    {
        try {
            $username = $pelanggan->username_mikrotik;
            $tipe = $this->detectTypeFromPaket($pelanggan);

            // Coba hapus di MikroTik dulu
            $sync = $mikrotikService->syncPelangganDeletedByType($username, $tipe);

            if (!$sync['success']) {
                return redirect()->back()->with('error', 'Gagal menghapus pelanggan di MikroTik: ' . $sync['message']);
            }

            // Jika sukses di MikroTik, baru hapus dari DB lokal
            $pelanggan->delete();

            return redirect()->route('pelanggan.index')->with('success', 'Pelanggan berhasil dihapus.');
        } catch (\Throwable $e) {
            Log::error('Gagal menghapus pelanggan', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Gagal menghapus pelanggan. Detail: ' . $this->getCleanErrorMessage($e));
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
        $oldStatus = $pelanggan->status_aktif;
        try {
            $isLocked = strtolower((string) $oldStatus) === 'locked';
            $tipe = $this->detectTypeFromPaket($pelanggan);
            $newStatus = $isLocked ? 'Aktif' : 'Locked';

            // Coba update di MikroTik dulu
            $sync = $mikrotikService->setPelangganStateByType($pelanggan, $isLocked, $tipe);

            if (!$sync['success']) {
                return redirect()->back()->with('error', 'Gagal mengubah status di MikroTik: ' . $sync['message']);
            }

            // Jika MikroTik sukses, barulah update database lokal
            $pelanggan->update(['status_aktif' => $newStatus]);
            $message = $isLocked ? 'Pelanggan dibuka kembali.' : 'Pelanggan berhasil dikunci.';

            return redirect()->route('pelanggan.index')->with('success', $message);
        } catch (\Throwable $e) {
            Log::error('Gagal mengubah status pelanggan di MikroTik', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Gagal mengubah status pelanggan. Detail: ' . $this->getCleanErrorMessage($e));
        }
    }

    public function disconnect(Pelanggan $pelanggan, MikrotikService $mikrotikService): RedirectResponse
    {
        try {
            $tipe = $this->detectTypeFromPaket($pelanggan);
            $syncDisconnect = $mikrotikService->disconnectActiveSessionByType($pelanggan, $tipe);

            if (!$syncDisconnect['success']) {
                return redirect()->back()->with('error', 'Gagal memutuskan sesi di MikroTik: ' . $syncDisconnect['message']);
            }

            return redirect()->route('pelanggan.index')->with('success', 'Sesi pelanggan berhasil diputus paksa (Kick).');
        } catch (\Throwable $e) {
            Log::error('Gagal memutuskan sesi di MikroTik', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Gagal memutuskan sesi pelanggan. Detail: ' . $this->getCleanErrorMessage($e));
        }
    }

    private function getCleanErrorMessage(\Throwable $e): string
    {
        $message = $e->getMessage();
        if ($e instanceof \Illuminate\Database\QueryException) {
            if (($pos = strpos($message, ' (Connection:')) !== false) {
                $message = substr($message, 0, $pos);
            }
        }
        return $message;
    }
}
