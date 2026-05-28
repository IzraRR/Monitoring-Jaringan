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

        // Ambil sesi aktif (Hotspot + PPPoE)
        $hotspotSessions = $mikrotikService->comm('/ip/hotspot/active/print') ?: [];
        $pppoeSessions = $mikrotikService->comm('/ppp/active/print') ?: [];

        $sessions = [];
        foreach ($hotspotSessions as $s) {
            $sessions[] = ['type' => 'Hotspot', 'raw' => $s];
        }
        foreach ($pppoeSessions as $s) {
            $sessions[] = ['type' => 'PPPoE', 'raw' => $s];
        }

        // Ambil Simple Queues untuk mengambil data bytes (terutama untuk PPPoE yang tidak memuat bytes di session print)
        $queues = $mikrotikService->comm('/queue/simple/print') ?: [];
        $queuesMap = [];
        foreach ($queues as $q) {
            if (isset($q['name'])) {
                $queuesMap[strtolower($q['name'])] = $q;
            }
        }

        $today = now()->toDateString();
        $processed = 0;
        $created = 0;
        $updated = 0;
        $restarted = 0;
        $skipped = 0;

        // Map untuk menampung pelanggan yang saat ini aktif di MikroTik
        $activeCustomerIds = [];

        foreach ($sessions as $entry) {
            $type = $entry['type'] ?? 'Hotspot';
            $hotspotUser = $entry['raw'] ?? [];

            // Hotspot menggunakan 'user', PPPoE menggunakan 'name'
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
                Log::info('SyncLogAktivitas: pelanggan tidak ditemukan untuk sesi aktif', [
                    'username' => $username,
                ]);
                continue;
            }

            // Dapatkan bytes upload/download
            $bytesIn = (int) ($hotspotUser['bytes-in'] ?? $hotspotUser['bytes_in'] ?? 0);
            $bytesOut = (int) ($hotspotUser['bytes-out'] ?? $hotspotUser['bytes_out'] ?? 0);

            // Fallback ke Simple Queue jika bytes 0 (kasus umum untuk PPPoE)
            if ($bytesIn === 0 && $bytesOut === 0) {
                $matchedQueue = null;
                $lowerUser = strtolower($username);
                if (isset($queuesMap[$lowerUser])) {
                    $matchedQueue = $queuesMap[$lowerUser];
                } else {
                    foreach ($queuesMap as $qName => $qData) {
                        if (strpos($qName, $lowerUser) !== false) {
                            $matchedQueue = $qData;
                            break;
                        }
                    }
                }

                if ($matchedQueue && isset($matchedQueue['bytes']) && strpos($matchedQueue['bytes'], '/') !== false) {
                    $bytesParts = explode('/', $matchedQueue['bytes']);
                    $bytesIn = (int)$bytesParts[0]; // upload
                    $bytesOut = (int)$bytesParts[1]; // download
                }
            }

            $dataUsageMb = ($bytesIn + $bytesOut) / 1048576;

            // Dapatkan uptime
            $uptimeRaw = (string) ($hotspotUser['uptime'] ?? $hotspotUser['session-time'] ?? '0s');
            $durasiMenit = $this->parseUptimeToMinutes($uptimeRaw);
            $isAnomali = $dataUsageMb > 1000;

            // Dapatkan IP address
            $ipAddress = trim((string) ($hotspotUser['address'] ?? $hotspotUser['remote-address'] ?? ''));

            $activeCustomerIds[$pelanggan->id_pelanggan] = [
                'id_paket' => $pelanggan->id_paket,
                'ip_address' => $ipAddress,
                'data_usage_mb' => $dataUsageMb,
                'durasi_menit' => $durasiMenit,
                'is_anomali' => $isAnomali,
            ];
        }

        // 1. TUTUP SESI LOG untuk pelanggan yang sudah DISCONNECT
        // Ambil semua log yang masih berjalan (waktu_selesai IS NULL)
        $openLogs = LogAktivitas::query()->whereNull('waktu_selesai')->get();
        foreach ($openLogs as $log) {
            // Jika pelanggan tidak ada di daftar aktif saat ini, tutup sesi lognya
            if (!isset($activeCustomerIds[$log->id_pelanggan])) {
                $log->update([
                    'waktu_selesai' => now(),
                ]);
            }
        }

        // 2. UPDATE / BUAT LOG BARU untuk pelanggan yang aktif
        foreach ($activeCustomerIds as $idPelanggan => $data) {
            // Cari sesi log yang masih terbuka untuk pelanggan ini
            $existingLog = LogAktivitas::query()
                ->where('id_pelanggan', $idPelanggan)
                ->whereNull('waktu_selesai')
                ->orderByDesc('waktu_mulai')
                ->first();

            if ($existingLog) {
                // Jika durasi/traffic menurun atau IP berubah, anggap sesi baru dimulai.
                // Ini membuat satu pelanggan bisa memiliki banyak log aktivitas per sesi.
                $sessionRestarted = ($data['durasi_menit'] < (int) ($existingLog->durasi_menit ?? 0))
                    || ($data['data_usage_mb'] < (float) ($existingLog->data_usage_mb ?? 0))
                    || (
                        !empty($data['ip_address'])
                        && !empty($existingLog->ip_address)
                        && $data['ip_address'] !== $existingLog->ip_address
                    );

                if ($sessionRestarted) {
                    $existingLog->update([
                        'waktu_selesai' => now(),
                    ]);

                    LogAktivitas::create([
                        'id_pelanggan' => $idPelanggan,
                        'id_paket' => $data['id_paket'],
                        'waktu_mulai' => now(),
                        'waktu_selesai' => null,
                        'ip_address' => $data['ip_address'],
                        'data_usage_mb' => $data['data_usage_mb'],
                        'durasi_menit' => $data['durasi_menit'],
                        'is_anomali' => $data['is_anomali'],
                    ]);

                    $restarted++;
                    $created++;
                } else {
                    // Update log sesi aktif yang sedang berjalan
                    $existingLog->update([
                        'data_usage_mb' => $data['data_usage_mb'],
                        'durasi_menit' => $data['durasi_menit'],
                        'is_anomali' => $data['is_anomali'],
                        'ip_address' => $data['ip_address'],
                    ]);
                    $updated++;
                }
            } else {
                // Buat log sesi baru karena pelanggan baru saja connect
                LogAktivitas::create([
                    'id_pelanggan' => $idPelanggan,
                    'id_paket' => $data['id_paket'],
                    'waktu_mulai' => now(),
                    'waktu_selesai' => null,
                    'ip_address' => $data['ip_address'],
                    'data_usage_mb' => $data['data_usage_mb'],
                    'durasi_menit' => $data['durasi_menit'],
                    'is_anomali' => $data['is_anomali'],
                ]);
                $created++;
            }
            $processed++;
        }

        $this->info("Sinkron log aktivitas selesai. Diproses: {$processed}, Baru (Connect): {$created}, Restart Sesi: {$restarted}, Update (Aktif): {$updated}, Skip: {$skipped}");

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