<?php

namespace App\Console\Commands;

use App\Models\LogAktivitas;
use App\Models\Pelanggan;
use App\Services\MikrotikService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncLogAktivitas extends Command
{
    protected $signature = 'app:sync-log-aktivitas';

    protected $description = 'Sinkronkan log aktivitas pelanggan dari sesi aktif MikroTik ke database';

    public function handle(MikrotikService $mikrotikService): int
    {
        if (!$mikrotikService->isConfigured()) {
            $this->warn('Konfigurasi MikroTik belum lengkap. Sinkronisasi dilewati.');
            return Command::SUCCESS;
        }

        $sessions = $mikrotikService->comm('/ip/hotspot/active/print') ?: [];

        if (empty($sessions)) {
            $this->info('Tidak ada sesi hotspot aktif yang ditemukan.');
            return Command::SUCCESS;
        }

        $today = now()->toDateString();
        $processed = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($sessions as $hotspotUser) {
            $username = trim((string) ($hotspotUser['user'] ?? ''));
            if ($username === '') {
                $skipped++;
                continue;
            }

            $pelanggan = Pelanggan::query()
                ->with('paket')
                ->where('username_mikrotik', $username)
                ->first();

            if (!$pelanggan) {
                $skipped++;
                Log::info('SyncLogAktivitas: pelanggan tidak ditemukan untuk sesi hotspot aktif', [
                    'username' => $username,
                ]);
                continue;
            }

            $bytesIn = (int) ($hotspotUser['bytes-in'] ?? 0);
            $bytesOut = (int) ($hotspotUser['bytes-out'] ?? 0);
            $dataUsageMb = ($bytesIn + $bytesOut) / 1048576;
            $durasiMenit = $this->parseUptimeToMinutes((string) ($hotspotUser['uptime'] ?? '0s'));
            $isAnomali = $dataUsageMb > 1000;

            $existingLog = LogAktivitas::query()
                ->where('id_pelanggan', $pelanggan->id_pelanggan)
                ->whereDate('waktu_mulai', $today)
                ->whereNull('waktu_selesai')
                ->first();

            if ($existingLog) {
                $existingLog->update([
                    'data_usage_mb' => $dataUsageMb,
                    'durasi_menit' => $durasiMenit,
                    'is_anomali' => $isAnomali,
                ]);
                $updated++;
            } else {
                LogAktivitas::updateOrCreate(
                    [
                        'id_pelanggan' => $pelanggan->id_pelanggan,
                        'waktu_selesai' => null,
                    ],
                    [
                        'waktu_mulai' => now(),
                        'data_usage_mb' => $dataUsageMb,
                        'durasi_menit' => $durasiMenit,
                        'is_anomali' => $isAnomali,
                    ]
                );
                $created++;
            }

            $processed++;
        }

        $this->info("Sinkron log aktivitas selesai. Diproses: {$processed}, Baru: {$created}, Update: {$updated}, Skip: {$skipped}");

        return Command::SUCCESS;
    }

    private function parseUptimeToMinutes(string $uptime): int
    {
        $uptime = trim(strtolower($uptime));
        if ($uptime === '') {
            return 0;
        }

        $days = 0;
        $hours = 0;
        $minutes = 0;
        $seconds = 0;

        if (preg_match('/(\d+)w/', $uptime, $matches)) {
            $days += ((int) $matches[1]) * 7;
        }

        if (preg_match('/(\d+)d/', $uptime, $matches)) {
            $days += (int) $matches[1];
        }

        if (preg_match('/(\d+)h/', $uptime, $matches)) {
            $hours = (int) $matches[1];
        }

        if (preg_match('/(\d+)m/', $uptime, $matches)) {
            $minutes = (int) $matches[1];
        }

        if (preg_match('/(\d+)s/', $uptime, $matches)) {
            $seconds = (int) $matches[1];
        }

        return (int) floor((($days * 24 * 60) + ($hours * 60) + $minutes + ($seconds / 60)));
    }
}