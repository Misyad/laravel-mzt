<?php

namespace Tests\Unit;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Services\CheckInService;
use PHPUnit\Framework\TestCase;

/**
 * Pure check-in state rules — no database required (ticket status is a plain
 * attribute; the verdict only reads it), mirroring TicketLifecycleTest.
 *
 * Phase 2C implements exactly `issued → checked_in`; everything else is either
 * a duplicate scan or rejected (ADR-011). `checked_in → finished` is NOT here.
 */
class CheckInServiceTest extends TestCase
{
    public function testOnlyIssuedTicketsMayBeCheckedIn(): void
    {
        $service = new CheckInService();

        $this->assertSame('ok', $service->verdict(new Ticket(['status' => TicketStatus::ISSUED->value])));
        $this->assertSame('duplicate', $service->verdict(new Ticket(['status' => TicketStatus::CHECKED_IN->value])));

        foreach ([
            TicketStatus::DRAFT,
            TicketStatus::CANCELLED,
            TicketStatus::REVOKED,
            TicketStatus::FINISHED,
        ] as $status) {
            $this->assertSame(
                'rejected',
                $service->verdict(new Ticket(['status' => $status->value])),
                "expected {$status->value} to be rejected",
            );
        }
    }
}
