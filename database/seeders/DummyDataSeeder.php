<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Paket Bandwidth (if empty)
        if (DB::table('paket_bandwidth')->count() === 0) {
            DB::table('paket_bandwidth')->insert([
                [
                    'nama_paket' => '10 Mbps Hotspot',
                    'limit_upload' => '2M',
                    'limit_download' => '10M',
                    'harga' => 100000,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'nama_paket' => '20 Mbps Hotspot',
                    'limit_upload' => '4M',
                    'limit_download' => '20M',
                    'harga' => 150000,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'nama_paket' => '30 Mbps PPPoE',
                    'limit_upload' => '5M',
                    'limit_download' => '30M',
                    'harga' => 200000,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'nama_paket' => '50 Mbps PPPoE',
                    'limit_upload' => '10M',
                    'limit_download' => '50M',
                    'harga' => 300000,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        $pakets = DB::table('paket_bandwidth')->get();
        $admin = DB::table('admin')->first();
        $adminId = $admin ? $admin->id_admin : 1;

        // 2. Seed Pelanggan (if empty)
        if (DB::table('pelanggan')->count() === 0) {
            $names = [
                'Budi Santoso', 'Siti Aminah', 'Rudi Hermawan', 'Dewi Lestari', 
                'Eko Prasetyo', 'Lani Wijaya', 'Agus Setiawan', 'Mega Utami'
            ];

            foreach ($names as $index => $name) {
                $paket = $pakets[$index % count($pakets)];
                $username = strtolower(str_replace(' ', '', $name)) . ($index + 1);

                DB::table('pelanggan')->insert([
                    'id_paket' => $paket->id_paket,
                    'nama_pelanggan' => $name,
                    'alamat' => 'Jl. Merdeka No. ' . ($index + 12) . ', Jakarta',
                    'no_hp' => '08123456789' . $index,
                    'username_mikrotik' => $username,
                    'password_mikrotik' => 'user123',
                    'masa_aktif' => now()->addDays(rand(5, 30)),
                    'status_aktif' => 'Aktif',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $pelanggans = DB::table('pelanggan')->get();

        // 3. Seed Pembayaran (if empty)
        if (DB::table('pembayaran')->count() === 0) {
            foreach ($pelanggans as $pelanggan) {
                $paket = DB::table('paket_bandwidth')->where('id_paket', $pelanggan->id_paket)->first();
                $nominal = $paket ? $paket->harga : 150000;
                
                // Pembayaran bulan lalu
                DB::table('pembayaran')->insert([
                    'id_pelanggan' => $pelanggan->id_pelanggan,
                    'id_paket' => $pelanggan->id_paket,
                    'id_admin' => $adminId,
                    'tanggal_bayar' => now()->subMonth()->day(rand(1, 10)),
                    'nominal' => $nominal,
                    'periode_tagihan' => now()->subMonth()->format('F Y'),
                    'status_notifikasi' => 'Success',
                    'created_at' => now()->subMonth(),
                    'updated_at' => now()->subMonth(),
                ]);

                // Pembayaran bulan ini (beberapa pelanggan saja yang sudah bayar)
                if (rand(0, 1)) {
                    DB::table('pembayaran')->insert([
                        'id_pelanggan' => $pelanggan->id_pelanggan,
                        'id_paket' => $pelanggan->id_paket,
                        'id_admin' => $adminId,
                        'tanggal_bayar' => now()->day(rand(1, 10)),
                        'nominal' => $nominal,
                        'periode_tagihan' => now()->format('F Y'),
                        'status_notifikasi' => 'Success',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // 4. Seed Log Aktivitas (if empty - for chart data)
        if (DB::table('log_aktivitas')->count() === 0) {
            // Generate log aktivitas selama 7 hari terakhir
            for ($day = 0; $day < 7; $day++) {
                $date = now()->subDays($day);
                
                foreach ($pelanggans as $pelanggan) {
                    $waktuMulai = clone $date;
                    $waktuMulai->hour(rand(6, 22))->minute(rand(0, 59));
                    
                    $durasi = rand(15, 180);
                    $waktuSelesai = clone $waktuMulai;
                    $waktuSelesai->addMinutes($durasi);
                    
                    // Usage in MB (e.g. 50 MB to 2000 MB)
                    $dataUsage = rand(50, 2000);
                    
                    // Tandai anomali kecil
                    $isAnomali = ($dataUsage > 1800 && rand(1, 10) === 1);

                    DB::table('log_aktivitas')->insert([
                        'id_pelanggan' => $pelanggan->id_pelanggan,
                        'id_paket' => $pelanggan->id_paket,
                        'ip_address' => '192.168.1.' . rand(100, 254),
                        'waktu_mulai' => $waktuMulai,
                        'waktu_selesai' => $waktuSelesai,
                        'durasi_menit' => $durasi,
                        'data_usage_mb' => $dataUsage,
                        'is_anomali' => $isAnomali,
                        'created_at' => $waktuMulai,
                        'updated_at' => $waktuMulai,
                    ]);
                }
            }
        }
    }
}
