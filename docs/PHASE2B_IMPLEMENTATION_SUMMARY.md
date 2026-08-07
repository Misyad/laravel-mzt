# MZT Apps — Phase 2B Implementation Summary

**Project** : Maziltutholiban Members Platform (MZT Apps)  
**Fase** : Phase 2B (Payment & Ticket Engine + Communication Engine)  
**Milestone** : v2.1.0-beta1  
**Status** : **Selesai — Production Ready**  
**Tanggal** : 07 Agustus 2026

---

# 1. Ringkasan Phase 2B

Phase 2B membawa EMS dari tahap alpha1 (Event/Registration/Order) menjadi **platform transaksional lengkap** yang mencakup pembayaran, tiket, dan komunikasi in-app.

Empat sprint utama (plus satu sprint gate) diselesaikan dan seluruhnya **PASS**:

- **Sprint 1** — Fondasi domain (6 tabel, enum, model)
- **Sprint 2** — Payment Engine
- **Sprint 3** — Ticket Engine
- **Sprint 3.5** — Gate arsitektur (instan payment→ticket, `after_commit`, queue)
- **Sprint 4** — Communication Engine

Seluruh implementasi telah dideploy ke production (`/opt/mzt`, Jenkins `mzt-deploy`) dan lolos regression serta Architecture Review.

---

# 2. Coverage Matrix

| Modul | Status |
|-------|--------|
| Registration | ✅ |
| Order | ✅ |
| Payment | ✅ |
| Ticket | ✅ |
| Communication | ✅ |
| Dashboard | ⏳ Planned |
| Attendance | ⏳ Planned |
| Broadcast | ⏳ Planned |
| Reminder | ⏳ Planned |
| Email | ⏳ Planned |
| WhatsApp | ⏳ Planned |

> Tidak ada modul berstatus "In Progress" — seluruh implementasi aktif pasca Sprint 4 telah selesai.

---

# 3. Completed

## 3.1 Phase 2A (v2.1.0-alpha1)

- Event Core (kapasitas/kuota, venue, visibility, registrasi window, harga numerik)
- Registration Engine (registrasi event, validasi kuota/waktu/visibility, anti duplikat)
- Order Engine (Order sebagai aggregate root, snapshot immutable, nomor global)
- Lulus deploy & regression pada milestone alpha1

## 3.2 Sprint 1 — Domain Foundation

- 6 tabel baru: `payments`, `payment_proofs`, `payment_logs`, `tickets`, `ticket_logs`, `communication_logs`
- Enum: `PaymentMethod`, `PaymentStatus`, `TicketStatus`, `OrderStatus`
- Model & relasi (Order → Payment/Ticket)
- Backend commit `63b074c`; frontend commit `961143f`; Jenkins #26 PASS

## 3.3 Sprint 2 — Payment Engine

- `PaymentService` (create, upload proof, outstanding), `PaymentVerificationService` (verify), `PaymentProofService`
- State machine `pending → waiting_verification → paid → rejected → refund`
- RBAC `RoleGuard` + `PaymentPolicy`
- 6 endpoint + 3 FormRequest
- Backend commit `5806ca6`; Jenkins #30 PASS

## 3.4 Sprint 3 — Ticket Engine

- `TicketNumberService`, `TicketService` (penerbitan idempoten), `TicketLifecycleService` (reissue/revoke), `TicketDocumentService` (PDF/QR)
- 5 endpoint + `TicketPolicy`
- Regression **27/27**; backend commit `2115e6f` (+ `69ce6f6`); Jenkins #33 PASS

## 3.5 Sprint 3.5 — Architecture Gates

- **Gate 1:** instan payment→ticket untuk seluruh jalur PAID (cash/sponsor/complimentary/transfer)
- **Gate 2:** event domain via `DB::afterCommit` (`PaymentStatusChanged`, `TicketIssued`, `TicketStatusChanged`)
- **Gate 3:** fondasi queue `database` + tabel `jobs` + worker kontainer
- Regression **36/36**; backend `5ad49c5`, frontend `db8af6f`; Jenkins #37 PASS

## 3.6 Sprint 4 — Communication Engine

- `CommunicationDispatcher`, `ChannelResolver`, provider (`InAppProvider`, `NullProvider`)
- `CommunicationLogService` (lifecycle immutable), `NotificationService`, `TemplateService`
- Listeners forward-only (3) + Job `ProcessCommunication` idempoten
- 4 endpoint + `CommunicationLogPolicy` + enums `CommunicationChannel`/`CommunicationStatus`
- Migration kolom notifikasi (`title`, `message`, `payload`, `read_at`)
- Regression **16/16**; backend `1dfaf9d`, frontend `58cbe88`; Jenkins #41 PASS

---

# 4. Planned

