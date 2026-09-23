<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountSetupEmailVerification extends Model
{
    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];
}
