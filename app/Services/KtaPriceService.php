<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class KtaPriceService
{
    private const SETTING_ID = 1;

    public function currentAmount(): int
    {
        $amount = DB::table('kta_price_settings')->where('id', self::SETTING_ID)->value('amount');

        return $amount === null ? (int) config('kta.print.amount', 25000) : (int) $amount;
    }

    public function snapshotAmount(): int
    {
        DB::table('kta_price_settings')->insertOrIgnore([
            'id' => self::SETTING_ID,
            'amount' => (int) config('kta.print.amount', 25000),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('kta_price_settings')
            ->where('id', self::SETTING_ID)
            ->lockForUpdate()
            ->value('amount');
    }

    public function setting(): array
    {
        $row = DB::table('kta_price_settings as settings')
            ->leftJoin('users as actor', 'actor.id', '=', 'settings.updated_by')
            ->where('settings.id', self::SETTING_ID)
            ->first([
                'settings.amount',
                'settings.updated_at',
                'actor.name as updated_by',
            ]);

        return [
            'amount' => $row ? (int) $row->amount : (int) config('kta.print.amount', 25000),
            'updated_at' => $row?->updated_at,
            'updated_by' => $row?->updated_by,
        ];
    }

    public function history(): array
    {
        return DB::table('kta_price_histories as history')
            ->leftJoin('users as actor', 'actor.id', '=', 'history.actor_user_id')
            ->orderByDesc('history.id')
            ->limit(100)
            ->get([
                'history.id',
                'history.old_amount',
                'history.new_amount',
                'actor.name as actor',
                'history.created_at',
            ])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'old_amount' => (int) $row->old_amount,
                'new_amount' => (int) $row->new_amount,
                'actor' => $row->actor,
                'created_at' => $row->created_at,
            ])
            ->all();
    }

    public function update(int $amount, User $actor): void
    {
        DB::transaction(function () use ($amount, $actor): void {
            DB::table('kta_price_settings')->insertOrIgnore([
                'id' => self::SETTING_ID,
                'amount' => (int) config('kta.print.amount', 25000),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $setting = DB::table('kta_price_settings')->where('id', self::SETTING_ID)->lockForUpdate()->first();
            $oldAmount = (int) $setting->amount;
            if ($oldAmount === $amount) {
                return;
            }

            DB::table('kta_price_settings')->where('id', self::SETTING_ID)->update([
                'amount' => $amount,
                'updated_by' => $actor->id,
                'updated_at' => now(),
            ]);
            DB::table('kta_price_histories')->insert([
                'old_amount' => $oldAmount,
                'new_amount' => $amount,
                'actor_user_id' => $actor->id,
                'created_at' => now(),
            ]);
        });
    }
}
