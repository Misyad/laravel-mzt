<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Generates globally unique admin order numbers: MZT-YYYY-NNNNNN.
 *
 * The counter is derived from the total number of orders in the current year
 * (not a separate sequence table), so it survives restores and is safe to
 * re-run. Locking is handled by the UNIQUE constraint on `orders.nomor_order`:
 * a race simply fails the insert instead of duplicating a number.
 */
class OrderNumberService
{
    /**
     * Build the next order number for today's year.
     *
     * @return string e.g. MZT-2026-000001
     */
    public function next(): string
    {
        $year = date('Y');
        $count = DB::table('orders')
            ->whereYear('created_at', $year)
            ->count();

        return sprintf('MZT-%s-%06d', $year, $count + 1);
    }
}
