<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tentang_mzt extends Model
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
