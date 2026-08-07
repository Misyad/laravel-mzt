# Sprint 3 Verification Report

## Overview
Ticket Engine (Sprint 3) implementation verification completed. All core services, lifecycle operations, API endpoints, validation, authorization, events, and audit trails for the Ticket Engine component, built on top of the Sprint 2 Payment Engine.

## Environment
- Backend repo: `laravel-mzt` (Misyad/laravel-mzt)
- Frontend repo: `maziltu-design-studio` (Misyad/maziltu-design-studio)
- Framework: Laravel 9.19 | PHP ^8.0.2 | MariaDB 11.4
- Deploy host: Dockerhost 192.168.1.60 (Proxmox)
- Jenkins: `jenkins.projecthasan.com:8084` (container `jenkins_server`)

## Deployment & Regression Record

### Pipeline Run
- **Job**: `mzt-deploy`
- **Builds**: #32 **SUCCESS** (deploy Sprint 3 code), #33 **SUCCESS** (deploy `69ce6f6` — DomPDF facade fix)
- **Backend commits deployed**: `2115e6f` (Ticket Engine), `69ce6f6` (fix DomPDF facade class)
- **Backend HEAD after deploy**: `69ce6f6`

### Pipeline Stages (build #33 — SUCCESS)
| Stage | Result |
|-------|--------|
| Validate host | PASS |
| Sync source | PASS (backend → 69ce6f6) |
| Build images | PASS (mzt-backend rebuilt) |
| Backup database | PASS (metadata `git_backend: 69ce6f6`) |
| Deploy stack | PASS |
| Migrate DB | `Nothing to migrate` (Sprint 3 added no migrations — tables from S1) ✓ |
| Verify schema | PASS (Phase 2A + users) |
| Health check | PASS (HTTP 200) |
| Prune | PASS |

## Scope Delivered
Files (backend `laravel-mzt`):

### Services
- **TicketNumberService** — `TKT-YYYY-NNNNNN` (PRD §10.5), mirrors Order/Payment number service. COUNT() is only a seed; the UNIQUE constraint on `tickets.nomor_ticket` is the guarantee. In-transaction retry loop on duplicate (SQLSTATE 23000 / code 1062) so parallel races never depend on COUNT().
- **TicketService** — order-scoped issuance (PRD §10.3): free order → issued on registration; paid order → issued when `payment_status = paid`. Idempotent (PRD §21/§10.11): existing ticket returned. `qr_payload = ticket UUID` only (PRD §10.6).
- **TicketLifecycleService** — `reissue` (identity preserved: uuid/nomor_ticket/issued_at/id_order/status unchanged; new document + TicketLog) and `revoke` (terminal, blocks reuse). Check-in deferred to Phase 2C.
- **TicketDocumentService** — on-demand PDF (dompdf) + QR (milon/barcode, `qr_payload` = ticket UUID). No QR image persisted (rendered at request time).

### API
- **TicketController** — `myTicket`, `show`, `download` (PDF), `reissue`, `revoke`.
- **Routes** — 5 authed (`auth:sanctum`) ticket routes.
- **TicketPolicy** — view/download = owner|staff; reissue/revoke = Finance/Ketua/Admin (via `canVerify`). Registered in `AuthServiceProvider`.
- **TicketActionRequest** — `note` validation.

### Events
- **TicketIssued** (specific "issued") + **TicketStatusChanged** (generic lifecycle: reissue/revoke/future check-in) — ADR-016.

### Hooks
- **RegistrationService** — free event → ticket issued immediately.
- **PaymentVerificationService** — transition to PAID → ticket issued (idempotent).

