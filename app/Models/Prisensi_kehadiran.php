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

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'id_ticket');
    }


    protected $fillable = [
        'id_event',
        'id_tanggal',
        'id_anggota',
        'id_user',
        'tanggal_kehadiran',
        'jam_kehadiran',
        'id_ticket',
        'gate',
        'scanned_at',
        'scanned_by',
    ];

    protected $casts = [
        'tanggal_kehadiran' => 'datetime',
        'scanned_at' => 'datetime',
    ];
}
