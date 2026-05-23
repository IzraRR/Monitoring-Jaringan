<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Admin extends Model
{
    use HasFactory;

    protected $table = 'admin';
    protected $primaryKey = 'id_admin';

    protected $fillable = [
        'username',
        'password',
        'nama_lengkap',
        'peran',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'password' => 'hashed',
    ];

    public function pembayaran(): HasMany
    {
        return $this->hasMany(Pembayaran::class, 'id_admin', 'id_admin');
    }
}
