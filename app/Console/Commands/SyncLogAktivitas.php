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

        // Lama: hanya membaca hotspot aktif
        // $sessions = $mikrotikService->comm('/ip/hotspot/active/print') ?: [];

        // Baru: baca kedua sumber sesi (Hotspot + PPPoE) sehingga PPPoE juga tercatat
        $hotspotSessions = $mikrotikService->comm('/ip/hotspot/active/print') ?: [];
        $pppoeSessions = $mikrotikService->comm('/ppp/active/print') ?: [];

        $sessions = [];

        foreach ($hotspotSessions as $s) {
            $sessions[] = ['type' => 'Hotspot', 'raw' => $s];
        }

        foreach ($pppoeSessions as $s) {
            $sessions[] = ['type' => 'PPPoE', 'raw' => $s];
        }

        if (empty($sessions)) {
            $this->info('Tidak ada sesi aktif (Hotspot/PPPoE) yang ditemukan.');
            return Command::SUCCESS;
        }

        $today = now()->toDateString();
        $processed = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($sessions as $entry) {
            $type = $entry['type'] ?? 'Hotspot';
            $hotspotUser = $entry['raw'] ?? [];

            // Hotspot menggunakan field 'user', sedangkan PPPoE menggunakan field 'name'
            $username = trim((string) ($hotspotUser['user'] ?? $hotspotUser['name'] ?? ''));
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

            // Field bytes biasanya 'bytes-in' / 'bytes-out', tapi gunakan fallback defensif
            $bytesIn = (int) ($hotspotUser['bytes-in'] ?? $hotspotUser['bytes_in'] ?? 0);
            $bytesOut = (int) ($hotspotUser['bytes-out'] ?? $hotspotUser['bytes_out'] ?? 0);
            $dataUsageMb = ($bytesIn + $bytesOut) / 1048576;

            // Uptime field biasanya 'uptime' but fallback to 'session-time' if available
            $uptimeRaw = (string) ($hotspotUser['uptime'] ?? $hotspotUser['session-time'] ?? '0s');
            $durasiMenit = $this->parseUptimeToMinutes($uptimeRaw);
            $isAnomali = $dataUsageMb > 1000;

            // IP field may differ: hotspot uses 'address', pppoe may use 'address' or 'remote-address'
            $ipAddress = trim((string) ($hotspotUser['address'] ?? $hotspotUser['remote-address'] ?? ''));

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
                    'ip_address' => $ipAddress,
                ]);
                $updated++;
            } else {
                LogAktivitas::updateOrCreate(
                    [
                        'id_pelanggan' => $pelanggan->id_pelanggan,
                        'waktu_selesai' => null,
                    ],
                    [
                        'id_paket' => $pelanggan->id_paket,
                        'waktu_mulai' => now(),
                        'ip_address' => $ipAddress,
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