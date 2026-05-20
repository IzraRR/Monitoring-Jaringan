<?php

namespace App\Console\Commands;

use App\Models\Pelanggan;
use App\Services\MikrotikService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncMikrotikSessions extends Command
{
    protected $signature = 'app:sync-mikrotik-sessions';

    protected $description = 'Sinkronkan status pelanggan ke MikroTik dan bersihkan sesi aktif yang tidak sesuai';

    public function handle(MikrotikService $mikrotikService): int
    {
        $this->info('Memulai sinkronisasi sesi aktif MikroTik...');

        $pelangganList = Pelanggan::with('paket')->get();
        $enabledCount = 0;
        $disabledCount = 0;
        $skippedCount = 0;

        foreach ($pelangganList as $pelanggan) {
            try {
                $tipe = $pelanggan->paket && stripos((string) $pelanggan->paket->nama_paket, 'pppoe') !== false
                    ? 'PPPoE'
                    : 'Hotspot';

                $statusAktif = strtolower((string) $pelanggan->status_aktif);

                if ($statusAktif === 'aktif') {
                    $result = $mikrotikService->setPelangganStateByType($pelanggan, true, $tipe);
                    if ($result['success']) {
                        $enabledCount++;
                        $this->line("Aktif disinkronkan: {$pelanggan->username_mikrotik} ({$tipe})");
                    } else {
                        $skippedCount++;
                        $this->warn("Gagal sinkron aktif: {$pelanggan->username_mikrotik} - {$result['message']}");
                    }
                    continue;
                }

                $disableResult = $mikrotikService->setPelangganStateByType($pelanggan, false, $tipe);
                $disconnectResult = $mikrotikService->disconnectActiveSessionByType($pelanggan, $tipe);

                if ($disableResult['success']) {
                    $disabledCount++;
                }

                if (!$disableResult['success'] || !$disconnectResult['success']) {
                    $skippedCount++;
                    $this->warn("Sinkron nonaktif bermasalah: {$pelanggan->username_mikrotik} - {$disableResult['message']} | {$disconnectResult['message']}");
                } else {
                    $this->line("Nonaktif/disconnect disinkronkan: {$pelanggan->username_mikrotik} ({$tipe})");
                }
            } catch (\Throwable $e) {
                $skippedCount++;
                Log::error('Gagal sinkron sesi MikroTik', [
                    'id_pelanggan' => $pelanggan->id_pelanggan ?? null,
                    'username_mikrotik' => $pelanggan->username_mikrotik ?? null,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Gagal sinkron {$pelanggan->username_mikrotik}: {$e->getMessage()}");
            }
        }

        $this->info("Sinkronisasi selesai. Aktif: {$enabledCount}, Nonaktif: {$disabledCount}, Lewat/Gagal: {$skippedCount}");

        return Command::SUCCESS;
    }
}