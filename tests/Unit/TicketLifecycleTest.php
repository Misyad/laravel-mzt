<?php

namespace Tests\Unit;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Services\TicketLifecycleService;
use App\Services\TicketService;
use PHPUnit\Framework\TestCase;

/**
 * Pure lifecycle rules — no database required (Ticket status is a plain
 * attribute; service reads it without persisting).
 */
class TicketLifecycleTest extends TestCase
{
    public function testCanRevokeOnlyWhileNotTerminal(): void
    {
        $lifecycle = new TicketLifecycleService();

        $revocable = [TicketStatus::DRAFT, TicketStatus::ISSUED, TicketStatus::CHECKED_IN];
        $terminal = [TicketStatus::FINISHED, TicketStatus::CANCELLED, TicketStatus::REVOKED];

        foreach ($revocable as $status) {
            $this->assertTrue($lifecycle->canRevoke(new Ticket(['status' => $status->value])), (string) $status->value);
        }

        foreach ($terminal as $status) {
            $this->assertFalse($lifecycle->canRevoke(new Ticket(['status' => $status->value])), (string) $status->value);
        }
    }

    public function testCanIssueFreeEventOnRegisteredOrder(): void
    {
        $tickets = new TicketService(new \App\Services\TicketNumberService());

        $free = new \App\Models\Order([
            'total_amount' => 0,
            'status_registrasi' => 'registered',
            'payment_status' => 'pending',
        ]);

        $this->assertTrue($tickets->canIssue($free));
    }

    public function testCanIssuePaidEventOnlyWhenPaid(): void
    {
        $tickets = new TicketService(new \App\Services\TicketNumberService());

        $pending = new \App\Models\Order([
            'total_amount' => 50000,
            'status_registrasi' => 'registered',
            'payment_status' => 'pending',
        ]);
        $paid = new \App\Models\Order([
            'total_amount' => 50000,
            'status_registrasi' => 'registered',
            'payment_status' => 'paid',
        ]);

        $this->assertFalse($tickets->canIssue($pending));
        $this->assertTrue($tickets->canIssue($paid));
    }
}