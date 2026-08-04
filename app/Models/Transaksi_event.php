<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaksi_event extends Model
{
    protected $table = 'm_transaksi_events';
    protected $guarded = [];

    public function dataUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_anggota', 'id_anggota');
    }
}
