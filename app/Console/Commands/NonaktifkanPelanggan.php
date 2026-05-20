<?php

namespace App\Console\Commands;

use App\Models\Pelanggan;
use App\Services\MikrotikService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NonaktifkanPelanggan extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:nonaktifkan-pelanggan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Nonaktifkan pelanggan yang masa aktifnya sudah lewat dan putus koneksi di MikroTik';

    public function handle(MikrotikService $mikrotikService): int
    {
        $today = Carbon::today()->toDateString();

        $this->info("Menjalankan nonaktifkan pelanggan - referensi tanggal: {$today}");

        $pelangganList = Pelanggan::with('paket')
            ->where('status_aktif', 'Aktif')
            ->whereDate('masa_aktif', '<', $today)
            ->get();

        if ($pelangganList->isEmpty()) {
            $this->info('Tidak ada pelanggan kedaluwarsa ditemukan.');
            return Command::SUCCESS;
        }

        $sukses = [];

        foreach ($pelangganList as $p) {
            try {
                $paketNama = (string) ($p->paket->nama_paket ?? '');
                $tipe = (stripos($paketNama, 'pppoe') !== false) ? 'PPPoE' : 'Hotspot';

                $p->update(['status_aktif' => 'Nonaktif']);

                // Kunci / nonaktifkan di MikroTik
                $mikrotikService->setPelangganStateByType($p, false, $tipe);

                // Putuskan sesi aktif jika ada
                $mikrotikService->disconnectActiveSessionByType($p, $tipe);

                $sukses[] = [
                    'id' => $p->id_pelanggan,
                    'nama' => $p->nama_pelanggan,
                    'tipe' => $tipe,
                ];

                $this->info("Dinonaktifkan: {$p->id_pelanggan} - {$p->nama_pelanggan} ({$tipe})");
            } catch (\Throwable $e) {
                Log::error('Gagal menonaktifkan pelanggan: ' . $e->getMessage(), [
                    'pelanggan' => $p->id_pelanggan ?? null,
                    'exception' => $e,
                ]);
                $this->error("Gagal menonaktifkan pelanggan {$p->id_pelanggan}: {$e->getMessage()}");
            }
        }

        if (!empty($sukses)) {
            Log::info('NonaktifkanPelanggan: pelanggan dinonaktifkan otomatis', ['items' => $sukses, 'tanggal' => $today]);
        }

        $this->info('Selesai.');

        return Command::SUCCESS;
    }
}
