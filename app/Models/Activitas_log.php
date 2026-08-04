<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activitas_log extends Model
{
    use HasFactory;

    public function dataUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
