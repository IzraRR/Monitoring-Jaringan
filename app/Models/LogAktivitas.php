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

    public function getDurasiFormattedAttribute(): string
    {
        $minutes = $this->durasi_menit;
        if (!$minutes || $minutes <= 0) {
            return '0 Menit';
        }

        $months = (int) floor($minutes / 43200);
        $minutes %= 43200;

        $weeks = (int) floor($minutes / 10080);
        $minutes %= 10080;

        $days = (int) floor($minutes / 1440);
        $minutes %= 1440;

        $hours = (int) floor($minutes / 60);
        $minutes %= 60;

        $parts = [];
        if ($months > 0) {
            $parts[] = $months . ' Bulan';
        }
        if ($weeks > 0) {
            $parts[] = $weeks . ' Minggu';
        }
        if ($days > 0) {
            $parts[] = $days . ' Hari';
        }
        if ($hours > 0) {
            $parts[] = $hours . ' Jam';
        }
        if ($minutes > 0 || empty($parts)) {
            $parts[] = $minutes . ' Menit';
        }

        return implode(' ', $parts);
    }
}

