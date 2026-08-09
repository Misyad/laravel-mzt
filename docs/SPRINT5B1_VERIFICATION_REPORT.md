# Sprint 5B.1 — Verifikasi Report (Ticket & Operational Monitoring)

## 1. Ringkasan

| Item | Detail |
|------|--------|
| **Sprint** | 5B.1 — Ticket & Operational Monitoring |
| **Status** | **PASS** (implementasi + regression + performance gate) + **PRODUCTION VERIFIED** |
| **Commit Backend** | `b173e91` (`feat(sprint5b): ticket & operational monitoring endpoints ...`) |
| **Commit Frontend** | `f74164a` (`feat(sprint5b): ticket & operational monitoring page ...`) |
| **Branch** | `main` (pushed → GitHub `Misyad/laravel-mzt` & `Misyad/maziltu-design-studio`) |
| **Jenkins** | `mzt-deploy` — auto-trigger via push webhook (lihat §7) |
| **Production Smoke** | PASS (lihat §10) — endpoint 5B.1 + regression 5A + authorization + no 500 |

---

## 2. Lingkup

Sprint 5B.1 membangun **monitoring tiket (per status) dan ringkasan operasional**
lintas entitas, sesuai arsitektur yang disetujui pada `SPRINT5B_ARCHITECTURE_REVIEW.md`
(score 80/100, gate closure §26, ADR-011 canonical state).

Backend:
- DTO: `TicketSummary`, `OperationalSummary` (nilai default 0, pure DTO).
- **Query Layer** `app/Queries/DashboardQuery.php`: `tickets()` & `operational()`
  — aggregate single-pass (COUNT/SUM/groupBy langsung pada kolom `tickets.status`,
  **tanpa mapping status baru**, tanpa N+1).
- **Service**: `DashboardService::ticketSummary()` & `operationalSummary()`
  (read-only, return DTO murni), via interface `DashboardServiceInterface`.
- **Policy**: `DashboardPolicy::viewTickets()` & `viewOperational()`
  (= `RoleGuard::isStaff`) sesuai Role Matrix §13.
- **Resources**: `TicketSummaryResource`, `OperationalSummaryResource` (DTO→JSON).
- **Controller**: `DashboardController::tickets()` & `operational()` + 2 route baru.

Frontend (repo `maziltu-design-studio`):
- `src/types/api.ts`: `TicketStatus` (canonical ADR-011), `TicketSummary`, `OperationalSummary`.
- `src/services/mzt-api.ts` + `src/services/queries.ts`: fetch + queryOptions baru.
- `src/routes/dashboard/finance/tickets/index.tsx`: halaman monitoring (5 kartu
  operasional + daftar per status canonical; state loading/empty/success).
- `src/routes/dashboard/route.tsx` (NAV_ITEMS + `TicketCheck`), `src/routeTree.gen.ts`
  (route `/dashboard/finance/tickets/` terdaftar).

---

## 3. Endpoint & RBAC

| Method | Path | Gate |
|--------|------|------|
| GET | `/api/dashboard/finance/tickets` | `viewTickets` (isStaff) |
| GET | `/api/dashboard/finance/operational` | `viewOperational` (isStaff) |

Semua endpoint dilindungi middleware `auth:sanctum`. Response `{ success: true, data }`
via API Resource. **Catatan interpretasi**: Planning §11 hanya mencantumkan endpoint
PROPOSED `tickets`, namun FR-02 + §13/§19 menuntut ringkasan operasional, sehingga
endpoint `/operational` ditambahkan dengan gate `viewOperational` (staff).

---

## 4. Verification — Regression Tests

### 4.1 Authorization (401/403/200)
- Test dibuat: tanpa token → 401; role non-staff → 403; staff (dashboard/event/
  finance/ketua/admin) → 200.

### 4.2 Empty Dataset
- Schema kosong (0 orders / payments / tickets): kedua endpoint mengembalikan
  **200 + data bernilai-nol**, tanpa error.

### 4.3 Large Dataset & Canonical Status
- Seed 500 Orders / 500 Payments / 500 Tickets (status dirotasi merata → 10 per
  6 status canonical).
