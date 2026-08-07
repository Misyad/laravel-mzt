# MZT Apps Release Notes

**Project** : Maziltutholiban Members Platform (MZT Apps)  
**Version** : v2.1.0-beta1  
**Release Type** : Beta  
**Status** : Stable (Production Verified)  
**Release Date** : 07 Agustus 2026

---

# Ringkasan

Release **v2.1.0-beta1** menyempurnakan **Payment & Ticket Engine** dan memperkenalkan **Communication Engine** pada Event Management System (EMS).

Pada rilis ini, MZT Apps melengkapi seluruh alur transaksional inti — dari **registrasi (Order)**, **pembayaran**, **penerbitan tiket**, hingga **notifikasi in-app** — dengan seluruh event domain dipublikasikan **setelah commit** dan komunikasi dikirim secara asinkron melalui antrian.

Seluruh implementasi telah dideploy ke production, diverifikasi melalui Jenkins Pipeline, dan lulus Architecture Review Phase 2B.

---

# Highlights

## Payment Engine

- State machine pembayaran `pending → waiting_verification → paid → rejected → refund`
- Metode: cash / sponsor / complimentary (langsung PAID) & transfer (verifikasi)
- Upload & validasi bukti pembayaran (MIME + ukuran)
- Verifikasi idempoten oleh Finance / Ketua / Admin
- Perhitungan sisa tagihan real-time (`outstanding`)

## Ticket Engine

- Penerbitan tiket **idempoten** untuk seluruh jalur PAID
- Nomor tiket `TKT-YYYY-NNNNNN` (UNIQUE, retry loop)
- Siklus tiket: generate / reissue / revoke
- Dokumen digital PDF + QR (digenerate on-demand, tidak disimpan)

## Communication Engine

- Dispatcher terpusat sebagai Single Communication Gateway
- Notification Center (owner) & Communication Log (admin/staff)
- Channel enum + ChannelResolver berbasis config
- Template registry (`config/communication.php`)
- Job `ProcessCommunication` idempoten (queue `communications`, `tries=3`)
- Template: `payment-approved`, `payment-rejected`, `ticket-issued`, `ticket-revoked`

---

# Sprint Summary

