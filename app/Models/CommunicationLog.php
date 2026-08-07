<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunicationLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'communication_logs';

    protected $guarded = [];

    protected $casts = [
        'retry_count' => 'integer',
        'created_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'payload' => 'array',
    ];
}