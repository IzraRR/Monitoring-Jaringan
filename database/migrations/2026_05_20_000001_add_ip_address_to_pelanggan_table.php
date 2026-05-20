<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('pelanggan', function (Blueprint $table) {
            // Tambahkan kolom ip_address (varchar 45 untuk support IPv4/IPv6), nullable karena mungkin ada pelanggan yang belum pernah login
            $table->string('ip_address', 45)->nullable()->after('status_aktif');
        });
    }

    public function down()
    {
        Schema::table('pelanggan', function (Blueprint $table) {
            $table->dropColumn('ip_address');
        });
    }
};