Pekerjaan yang dijadwalkan pada sprint berikutnya (belum dimulai):

### Sprint 5 — Finance Dashboard & Reporting

- Finance Dashboard & Reporting
- Provider komunikasi eksternal: **Email** & **WhatsApp** (melalui `CommunicationDispatcher` yang sudah tersedia)
- **Broadcast** (one-to-many) & **Reminder** (jadwal otomatis)
- Pembersihan technical debt prioritas: automated test suite, refactor controller, sinkronisasi ADR-011/017

### Milestone selanjutnya (v2.1.0-rc1)

- **Attendance / QR Check-In** (ADR-012)
- Finalisasi lifecycle tiket `revoked`

### Backlog lain

- **Dashboard** generik (admin)
- Kebijakan retensi/arsip `communication_logs`
- Monitoring queue (`failed_jobs` UI + alerting)

---

# 5. Statistik Implementasi

| Metrik | Nilai |
|--------|-------|
| Jumlah sprint | 5 (S1, S2, S3, S3.5, S4) |
| Tabel DB baru | 6 + 1 queue (`jobs`) |
| Migration Phase 2B | 8 (+ baseline orders) |
| Service baru (backend) | ± 11 |
| Controller baru | 3 (Payment, Ticket, Communication) |
| Policy baru | 3 |
| Listeners | 3 |
| Job produksi | 1 (`ProcessCommunication`) |
| Route baru | 15 (payment 6, ticket 5, communication 4) |
| Total route `api.php` | 71 |

> Angka commit/route bersumber dari Verification Report dan implementasi aktual.

---

# 6. Statistik Deployment

| Build Jenkins `mzt-deploy` | Sprint | Hasil |
|----------------------------|--------|-------|
| #26 | Sprint 1 | SUCCESS |
| #30 | Sprint 2 | SUCCESS |
| #33 | Sprint 3 | SUCCESS |
| #37 | Sprint 3.5 | SUCCESS |
| #41 | Sprint 4 | SUCCESS |

- Stack: Docker Compose (`/opt/mzt`) — backend, frontend (Nitro), Caddy, MariaDB, **worker**
- Worker: `queue:work --queue=high,communications,default`
- Semua deploy melewati tahap Backup DB → Migrate → Verify schema → Health check

---

# 7. Statistik Verifikasi

| Area | Hasil |
|------|-------|
| Schema DB | 6 tabel + jobs + kolom notifikasi sesuai PRD, idempotent |
| API | 71 route; auth 401 / authz 401–403 terverifikasi |
| E2E (HTTP) | Login, payment, ticket, notification, policy — terverifikasi |
| Production | Semua milestone terverifikasi di production stack |

---

# 8. Statistik Regression

| Sprint | Jumlah Check | Hasil |
|--------|--------------|-------|
| Sprint 3 | 27 | 27/27 PASS |
| Sprint 3.5 | 36 | 36/36 PASS |
| Sprint 4 | 16 | 16/16 PASS |
| **Total** | **79** | **79/79 PASS** |

Semua regression berjalan **server-side** dan menghasilkan **zero artifacts** (tidak ada data uji tersisa di DB).

---

# 9. Technical Debt yang Tersisa

| ID | Deskripsi | Severity |
|----|-----------|----------|
| TD-01 | `ApiController.php` (1482 baris) & controller admin lain — god-controller vs ADR-004 | Minor |
| TD-02 | Belum ada automated test suite yang di-commit | Minor |
| TD-03 | ADR-011/ADR-017 belum di-sync ke `docs/ADR.md` | Minor |
| TD-04 | Job failure belum dimonitor (alerting/UI retry) | Minor |
| TD-05 | `communication_logs` tanpa kebijakan retensi/arsip | Observation |
| TD-06 | Inkonsistensi dokumen PRD (`completed`, `revoked` vs `cancelled`, QR payload) | Observation |

Semua item bersifat **non-blocking** untuk produksi v2.1.0-beta1.

---

# 10. Kesimpulan

Phase 2B selesai dengan **seluruh target tercapai**:

- Payment Engine, Ticket Engine, dan Communication Engine **berfungsi penuh** di production
- **79/79** regression PASS dengan zero artifacts
- Architecture Score **81/100** — Production Ready
- Tidak ada temuan Critical/Major

Phase berikutnya (Sprint 5 — Finance Dashboard & Reporting) dapat dimulai di atas fondasi yang telah kokoh: event domain `after_commit`, queue worker, dispatcher komunikasi, RBAC, dan policy yang sudah teruji.

---

*Dokumen ini merupakan bagian dari Phase 2B Closure. Fakta bersumber dari implementasi aktual dan Sprint 1–4 Verification Report. Tidak ada kode aplikasi, PRD, maupun ADR yang diubah.*