- Memverifikasi agregasi benar dan `tickets` dikelompokkan langsung pada status
  canonical ADR-011 (tanpa mapping baru).

Hasil:

| Command | Hasil |
|---------|-------|
| `phpunit tests/Feature/DashboardTest.php` | **OK (10 tests, 53 assertions)** |
| `phpunit --group performance` | **OK (2 tests, 17 assertions)** |
| `phpunit` (full suite) | 18/19 PASS — satu failure `ExampleTest` **pre-existing** (§8) |

### 4.4 Performance Gate (`PERFORMANCE_GATE_MS = 500`)
Pada Large Dataset (500×3):

| Endpoint | Latency |
|----------|---------|
| tickets | <500 ms |
| operational | <500 ms |

Keduanya memenuhi gate per-endpoint **<500 ms**. Pola kueri adalah aggregate
single-pass (COUNT/SUM/groupBy), tanpa N+1.

---

## 5. Self-Review Checklist

| Nomor | Checklist (arsitektur Sprint 5B) | Hasil |
|-------|----------------------------------|-------|
| 1 | DTO untuk semua response | PASS |
| 2 | Controller → Service Interface → Service → Query Layer → DTO → Resource → JSON | PASS |
| 3 | Read-only (tanpa side-effect di Query/Service/Controller) | PASS |
| 4 | Tanpa caching | PASS |
| 5 | Gate authorization per endpoint (Role Matrix §13) | PASS |
| 6 | Regression Empty Dataset (200 + nilai 0) | PASS |
| 7 | Service return DTO murni (bukan Model/Builder/Collection) | PASS |
| 8 | Controller pakai Laravel API Resource | PASS |
| 9 | Interface terotisasi + implementasi | PASS |
| 10 | Regression Large Dataset (500×3) | PASS |
| 11 | Tanpa N+1 (aggregate single-pass) | PASS |
| 12 | Status tiket = canonical ADR-011 (tanpa mapping baru) | PASS |
| 13 | Performance Gate <500ms | PASS |
| 14 | Sprint 5A tetap PASS (regression) | PASS |

---

## 6. Deployment / Frontend Status

- Backend **dipush** ke `main` (`b173e91`) — siap deploy.
- Frontend **dipush** ke `main` (`f74164a`), **build** vite `✓ built` sukses,
  route tree regenerated, dan `eslint` pada semua file Sprint 5B.1 **0 error**
  (error lint yang tersisa di repo adalah pre-existing CRLF `Delete ␍` di file
  lama shadcn/ui, di luar scope).

---

## 7. Jenkins Build Status

- **Job**: `mzt-deploy` (jenkins.projecthasan.com:8084, Dockerhost 192.168.1.60).
- **Build**: SUCCESS (auto-trigger via push webhook pada `main`).
  - Backend sync: `443233f..b173e91` (`git rev-parse --short HEAD` = `b173e91`).
  - Frontend checkout: `f74164a` (revision `f74164ab4525567d17b6d7f1bba46e74d18f86e9`).
  - Migration: `Nothing to migrate.` — PASS.
  - Schema verification: audit columns + Phase 2A columns — PASS.
  - Health check: `GET /api/public/stats` -> `HTTP 200`, body
    `{"success":true,"data":{"event":4,"event_selesai":4,"event_mendatang":0,"total_anggota":950}}`.
  - Semua container `mzt-backend-1`, `mzt-frontend-1`, `mzt-worker-1`, `mzt-caddy-1`, `mzt-db-1` (healthy) = **Up**.

---

## 8. Keterbatasan / Catatan

- `tests/Feature/ExampleTest.php` masih failure **pre-existing** (expect 200 vs 500
  terhadap homepage pada DB test kosong) — di luar scope Sprint 5B.1.
- Error lint TS pre-existing (CRLF `Delete ␍`) di file lama shadcn/ui — di luar
  scope; tidak ada error baru dari file Sprint 5B.1.

---

## 9. Kesimpulan

