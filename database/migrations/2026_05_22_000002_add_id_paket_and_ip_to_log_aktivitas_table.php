<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Menambahkan id_paket dan ip_address ke tabel log_aktivitas untuk:
     * 1. Menyimpan snapshot paket pada saat aktivitas terjadi
     * 2. Menyimpan IP address dari session MikroTik
     * 
     * Ini memastikan history log tidak berubah ketika pelanggan mengubah paket.
     */
    public function up(): void
    {
        Schema::table('log_aktivitas', function (Blueprint $table) {
            // Tambahkan kolom id_paket setelah id_pelanggan
            $table->foreignId('id_paket')
                ->nullable() // Nullable untuk data lama yang belum punya id_paket
                ->after('id_pelanggan')
                ->constrained('paket_bandwidth', 'id_paket')
                ->onDelete('restrict'); // Restrict agar paket tidak bisa dihapus jika ada log
            
            // Tambahkan kolom ip_address (sudah ada di model tapi belum di database)
            $table->string('ip_address', 45)->nullable()->after('id_paket');
        });

        // Update data lama: set id_paket dari pelanggan saat ini
        // Ini hanya untuk migrasi data existing
        DB::statement('
            UPDATE log_aktivitas la
            INNER JOIN pelanggan pel ON la.id_pelanggan = pel.id_pelanggan
            SET la.id_paket = pel.id_paket
            WHERE la.id_paket IS NULL
        ');

        // Setelah update, ubah kolom id_paket menjadi NOT NULL
        Schema::table('log_aktivitas', function (Blueprint $table) {
            $table->foreignId('id_paket')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('log_aktivitas', function (Blueprint $table) {
            $table->dropForeign(['id_paket']);
            $table->dropColumn(['id_paket', 'ip_address']);
        });
    }
};