| Sprint | Konten | Hasil Verifikasi |
|--------|--------|------------------|
| **Sprint 1** | Fondasi domain (6 tabel, enum, model) | PASS (#26) |
| **Sprint 2** | Payment Engine | PASS (#30) |
| **Sprint 3** | Ticket Engine | PASS (#33), regression 27/27 |
| **Sprint 3.5** | Gate arsitektur (after_commit, instan→ticket, queue) | PASS (#37), regression 36/36 |
| **Sprint 4** | Communication Engine | PASS (#41), regression 16/16 |

---

# Architecture

- **Aggregate Root:** `Order` sebagai pusat Payment/Ticket (ADR-001)
- **Immutable Snapshot:** `event_name`/`event_price`/`event_start_at` di Order (ADR-002)
- **Identitas Publik:** Internal ID + UUID + nomor administrasi (ADR-003)
- **Business Logic di Service** (ADR-004)
- **Database Evolution First** — additive & idempotent (ADR-005)

---

# Database

Migration yang ditambahkan pada fase ini:

| Migration | Isi |
|-----------|-----|
| `create_payments_table` | Payments |
| `create_payment_proofs_table` | Bukti pembayaran |
| `create_payment_logs_table` | Audit trail status |
| `create_tickets_table` | Tiket |
| `create_ticket_logs_table` | Audit lifecycle tiket |
| `create_communication_logs_table` | Log komunikasi/notifikasi |
| `create_jobs_table` | Antrian queue (Sprint 3.5) |
| `add_notification_columns_to_communication_logs` | `title`, `message`, `payload`, `read_at` (Sprint 4) |

Semua migrasi **additive & idempotent**; re-run menghasilkan "Nothing to migrate".

---

# Security

- Autentikasi Sanctum + `auth:sanctum`
- RBAC via `RoleGuard` + Policy per entitas (Payment/Ticket/Communication)
- Validasi input via `FormRequest`
- Rate limit (throttle) pada upload
- Tidak ada secret di payload komunikasi; password di-hash

---

# Backend

## Service-layer baru

- `PaymentService`, `PaymentVerificationService`, `PaymentProofService`
- `TicketService`, `TicketNumberService`, `TicketLifecycleService`, `TicketDocumentService`
- `CommunicationDispatcher`, `CommunicationLogService`, `NotificationService`, `TemplateService`
- `RoleGuard` (RBAC)

## Controllers & Policies

- `PaymentController`, `TicketController`, `CommunicationController`
- `PaymentPolicy`, `TicketPolicy`, `CommunicationLogPolicy`

## Listeners & Job

- Listeners forward-only: `PaymentStatusChanged`, `TicketIssued`, `TicketStatusChanged`
- Job `ProcessCommunication` (queue `communications`, `tries=3`, idempotent)

---

## Frontend

Pada rilis ini frontend berfokus pada **deploy/konfigurasi** (ops):

- `docker-compose.yml` — penambahan service **worker** (queue `high,communications,default`)
- Caddyfile entrypoint same-origin (`/api`, `/storage`)
- Dokumentasi ADR & release notes

> Integrasi UI (formulir pembayaran, halaman tiket, notification center) dijadwalkan pada milestone berikutnya.

---

# Queue

- Driver queue `database` (tabel `jobs` + `failed_jobs`)
- Worker: `queue:work --queue=high,communications,default --sleep=2 --tries=3 --timeout=90`
- Event dispatch **setelah commit** — mencegah notifikasi pada transaksi yang gagal commit/rollback
- Job `ProcessCommunication` **idempoten** — log `DELIVERED`/`FAILED` final tidak pernah dikirim ulang
- Lifecycle log immutable: `queued → processing → delivered | failed`

---

# Deployment

```
[CI/CD Jenkins mzt-deploy]
  Validate host → Sync → Build → Backup DB
  → Deploy stack → Migrate DB → Verify schema
  → Health check → Prune
```

- Build sukses: `#26` (S1), `#30` (S2), `#33` (S3), `#37` (S3.5), `#41` (S4)
- Orkestrasi Docker Compose di `/opt/mzt`
- Worker supervised (`restart: unless-stopped`)

---

# Backup Strategy

- Pre-migration backup pada setiap deploy produksi (ADR-014)
- Volume `dbdata` (MariaDB) + instruksi rollback terdokumentasi
- Migrasi idempotent + post-deploy health check

---

# Verification

## Schema

- 6 tabel Phase 2B + tabel `jobs` + kolom notifikasi — terverifikasi (kolom & index sesuai PRD)
- `migrate:status` → "Ran"; re-run → "Nothing to migrate"

## API

- Endpoint payment/ticket/communication terdaftar, otentikasi (401), otorisasi (401/403) terverifikasi

## E2E/Regression

| Sprint | Hasil |
|--------|-------|
| Sprint 3 | 27/27 PASS |
| Sprint 3.5 | 36/36 PASS |
| Sprint 4 | 16/16 PASS |

Semuanya **zero artifacts** (data uji tidak meninggalkan jejak di DB).

---

# Architecture Review Summary

| Area | Skor (0–10) |
|------|-------------|
| Domain | 9 |
| Aggregate | 10 |
| Database | 8 |
| API | 8 |
| Queue | 8 |
| Communication | 9 |
| Security | 8 |
| Performance | 8 |
| Scalability | 7 |
| Technical Debt | 6 |
| **Total** | **81/100** |

**Verdict: PASS — Production Ready (v2.1.0-beta1).**

---

# Breaking Changes

Tidak ada.

---

## Known Limitations

- Provider eksternal (email/WhatsApp/Telegram) belum diimplementasikan — komunikasi berjalan **in-app** saja
- Queue masih driver `database` (Redis belum)
- Dashboard Finance/Reporting belum ada
- QR Check-In/Attendance belum ada (dijadwalkan v2.1.0-rc1)
- Broadcast & Reminder belum ada
- Automated test suite belum di-commit

---

# Next Milestone

**Sprint 5 — Finance Dashboard & Reporting**

- Finance Dashboard & Reporting
- Provider komunikasi eksternal (Email/WhatsApp/Telegram)
- Broadcast & Reminder
- QR Check-In/Attendance (v2.1.0-rc1)
- Sinkronisasi ADR-011/017 dan pembentukan automated test suite

---

# Release Status

| Komponen | Status |
|----------|--------|
| Registration | ✅ |
| Order | ✅ |
| Payment | ✅ |
| Ticket | ✅ |
| Communication (in-app) | ✅ |
| Queue | ✅ |
| Dashboard | ⏳ Planned (Sprint 5) |
| Attendance / Check-In | ⏳ Planned (v2.1.0-rc1) |
| Broadcast | ⏳ Planned (Sprint 5) |
| Reminder | ⏳ Planned (Sprint 5) |
| Email | ⏳ Planned (Sprint 5) |
| WhatsApp | ⏳ Planned (Sprint 5) |

---

# Penutup

Release **v2.1.0-beta1** menandakan kelengkapan **layanan transaksional inti** — pembayaran, tiket, dan komunikasi in-app — dengan arsitektur **Production Ready** yang selaras dengan ADR.

Fase berikutnya adalah **Finance Dashboard & Reporting** (Sprint 5) dan penyambungan komunikasi eksternal; seluruh fondasi (event, queue, dispatcher, RBAC) telah tersedia sejak rilis ini.

Seluruh dokumentasi evaluasi (Architecture Review, Sprint Verification Report, Implementation Summary) menunjukkan rilis ini siap untuk pengembangan selanjutnya.

---

*Dokumen ini merupakan bagian dari Phase 2B Closure. Fakta bersumber dari implementasi aktual dan Sprint 1–4 Verification Report. Tidak ada kode aplikasi, PRD, maupun ADR yang diubah.*