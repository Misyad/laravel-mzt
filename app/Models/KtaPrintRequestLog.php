<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Immutable audit row for a KTA print request status transition (PRD v3.0 §25).
 */
class KtaPrintRequestLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'kta_print_request_logs';

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function request()
    {
        return $this->belongsTo(KtaPrintRequest::class, 'kta_print_request_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
