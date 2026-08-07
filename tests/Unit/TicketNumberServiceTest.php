<?php

namespace Tests\Unit;

use App\Services\TicketNumberService;
use PHPUnit\Framework\TestCase;

class TicketNumberServiceTest extends TestCase
{
    public function testMakeReturnsTktPrefixWithCurrentYear(): void
    {
        $service = new TicketNumberService();

        public function testFormat_producesZeroPaddedYearBasedNumber(): void
    {
        $service = new TicketNumberService();

        $this->assertSame('TKT-2026-000001', $service->format('2026', 1));
        $this->assertSame('TKT-2026-000123', $service->format('2026', 123));
    }
    }

    public function testDuplicateViolationIsDetectedOnUniqueConstraint(): void
    {
        $service = new TicketNumberService();

        $query = new \Illuminate\Database\QueryException(
            'sql',
            ['tickets.nomor_ticket'],
            new \Exception('SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry'),
        );

        $this->assertTrue($service->isDuplicateViolation($query));
    }

    public function testNonDuplicateExceptionIsNotTreatedAsRace(): void
    {
        $service = new TicketNumberService();

        $query = new \Illuminate\Database\QueryException(
            'sql',
            ['tickets.nomor_ticket'],
            new \Exception('SQLSTATE[23000]: Integrity constraint violation: 1048 Column cannot be null'),
        );

        $this->assertFalse($service->isDuplicateViolation($query));
    }
}