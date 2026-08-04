<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prisensi_kehadiran extends Model
{
    protected $table = 'prisensi_kehadiran';

    public function dataUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_anggota', 'id_anggota');
    }


    protected $fillable = [
        'id_event',
        'id_tanggal',
        'id_anggota',
        'id_user',
        'tanggal_kehadiran',
        'jam_kehadiran',
    ];
}
