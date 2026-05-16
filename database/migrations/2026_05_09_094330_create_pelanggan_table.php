<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('pelanggan', function (Blueprint $table) {
            $table->id('id_pelanggan');
            $table->foreignId('id_paket')->constrained('paket_bandwidth', 'id_paket')->onDelete('cascade');
            $table->string('nama_pelanggan', 100);
            $table->string('no_hp', 20);
            $table->string('username_mikrotik', 50)->unique();
            $table->string('password_mikrotik', 50);
            $table->date('masa_aktif')->nullable();
            $table->string('status_aktif', 20)->default('Aktif');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pelanggan');
    }
};
