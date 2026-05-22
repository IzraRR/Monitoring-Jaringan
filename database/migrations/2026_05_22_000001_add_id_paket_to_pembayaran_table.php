<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Menambahkan id_paket ke tabel pembayaran untuk menyimpan snapshot paket
     * pada saat transaksi dilakukan. Ini memastikan history transaksi tidak berubah
     * ketika pelanggan mengubah paket bandwidth mereka.
     */
    public function up(): void
    {
        Schema::table('pembayaran', function (Blueprint $table) {
            // Tambahkan kolom id_paket setelah id_pelanggan
            $table->foreignId('id_paket')
                ->nullable() // Nullable untuk data lama yang belum punya id_paket
                ->after('id_pelanggan')
                ->constrained('paket_bandwidth', 'id_paket')
                ->onDelete('restrict'); // Restrict agar paket tidak bisa dihapus jika ada transaksi
        });

        // Update data lama: set id_paket dari pelanggan saat ini
        // Ini hanya untuk migrasi data existing
        DB::statement('
            UPDATE pembayaran p
            INNER JOIN pelanggan pel ON p.id_pelanggan = pel.id_pelanggan
            SET p.id_paket = pel.id_paket
            WHERE p.id_paket IS NULL
        ');

        // Setelah update, ubah kolom menjadi NOT NULL
        Schema::table('pembayaran', function (Blueprint $table) {
            $table->foreignId('id_paket')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pembayaran', function (Blueprint $table) {
            $table->dropForeign(['id_paket']);
            $table->dropColumn('id_paket');
        });
    }
};
