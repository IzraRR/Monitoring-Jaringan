<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class LogAktivitas extends Model
{
    use HasFactory;

    protected $table = 'log_aktivitas';
    protected $primaryKey = 'id_log';

    protected $fillable = [
        'id_pelanggan',
        'id_paket',
        'waktu_mulai',
        'waktu_selesai',
        'ip_address',
        'durasi_menit',
        'data_usage_mb',
        'is_anomali',
    ];

    protected $casts = [
        'waktu_mulai' => 'datetime',
        'waktu_selesai' => 'datetime',
        'is_anomali' => 'boolean',
        'data_usage_mb' => 'float',
        'durasi_menit' => 'integer',
    ];

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class, 'id_pelanggan', 'id_pelanggan');
    }

    public function paket(): BelongsTo
    {
        return $this->belongsTo(PaketBandwidth::class, 'id_paket', 'id_paket');
    }
}
