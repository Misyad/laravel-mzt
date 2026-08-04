<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Info_pesantren extends Model
{
    use HasFactory;

    protected $fillable = [
        'judul',
        'deskripsi',
        'alamat',
        'foto',
        'telpon',
        'email',
    ];
}
