<?php

namespace App\Models;

use App\Enums\KtaPrintStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Physical KTA print request (PRD v3.0).
 *
 * Created only for a verified, active member. Payment is confirmed by the
 * Paymenku webhook; when `payment_status` becomes paid the request advances to
 * `menunggu_cetak` atomically — no manual verification step exists.
 *
 * `active_key` is a nullable-unique guard: it holds the user id while the
 * request is active and is cleared on terminal status, so a member can only
 * ever have one active request while concurrent inserts still collide safely.
 */
class KtaPrintRequest extends Model
{
    use HasFactory;

    protected $table = 'kta_print_requests';

    protected $guarded = [];

    protected $casts = [
        'payment_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'submitted_at' => 'datetime',
        'printed_at' => 'datetime',
        'ready_at' => 'datetime',
        'shipped_at' => 'datetime',
        'completed_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_users');
    }

    public function logs()
    {
        return $this->hasMany(KtaPrintRequestLog::class, 'kta_print_request_id')->orderBy('id');
    }

    public function paymentEvents()
    {
        return $this->hasMany(KtaPaymentEvent::class, 'kta_print_request_id');
    }

    /**
     * Requests still occupying the member's single active slot.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', KtaPrintStatus::activeValues());
    }

    /**
     * Row shown in the production print queue.
     */
    public function scopeProductionQueue($query)
    {
        return $query->whereIn('status', KtaPrintStatus::productionQueueValues());
    }

    public function isActive(): bool
    {
        return in_array($this->status, KtaPrintStatus::activeValues(), true);
    }

    /**
     * Public-facing projection — masked identity only, no raw PII.
     *
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'reference' => 'KTA-' . $this->id,
            'status' => $this->status,
            'delivery_method' => $this->delivery_method,
            'payment_status' => $this->payment_status,
            'payment_amount' => $this->payment_amount,
            'pay_url' => $this->pay_url,
            'submitted_at' => optional($this->submitted_at)->toIso8601String(),
            'paid_at' => optional($this->paid_at)->toIso8601String(),
            'printed_at' => optional($this->printed_at)->toIso8601String(),
            'ready_at' => optional($this->ready_at)->toIso8601String(),
            'shipped_at' => optional($this->shipped_at)->toIso8601String(),
            'completed_at' => optional($this->completed_at)->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
        ];
    }

    public function toMemberArray(): array
    {
        $data = [
            'reference' => 'KTA-' . $this->id,
            'status' => $this->status,
            'delivery_method' => $this->delivery_method,
            'payment_status' => $this->payment_status,
            'payment_amount' => $this->payment_amount,
            'submitted_at' => optional($this->submitted_at)->toIso8601String(),
            'paid_at' => optional($this->paid_at)->toIso8601String(),
            'printed_at' => optional($this->printed_at)->toIso8601String(),
            'ready_at' => optional($this->ready_at)->toIso8601String(),
            'shipped_at' => optional($this->shipped_at)->toIso8601String(),
            'completed_at' => optional($this->completed_at)->toIso8601String(),
            'rejected_at' => optional($this->rejected_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];

        if ($this->status === KtaPrintStatus::MENUNGGU_PEMBAYARAN->value && $this->payment_status === 'pending') {
            $data['pay_url'] = $this->pay_url;
        }

        if ($this->status === KtaPrintStatus::DITOLAK->value) {
            $data['rejection_reason'] = $this->rejection_reason;
        }

        return $data;
    }
}
