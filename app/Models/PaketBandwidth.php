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

    public function pelanggan(): HasMany
    {
        return $this->hasMany(Pelanggan::class, 'id_paket', 'id_paket');
    }
}
