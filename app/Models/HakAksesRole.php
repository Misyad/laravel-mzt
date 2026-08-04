<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HakAksesRole extends Model
{
    protected $table = 'hak_akses_role';

    protected $fillable = [
        'id_users',
        'nama_role',
        'hak_akses',
    ];
}
