<?php

namespace App\Services;

require_once __DIR__ . '/routeros_api.class.php';

use App\Exceptions\MikrotikConnectionException;
use App\Models\PaketBandwidth;
use App\Models\Pelanggan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MikrotikService
{
    private const HOTSPOT_PROFILE_PATH = '/ip/hotspot/user/profile';
    private const PPPOE_PROFILE_PATH = '/ppp/profile';
    private const HOTSPOT_USER_PATH = '/ip/hotspot/user';
    private const PPPOE_SECRET_PATH = '/ppp/secret';
    private const HOTSPOT_ACTIVE_PATH = '/ip/hotspot/active';
    private const PPPOE_ACTIVE_PATH = '/ppp/active';

    private function detectTypeFromPaket(Pelanggan $pelanggan): string
    {
        $pelanggan->loadMissing('paket');
        $namaPaket = $pelanggan->paket?->nama_paket ?? '';

        return Str::contains(strtolower($namaPaket), 'pppoe') ? 'PPPoE' : 'Hotspot';
    }

    private function getBasePathByType(string $tipe): string
    {
        return trim($tipe) === 'PPPoE' ? self::PPPOE_SECRET_PATH : self::HOTSPOT_USER_PATH;
    }

    private function getProfilePathByType(string $tipe): string
    {
        return strtolower(trim($tipe)) === 'pppoe' ? self::PPPOE_PROFILE_PATH : self::HOTSPOT_PROFILE_PATH;
    }

    private function getActivePathByType(string $tipe): string
    {
        return trim($tipe) === 'PPPoE' ? self::PPPOE_ACTIVE_PATH : self::HOTSPOT_ACTIVE_PATH;
    }

    private function firstRecord(array $response): array
    {
        return isset($response[0]) && is_array($response[0]) ? $response[0] : [];
    }

    private function resolveTrafficInterfaceName(\RouterosAPI $api): ?string
    {
        $configuredInterface = trim((string) config('services.mikrotik.traffic_interface', ''));
        if ($configuredInterface !== '') {
            return $configuredInterface;
        }

        $interfaces = $api->comm('/interface/print');
        if (!is_array($interfaces)) {
            return null;
        }

        foreach ($interfaces as $interface) {
            if (!empty($interface['name'])) {
                return (string) $interface['name'];
            }
        }

        return null;
    }

    private function getInterfaceTrafficSnapshot(\RouterosAPI $api, ?string $interfaceName): array
    {
        $result = [
            'interface_name' => $interfaceName,
            'rx_bps' => 0,
            'tx_bps' => 0,
            'source' => 'monitor-traffic',
        ];

        if ($interfaceName === null || trim($interfaceName) === '') {
            $result['source'] = 'none';
            return $result;
        }

        try {
            $traffic = $api->comm('/interface/monitor-traffic', [
                'interface' => $interfaceName,
                'once' => '',
            ]);
            $entry = $this->firstRecord(is_array($traffic) ? $traffic : []);

            $rx = $entry['rx-bits-per-second'] ?? $entry['rx-rate'] ?? $entry['rx-byte'] ?? null;
            $tx = $entry['tx-bits-per-second'] ?? $entry['tx-rate'] ?? $entry['tx-byte'] ?? null;

            if ($rx !== null) {
                $result['rx_bps'] = (int) $rx;
            }

            if ($tx !== null) {
                $result['tx_bps'] = (int) $tx;
            }

            if ($result['rx_bps'] === 0 && $result['tx_bps'] === 0) {
                $result['source'] = 'interface-print';
                $interface = $this->firstRecord($api->comm('/interface/print', ['?name' => $interfaceName]));
                $result['rx_bps'] = isset($interface['rx-byte']) ? ((int) $interface['rx-byte'] * 8) : 0;
                $result['tx_bps'] = isset($interface['tx-byte']) ? ((int) $interface['tx-byte'] * 8) : 0;
            }

            return $result;
        } catch (\Throwable $e) {
            Log::warning('MikroTik interface traffic snapshot failed', [
                'interface' => $interfaceName,
                'error' => $e->getMessage(),
            ]);

            $result['source'] = 'error';
            return $result;
        }
    }

    public function isConfigured(): bool
    {
        $config = config('services.mikrotik', []);

        return !empty($config['host'])
            && !empty($config['user'])
            && array_key_exists('pass', $config);
    }

    /**
     * Mengeksekusi perintah mentah (raw command) ke RouterOS API.
     *
     * @param string $command Path perintah MikroTik (contoh: '/ip/hotspot/active/print')
     * @param array $queries Array parameter query opsional
     * @return array|mixed
     */
    public function comm(string $command, array $queries = [])
    {
        if (!$this->shouldSync()) {
            return [];
        }

        try {
            $api = $this->makeLegacyClient();
            return $api->comm($command, $queries);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('MikroTik raw comm failed', [
                'command' => $command,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    private function makeLegacyClient(): \RouterosAPI
    {
        $api = new \RouterosAPI();
        $api->port = (int) config('services.mikrotik.api_port', 8728);
        $api->ssl = (bool) config('services.mikrotik.api_ssl', false);
        $api->timeout = min(2, max(1, (int) config('services.mikrotik.api_timeout', 2)));
        $api->attempts = max(1, (int) config('services.mikrotik.api_attempts', 1));
        $api->delay = (int) config('services.mikrotik.api_delay', 0);

        $connected = false;
        try {
            $connected = $api->connect(
                (string) config('services.mikrotik.host'),
                (string) config('services.mikrotik.user'),
                (string) config('services.mikrotik.pass')
            );
        } catch (\Throwable $e) {
            throw new MikrotikConnectionException('Koneksi MikroTik Timeout/Gagal', 0, $e);
        }

        if (!$connected) {
            $lastError = $api->error_str ?? null;
            throw new MikrotikConnectionException($lastError ?: 'Koneksi MikroTik Timeout/Gagal');
        }

        return $api;
    }

    private function shouldSync(): bool
    {
        return (bool) config('services.mikrotik.sync_enabled', true);
    }

    private function getSyncMode(): string
    {
        return strtolower((string) config('services.mikrotik.sync_mode', 'hotspot'));
    }

    private function getProfile(): string
    {
        return $this->getSyncMode() === 'pppoe'
            ? (string) config('services.mikrotik.pppoe_profile', '')
            : (string) config('services.mikrotik.hotspot_profile', '');
    }

    private function getProfilePath(): string
    {
        return $this->getSyncMode() === 'pppoe' ? self::PPPOE_PROFILE_PATH : self::HOTSPOT_PROFILE_PATH;
    }

    private function getBasePath(): string
    {
        return $this->getSyncMode() === 'pppoe' ? self::PPPOE_SECRET_PATH : self::HOTSPOT_USER_PATH;
    }

    private function normalizeLimit(string $value): string
    {
        $v = trim($value);
        if ($v === '') {
            return '';
        }

        $v = strtolower($v);
        $v = str_replace(['mbps', 'mbit', 'mb'], 'm', $v);
        $v = str_replace(['kbps', 'kbit', 'kb'], 'k', $v);
        $v = str_replace(['gbps', 'gbit', 'gb'], 'g', $v);
        $v = preg_replace('/\s+/', '', $v) ?? $v;

        return strtoupper($v);
    }

    private function formatRateLimit(PaketBandwidth $paket): string
    {
        $download = $this->normalizeLimit((string) $paket->limit_download);
        $upload = $this->normalizeLimit((string) $paket->limit_upload);

        if ($download === '' && $upload === '') {
            return '';
        }

        if ($upload === '') {
            $upload = $download;
        }

        if ($download === '') {
            $download = $upload;
        }

        return $upload . '/' . $download;
    }

    private function buildProfilePayload(PaketBandwidth $paket): array
    {
        $payload = ['name' => $paket->nama_paket];

        $rateLimit = $this->formatRateLimit($paket);
        if ($rateLimit !== '') {
            $payload['rate-limit'] = $rateLimit;
        }

        return $payload;
    }

    private function resolveProfile(Pelanggan $pelanggan): ?string
    {
        $fromPaket = $pelanggan->relationLoaded('paket')
            ? ($pelanggan->paket->nama_paket ?? null)
            : null;

        if (is_string($fromPaket) && trim($fromPaket) !== '') {
            return $fromPaket;
        }

        $fromConfig = $this->getProfile();
        return $fromConfig !== '' ? $fromConfig : null;
    }

    private function buildUserPayloadByType(Pelanggan $pelanggan, string $tipe): array
    {
        $tipe = strtolower(trim($tipe));

        $payload = [
            'name' => $pelanggan->username_mikrotik,
            'password' => $pelanggan->password_mikrotik,
            'disabled' => in_array(strtolower($pelanggan->status_aktif), ['nonaktif', 'locked'], true) ? 'yes' : 'no',
        ];

        $profile = $this->resolveProfile($pelanggan);
        if (!empty($profile)) {
            $payload['profile'] = $profile;
        }

        if ($tipe === 'pppoe') {
            $payload['service'] = 'pppoe';
        }

        return $payload;
    }

    private function buildUserPayload(Pelanggan $pelanggan): array
    {
        $payload = [
            'name' => $pelanggan->username_mikrotik,
            'password' => $pelanggan->password_mikrotik,
            'disabled' => in_array(strtolower($pelanggan->status_aktif), ['nonaktif', 'locked'], true) ? 'yes' : 'no',
        ];

        $profile = $this->resolveProfile($pelanggan);
        if (!empty($profile)) {
            $payload['profile'] = $profile;
        }

        if ($this->getSyncMode() === 'pppoe') {
            $payload['service'] = 'pppoe';
        }

        return $payload;
    }

    public function getSystemSummary(): array
    {
        $result = [
            'configured' => $this->isConfigured(),
            'connected' => false,
            'host' => (string) config('services.mikrotik.host', ''),
            'identity' => null,
            'uptime' => null,
            'cpu_load' => null,
            'error' => null,
            'mode' => 'legacy-api',
        ];

        if (!$result['configured']) {
            $result['error'] = 'Konfigurasi MikroTik belum lengkap di file .env';
            return $result;
        }

        try {
            $client = $this->makeLegacyClient();
            $identity = $this->firstRecord($client->comm('/system/identity/print'));
            $resource = $this->firstRecord($client->comm('/system/resource/print'));

            $result['connected'] = true;
            $result['identity'] = $identity['name'] ?? null;
            $result['uptime'] = $resource['uptime'] ?? null;
            $result['cpu_load'] = isset($resource['cpu-load']) ? (int) $resource['cpu-load'] : null;
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    public function getRealtimeStats(): array
    {
        $result = [
            'status' => 'offline',
            'configured' => $this->isConfigured(),
            'connected' => false,
            'identity' => null,
            'uptime' => null,
            'cpu_load' => null,
            'hotspot_active' => 0,
            'pppoe_active' => 0,
            'interface_name' => null,
            'rx_bps' => 0,
            'tx_bps' => 0,
            'error' => null,
        ];

        if (!$result['configured']) {
            $result['error'] = 'Konfigurasi MikroTik belum lengkap di file .env';
            return $result;
        }

        try {
            $api = $this->makeLegacyClient();
            $identity = $this->firstRecord($api->comm('/system/identity/print'));
            $resource = $this->firstRecord($api->comm('/system/resource/print'));
            $hotspotActive = $api->comm(self::HOTSPOT_ACTIVE_PATH . '/print');
            $pppoeActive = $api->comm(self::PPPOE_ACTIVE_PATH . '/print');

            $interfaceName = $this->resolveTrafficInterfaceName($api);
            $traffic = $this->getInterfaceTrafficSnapshot($api, $interfaceName);

            $result['status'] = 'online';
            $result['connected'] = true;
            $result['identity'] = $identity['name'] ?? null;
            $result['uptime'] = $resource['uptime'] ?? null;
            $result['cpu_load'] = isset($resource['cpu-load']) ? (int) $resource['cpu-load'] : null;
            $result['hotspot_active'] = is_array($hotspotActive) ? count($hotspotActive) : 0;
            $result['pppoe_active'] = is_array($pppoeActive) ? count($pppoeActive) : 0;
            $result['interface_name'] = $traffic['interface_name'] ?? $interfaceName;
            $result['rx_bps'] = (int) ($traffic['rx_bps'] ?? 0);
            $result['tx_bps'] = (int) ($traffic['tx_bps'] ?? 0);
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    public function getHotspotActiveSessions(): array
    {
        if (!$this->shouldSync()) {
            return [];
        }

        try {
            $api = $this->makeLegacyClient();
            $sessions = $api->comm(self::HOTSPOT_ACTIVE_PATH . '/print');

            return is_array($sessions) ? $sessions : [];
        } catch (\Throwable $e) {
            Log::warning('MikroTik hotspot active sessions unavailable', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function ensureProfileExists(PaketBandwidth $paket): array
    {
        if (!$this->shouldSync()) {
            return ['success' => true, 'message' => 'Sinkronisasi MikroTik dinonaktifkan.'];
        }

        try {
            $api = $this->makeLegacyClient();
            $profilePath = $this->getProfilePath();
            $existing = $api->comm($profilePath . '/print', ['?name' => $paket->nama_paket]);
            $entry = is_array($existing) && isset($existing[0]) ? $existing[0] : null;

            $payload = $this->buildProfilePayload($paket);

            if ($entry && isset($entry['.id'])) {
                $payload['.id'] = $entry['.id'];
                $api->comm($profilePath . '/set', $payload);
                return ['success' => true, 'message' => 'Profile MikroTik berhasil diperbarui.'];
            }

            $api->comm($profilePath . '/add', $payload);
            return ['success' => true, 'message' => 'Profile MikroTik berhasil dibuat.'];
        } catch (\Throwable $e) {
            Log::warning('MikroTik profile sync failed', [
                'id_paket' => $paket->id_paket,
                'nama_paket' => $paket->nama_paket,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Gagal sinkron profile MikroTik: ' . $e->getMessage()];
        }
    }

    public function ensureProfileExistsByType(PaketBandwidth $paket, string $tipe): array
    {
        if (!$this->shouldSync()) {
            return ['success' => true, 'message' => 'Sinkronisasi MikroTik dinonaktifkan.'];
        }

        try {
            $api = $this->makeLegacyClient();
            $profilePath = $this->getProfilePathByType($tipe);
            $existing = $api->comm($profilePath . '/print', ['?name' => $paket->nama_paket]);
            $entry = is_array($existing) && isset($existing[0]) ? $existing[0] : null;

            $payload = $this->buildProfilePayload($paket);

            if ($entry && isset($entry['.id'])) {
                $payload['.id'] = $entry['.id'];
                $api->comm($profilePath . '/set', $payload);
                return ['success' => true, 'message' => 'Profile MikroTik berhasil diperbarui.'];
            }

            $api->comm($profilePath . '/add', $payload);
            return ['success' => true, 'message' => 'Profile MikroTik berhasil dibuat.'];
        } catch (\Throwable $e) {
            Log::warning('MikroTik profile sync by type failed', [
                'id_paket' => $paket->id_paket,
                'nama_paket' => $paket->nama_paket,
                'tipe' => $tipe,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Gagal sinkron profile MikroTik: ' . $e->getMessage()];
        }
    }

    public function removeProfileIfUnused(string $profileName): array
    {
        if (!$this->shouldSync()) {
            return ['success' => true, 'message' => 'Sinkronisasi MikroTik dinonaktifkan.'];
        }

        try {
            $api = $this->makeLegacyClient();
            $profilePath = $this->getProfilePath();
            $existing = $api->comm($profilePath . '/print', ['?name' => $profileName]);
            $entry = is_array($existing) && isset($existing[0]) ? $existing[0] : null;

            if (!$entry || !isset($entry['.id'])) {
                return ['success' => true, 'message' => 'Profile tidak ditemukan di MikroTik.'];
            }

            $userPath = $this->getBasePath();
            $usedByUsers = $api->comm($userPath . '/print', ['?profile' => $profileName]);
            if (is_array($usedByUsers) && count($usedByUsers) > 0) {
                return ['success' => true, 'message' => 'Profile lama masih dipakai user MikroTik, tidak dihapus.'];
            }

            $api->comm($profilePath . '/remove', ['.id' => $entry['.id']]);

            return ['success' => true, 'message' => 'Profile MikroTik berhasil dihapus.'];
        } catch (\Throwable $e) {
            Log::warning('MikroTik profile remove failed', [
                'profile' => $profileName,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Gagal hapus profile MikroTik: ' . $e->getMessage()];
        }
    }

    public function removeProfileIfUnusedByType(string $profileName, string $tipe): array
    {
        if (!$this->shouldSync()) {
            return ['success' => true, 'message' => 'Sinkronisasi MikroTik dinonaktifkan.'];
        }

        try {
            $api = $this->makeLegacyClient();
            $profilePath = $this->getProfilePathByType($tipe);
            $existing = $api->comm($profilePath . '/print', ['?name' => $profileName]);
            $entry = is_array($existing) && isset($existing[0]) ? $existing[0] : null;

            if (!$entry || !isset($entry['.id'])) {
                return ['success' => true, 'message' => 'Profile tidak ditemukan di MikroTik.'];
            }

            $userPath = $this->getBasePathByType($tipe);
            $usedByUsers = $api->comm($userPath . '/print', ['?profile' => $profileName]);
            if (is_array($usedByUsers) && count($usedByUsers) > 0) {
                return ['success' => true, 'message' => 'Profile lama masih dipakai user MikroTik, tidak dihapus.'];
            }

            $api->comm($profilePath . '/remove', ['.id' => $entry['.id']]);

            return ['success' => true, 'message' => 'Profile MikroTik berhasil dihapus.'];
        } catch (\Throwable $e) {
            Log::warning('MikroTik profile remove by type failed', [
                'profile' => $profileName,
                'tipe' => $tipe,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Gagal hapus profile MikroTik: ' . $e->getMessage()];
        }
    }

    public function tambahProfilHotspot(PaketBandwidth $paket): array
    {
        return $this->ensureProfileExistsByType($paket, 'Hotspot');
    }

    public function tambahProfilPPPoE(PaketBandwidth $paket): array
    {
        return $this->ensureProfileExistsByType($paket, 'PPPoE');
    }

    public function tambahUserHotspot(Pelanggan $pelanggan): array
    {
        return $this->syncPelangganCreatedByType($pelanggan, 'Hotspot');
    }

    public function tambahUserPPPoE(Pelanggan $pelanggan): array
    {
        return $this->syncPelangganCreatedByType($pelanggan, 'PPPoE');
    }

    public function syncPelangganCreatedByType(Pelanggan $pelanggan, string $tipe): array
    {
        if (!$this->shouldSync()) {
            return ['success' => true, 'message' => 'Sinkronisasi MikroTik dinonaktifkan.'];
        }

        try {
            $pelanggan->loadMissing('paket');
            if ($pelanggan->paket) {
                $profileSync = $this->ensureProfileExistsByType($pelanggan->paket, $tipe);
                if (!$profileSync['success']) {
                    return $profileSync;
                }
            }

            $api = $this->makeLegacyClient();
            $payload = $this->buildUserPayloadByType($pelanggan, $tipe);
            $api->comm($this->getBasePathByType($tipe) . '/add', $payload);

            return ['success' => true, 'message' => "Pelanggan {$tipe} berhasil ditambahkan ke MikroTik."];
        } catch (\Throwable $e) {
            Log::warning('MikroTik sync create by type failed', [
                'tipe' => $tipe,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Gagal sinkron ke MikroTik: ' . $e->getMessage()];
        }
    }

    public function syncPelangganCreated(Pelanggan $pelanggan): array
    {
        if (!$this->shouldSync()) {
            return ['success' => true, 'message' => 'Sinkronisasi MikroTik dinonaktifkan.'];
        }

        try {
            $pelanggan->loadMissing('paket');
            if ($pelanggan->paket) {
                $profileSync = $this->ensureProfileExists($pelanggan->paket);
                if (!$profileSync['success']) {
                    return $profileSync;
                }
            }

            $api = $this->makeLegacyClient();
            $api->comm($this->getBasePath() . '/add', $this->buildUserPayload($pelanggan));

            return ['success' => true, 'message' => 'Data pelanggan berhasil dikirim ke MikroTik.'];
        } catch (\Throwable $e) {
            Log::warning('MikroTik sync create failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Gagal sinkron ke MikroTik: ' . $e->getMessage()];
        }
    }

    public function syncPelangganUpdatedByType(Pelanggan $pelanggan, string $oldUsername, string $tipe): array
    {
        if (!$this->shouldSync()) {
            return ['success' => true, 'message' => 'Sinkronisasi MikroTik dinonaktifkan.'];
        }

        try {
            $pelanggan->loadMissing('paket');
            if ($pelanggan->paket) {
                $profileSync = $this->ensureProfileExistsByType($pelanggan->paket, $tipe);
                if (!$profileSync['success']) {
                    return $profileSync;
                }
            }

            $api = $this->makeLegacyClient();
            $endpointBase = trim($tipe) === 'PPPoE' ? '/ppp/secret' : '/ip/hotspot/user';

            $existing = $api->comm($endpointBase . '/print', ['?name' => $oldUsername]);
            $entry = is_array($existing) && isset($existing[0]) ? $existing[0] : null;

            if (!$entry || !isset($entry['.id'])) {
                $payload = $this->buildUserPayloadByType($pelanggan, $tipe);

                $api->comm($endpointBase . '/add', $payload);
                return ['success' => true, 'message' => "Pelanggan {$tipe} ditambahkan ulang di MikroTik (tidak ditemukan data lama)."];
            }

            $payload = $this->buildUserPayloadByType($pelanggan, $tipe);
            $payload['.id'] = $entry['.id'];

            $api->comm($endpointBase . '/set', $payload);

            return ['success' => true, 'message' => "Data pelanggan {$tipe} berhasil diperbarui di MikroTik."];
        } catch (\Throwable $e) {
            Log::warning('MikroTik sync update by type failed', [
                'tipe' => $tipe,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Gagal update MikroTik: ' . $e->getMessage()];
        }
    }

    public function syncPelangganUpdated(Pelanggan $pelanggan, string $oldUsername): array
    {
        if (!$this->shouldSync()) {
            return ['success' => true, 'message' => 'Sinkronisasi MikroTik dinonaktifkan.'];
        }

        try {
            $tipe = $this->detectTypeFromPaket($pelanggan);
            return $this->syncPelangganUpdatedByType($pelanggan, $oldUsername, $tipe);
        } catch (\Throwable $e) {
            Log::warning('MikroTik sync update failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Gagal update MikroTik: ' . $e->getMessage()];
        }
    }

    public function syncPelangganDeletedByType(string $username, string $tipe): array
    {
        if (!$this->shouldSync()) {
            return ['success' => true, 'message' => 'Sinkronisasi MikroTik dinonaktifkan.'];
        }

        try {
            $api = $this->makeLegacyClient();
            $basePath = $this->getBasePathByType($tipe);

            $existing = $api->comm($basePath . '/print', ['?name' => $username]);
            $entry = is_array($existing) && isset($existing[0]) ? $existing[0] : null;

            if ($entry && isset($entry['.id'])) {
                $api->comm($basePath . '/remove', ['.id' => $entry['.id']]);
            }

            return ['success' => true, 'message' => "Pelanggan {$tipe} berhasil dihapus di MikroTik."];
        } catch (\Throwable $e) {
            Log::warning('MikroTik sync delete by type failed', [
                'username' => $username,
                'tipe' => $tipe,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Gagal hapus di MikroTik: ' . $e->getMessage()];
        }
    }

    public function syncPelangganDeleted(string $username): array
    {
        if (!$this->shouldSync()) {
            return ['success' => true, 'message' => 'Sinkronisasi MikroTik dinonaktifkan.'];
        }

        try {
            $api = $this->makeLegacyClient();
            $base = $this->getBasePath();

            $existing = $api->comm($base . '/print', ['?name' => $username]);
            $entry = is_array($existing) && isset($existing[0]) ? $existing[0] : null;

            if ($entry && isset($entry['.id'])) {
                $api->comm($base . '/remove', ['.id' => $entry['.id']]);
            }

            return ['success' => true, 'message' => 'Data pelanggan berhasil dihapus di MikroTik.'];
        } catch (\Throwable $e) {
            Log::warning('MikroTik sync delete failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Gagal hapus di MikroTik: ' . $e->getMessage()];
        }
    }

    public function syncAllPelanggan(): array
    {
        if (!$this->shouldSync()) {
            return ['success' => true, 'message' => 'Sinkronisasi MikroTik dinonaktifkan.', 'synced' => 0, 'failed' => 0];
        }

        try {
            $api = $this->makeLegacyClient();
            $base = $this->getBasePath();
            $synced = 0;
            $failed = 0;

            Pelanggan::with('paket')->chunk(100, function ($chunk) use ($api, $base, &$synced, &$failed) {
                foreach ($chunk as $pelanggan) {
                    try {
                        if ($pelanggan->paket) {
                            $profileSync = $this->ensureProfileExists($pelanggan->paket);
                            if (!$profileSync['success']) {
                                throw new \RuntimeException($profileSync['message']);
                            }
                        }

                        $existing = $api->comm($base . '/print', ['?name' => $pelanggan->username_mikrotik]);
                        $entry = is_array($existing) && isset($existing[0]) ? $existing[0] : null;

                        $payload = $this->buildUserPayload($pelanggan);
                        if ($entry && isset($entry['.id'])) {
                            $payload['.id'] = $entry['.id'];
                            $api->comm($base . '/set', $payload);
                        } else {
                            $api->comm($base . '/add', $payload);
                        }

                        $synced++;
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::warning('MikroTik sync bulk failed per user', [
                            'id_pelanggan' => $pelanggan->id_pelanggan,
                            'username' => $pelanggan->username_mikrotik,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

            return [
                'success' => $failed === 0,
                'message' => "Sinkronisasi selesai. Berhasil: {$synced}, Gagal: {$failed}.",
                'synced' => $synced,
                'failed' => $failed,
            ];
        } catch (\Throwable $e) {
            Log::warning('MikroTik sync bulk failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Gagal sinkronisasi massal: ' . $e->getMessage(), 'synced' => 0, 'failed' => 0];
        }
    }

    public function setPelangganStateByType(Pelanggan $pelanggan, bool $enabled, string $tipe): array
    {
        if (!$this->shouldSync()) {
            return ['success' => true, 'message' => 'Sinkronisasi MikroTik dinonaktifkan.'];
        }

        try {
            $api = $this->makeLegacyClient();
            $basePath = $this->getBasePathByType($tipe);
            $existing = $api->comm($basePath . '/print', ['?name' => $pelanggan->username_mikrotik]);
            $entry = is_array($existing) && isset($existing[0]) ? $existing[0] : null;

            if (!$entry || !isset($entry['.id'])) {
                return ['success' => false, 'message' => "User {$tipe} MikroTik tidak ditemukan untuk diubah statusnya."];
            }

            $api->comm($basePath . '/set', [
                '.id' => $entry['.id'],
                'disabled' => $enabled ? 'no' : 'yes',
            ]);

            $statusMsg = $enabled ? 'diaktifkan' : 'dikunci (disabled)';
            return ['success' => true, 'message' => "User {$tipe} di MikroTik berhasil {$statusMsg}."];
        } catch (\Throwable $e) {
            Log::warning('MikroTik set state by type failed', [
                'tipe' => $tipe,
                'id_pelanggan' => $pelanggan->id_pelanggan,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => 'Gagal ubah status di MikroTik: ' . $e->getMessage()];
        }
    }

    public function setPelangganState(Pelanggan $pelanggan, bool $enabled): array
    {
        if (!$this->shouldSync()) {
            return ['success' => true, 'message' => 'Sinkronisasi MikroTik dinonaktifkan.'];
        }

        try {
            $tipe = $this->detectTypeFromPaket($pelanggan);
            return $this->setPelangganStateByType($pelanggan, $enabled, $tipe);
        } catch (\Throwable $e) {
            Log::warning('MikroTik set state failed', [
                'id_pelanggan' => $pelanggan->id_pelanggan,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => 'Gagal ubah status di MikroTik: ' . $e->getMessage()];
        }
    }

    public function disconnectActiveSessionByType(Pelanggan $pelanggan, string $tipe): array
    {
        if (!$this->shouldSync()) {
            return ['success' => true, 'message' => 'Sinkronisasi MikroTik dinonaktifkan.'];
        }

        try {
            $api = $this->makeLegacyClient();
            $activePath = $this->getActivePathByType($tipe);
            $active = $api->comm($activePath . '/print', ['?name' => $pelanggan->username_mikrotik]);

            if (!is_array($active) || count($active) === 0) {
                return ['success' => true, 'message' => "Tidak ada sesi {$tipe} aktif untuk user ini."];
            }

            $removed = 0;
            foreach ($active as $item) {
                if (isset($item['.id'])) {
                    $api->comm($activePath . '/remove', ['.id' => $item['.id']]);
                    $removed++;
                }
            }

            return ['success' => true, 'message' => "Sesi {$tipe} aktif user diputus ({$removed} sesi)."];
        } catch (\Throwable $e) {
            Log::warning('MikroTik disconnect session by type failed', [
                'tipe' => $tipe,
                'id_pelanggan' => $pelanggan->id_pelanggan,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => "Gagal putus sesi {$tipe} di MikroTik: " . $e->getMessage()];
        }
    }

    public function disconnectActiveSession(Pelanggan $pelanggan): array
    {
        if (!$this->shouldSync()) {
            return ['success' => true, 'message' => 'Sinkronisasi MikroTik dinonaktifkan.'];
        }

        try {
            $tipe = $this->detectTypeFromPaket($pelanggan);
            return $this->disconnectActiveSessionByType($pelanggan, $tipe);
        } catch (\Throwable $e) {
            Log::warning('MikroTik disconnect session failed', [
                'id_pelanggan' => $pelanggan->id_pelanggan,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => 'Gagal putus sesi di MikroTik: ' . $e->getMessage()];
        }
    }
}
