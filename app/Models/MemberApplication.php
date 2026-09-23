<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberApplication extends Model
{
    public const PENDING_EMAIL = 'pending_email';

    public const SUBMITTED = 'submitted';

    public const UNDER_REVIEW = 'under_review';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    protected $guarded = [];

    protected $hidden = [
        'active_email',
        'submission_key_hash',
        'normalized_name',
        'normalized_phone',
    ];

    protected $appends = [
        'id_anggota',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date:Y-m-d',
        'tahun_masuk' => 'date:Y',
        'tahun_keluar' => 'date:Y',
        'email_verified_at' => 'datetime',
        'under_review_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function logs()
    {
        return $this->hasMany(MemberApplicationLog::class)->orderBy('id');
    }

    public function approvedUser()
    {
        return $this->belongsTo(User::class, 'approved_user_id');
    }

    public function getIdAnggotaAttribute(): ?string
    {
        if (! $this->approved_user_id) {
            return null;
        }

        if ($this->relationLoaded('approvedUser')) {
            return $this->approvedUser?->id_anggota;
        }

        return User::whereKey($this->approved_user_id)->value('id_anggota');
    }
}
