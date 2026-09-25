<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $table = 'payments';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'base_amount' => 'decimal:2',
        'gateway_fee' => 'decimal:2',
        'gateway_total' => 'decimal:2',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'id_order');
    }

    public function proofs()
    {
        return $this->hasMany(PaymentProof::class, 'id_payment');
    }

    public function logs()
    {
        return $this->hasMany(PaymentLog::class, 'id_payment');
    }

    public function gatewayEvents()
    {
        return $this->hasMany(EventPaymentEvent::class, 'payment_id');
    }
}
