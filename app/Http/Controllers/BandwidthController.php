<?php

namespace App\Http\Controllers;

use App\Models\PaketBandwidth;
use App\Models\Pelanggan;
use App\Services\MikrotikService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Str;

class BandwidthController extends Controller
{
    private function normalizeRateInput(string $value): string
    {
        $normalized = preg_replace('/\s+/', '', trim($value)) ?? trim($value);

        $normalized = preg_replace_callback('/(\d+(?:\.\d+)?)([a-zA-Z]+)/', function (array $matches) {
            $unit = strtoupper($matches[2]);
            $unit = str_replace(['MBPS', 'MBIT', 'MB'], 'M', $unit);
            $unit = str_replace(['KBPS', 'KBIT', 'KB'], 'K', $unit);
            $unit = str_replace(['GBPS', 'GBIT', 'GB'], 'G', $unit);

            return $matches[1] . $unit;
        }, $normalized) ?? $normalized;

        return $normalized;
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $paket = PaketBandwidth::withCount([
            'pelanggan',
            'pelanggan as pelanggan_aktif_count' => function ($q) {
                $q->where('status_aktif', 'Aktif');
            },
        ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where('nama_paket', 'like', "%{$search}%");
            })
            ->latest('id_paket')
            ->paginate(10)
            ->withQueryString();

        return view('bandwidth.index', [
            'paket' => $paket,
            'search' => $search,
            'summary' => [
                'total_paket' => PaketBandwidth::count(),
                'total_pelanggan_aktif' => Pelanggan::where('status_aktif', 'Aktif')->count(),
                'total_pelanggan_locked' => Pelanggan::where('status_aktif', 'Locked')->count(),
                'rata_harga' => (float) PaketBandwidth::avg('harga'),
            ],
        ]);
    }

    public function create(): View
    {
        return view('bandwidth.create');
    }

    public function store(Request $request, MikrotikService $mikrotikService): RedirectResponse
    {
        $validated = $request->validate([
            'nama_paket' => ['required', 'string', 'max:50'],
            'limit_download' => ['required', 'string', 'max:20'],
            'limit_upload' => ['required', 'string', 'max:20'],
            'harga' => ['required', 'numeric', 'min:0'],
        ]);

        $validated['limit_download'] = $this->normalizeRateInput($validated['limit_download']);
        $validated['limit_upload'] = $this->normalizeRateInput($validated['limit_upload']);

        PaketBandwidth::create($validated);

        $paket = PaketBandwidth::latest('id_paket')->first();
        $namaPaket = strtolower(trim($validated['nama_paket']));

        try {
            if (stripos($namaPaket, 'pppoe') !== false) {
                $sync = $mikrotikService->tambahProfilPPPoE($paket);
            } else {
                $sync = $mikrotikService->tambahProfilHotspot($paket);
            }
        } catch (\Throwable $e) {
            $sync = ['success' => false, 'message' => $e->getMessage()];
        }

        $redirect = redirect()->route('bandwidth.index')->with('success', 'Paket bandwidth berhasil ditambahkan.');
        if (!empty($sync) && !$sync['success']) {
            $redirect->with('warning', $sync['message']);
        }

        return $redirect;
    }

    public function edit(PaketBandwidth $bandwidth): View
    {
        return view('bandwidth.edit', [
            'paket' => $bandwidth,
        ]);
    }

    public function update(Request $request, PaketBandwidth $bandwidth, MikrotikService $mikrotikService): RedirectResponse
    {
        $oldProfileName = $bandwidth->nama_paket;

        $validated = $request->validate([
            'nama_paket' => ['required', 'string', 'max:50'],
            'limit_download' => ['required', 'string', 'max:20'],
            'limit_upload' => ['required', 'string', 'max:20'],
            'harga' => ['required', 'numeric', 'min:0'],
        ]);

        $validated['limit_download'] = $this->normalizeRateInput($validated['limit_download']);
        $validated['limit_upload'] = $this->normalizeRateInput($validated['limit_upload']);

        $bandwidth->update($validated);

        $fresh = $bandwidth->fresh();

        $namaPaketBaru = strtolower(trim($fresh->nama_paket));

        try {
            if (stripos($namaPaketBaru, 'pppoe') !== false) {
                $sync = $mikrotikService->tambahProfilPPPoE($fresh);
            } else {
                $sync = $mikrotikService->tambahProfilHotspot($fresh);
            }
        } catch (\Throwable $e) {
            $sync = ['success' => false, 'message' => $e->getMessage()];
        }

        if ($oldProfileName !== $fresh->nama_paket) {
            $namaPaketLama = strtolower(trim($oldProfileName));
            try {
                if (stripos($namaPaketLama, 'pppoe') !== false) {
                    $removeOld = $mikrotikService->removeProfileIfUnusedByType($oldProfileName, 'PPPoE');
                } else {
                    $removeOld = $mikrotikService->removeProfileIfUnusedByType($oldProfileName, 'Hotspot');
                }
            } catch (\Throwable $e) {
                $removeOld = ['success' => false, 'message' => $e->getMessage()];
            }

            if (!$removeOld['success']) {
                $sync = $removeOld;
            }
        }

        $redirect = redirect()->route('bandwidth.index')->with('success', 'Paket bandwidth berhasil diperbarui.');
        if (!empty($sync) && !$sync['success']) {
            $redirect->with('warning', $sync['message']);
        }

        return $redirect;
    }

    public function destroy(PaketBandwidth $bandwidth, MikrotikService $mikrotikService): RedirectResponse
    {
        if ($bandwidth->pelanggan()->exists()) {
            return redirect()
                ->route('bandwidth.index')
                ->with('warning', 'Profil bandwidth tidak dapat dihapus karena masih digunakan pelanggan.');
        }

        $profileName = $bandwidth->nama_paket;
        $namaPaket = strtolower(trim($profileName));

        $bandwidth->delete();

        try {
            if (stripos($namaPaket, 'pppoe') !== false) {
                $sync = $mikrotikService->removeProfileIfUnusedByType($profileName, 'PPPoE');
            } else {
                $sync = $mikrotikService->removeProfileIfUnusedByType($profileName, 'Hotspot');
            }
        } catch (\Throwable $e) {
            $sync = ['success' => false, 'message' => $e->getMessage()];
        }

        $redirect = redirect()->route('bandwidth.index')->with('success', 'Paket bandwidth berhasil dihapus.');
        if (!empty($sync) && !$sync['success']) {
            $redirect->with('warning', $sync['message']);
        }

        return $redirect;
    }
}
