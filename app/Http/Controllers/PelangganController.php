<?php

namespace App\Http\Controllers;

use App\Models\PaketBandwidth;
use App\Models\Pelanggan;
use App\Services\MikrotikService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PelangganController extends Controller
{
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

        $pelanggan = Pelanggan::create($validated);
        $sync = $mikrotikService->syncPelangganCreated($pelanggan);

        $redirect = redirect()->route('pelanggan.index')->with('success', 'Pelanggan berhasil ditambahkan.');
        if (!$sync['success']) {
            $redirect->with('warning', $sync['message']);
        }

        return $redirect;
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

        $oldUsername = $pelanggan->username_mikrotik;
        $pelanggan->update($validated);
        $sync = $mikrotikService->syncPelangganUpdated($pelanggan, $oldUsername);

        $redirect = redirect()->route('pelanggan.index')->with('success', 'Pelanggan berhasil diperbarui.');
        if (!$sync['success']) {
            $redirect->with('warning', $sync['message']);
        }

        return $redirect;
    }

    public function destroy(Pelanggan $pelanggan, MikrotikService $mikrotikService): RedirectResponse
    {
        $username = $pelanggan->username_mikrotik;
        $pelanggan->delete();
        $sync = $mikrotikService->syncPelangganDeleted($username);

        $redirect = redirect()->route('pelanggan.index')->with('success', 'Pelanggan berhasil dihapus.');
        if (!$sync['success']) {
            $redirect->with('warning', $sync['message']);
        }

        return $redirect;
    }

    public function syncMikrotik(MikrotikService $mikrotikService): RedirectResponse
    {
        $result = $mikrotikService->syncAllPelanggan();
        $flashKey = $result['success'] ? 'success' : 'warning';

        return redirect()->route('pelanggan.index')->with($flashKey, $result['message']);
    }

    public function toggleLock(Pelanggan $pelanggan, MikrotikService $mikrotikService): RedirectResponse
    {
        $isLocked = strtolower((string) $pelanggan->status_aktif) === 'locked';

        if ($isLocked) {
            $pelanggan->update(['status_aktif' => 'Aktif']);
            $sync = $mikrotikService->setPelangganState($pelanggan->fresh(), true);
            $message = 'Pelanggan dibuka kembali.';
        } else {
            $pelanggan->update(['status_aktif' => 'Locked']);
            $sync = $mikrotikService->setPelangganState($pelanggan->fresh(), false);
            $message = 'Pelanggan berhasil dikunci.';
        }

        $redirect = redirect()->route('pelanggan.index')->with('success', $message);
        if (!$sync['success']) {
            $redirect->with('warning', $sync['message']);
        }

        return $redirect;
    }

    public function disconnect(Pelanggan $pelanggan, MikrotikService $mikrotikService): RedirectResponse
    {
        $syncDisconnect = $mikrotikService->disconnectActiveSession($pelanggan);

        $pelanggan->update(['status_aktif' => 'Nonaktif']);
        $syncDisable = $mikrotikService->setPelangganState($pelanggan->fresh(), false);

        $redirect = redirect()->route('pelanggan.index')->with('success', 'User berhasil dinonaktifkan dan sesi aktif diputus.');

        if (!$syncDisconnect['success']) {
            $redirect->with('warning', $syncDisconnect['message']);
        } elseif (!$syncDisable['success']) {
            $redirect->with('warning', $syncDisable['message']);
        }

        return $redirect;
    }
}
