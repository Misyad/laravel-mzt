<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Processed Paymenku webhook event (PRD v3.0 §24).
 *
 * Stores a hash of the raw body (not the payload) so redeliveries are
 * recognised without persisting contact data.
 */
class KtaPaymentEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'kta_payment_events';

    protected $guarded = [];

    protected $casts = [
        'signature_valid' => 'boolean',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function request()
    {
        return $this->belongsTo(KtaPrintRequest::class, 'kta_print_request_id');
    }
}
