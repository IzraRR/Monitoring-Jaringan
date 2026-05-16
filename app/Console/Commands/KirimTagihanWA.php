<?php

namespace App\Console\Commands;

use App\Models\Pelanggan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        $this->line('🔄 Mencari pelanggan dengan masa aktif mendekati batas...');

        try {
            // Tentukan range tanggal untuk H-3 atau sudah lewat masa aktif (belum suspend)
            $today = Carbon::now()->toDateString();
            $threeDaysFromNow = Carbon::now()->addDays(3)->toDateString();

            // Query pelanggan aktif dengan masa aktif H-3 atau lebih awal
            $pelangganList = Pelanggan::with('paket')
                ->where('status_aktif', 'Aktif')
                ->whereDate('masa_aktif', '<=', $threeDaysFromNow)
                ->whereDate('masa_aktif', '>=', $today)
                ->get();

            if ($pelangganList->isEmpty()) {
                Log::info('Tidak ada pelanggan dengan masa aktif H-3. Cron selesai.');
                $this->info('✅ Tidak ada pelanggan yang memerlukan notifikasi saat ini.');
                return self::SUCCESS;
            }

            $this->line("📋 Ditemukan {$pelangganList->count()} pelanggan untuk dikirim notifikasi.");
            Log::info("Ditemukan {$pelangganList->count()} pelanggan untuk notifikasi tagihan.");

            $successCount = 0;
            $failureCount = 0;

            // Looping setiap pelanggan dan kirim notifikasi
            foreach ($pelangganList as $pelanggan) {
                try {
                    // Validasi data pelanggan
                    if (empty($pelanggan->no_hp)) {
                        Log::warning("Pelanggan ID {$pelanggan->id_pelanggan} ({$pelanggan->nama_pelanggan}) tidak punya nomor HP.");
                        $this->warn("⚠️  Pelanggan {$pelanggan->nama_pelanggan} tidak punya nomor HP.");
                        $failureCount++;
                        continue;
                    }

                    // Validasi paket relasi
                    if (!$pelanggan->paket) {
                        Log::warning("Pelanggan ID {$pelanggan->id_pelanggan} tidak punya paket terkait.");
                        $this->warn("⚠️  Pelanggan {$pelanggan->nama_pelanggan} tidak punya paket.");
                        $failureCount++;
                        continue;
                    }

                    // Normalisasi nomor WhatsApp (08xxx -> 628xxx)
                    $phoneNumber = $this->normalizeWhatsAppNumber($pelanggan->no_hp);
                    if (empty($phoneNumber)) {
                        Log::warning("Nomor HP {$pelanggan->no_hp} pelanggan ID {$pelanggan->id_pelanggan} tidak valid.");
                        $this->warn("⚠️  Nomor HP pelanggan {$pelanggan->nama_pelanggan} tidak valid.");
                        $failureCount++;
                        continue;
                    }

                    // Siapkan pesan notifikasi
                    $masaAktifFormat = Carbon::parse($pelanggan->masa_aktif)->translatedFormat('d F Y');
                    $hargaFormat = number_format($pelanggan->paket->harga, 0, ',', '.');

                    $pesan = "Peringatan Tagihan: Masa aktif paket {$pelanggan->paket->nama_paket} Anda akan habis pada {$masaAktifFormat}. Tagihan: Rp {$hargaFormat}. Silakan lakukan pembayaran segera untuk menjaga kelancaran layanan Anda.";

                    // Kirim via WhatsApp Gateway (Fonnte)
                    $this->sendWhatsAppNotification(
                        $phoneNumber,
                        $pesan,
                        $pelanggan->id_pelanggan,
                        $pelanggan->nama_pelanggan
                    );

                    $successCount++;
                    $this->line("✅ Notifikasi terkirim ke {$pelanggan->nama_pelanggan}");

                } catch (\Exception $e) {
                    Log::error("Error mengirim notifikasi untuk pelanggan ID {$pelanggan->id_pelanggan}: " . $e->getMessage());
                    $this->error("❌ Gagal mengirim ke {$pelanggan->nama_pelanggan}: {$e->getMessage()}");
                    $failureCount++;
                }
            }

            // Summary hasil
            Log::info("=== SELESAI: Kirim Tagihan WhatsApp ===");
            Log::info("Berhasil: {$successCount}, Gagal: {$failureCount}");

            $this->line("\n📊 RINGKASAN:");
            $this->line("   ✅ Berhasil: {$successCount}");
            $this->line("   ❌ Gagal: {$failureCount}");

            return self::SUCCESS;

        } catch (\Exception $e) {
            Log::error('Fatal Error dalam KirimTagihanWA: ' . $e->getMessage());
            $this->error("❌ Error Fatal: {$e->getMessage()}");
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
     */
    private function sendWhatsAppNotification(string $phoneNumber, string $pesan, int $idPelanggan, string $namaPelanggan): void
    {
        // Ambil konfigurasi WhatsApp dari services.php
        $whatsappConfig = config('services.whatsapp');

        // Validasi konfigurasi
        if (!$whatsappConfig || !$whatsappConfig['enabled']) {
            Log::warning("WhatsApp Gateway tidak diaktifkan untuk pelanggan ID {$idPelanggan}.");
            return;
        }

        if (empty($whatsappConfig['token'])) {
            Log::error("WhatsApp API Token tidak dikonfigurasi untuk pelanggan ID {$idPelanggan}.");
            return;
        }

        try {
            // Kirim request ke Fonnte API dengan format form-data
            $response = Http::asForm()
                ->timeout(15)
                ->post($whatsappConfig['url'], [
                    'target' => $phoneNumber,
                    'message' => $pesan,
                    'countryCode' => $whatsappConfig['country_code'] ?? '62',
                ])
                ->throw() // Lempar exception jika HTTP error
                ->json();

            // Cek response status
            if (isset($response['status']) && $response['status'] == 'success') {
                Log::info("WhatsApp notifikasi terkirim ke pelanggan ID {$idPelanggan} ({$namaPelanggan}), target: {$phoneNumber}");
            } else {
                Log::warning("WhatsApp notifikasi gagal untuk pelanggan ID {$idPelanggan}, response: " . json_encode($response));
            }

        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error("HTTP Request Error saat mengirim WA ke {$phoneNumber} (Pelanggan ID {$idPelanggan}): " . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Error saat mengirim WhatsApp ke pelanggan ID {$idPelanggan}: " . $e->getMessage());
        }
    }
}
