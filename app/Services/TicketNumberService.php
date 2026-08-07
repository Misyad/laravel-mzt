<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Generates globally unique ticket numbers: TKT-YYYY-NNNNNN (PRD §10.5).
 *
 * Mirrors OrderNumberService / OrderNumberService::nextPayment: the counter is
 * derived from the total number of tickets created in the same year, so it
 * survives restores and is safe to re-run.
 *
 * COUNT() is only a cheap seed — the real guarantee is the UNIQUE constraint on
 * `tickets.nomor_ticket`. A parallel race on the same number raises a unique
 * violation (SQLSTATE 23000 / code 1062); the caller retries inside its DB
 * transaction until a free number is reserved, so it never depends on COUNT().
 */
class TicketNumberService
{
    /**
     * Build the next candidate ticket number for today's year.
     *
     * Best-effort starting point only. The authoritative uniqueness check is
     * the UNIQUE constraint on `tickets.nomor_ticket` enforced on insert.
     *
     * @return string e.g. TKT-2026-000001
     */
    public function make(): string
    {
        $year = date('Y');
        $count = DB::table('tickets')
            ->whereYear('created_at', $year)
            ->count();

        return $this->format($year, $count + 1);
    }

    /**
     * Build a TKT-YYYY-NNNNNN string (PRD §10.5). Pure helper, DB-free.
     *
     * @param  string  $year  four digit year
     * @param  int  $sequence  the eager full numeric sequence
     *
     * @return string e.g. TKT-2026-000001
     */
    public function format(string $year, int $sequence): string
    {
        return sprintf('TKT-%s-%06d', $year, $sequence);
    }

    /**
     * Whether a thrown exception is a unique-constraint violation on a ticket
     * number reservation (i.e. a race between parallel generations).
     */
    public function isDuplicateViolation(\Throwable $e): bool
    {
        if (!$e instanceof QueryException) {
            return false;
        }

        $message = strtolower((string) ($e->getMessage() ?? ''));

        return ($e->errorInfo[1] ?? null) === 1062 ||
            str_contains($message, 'duplicate entry') ||
            str_contains($message, 'for key');
    }
}