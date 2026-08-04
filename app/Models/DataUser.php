<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataUser extends Model
{
    protected $table = 'data_users';
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_users');
    }
}

