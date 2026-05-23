<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class PaketBandwidth extends Model
{
    use HasFactory;

    protected $table = 'paket_bandwidth';
    protected $primaryKey = 'id_paket';

    protected $fillable = [
        'nama_paket',
        'limit_upload',
        'limit_download',
        'harga',
    ];

    protected $casts = [
        'harga' => 'decimal:0',
    ];

    public function pelanggan(): HasMany
    {
        return $this->hasMany(Pelanggan::class, 'id_paket', 'id_paket');
    }

    public function pembayaran(): HasMany
    {
        return $this->hasMany(Pembayaran::class, 'id_paket', 'id_paket');
    }

    public function logAktivitas(): HasMany
    {
        return $this->hasMany(LogAktivitas::class, 'id_paket', 'id_paket');
    }
}
