<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::table('admin')->updateOrInsert(
            ['username' => 'admin'],
            [
                'password' => Hash::make('admin123'),
                'nama_lengkap' => 'Administrator',
                'peran' => 'Admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
