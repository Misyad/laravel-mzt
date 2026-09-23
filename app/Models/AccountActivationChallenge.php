<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountActivationChallenge extends Model
{
    protected $guarded = [];

    protected $casts = [
        'candidate_ids' => 'array',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];
}
