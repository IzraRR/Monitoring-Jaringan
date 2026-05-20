<?php

namespace App\Console\Commands;

use App\Models\Pelanggan;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class KirimTagihanWA extends Command
{
    /**
     * Signature command untuk Laravel Console
     */
    protected $signature = 'app:kirim-tagihan-wa';

    /**
     * Deskripsi command
     */
    protected $description = 'Mengirim notifikasi tagihan via WhatsApp untuk pelanggan yang masa aktifnya akan habis (H-3)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Log::info('=== MULAI: Kirim Tagihan WhatsApp ===');
        $this->line('🔄 Mencari pelanggan dengan masa aktif yang akan habis...');

        try {
            // Tentukan range tanggal: hari ini sampai H+3 (3 hari ke depan)
            // Gunakan Carbon::today() agar hanya membandingkan tanggal (tanpa waktu/jam)
            $tanggalMulai = Carbon::today();
            $tanggalAkhir = Carbon::today()->addDays(3);

            $this->line('🔍 Memeriksa pelanggan dengan masa aktif antara ' . $tanggalMulai->translatedFormat('d F Y') . ' sampai ' . $tanggalAkhir->translatedFormat('d F Y') . '.');

            // Query pelanggan aktif dengan masa aktif di antara hari ini sampai 3 hari ke depan
            $pelangganList = Pelanggan::with('paket')
                ->where('status_aktif', 'Aktif')
                ->whereBetween('masa_aktif', [$tanggalMulai->toDateString(), $tanggalAkhir->toDateString()])
                ->get();

            if ($pelangganList->isEmpty()) {
                Log::info('Tidak ada pelanggan yang jatuh tempo antara ' . $tanggalMulai->translatedFormat('d F Y') . ' sampai ' . $tanggalAkhir->translatedFormat('d F Y') . '.');
                $this->info("✅ Tidak ada pelanggan yang memerlukan notifikasi dalam rentang ini.");
                return self::SUCCESS;
            }

            $this->line("📋 Ditemukan {$pelangganList->count()} pelanggan untuk diperiksa.");
            Log::info('Ditemukan ' . $pelangganList->count() . ' pelanggan untuk notifikasi tagihan dalam rentang ' . $tanggalMulai->translatedFormat('d F Y') . ' sampai ' . $tanggalAkhir->translatedFormat('d F Y') . '.');

            $successCount = 0;
            $failureCount = 0;
            $skippedCount = 0;

            // Looping setiap pelanggan dan kirim notifikasi
            foreach ($pelangganList as $pelanggan) {
                try {
                    // Cek cache untuk mencegah spam: jika sudah pernah dikirim dalam periode ini, skip
                    $cacheKey = 'wa_tagihan_sent_' . $pelanggan->id_pelanggan;
                    if (Cache::has($cacheKey)) {
                        Log::info("Pelanggan ID {$pelanggan->id_pelanggan} ({$pelanggan->nama_pelanggan}) sudah menerima notifikasi sebelumnya, skip.");
                        $this->line("⏭️  Pelanggan {$pelanggan->nama_pelanggan} sudah pernah dikirimi pesan, dilewati.");
                        $skippedCount++;
                        continue;
                    }

                    // Validasi data pelanggan
                    if (empty($pelanggan->no_hp)) {
                        Log::warning('Pelanggan ID ' . $pelanggan->id_pelanggan . ' (' . $pelanggan->nama_pelanggan . ') tidak punya nomor HP.');
                        $this->warn('⚠️  Pelanggan ' . $pelanggan->nama_pelanggan . ' tidak punya nomor HP.');
                        $failureCount++;
                        continue;
                    }

                    // Validasi paket relasi
                    if (!$pelanggan->paket) {
                        Log::warning('Pelanggan ID ' . $pelanggan->id_pelanggan . ' tidak punya paket terkait.');
                        $this->warn('⚠️  Pelanggan ' . $pelanggan->nama_pelanggan . ' tidak punya paket.');
                        $failureCount++;
                        continue;
                    }

                    // Normalisasi nomor WhatsApp (08xxx -> 628xxx)
                    $phoneNumber = $this->normalizeWhatsAppNumber($pelanggan->no_hp);
                    if (empty($phoneNumber)) {
                        Log::warning('Nomor HP ' . $pelanggan->no_hp . ' pelanggan ID ' . $pelanggan->id_pelanggan . ' tidak valid.');
                        $this->warn('⚠️  Nomor HP pelanggan ' . $pelanggan->nama_pelanggan . ' tidak valid.');
                        $failureCount++;
                        continue;
                    }

                    // Siapkan pesan notifikasi
                    $masaAktifFormat = Carbon::parse($pelanggan->masa_aktif)->translatedFormat('d F Y');
                    $hargaFormat = number_format($pelanggan->paket->harga, 0, ',', '.');

                    $pesan = 'Peringatan Tagihan: Masa aktif paket ' . $pelanggan->paket->nama_paket . ' Anda akan habis pada ' . $masaAktifFormat . '. Tagihan: Rp ' . $hargaFormat . '. Silakan lakukan pembayaran segera untuk menjaga kelancaran layanan Anda.';

                    // Kirim via WhatsApp Gateway (Fonnte)
                    $sendResult = $this->sendWhatsAppNotification(
                        $phoneNumber,
                        $pesan,
                        $pelanggan->id_pelanggan,
                        $pelanggan->nama_pelanggan
                    );

                    if ($sendResult['success']) {
                        // Set cache untuk 4 hari ke depan agar tidak terkirim lagi dalam rentang ini
                        Cache::put($cacheKey, true, now()->addDays(4));
                        $successCount++;
                        $this->line('✅ Notifikasi terkirim ke ' . $pelanggan->nama_pelanggan . '.');
                    } else {
                        $this->error('❌ Gagal kirim ke ' . $pelanggan->nama_pelanggan . ' (' . $pelanggan->no_hp . '): ' . $sendResult['error']);
                        $failureCount++;
                    }

                } catch (\Throwable $e) {
                    $errorMessage = $this->formatWhatsAppError($e);
                    Log::error('Error mengirim notifikasi untuk pelanggan ID ' . $pelanggan->id_pelanggan . ': ' . $errorMessage, [
                        'exception' => get_class($e),
                        'raw_message' => $e->getMessage(),
                    ]);
                    $this->error('❌ Gagal mengirim ke ' . $pelanggan->nama_pelanggan . ': ' . $errorMessage);
                    $failureCount++;
                }
            }

            // Summary hasil
            Log::info('=== SELESAI: Kirim Tagihan WhatsApp ===');
            Log::info('Rentang: ' . $tanggalMulai->translatedFormat('d F Y') . ' sampai ' . $tanggalAkhir->translatedFormat('d F Y') . ' | Berhasil: ' . $successCount . ', Gagal: ' . $failureCount . ', Dilewati (cache): ' . $skippedCount);

            $this->line("\n📊 RINGKASAN:");
            $this->line('   📅 Rentang: ' . $tanggalMulai->translatedFormat('d F Y') . ' sampai ' . $tanggalAkhir->translatedFormat('d F Y'));
            $this->line("   ✅ Berhasil dikirim: {$successCount}");
            $this->line("   ❌ Gagal: {$failureCount}");
            $this->line("   ⏭️  Dilewati (sudah dikirim): {$skippedCount}");

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $errorMessage = $this->formatWhatsAppError($e);
            Log::error('Galat fatal dalam KirimTagihanWA: ' . $errorMessage, [
                'exception' => get_class($e),
                'raw_message' => $e->getMessage(),
            ]);
            $this->error('❌ Galat fatal: ' . $errorMessage);
            return self::FAILURE;
        }
    }

    /**
     * Normalisasi nomor WhatsApp ke format internasional (628xxxxx)
     */
    private function normalizeWhatsAppNumber(?string $rawNumber): string
    {
        $digits = preg_replace('/\D+/', '', (string) $rawNumber) ?? '';

        if ($digits === '') {
            return '';
        }

        // Konversi format lokal 08xxxx menjadi 628xxxx
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        }

        // Jika masih tanpa prefix negara, default ke Indonesia
        if (!str_starts_with($digits, '62')) {
            $digits = '62' . ltrim($digits, '0');
        }

        return $digits;
    }

    /**
     * Kirim notifikasi via WhatsApp Gateway (Fonnte API)
     * Mengembalikan array dengan format ['success' => bool, 'error' => string]
     */
    private function sendWhatsAppNotification(string $phoneNumber, string $pesan, int $idPelanggan, string $namaPelanggan): array
    {
        // Ambil konfigurasi WhatsApp dari services.php
        $whatsappConfig = config('services.whatsapp');

        // Validasi konfigurasi
        if (!$whatsappConfig || !$whatsappConfig['enabled']) {
            $msg = "WhatsApp Gateway tidak diaktifkan. Aktifkan di config('services.whatsapp.enabled')";
            Log::warning("Pelanggan ID {$idPelanggan}: {$msg}");
            return ['success' => false, 'error' => $msg];
        }

        if (empty($whatsappConfig['token'])) {
            $msg = "WhatsApp API Token tidak dikonfigurasi. Set di .env: WHATSAPP_TOKEN dan WHATSAPP_URL";
            Log::error("Pelanggan ID {$idPelanggan}: {$msg}");
            return ['success' => false, 'error' => $msg];
        }

        if (empty($whatsappConfig['url'])) {
            $msg = "WhatsApp API URL tidak dikonfigurasi. Set di .env: WHATSAPP_URL";
            Log::error("Pelanggan ID {$idPelanggan}: {$msg}");
            return ['success' => false, 'error' => $msg];
        }

        try {
            // Kirim request ke Fonnte API dengan format form-data
            // PENTING: Fonnte hanya menerima token murni di header (bukan "Bearer " prefix)
            $response = Http::asForm()
                ->withHeaders([
                    'Authorization' => $whatsappConfig['token'],
                    'Accept' => 'application/json',
                ])
                ->timeout(15)
                ->post($whatsappConfig['url'], [
                    'target' => $phoneNumber,
                    'message' => $pesan,
                    'countryCode' => $whatsappConfig['country_code'] ?? '62',
                ]);

            // Cek status HTTP response
            if (!$response->successful()) {
                $errorMsg = "HTTP Error {$response->status()}: " . $response->body();
                Log::error("WhatsApp HTTP Error untuk pelanggan ID {$idPelanggan}: {$errorMsg}");
                return ['success' => false, 'error' => $errorMsg];
            }

            $responseData = $response->json();

            // Cek response status dari API
            if (isset($responseData['status']) && $responseData['status'] == 'success') {
                Log::info("WhatsApp notifikasi terkirim ke pelanggan ID {$idPelanggan} ({$namaPelanggan}), target: {$phoneNumber}");
                return ['success' => true, 'error' => ''];
            } else {
                $errorMsg = "API Response Error: " . json_encode($responseData);
                Log::warning("WhatsApp notifikasi gagal untuk pelanggan ID {$idPelanggan}: {$errorMsg}");
                return ['success' => false, 'error' => $errorMsg];
            }

        } catch (ConnectionException $e) {
            $errorMsg = $this->formatWhatsAppError($e);
            Log::error("Koneksi gagal saat mengirim WA ke {$phoneNumber} (Pelanggan ID {$idPelanggan}): {$errorMsg}", [
                'raw_message' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $errorMsg];
        } catch (\Throwable $e) {
            $errorMsg = $this->formatWhatsAppError($e);
            Log::error("Error saat mengirim WhatsApp ke pelanggan ID {$idPelanggan}: {$errorMsg}", [
                'raw_message' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $errorMsg];
        }
    }

    /**
     * Ubah pesan error teknis menjadi pesan yang lebih jelas untuk operator.
     */
    private function formatWhatsAppError(\Throwable $e): string
    {
        $message = $e->getMessage();
        $curlCode = $this->extractCurlCode($message);

        if ($curlCode !== null) {
            return match ($curlCode) {
                6 => 'Gagal menghubungi server WhatsApp: domain API tidak bisa di-resolve (cek internet atau DNS).',
                7 => 'Gagal terhubung ke server WhatsApp: koneksi ditolak.',
                28 => 'Gagal mengirim ke server WhatsApp: koneksi terlalu lama / timeout.',
                35 => 'Gagal mengirim ke server WhatsApp: masalah SSL/TLS.',
                52 => 'Gagal mengirim ke server WhatsApp: respons kosong dari server.',
                56 => 'Gagal mengirim ke server WhatsApp: koneksi terputus saat proses pengiriman.',
                default => 'Gagal mengirim ke server WhatsApp (cURL ' . $curlCode . '): ' . $message,
            };
        }

        if ($e instanceof ConnectionException) {
            return 'Gagal koneksi ke server WhatsApp: ' . $message;
        }

        return 'Terjadi galat saat mengirim WhatsApp: ' . $message;
    }

    /**
     * Ambil kode cURL dari pesan error bila tersedia.
     */
    private function extractCurlCode(string $message): ?int
    {
        if (preg_match('/cURL error\s+(\d+)/i', $message, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
