# Phase 2C — QR Check-In Verification Report

## Overview

Phase 2C QR Check-In (ticket-based attendance, `POST /api/checkin`, PRD §12.3/§12.4/§17.8/§17.14.4, ADR-011/016) was verified against the **production** environment. The verification initially exposed a real datetime-format defect that broke every valid check-in with **HTTP 500**. The defect was fixed in hotfix **`70f26f7`**, the backend image was rebuilt, the container was recreated, and the full production smoke suite was re-run **PASS**.

> **Final verdict is NOT a clean first-pass.** Production verification first discovered a real defect (see §Incident & Hotfix), which was then fixed and re-verified.

## Environment

- Backend repo: `laravel-mzt` (Misyad/laravel-mzt)
- Frontend repo: `maziltu-design-studio` (Misyad/maziltu-design-studio)
- Framework: Laravel 9.19 | PHP ^8.0.2 | MariaDB 11.4
- Deploy host: Dockerhost 192.168.1.60 (Proxmox)
- Production entrypoint used for verification: `http://192.168.1.60:3015` (Caddy) → `/api/*` → backend

## Incident & Hotfix (mandatory record)

### Root cause
`CheckInService` stored attendance time using the format:

```
H:i:s
```

while the column

```
prisensi_kehadiran.jam_kehadiran
```

is of type **TIMESTAMP** and the legacy path (`ApiController`) stores the full

```
Y-m-d H:i:s
```

value.

### Impact
Every valid check-in raised `SQLSTATE[22007] ... 1292 Incorrect datetime value: '10:33:56' for column prisensi_kehadiran.jam_kehadiran`, and the API returned **HTTP 500** for all valid check-in attempts (roles prisensi/event/finance/ketua/admin). Negative cases (401/403/422/404/409) still short-circuited before the DB write, which is why only the success path failed.

### Fix (commit `70f26f7`)
- `app/Services/CheckInService.php`: `'jam_kehadiran' => $scannedAt->format('H:i:s')` → `'jam_kehadiran' => $scannedAt->format('Y-m-d H:i:s')` — consistent with the legacy column format.
- `tests/Feature/CheckInTest.php`: schema fixture corrected from `$table->time('jam_kehadiran')` to `$table->timestamp('jam_kehadiran')` to mirror the production column.

### Hotfix deployment status
| Step | Status |
|------|--------|
| Fix committed & pushed (`70f26f7` → `origin/main`) | ✅ |
| Backend image rebuilt (`mzt-backend:local` rebuilt) | ✅ |
| Backend container recreated | ✅ |
| `CheckInService.php` in the fresh container verified to contain `Y-m-d H:i:s` | ✅ |
| Production smoke test after fix | ✅ PASS |

**No HTTP 500 remained after the hotfix.**

## Production Verification Evidence

