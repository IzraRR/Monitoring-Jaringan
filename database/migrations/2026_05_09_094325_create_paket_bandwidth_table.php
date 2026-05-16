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
        Schema::create('paket_bandwidth', function (Blueprint $table) {
            $table->id('id_paket');
            $table->string('nama_paket', 50);
            $table->string('limit_upload', 20);
            $table->string('limit_download', 20);
            $table->double('harga');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paket_bandwidth');
    }
};
