<?php

namespace App\Queries;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

/**
 * Query layer for the Dashboard read model (Sprint 5A).
 *
 * This is the ONLY place that touches raw database reads for the dashboard. It
 * performs aggregate reads (COUNT / SUM / groupBy) and returns only plain,
 * scalar array values — never Eloquent models, builders, collections or
 * paginators. It instantiates no services and carries no side effects.
 *
 * Partial application of the period filters to a shared underlying query avoids
 * re-scanning (no N+1), keeping each endpoint fast.
 */
class DashboardQuery
{
    private function orderQuery(?string $start, ?string $end)
    {
        return Order::query()
            ->when($start, fn ($q) => $q->whereDate('created_at', '>=', $start))
            ->when($end, fn ($q) => $q->whereDate('created_at', '<=', $end));
    }

    private function paymentQuery(?string $start, ?string $end)
    {
        return Payment::query()
            ->when($start, fn ($q) => $q->whereDate('created_at', '>=', $start))
            ->when($end, fn ($q) => $q->whereDate('created_at', '<=', $end));
    }

    /**
     * KPI snapshot over orders, payments and tickets.
     *
     * @return array{
     *   total_orders: int,
     *   total_revenue: float,
     *   total_paid: float,
     *   total_outstanding: float,
     *   total_tickets: int,
     *   pending_verifications: int
     * }
     */
    public function overview(?string $start = null, ?string $end = null): array
    {
        $orders = $this->orderQuery($start, $end);
        $payments = $this->paymentQuery($start, $end);

        $totalOrders = (int) (clone $orders)->count('id');
        $totalRevenue = (float) (clone $orders)->sum('total_amount');

        $totalPaid = (float) (clone $payments)
            ->where('status', PaymentStatus::PAID->value)
            ->sum('amount');

        $pendingVerifications = (int) (clone $payments)
            ->where('status', PaymentStatus::WAITING_VERIFICATION->value)
            ->count('id');

        $totalTickets = (int) Ticket::query()
            ->when($start, fn ($q) => $q->whereDate('created_at', '>=', $start))
            ->when($end, fn ($q) => $q->whereDate('created_at', '<=', $end))
            ->count('id');

        return [
            'total_orders' => $totalOrders,
            'total_revenue' => round($totalRevenue, 2),
            'total_paid' => round($totalPaid, 2),
            'total_outstanding' => round(max($totalRevenue - $totalPaid, 0), 2),
            'total_tickets' => $totalTickets,
            'pending_verifications' => $pendingVerifications,
        ];
    }

    /**
     * Registration counts grouped by registration status.
     *
     * @return array{total_orders: int, by_status: array<int, array{status: string, count: int}>}
     */
    public function registration(?string $start = null, ?string $end = null): array
    {
        $query = $this->orderQuery($start, $end);

        $totalOrders = (int) (clone $query)->count('id');

        $rows = (clone $query)
            ->selectRaw('status_registrasi as status, COUNT(*) as count')
            ->groupBy('status_registrasi')
            ->orderBy('status_registrasi')
            ->get()
            ->map(fn ($row) => ['status' => $row->status, 'count' => (int) $row->count])
            ->all();

        return [
            'total_orders' => $totalOrders,
            'by_status' => $rows,
        ];
    }

    /**
     * Revenue over an optional period.
     *
     * @return array{
     *   total_revenue: float,
     *   total_paid: float,
     *   outstanding: float,
     *   by_status: array<int, array{status: string, total: float, count: int}>
     * }
     */
    public function revenue(?string $start = null, ?string $end = null): array
    {
        $orders = $this->orderQuery($start, $end);
        $payments = $this->paymentQuery($start, $end);

        $totalRevenue = (float) (clone $orders)->sum('total_amount');
        $totalPaid = (float) (clone $payments)
            ->where('status', PaymentStatus::PAID->value)
            ->sum('amount');

        $byStatus = (clone $payments)
            ->select('status', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn ($r) => ['status' => $r->status, 'total' => round((float) $r->total, 2), 'count' => (int) $r->count])
            ->all();

        return [
            'total_revenue' => round($totalRevenue, 2),
            'total_paid' => round($totalPaid, 2),
            'outstanding' => round(max($totalRevenue - $totalPaid, 0), 2),
            'by_status' => $byStatus,
        ];
    }

    /**
     * Payment sums and counts grouped by payment status.
     *
     * @return array{by_status: array<int, array{status: string, total: float, count: int}>, waiting_verification: int}
     */
    public function payments(?string $start = null, ?string $end = null): array
    {
        $query = $this->paymentQuery($start, $end);

        $byStatus = (clone $query)
            ->select('status', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn ($r) => ['status' => $r->status, 'total' => round((float) $r->total, 2), 'count' => (int) $r->count])
            ->all();

        $waiting = (int) (clone $query)
            ->where('status', PaymentStatus::WAITING_VERIFICATION->value)
            ->count('id');

        return [
            'by_status' => $byStatus,
            'waiting_verification' => $waiting,
        ];
    }
}