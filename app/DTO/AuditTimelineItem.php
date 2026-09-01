<?php

namespace App\DTO;

/**
 * Single item in the unified audit timeline.
 *
 * Fields map directly from PaymentLog, TicketLog, and check-in events:
 *   - actor: user who performed the action
 *   - action: type of event (payment_verified, ticket_issued, checkin_performed, etc.)
 *   - entity: "payment", "ticket", or "checkin"
 *   - entity_id: reference ID (payment.id, ticket.id, etc.)
 *   - old_status: previous status (nullable)
 *   - new_status: new status (nullable)
 *   - timestamp: when it happened
 *   - reason/note: optional note/message
 */
class AuditTimelineItem
{
    public function __construct(
        public readonly string $actor,
        public readonly string $action,
        public readonly string $entity,
        public readonly int $entity_id,
        public readonly ?string $old_status,
        public readonly ?string $new_status,
        public readonly \DateTimeInterface $timestamp,
        public readonly ?string $note,
    ) {
    }
}