Sprint 5B.1 telah **lolos semua** kriteria: implementasi lengkap (backend +
frontend), regression (authorization / empty / large dataset / canonical status)
PASS, performance gate PASS, self-review PASS, **production smoke test PASS**.
**Sprint 5B.1 PRODUCTION VERIFIED.** Siap untuk lanjut ke Sprint 5B.2 (data
analytics) setelah persetujuan.

---

## 10. Production Smoke Test — Evidence

Diakses dari Dockerhost (LXC 104) melalui Tailscale `pve` -> `pct exec 104`,
hit Caddy `http://localhost:3015`. Token Sanctum dibuat sementara via container
`mzt-backend-1` (bootstrap Laravel) untuk user staff (`dashboard`), non-staff
(`profil`), dan verifier temp (`finance`, user ID 1165), lalu **dihapus seluruhnya
setelah pengujian — ZERO ARTIFACTS** (SMOKE_TOKENS=0, SMOKE_USERS=0,
VERIFIER_ROLES_LEFT=0, TEMP_FILES_LEFT=0).

### 10.1 Unauthenticated (expect 401)

| Endpoint | HTTP |
|----------|------|
| `GET /api/dashboard/finance/tickets` | 401 |
| `GET /api/dashboard/finance/operational` | 401 |
| `GET /api/dashboard/finance/overview` | 401 |
| `GET /api/dashboard/finance/registration` | 401 |
| `GET /api/dashboard/finance/revenue` | 401 |
| `GET /api/dashboard/finance/payments` | 401 |

### 10.2 Staff role (`dashboard`) — expect 200 + success:true

| Endpoint | HTTP | Body |
|----------|------|------|
| `tickets` | 200 | `{"success":true,"data":{"total_tickets":0,"by_status":[]}}` |
| `operational` | 200 | `{"success":true,"data":{"total_orders":0,"total_paid":0,"outstanding":0,"waiting_verification":0,"total_tickets":0}}` |
| `overview` | 200 | `{"success":true,"data":{"total_orders":0,...}}` |
| `registration` | 200 | `{"success":true,"data":{"total_orders":0,"by_status":[]}}` |
| `revenue` | 403 | verifier-only (`viewRevenue` = canVerify) — sesuai Role Matrix §13 |
| `payments` | 403 | verifier-only (`viewPayment` = canVerify) — sesuai Role Matrix §13 |

### 10.3 Non-staff role (`profil`) — expect 403

| Endpoint | HTTP |
|----------|------|
| `tickets` | 403 |
| `operational` | 403 |
| `overview` | 403 |
| `registration` | 403 |
| `revenue` | 403 |
| `payments` | 403 |

### 10.4 Verifier role (temp `finance`, user 1165) — expect 200 + success:true

| Endpoint | HTTP | Body |
|----------|------|------|
| `revenue` | 200 | `{"success":true,"data":{"total_revenue":0,"total_paid":0,"outstanding":0,"by_status":[]}}` |
| `payments` | 200 | `{"success":true,"data":{"by_status":[],"waiting_verification":0}}` |
| `tickets` | 200 | success:true |
| `operational` | 200 | success:true |

### 10.5 Public health

| Endpoint | HTTP | Body |
|----------|------|------|
| `GET /api/public/stats` | 200 | `{"success":true,"data":{"event":4,"event_selesai":4,"event_mendatang":0,"total_anggota":950}}` |

### 10.6 Kesimpulan Smoke

- Semua endpoint mengembalikan HTTP status yang benar (401/403/200).
- `success:true` hadir pada semua respons yang diizinkan.
- Struktur JSON sesuai Resource (TicketSummaryResource / OperationalSummaryResource).
- **Tidak ada HTTP 500** pada semua probe.
- Empty dataset production: semua agregat bernilai 0, tidak ada error (perilaku
  empty-state benar).
- Authorization production sesuai Role Matrix §13: 5B.1 endpoints (staff) &
  analytics/verifier-only revenue/payments (finance/ketua/admin).
- ZERO ARTIFACTS setelah pengujian (token sementara + user verifier temp dihapus).

