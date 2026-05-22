<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('ADMIN_SEED_PASSWORD');

        if (empty($password)) {
            if (app()->environment('production')) {
                $this->command?->warn('ADMIN_SEED_PASSWORD tidak diatur. Lewati seeding admin.');

                return;
            }

            $password = 'admin123';
            $this->command?->warn('Menggunakan password default dev (admin123). Ubah segera setelah login!');
        }

        DB::table('admin')->updateOrInsert(
            ['username' => env('ADMIN_SEED_USERNAME', 'admin')],
            [
                'password' => Hash::make($password),
                'nama_lengkap' => 'Administrator',
                'peran' => 'Admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
