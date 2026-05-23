<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pembayaran extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pembayaran';
    protected $primaryKey = 'id_pembayaran';

    protected $fillable = [
        'id_pelanggan',
        'id_paket',
        'id_admin',
        'tanggal_bayar',
        'nominal',
        'periode_tagihan',
        'status_notifikasi',
    ];

    protected $casts = [
        'tanggal_bayar' => 'date',
        'nominal' => 'decimal:0',
    ];

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class, 'id_pelanggan', 'id_pelanggan');
    }

    public function paket(): BelongsTo
    {
        return $this->belongsTo(PaketBandwidth::class, 'id_paket', 'id_paket');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'id_admin', 'id_admin');
    }
}