Smoke tests executed against production (`http://192.168.1.60:3015/api`) using temporary `SMOKE_PHASE2C` accounts, each synthetic ticket bound to an order of event 16, `id_tanggal=202` (a valid day for the ticket's event; `id_tanggal=195` used as the invalid-day case).

| ID | Test | Expected | Actual | Result |
|----|------|----------|--------|--------|
| A | Unauthenticated request | 401 | **401** | PASS |
| B | Role without check-in permission (alumni) | 403 | **403** | PASS |
| C | Role prisensi, valid ticket | 200 | **200** | PASS |
| D | Role event, valid ticket | 200 | **200** | PASS |
| E | Role finance, valid ticket | 200 | **200** | PASS |
| F | Role ketua, valid ticket | 200 | **200** | PASS |
| G | Role admin, valid ticket | 200 | **200** | PASS |
| H | Invalid UUID in `ticket_uuid` | 422 | **422** | PASS |
| I | Unknown / non-existent ticket | 404 | **404** | PASS |
| J | Non-usable ticket status (`finished`) | 409 | **409** | PASS |
| L | Valid check-in (issued ticket, valid date) | 200 | **200** | PASS |
| M | Valid check-in, fresh `valid` ticket | 200 | **200** | PASS |
| N | Invalid event date (`id_tanggal=195`) | 422 | **422** | PASS |

Additional checks exercised during the re-verification round:
- Duplicate scan (re-scan of an already `checked_in` ticket) → **409** `"Tiket sudah digunakan"` with `first_scanned_at` and `first_scanned_by` (see §Duplicate Check). Labeled **DUP** in the refactor round.

**No HTTP 500 remained after the hotfix.**

## Database Evidence (successful production check-in)

After a successful valid check-in (ticket `a0000000-...-000002`, scanned by prisensi user 1203, `id_tanggal=202`, gate not sent in this round):

- `tickets` row: `status = checked_in`, `used_at = 2026-08-10 11:57:38` ✅
- `prisensi_kehadiran` row created (id 922):
  - `id_ticket` = 49 ✅
  - `scanned_at` = 2026-08-10 11:57:38 ✅
  - `scanned_by` = 1203 ✅
  - `tanggal_kehadiran` / `jam_kehadiran` = full datetime ✅
  - `gate` = **NULL** (request in this round did not send a gate); gate propagation was verified separately in an earlier reproduction that sent `GATE-A` → stored `GATE-A` ✅
- `ticket_logs` row created: `old_status=issued`, `new_status=checked_in`, `note=check_in`, `changed_by=1203` ✅
- `TicketStatusChanged` dispatched via `DB::afterCommit` — no error surfaced on the success path; the event is consumed by the Communication listener (§Communication Regression). The after-commit dispatch itself is not independently observable from the DB side; see §Communication Regression.

Fields without explicit evidence in the recorded rounds are not claimed to be verified (e.g., `expired_at`/`revoked_at` remain NULL by design and were not asserted).

## Duplicate Check

Duplicate scan (a second scan of the ticket that had already been checked in) was exercised as a live HTTP request:

- **Response: HTTP 409**, `{"success":false,"message":"Tiket sudah digunakan","data":{"first_scanned_at":"2026-08-10T11:57:38.000000Z","first_scanned_by":1203}}` — rejected per contract, and the first-scan info is returned. ✅
- `first_scanned_at` present ✅
- `first_scanned_by` present ✅
- No additional attendance row created (attendance count remained **1**) ✅
- No additional ticket_log row created (ticket_log count remained **1**) ✅

## Communication Regression

- `TicketStatusChangedListener` only forwards lifecycle transitions with `action = 'revoke'`; `check_in` is explicitly ignored by the listener until a template exists. ✅
- No `communication_logs` row was created by any check-in in this verification (marker-users count = 0, and the table total was 0). ✅
- The Communication Engine was **not** extended for check-in in Phase 2C.
- The after-commit dispatch of `TicketStatusChanged(action='check_in')` reached the listener without error; because the listener ignores non-revoke actions, no communication side effect follows. Dispatcher/template execution beyond this is **not** independently asserted (the only observable evidence is the absence of new `communication_logs` rows and the listener's non-revoke early return).

## Legacy Regression

| Check | Result | Evidence |
|-------|--------|----------|
| `GET /api/public/stats` | **PASS (200, `success:true`, `total_anggota: 950`)** | live HTTP |
| Dashboard 5A — `tickets`, `operational`, `overview`, `registration`, `revenue`, `payments` | **PASS (200, `success:true`)** × 6 | live HTTP (finance token) |
| Ticket Engine — `GET /api/tickets/{uuid}` | **PASS (200)** | live HTTP (member token) |
| `GET /api/attendance/{eventId}/{tanggalId}` (attendanceIndex) | **PASS (200)** | live HTTP (prisensi token) |
| Legacy `POST /api/attendance` (attendanceStore) | **PASS (201, `success:true`, "Attendance recorded successfully")** | HTTP kernel in a rolled-back transaction (no production row persisted; real member id used) |

The legacy attendance run was wrapped in a transaction that was rolled back, so the verification created **no** residual production attendance row (verified afterwards).

## Cleanup

Temporary production data created for the smoke test (`SMOKE_PHASE2C` marker) was fully removed:

| Artifact | Cleaned |
|----------|---------|
| Temporary users | 7 |
| Temporary tickets | 9 |
| Attendance rows | 1 (final round) + earlier rounds (total seen: 7 in the full-suite round, 1 in the duplicate round) |
| Ticket logs | 1 (final round) + earlier rounds |
| Orders (and tokens) | ✓ |

Final verification after cleanup: **0 residual SMOKE_PHASE2C artifacts** (`smoke_users=0, smoke_orders=0, smoke_tickets=0, smoke_tokens=0`). No production (non-test) data was touched other than read-only checks; the legacy attendance probe used rollback and left no rows.

## Known Infrastructure Observation — NON-BLOCKING

- `api.maziltu.com` currently resolves to **NXDOMAIN** (checked from both the deploy host and the verification client).
- Production verification was therefore performed through the Caddy endpoint `http://192.168.1.60:3015` (`/api/*` → backend). The single-host `public/stats` health check and every check-in call used this entrypoint successfully.
- No `api.maziltu.com` configuration was found on the cloudflared/Caddy ingress inspected during verification.
- This is recorded as a **non-blocking** infrastructure observation, **not** a Phase 2C defect. It is tracked as a **separate follow-up infrastructure investigation**. No conclusion about the domain being misconfigured is drawn without additional evidence, and no fix was attempted as part of this task.

## Final Verdict

**PHASE 2C — PRODUCTION VERIFIED**

Rationale after hotfix `70f26f7`:
- Deployment succeeded; fresh container carries the fix (verified in the container).
- Production smoke suite PASS: every role authorization case (401/403/200 per role) ✅
- Valid check-ins PASS (C–G, L, M → 200) ✅
- Duplicate handling PASS (409 + first-scan info, no extra attendance/log) ✅
- **No HTTP 500 remained after the fix** ✅
- Cleanup PASS (0 residual) ✅

Production verification **initially discovered a real datetime-format defect** (MySQL 1292), which was fixed in `70f26f7` and successfully re-verified in production. The result is **not** a clean first-pass; the incident is documented above and not hidden.

---
*Phase 2C QR Check-In — production verified 2026-08-10. Hotfix `70f26f7`. Written by the closing-verification task.*