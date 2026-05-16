<?php

namespace App\Http\Controllers;

use App\Models\PaketBandwidth;
use App\Models\Pelanggan;
use App\Services\MikrotikService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BandwidthController extends Controller
{
    private function normalizeRateInput(string $value): string
    {
        $normalized = preg_replace('/\s+/', '', trim($value)) ?? trim($value);

        // Standarkan input seperti 1m, 10Mbps, 512k menjadi format kapital (1M, 10M, 512K).
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

        $paket = PaketBandwidth::withCount('pelanggan')
            ->withCount([
                'pelanggan as pelanggan_aktif_count' => function ($query) {
                    $query->where('status_aktif', 'Aktif');
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

        $paket = PaketBandwidth::create($validated);

        $sync = $mikrotikService->ensureProfileExists($paket);
        $redirect = redirect()->route('bandwidth.index')->with('success', 'Paket bandwidth berhasil ditambahkan.');
        if (!$sync['success']) {
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

        $sync = $mikrotikService->ensureProfileExists($bandwidth->fresh());

        if ($oldProfileName !== $bandwidth->nama_paket) {
            $removeOld = $mikrotikService->removeProfileIfUnused($oldProfileName);
            if (!$removeOld['success']) {
                $sync = $removeOld;
            }
        }

        $redirect = redirect()->route('bandwidth.index')->with('success', 'Paket bandwidth berhasil diperbarui.');
        if (!$sync['success']) {
            $redirect->with('warning', $sync['message']);
        }

        return $redirect;
    }

    public function destroy(PaketBandwidth $bandwidth, MikrotikService $mikrotikService): RedirectResponse
    {
        if ($bandwidth->pelanggan()->exists()) {
            return redirect()
                ->route('bandwidth.index')
                ->with('warning', 'Paket tidak dapat dihapus karena masih digunakan oleh pelanggan. Pindahkan paket pelanggan terlebih dahulu.');
        }

        $profileName = $bandwidth->nama_paket;
        $bandwidth->delete();

        $sync = $mikrotikService->removeProfileIfUnused($profileName);
        $redirect = redirect()->route('bandwidth.index')->with('success', 'Paket bandwidth berhasil dihapus.');
        if (!$sync['success']) {
            $redirect->with('warning', $sync['message']);
        }

        return $redirect;
    }
}