## Verification Checklist
| Item | Status |
|------|--------|
| TicketNumberService (UNIQUE + retry, not COUNT-only, `TKT-YYYY-NNNNNN`) | ✅ |
| qr_payload stores only ticket UUID; QR rendered on-demand, not stored | ✅ |
| Reissue never makes a new Ticket (uuid/nomor/issued_at/status preserved) | ✅ |
| Generate idempotent (existing returned, single row) | ✅ |
| Payment hook: paid → ticket issued | ✅ (server-side path) |
| Registration hook: free → ticket issued | ✅ (server-side path) |
| TicketPolicy + AuthServiceProvider registration | ✅ |
| TicketActionRequest validation | ✅ |
| TicketController 5 endpoints | ✅ |
| TicketIssued + TicketStatusChanged events | ✅ |
| Routes registered & auth-gated | ✅ |
| Backend migrated cleanly (tickets/logs tables from S1) | ✅ |
| Health check 200 + `success:true` | ✅ |
| **Download endpoint: HTTP 200 + `application/pdf` + `%PDF` body** | ✅ |
| **QR payload = ticket UUID** | ✅ |
| Reissue preserves uuid/nomor/issued_at/status (log appended) | ✅ |
| Revoke terminal + canRevoke false | ✅ |
| Test rows = 0 (tickets/logs/users) after regression | ✅ |

## Regression Harness Results (host-verified, build #33)
Server-side harness executed the full Ticket flow inside the deployed backend (authenticated via Sanctum, direct HTTP through the framework kernel), wrapped in `DB::transaction` + `rollBack` so **nothing persisted**:

```
27 checks, 0 failed
```

Key live results:
- Free order → `generate` ok, status `issued`, `nomor_ticket` `TKT-*`, `qr_payload === uuid`.
- Idempotency: second `generate` returns existing ticket, single row.
- PDF: `%PDF` header, non-empty.
- Authenticated `GET /api/tickets/{uuid}/download` → **HTTP 200**, **Content-Type `application/pdf`**, body `%PDF`.
- Reissue: uuid / nomor_ticket / issued_at / status unchanged; a second `ticket_logs` entry appended.
- Revoke: status `revoked`, `revoked_at` set, `canRevoke` false, guarded on already-revoked.

## Architecture Review Notes
- Ticket is a child of **Order** (Proof/Call ADR-001); never issued from Event alone.
- Public identity = UUID; QR = UUID only (no internal id / order / personal data).
- Transactions: issuance uses `DB::transaction`; number uniqueness enforced by the DB constraint + retry.
- Events dispatched from services (ADR-016). No listener is wired in Sprint 3 (Communication Engine is Sprint 4); `TicketIssued`/`TicketStatusChanged` are emitted synchronously and will be consumed by the queue in Sprint 4.
- RBAC reuse of `RoleGuard`; no `completed` status (ADR-017).

## PRD / ADR Compliance
| Reference | Status |
|-----------|--------|
| §10.1–10.6 (ticket from Order; issue gates; identity; number; QR payload) | ✅ |
| §16.8 / §16.9 (tickets + ticket_logs schema) | ✅ |
| §17.14.4 (lifecycle / reissue semantics) | ✅ |
| §21.7 (Ticket API, download PDF) | ✅ |
| §23.9 / §23.8 (QR = UUID only, ticket manipulation safe) | ✅ |
| ADR-011 (Ticket Lifecycle), ADR-016 (events), ADR-017 (no completed) | ✅ |

## Test Artifacts
- No test data left in production: `tickets=0`, `ticket_logs=0`, test user rows=0 ✅
- No PRD/ADR/migration Sprint 1 files modified (only code additions + two registration/payment service hooks + AuthServiceProvider policy mapping) ✅
- No Check-In Engine / Communication Engine / Dashboard scope creep ✅

## Conclusion
Sprint 3 Ticket Engine is **DEPLOYED & VERIFIED** via Jenkins build #33 (SUCCESS), committed as `2115e6f` (+ `69ce6f6` fix), backend HEAD confirmed, all 5 ticket routes live, health check green, and the full server-side regression (issue → idempotency → idempotent re-issue → revoke → PDF/QR download) passes with **27/27** checks and zero production pollution.

---
*Deployed & verified: 2026-08-07 via Jenkins build #33 (SUCCESS)*