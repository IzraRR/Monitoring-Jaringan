<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pelanggan', function (Blueprint $table) {
            $table->dropForeign(['id_paket']);
            $table->foreign('id_paket')
                ->references('id_paket')
                ->on('paket_bandwidth')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pelanggan', function (Blueprint $table) {
            $table->dropForeign(['id_paket']);
            $table->foreign('id_paket')
                ->references('id_paket')
                ->on('paket_bandwidth')
                ->cascadeOnDelete();
        });
    }
};
