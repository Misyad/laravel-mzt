# SPRINT 4 — Verifikasi Communication Engine (ADR-016)

**Tanggal:** 2026-08-07
**Fase:** Phase 2B, Sprint 4
**Status:** **PASS** ✅

---

## 1. Ruang lingkup

Implementasi **Communication Engine** (ADR-016 / PRD §20): penerjemah
domain-event → outbound-communication yang **Queue-First**, terpisah dari
domain Payment/Ticket. Menyediakan Notification Center (owner) dan
Communication Log audit (admin/staff), menggunakan storage in-app yang
memakai kembali tabel `communication_logs` (kolom tambahan `title`,
`message`, `payload`, `read_at`).

**Sengaja di luar scope:** belum ada provider email/WhatsApp/Telegram/
push (menggunakan `NullProvider` sebagai stub), belum ada Broadcast/Reminder/
Dashboard — semua dijadwalkan di sprint berikutnya.

---

## 2. Aturan arsitektur yang dipertahankan

| # | Reg. | Leran |
|---|------|------|
| 1 | `CommunicationDispatcher` hanya **mengorkestrasi**; lifecycle log adalah milik `CommunicationLogService` | ✅ |
| 2 | Provider dialihkan oleh `ChannelResolver` (config `communication.channels`); dispatcher hanya kenal `CommunicationChannel` enum | ✅ |
| 3 | Listener **forward-only** (tanpa business logic) | ✅ |
| 4 | Template registry di `config/communication.php`; `TemplateService` tanpa switch/match | ✅ |
| 5 | `ProcessCommunication` idempoten — log `DELIVERED`/`FAILED` (final) tidak pernah kirim ulang | ✅ |
| 6 | Split akses: `GET /api/notifications*` → owner; `GET /api/communication-logs` → admin/staff via `CommunicationLogPolicy` | ✅ |
| 7 | Enums `CommunicationChannel` & `CommunicationStatus` (tanpa string literal) | ✅ |
| 8 | Lifecycle log immutable: `queued → processing → delivered | failed` (+ `retry_count`, `read_at`) | ✅ |

---

## 3. Hasil Pipeline

- Build Jenkins `mzt-deploy`: **#41 (SUCCESS)**  *(Build #39/#40 gagal di
  `docker compose build` karena YAML worker yang salah, diperbaiki dan
  -> SUCCESS pada #41.)*
- Commit yang diterapkan di deploy dir:
  - Backend : `1dfaf9d` — Sprint 4 Communication Engine
  - Frontend: `58cbe88` — deploy compose worker `communications queue`
- Stages: Validate → Sync → Build → Backup DB → Deploy → Migrate DB →
  Verify schema → Health check → Prune. **Semua PASS.**
- Kontainer worker aktif dengan command:
  `php artisan queue:work --queue=high,communications,default --sleep=2 --tries=3 --timeout=90 --max-time=3600`
  (Konsumsi queue `communications` + `high` + `default`).

---

## 4. Deployment / Migrasi (diverifikasi)

- Migration `2026_08_13_000001_create_jobs_table.php` **Ran** (queue).
- Migration `2026_08_14_000001_add_notification_columns_to_communication_logs.php`
  **Ran** — kolom `title`, `message`, `payload`, `read_at` ada di tabel
  `communication_logs` (dibuktikan dari `SHOW COLUMNS`).
- Backend `GET /api/...` response 200 (Caddy reverse-proxy sehat).

---

## 5. Hasil Regression Harness (server-side, real queue + worker + HTTP)

Harness dijalankan di kontainer `mzt-backend-1` dalam `tinker`: membuat 1
order+user test, `PaymentService::create` **cash → PAID** (jalur lengkap),
menunggu worker memproses queue `communications`, lalu memeriksa
Communication Log + endpoint HTTP (owner & admin). Setelah selesai **semua
artefak uji di-hapus → zero residual**.

`RESULT: 16/16 PASS`

| Kelompok | Kasus | Hasil |
|----------|-------|-------|
| Alur | cash → PAID → ticket | ✅ |
| Alur | `PaymentStatusChanged` → notifikasi `payment-approved` | ✅ |
| Alur | `TicketIssued` → notifikasi `ticket-issued` | ✅ |
| Notifikasi | jumlah log = 2 (PAYMENT-APPROVED + TICKET-ISSUED) | ✅ |
| Notifikasi | status seluruhnya `delivered` + `delivered_at` | ✅ |
| Channel | channel `in-app` (enum toggle) | ✅ |
| Template | judul ter-render dari registry `config/communication.php` | ✅ |
| Idempotency | re-run job pada log `DELIVERED` → tidak dikirim lagi | ✅ |
| Owner | `GET /api/notifications` → 200, list berisi 2 rows | ✅ |
| Owner | `unread_count` > 0 sebelum dibaca | ✅ |
| Owner | `PUT /api/notifications/read` → 200 (`read_at` set) | ✅ |
| Owner | `PATCH /api/notifications/read-all` → 200 | ✅ |
| Owner | setelah read → `unread_count = 0` | ✅ |
| Policy | owner `GET /api/communication-logs` → **403** | ✅ |
| Policy | admin `GET /api/communication-logs` → **200** | ✅ |
| Queue | tabel `jobs` kosong (worker memproses) (worker memproses); `failed_jobs` =0 | ✅ |

### Check no-artifacts (post-run)
`users(id_anggota LIKE %S4T%)` → **0** + `orders SO%S4T%` → **0** +
`payments %S4T%` → **0** + `tickets %S4T%` → **0** +
`communication_logs (post-test)` → **0** + `jobs` → **0** + `failed_jobs`
→ **0**.

---

## 6. Hasil Architecture Review (ADR-016)

- **Single Communication Gateway:** semua outbound lewat `CommunicationDispatcher`
  → `CommunicationLogService` + queue (`communications`) — konsisten satu
  tempat mutasi lifecycle log.
- Queue-First: `ProcessCommunication` berjalan di queue sendiri
  (`communications`) dengan `tries=3`; error `Provider send returned false`
  men-trigger retry otomatis lalu replay ke `FAILED` (via `failed()`).
- Idempotency INSIDE job (`$this::handle()`) **dan** dalam fallback service,
  tanpa `null` digunakan re-delivery tidak mengubah log.
- Policy gating berbasis `RoleGuard::canVerify` (finance/ketua/admin) —
  Communication Log read-only (tanpa ability create/update/delete).
- Best-effort dispatch dilindungi `try/catch` — kegagalan komunikasi tidak
  pernah memutus transaksi domain Payment.

**Keputusan:** **PASS** ✅ — Sprint 4 siap; backlog selanjutnya:
provider external (email/WhatsApp), Broadcast/Reminder, Dashboard
Communication, Notification UI di frontend (engine sudah siap data).