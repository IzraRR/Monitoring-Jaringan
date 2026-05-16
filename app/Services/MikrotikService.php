<?php

namespace App\Services;

require_once __DIR__ . '/routeros_api.class.php';
use App\Models\PaketBandwidth;
use App\Models\Pelanggan;
use Illuminate\Support\Facades\Log;

class MikrotikService
{
    private const HOTSPOT_PROFILE_PATH = '/ip/hotspot/user/profile';
    private const PPPOE_PROFILE_PATH = '/ppp/profile';

    public function isConfigured(): bool
    {
        $config = config('services.mikrotik', []);

        return !empty($config['host'])
            && !empty($config['user'])
            && array_key_exists('pass', $config);
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

            $identityResponse = $client->comm('/system/identity/print');
            $resourceResponse = $client->comm('/system/resource/print');

            $identity = is_array($identityResponse) && isset($identityResponse[0]) ? $identityResponse[0] : [];
            $resource = is_array($resourceResponse) && isset($resourceResponse[0]) ? $resourceResponse[0] : [];

            $result['connected'] = true;
            $result['identity'] = $identity['name'] ?? null;
            $result['uptime'] = $resource['uptime'] ?? null;
            $result['cpu_load'] = isset($resource['cpu-load']) ? (int) $resource['cpu-load'] : null;
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    private function makeLegacyClient(): \RouterosAPI
    {
        $api = new \RouterosAPI();
        $api->port = (int) config('services.mikrotik.api_port', 8728);
        $api->ssl = (bool) config('services.mikrotik.api_ssl', false);
        $api->timeout = (int) config('services.mikrotik.api_timeout', 10);

        $connected = $api->connect(
            (string) config('services.mikrotik.host'),
            (string) config('services.mikrotik.user'),
            (string) config('services.mikrotik.pass')
        );

        if (!$connected) {
            throw new \RuntimeException($api->getLastError() ?? 'Gagal terhubung ke RouterOS API.');
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

    private function getProfile(): ?string
    {
        return $this->getSyncMode() === 'pppoe'
            ? (string) config('services.mikrotik.pppoe_profile')
            : (string) config('services.mikrotik.hotspot_profile');
    }

    private function getBasePath(): string
    {
        return $this->getSyncMode() === 'pppoe'
            ? '/ppp/secret'
            : '/ip/hotspot/user';
    }

    private function getProfilePath(): string
    {
        return $this->getSyncMode() === 'pppoe'
            ? self::PPPOE_PROFILE_PATH
            : self::HOTSPOT_PROFILE_PATH;
    }

    private function normalizeLimit(string $value): string
    {
        $v = strtolower(trim($value));
        if ($v === '') {
            return '';
        }

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

        // RouterOS format: tx/rx -> upload/download.
        return $upload . '/' . $download;
    }

    private function buildProfilePayload(PaketBandwidth $paket): array
    {
        $payload = [
            'name' => $paket->nama_paket,
        ];

        $rateLimit = $this->formatRateLimit($paket);
        if ($rateLimit !== '') {
            if ($this->getSyncMode() === 'pppoe') {
                $payload['rate-limit'] = $rateLimit;
            } else {
                $payload['rate-limit'] = $rateLimit;
            }
        }

        return $payload;
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

    public function syncPelangganUpdated(Pelanggan $pelanggan, string $oldUsername): array
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
            $base = $this->getBasePath();

            $existing = $api->comm($base . '/print', ['?name' => $oldUsername]);
            $entry = is_array($existing) && isset($existing[0]) ? $existing[0] : null;

            if (!$entry || !isset($entry['.id'])) {
                $api->comm($base . '/add', $this->buildUserPayload($pelanggan));
                return ['success' => true, 'message' => 'Data pelanggan ditambahkan ulang di MikroTik.'];
            }

            $payload = $this->buildUserPayload($pelanggan);
            $payload['.id'] = $entry['.id'];

            $api->comm($base . '/set', $payload);

            return ['success' => true, 'message' => 'Data pelanggan berhasil diperbarui di MikroTik.'];
        } catch (\Throwable $e) {
            Log::warning('MikroTik sync update failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Gagal update MikroTik: ' . $e->getMessage()];
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

    public function setPelangganState(Pelanggan $pelanggan, bool $enabled): array
    {
        if (!$this->shouldSync()) {
            return ['success' => true, 'message' => 'Sinkronisasi MikroTik dinonaktifkan.'];
        }

        try {
            $api = $this->makeLegacyClient();
            $base = $this->getBasePath();
            $existing = $api->comm($base . '/print', ['?name' => $pelanggan->username_mikrotik]);
            $entry = is_array($existing) && isset($existing[0]) ? $existing[0] : null;

            if (!$entry || !isset($entry['.id'])) {
                return ['success' => false, 'message' => 'User MikroTik tidak ditemukan untuk diubah statusnya.'];
            }

            $api->comm($base . '/set', [
                '.id' => $entry['.id'],
                'disabled' => $enabled ? 'no' : 'yes',
            ]);

            return ['success' => true, 'message' => $enabled ? 'User diaktifkan di MikroTik.' : 'User dikunci/disable di MikroTik.'];
        } catch (\Throwable $e) {
            Log::warning('MikroTik set state failed', [
                'id_pelanggan' => $pelanggan->id_pelanggan,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => 'Gagal ubah status di MikroTik: ' . $e->getMessage()];
        }
    }

    public function disconnectActiveSession(Pelanggan $pelanggan): array
    {
        if (!$this->shouldSync()) {
            return ['success' => true, 'message' => 'Sinkronisasi MikroTik dinonaktifkan.'];
        }

        try {
            $api = $this->makeLegacyClient();
            $activePath = $this->getSyncMode() === 'pppoe' ? '/ppp/active' : '/ip/hotspot/active';
            $active = $api->comm($activePath . '/print', ['?name' => $pelanggan->username_mikrotik]);

            if (!is_array($active) || count($active) === 0) {
                return ['success' => true, 'message' => 'Tidak ada sesi aktif untuk user ini.'];
            }

            $removed = 0;
            foreach ($active as $item) {
                if (isset($item['.id'])) {
                    $api->comm($activePath . '/remove', ['.id' => $item['.id']]);
                    $removed++;
                }
            }

            return ['success' => true, 'message' => "Sesi aktif user diputus ({$removed} sesi)."];
        } catch (\Throwable $e) {
            Log::warning('MikroTik disconnect session failed', [
                'id_pelanggan' => $pelanggan->id_pelanggan,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => 'Gagal putus sesi di MikroTik: ' . $e->getMessage()];
        }
    }
}
