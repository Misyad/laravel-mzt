<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicantSession extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'session_id';

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];
}
