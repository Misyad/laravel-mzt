<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentProof extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'payment_proofs';

    protected $guarded = [];

    protected $casts = [
        'file_size' => 'integer',
        'uploaded_at' => 'datetime',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'id_payment');
    }
}