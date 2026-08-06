<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $table = 'orders';

    protected $guarded = [];

    protected $casts = [
        'event_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'event_start_at' => 'date',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'id_event');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'id_order');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'id_order');
    }
}
