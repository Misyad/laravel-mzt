# MZT Apps — Phase 2B Architecture Review

**Project** : Maziltutholiban Members Platform (MZT Apps)  
**Fase** : Phase 2B (Payment & Ticket Engine + Communication Engine)  
**Milestone** : v2.1.0-beta1  
**Scope Review** : Phase 2A + Sprint 1 + Sprint 2 + Sprint 3 + Sprint 3.5 + Sprint 4  
**Status** : **PASS — Production Ready (v2.1.0-beta1)**  
**Tanggal Review** : 07 Agustus 2026  
**Acuan** : Sprint 1–4 Verification Report, ADR-001 s.d. ADR-017, Audit-Gap-Analysis, implementasi aktual di `Misyad/laravel-mzt`

---

# 1. Metadata

| Item | Nilai |
|------|-------|
| Nama Produk | Maziltutholiban Members Platform (MZT Apps) — Event Management System |
| Repositori Backend | `Misyad/laravel-mzt` |
| Repositori Frontend | `Misyad/maziltu-design-studio` |
| Lingkungan | Production single-host (`192.168.1.60`), stack `/opt/mzt` |
| Deploy Orchestrator | Jenkins Pipeline `mzt-deploy` + Docker Compose |
| Metode Review | Static code review + hasil Sprint 1–4 Verification Report (deploy & regression) |
| Reviewed By | Architecture Review Phase 2B |

### Commit baseline terverifikasi

| Sprint | Backend Commit | Frontend Commit | Report |
|--------|----------------|------------------|--------|
| Sprint 1 (Domain) | `63b074c` | `961143f` | SPRINT1 |
| Sprint 2 (Payment) | `5806ca6` | — | SPRINT2 |
| Sprint 3 (Ticket) | `2115e6f` (+ `69ce6f6`) | — | SPRINT3 |
| Sprint 3.5 (Gates) | `5ad49c5` | `db8af6f` | SPRINT3_5 |
| Sprint 4 (Communication) | `1dfaf9d` | `58cbe88` | SPRINT4 |

> Commit/angka merujuk pada Verification Report resmi; bila field tidak tercatat di laporan, ditandai `—`.

---

# 2. Executive Summary

Phase 2B menyelesaikan transformasi MZT Apps dari aplikasi keanggotaan menjadi **platform event berbasis Order** yang mencakup:

- **Sprint 1** — fondasi domain (6 tabel baru, enum, model).
- **Sprint 2** — Payment Engine (state machine, bukti transfer, RBAC).
- **Sprint 3** — Ticket Engine (penerbitan idempoten, PDF & QR).
- **Sprint 3.5** — penutupan gate arsitektur (instan payment→tiket, `after_commit`, fondasi queue).
- **Sprint 4** — Communication Engine (Notification Center + Communication Log, queue-first).

Seluruh sprint **PASS** dan terdeploy ke production. Arsitektur konsisten dengan ADR-001 s.d. ADR-017. Tidak ada temuan **Critical/Major**; terdapat sejumlah **Minor** dan **Observation** terkait technical debt dokumentasi dan struktur controller. Sistem **Production Ready** untuk scope v2.1.0-beta1.

---

# 3. Architecture Evolution Timeline

```
Phase 2A (v2.1.0-alpha1)
  Event / Registration / Order Core
        |
        v
Sprint 1 — Domain Foundation   → 6 tabel, enum, model
                                   (payments/proofs/logs,
                                    tickets/ticket_logs, communication_logs)
        |
        v
Sprint 2 — Payment Engine      → PaymentService, Verification, Proof, RBAC,
                                   6 endpoint, state machine
        |
        v
Sprint 3 — Ticket Engine       → TicketService, Lifecycle, Document (PDF/QR),
                                   5 endpoint, idempotent issue
        |
        v
Sprint 3.5 — Architecture Gate → after_commit event, instan payment→ticket,
                                   queue foundation (database + worker)
        |
        v
Sprint 4 — Communication       → Dispatcher, ChannelResolver, ProcessJob,
                                   Notification Center + Communication Log
```

Arsitektur berkembang inkremental tanpa migrasi destruktif; setiap sprint melengkapi lapisan sebelumnya.

---

# 4. Domain Review

- **Business Logic di Service** (ADR-004): diterapkan konsisten. Controller hanya memvalidasi, mengotorisasi, dan memanggil service; aturan bisnis berada di layer service.
- **Pemisahan domain:** registrasi, payment, ticket, dan komunikasi dipisah ke service tersendiri (`RegistrationService`, `EventCapacityService`, `PaymentService`, `PaymentVerificationService`, `PaymentProofService`, `TicketService`, `TicketNumberService`, `TicketLifecycleService`, `TicketDocumentService`, `CommunicationDispatcher`, `CommunicationLogService`).
- **State machine:** Payment `pending → waiting_verification → paid → rejected → refund` (ADR-010); Ticket generate/revoke/reissue (ADR-011). Tidak ada status `completed` sesuai keputusan yang disepakati.
- **Domain events:** `PaymentStatusChanged`, `TicketIssued`, `TicketStatusChanged` — dispatch **hanya setelah commit** (`DB::afterCommit`), menjamin transactional consistency.

**Temuan (Observation):** PRD §16.5 masih menyebut `Completed` sedangkan implementasi menghapusnya; PRD §16.8 menggunakan `cancelled` sedangkan implementasi memakai `revoked`. Ini inkonsistensi dokumen (non-runtime); keputusan implementasi sudah disepakati di Implementation Plan (ADR-010/011).

---

# 5. Aggregate Review

- **Order sebagai Aggregate Root** (ADR-001): `Payment` dan `Ticket` merupakan child entity di bawah `Order` (relasi `id_order`).
- **Immutable Snapshot** (ADR-002): `Order` menyimpan snapshot `event_name`, `event_price`, `event_start_at` saat registrasi — order tetap valid meski event asal berubah.
- **Identitas Publik** (ADR-003): setiap entitas punya Internal ID (DB), UUID (API/URL/QR/integrasi), dan nomor administrasi (`PAY-…`, `TKT-…`).
- **Audit columns** (ADR-006): `created_by`, `updated_by` pada entity bisnis baru.

Agregat Order memberikan satu titik masuk untuk Payment/Ticket/Notification, konsisten dengan ADR-001.

---

# 6. Database Review

- **Tabel baru (Sprint 1):** `payments`, `payment_proofs`, `payment_logs`, `tickets`, `ticket_logs`, `communication_logs` — kesesuaian kolom & index dengan PRD §16.5–16.9 / §20.13 **PASS**.
- **Migration additive & idempotent** (ADR-005): re-run → "Nothing to migrate".
- **Queue:** tabel `jobs` (driver `database`) + `failed_jobs`.
- **Sprint 4:** kolom notifikasi pada `communication_logs` (`title`, `message`, `payload`, `read_at`) ditambahkan **additive**.
- **Foreign keys:** keputusan index-only (tanpa FK ketat) sesuai Audit-Gap anomaly — disengaja demi kemudahan migrasi.

**Temuan (Observation):** keputusan "index alih-alih FK" adalah trade-off konsistensi referensial vs fleksibilitas; tetap terkendali karena diputuskan di Audit-Gap.

---

# 7. API Review

- Total route di `routes/api.php`: **71**.
- **Payment (6 route):** `POST /orders/{uuid}/payment`, `GET /payments/{uuid}`, `GET /payments/{uuid}/proof`, `PUT /payments/{uuid}/verify`, `GET /my-payments`, `POST /payments`.
- **Ticket (5 route):** `GET /orders/{uuid}/ticket`, `GET /tickets/{uuid}`, `GET /tickets/{uuid}/download`, `POST /tickets/{uuid}/reissue`, `DELETE /tickets/{uuid}` (revoke).
- **Communication (4 route):** `GET /notifications`, `PUT /notifications/read`, `PATCH /notifications/read-all`, `GET /communication-logs`.
- **Validasi:** 6 kelas `FormRequest` (`CreatePayment`, `VerifyPayment`, `UploadPaymentProof`, `TicketAction`, `NotificationRead`).
- **Auth:** Sanctum + `auth:sanctum`; rate limit (throttle) pada upload.
- **Authorization:** Policy (`PaymentPolicy`, `TicketPolicy`, `CommunicationLogPolicy`) untuk gating admin/staff.

**Temuan (Observation):** `ApiController.php` (1482 baris) masih menampung banyak endpoint lama dan baru (god-controller) — non-blocking, ditangani pada refactor (lihat Technical Debt).

---

# 8. Queue Review

- **Driver:** `QUEUE_CONNECTION=database`, tabel `jobs`, worker kontainer.
- **Worker CLI:** `php artisan queue:work --queue=high,communications,default --sleep=2 --tries=3 --timeout=90 --max-time=3600` (supervised via `restart: unless-stopped`).
- **Job:** `ProcessCommunication` (idempoten, `tries=3`, queue `communications`) — satu-satunya job produksi di Sprint 4.
- **Hasil:** regression membuktikan tabel `jobs` terproses habis oleh worker dan `failed_jobs` = 0.

**Temuan (Minor):** desain queue-first benar untuk komunikasi asinkron; belum ada monitoring job (retention, alerting, UI retry).

---

# 9. Communication Review

- **Dispatcher** (`CommunicationDispatcher`) — Single Communication Gateway (ADR-016); orchestrator hanya, tanpa business logic; **best-effort** (dilindungi `try/catch`, kegagalan tidak memutus domain flow).
- **ChannelResolver** — memilih provider berbasis config `communication.channels`, terpisah dari dispatcher.
- **Template registry** di `config/communication.php`; `TemplateService` membaca tanpa `switch/match`.
- **Listener forward-only** — hanya meneruskan event domain, tanpa business logic.
- **Idempotent job** `ProcessCommunication` — log `DELIVERED`/`FAILED` (final) tidak pernah dikirim ulang.
- **Lifecycle log immutable:** `queued → processing → delivered | failed` (+ `retry_count`, `read_at`); mutasi hanya lewat `CommunicationLogService`.
- **Split enforcement:** Notification Center → owner; Communication Log → admin/staff via `CommunicationLogPolicy`.

**Status:** verifikasi regression **16/16 PASS**.

---

# 10. Security Review

- **Autentikasi:** Sanctum token; gate `auth:sanctum`.
- **Otorisasi:** RBAC `RoleGuard` (staff set; verifier set `finance/ketua/admin`); Policy per resource (Payment, Ticket, CommunicationLog). Regression membuktikan: owner `/communication-logs` → 403, admin → 200, tanpa token → 401.
- **Input validation:** `FormRequest` untuk seluruh alur wajib.
- **Throttle:** upload di-throttle.
- **Data sensitif:** payload template tidak menyimpan secret; password di-hash (bcrypt).
- **Upload proof:** validasi MIME + ukuran file.

**Temuan (Observation):** belum ada rate-limit global di semua endpoint; ini di luar scope v2.1.0-beta1.

---

# 11. Performance Review

- **Query:** penerbitan ticket memakai index (`id_order`, `uuid`, `status`).
- **PDF/QR:** dokumen digenerate **on-demand** (bukan disimpan), QR tidak disisipkan sebagai file — hemat storage.
- **Outstanding:** dihitung per order saat request (`outstanding = total − paid`).
- **Communication:** single write per event + penerima.

**Temuan (Minor):** `communication_logs` tumbuh seiring event — perlu kebijakan retensi/arsip; beberapa list admin belum ber-pagination.

---

# 12. Scalability Review

- Arsitektur **modular service** memungkinkan scale-out (queue dan worker terpisah).
- **Queue-first** mendukung asynchronous — komunikasi massal tidak memblokir HTTP (akan dimanfaatkan penuh di Sprint 5).
- DB single-host MariaDB saat ini — sesuai skala organisasi; migrasi ke cluster/Redis dapat dilakukan tanpa perubahan struktur aplikasi.

**Kesimpulan:** arsitektur mampu menampung pertumbuhan skala organisasi (ribuan anggota) tanpa perubahan struktural; naik skala transaksi ditunda ke tahap berikutnya (bukan blocker).

---

# 13. Technical Debt

| Kategori | ID | Deskripsi |
|----------|----|-----------|
| **Minor** | TD-01 | `ApiController.php` 1482 baris, `HomeViews.php` 843, `C_Anggota` 490 — god-controller, drift vs ADR-004. |
| **Minor** | TD-02 | Belum ada automated test suite yang di-commit; regression hanya lewat harness eksperimen di Verification Report. |
| **Minor** | TD-03 | ADR-011 dan ADR-017 belum di-sync ke `docs/ADR.md` (masih Planned). |
| **Minor** | TD-04 | Job failure belum dimonitor (belum ada alerting/UI retry management). |
| **Observation** | TD-05 | `communication_logs` tumbuh tanpa kebijakan retensi/arsip. |
| **Observation** | TD-06 | Inkonsistensi dokumen PRD (`completed`, `revoked` vs `cancelled`, QR payload JSON vs bare-UUID) — non-runtime. |

---

# 14. Positive Findings

1. **Konsistensi agregat Order** menjaga integritas Payment/Ticket/Notification (ADR-001).
2. **`after_commit`** pada seluruh domain event — menjamin transactional consistency, siap untuk integrasi eksternal.
3. **Idempotency merata** (ticket, verifikasi payment, notification job) — mencegah duplikasi pada retry.
4. **Queue-first** untuk komunikasi — tidak memblokir HTTP request di domain flow.
5. **Lifecycle log immutable** dan **split akses** (owner vs staff) memenuhi ADR-016.
6. **Config-driven provider** (ChannelResolver) mendukung penambahan channel tanpa kode baru.
7. **RBAC** via Policy + RoleGuard seragam.
8. **Zero artifacts** di semua regression — data uji tidak meninggalkan jejak.

---

# 15. Remaining Risks

| Level | Risiko |
|-------|--------|
| **Low** | Keterbatasan driver queue `database` pada beban tinggi (Redis belum dipakai). |
| **Low** | Pertumbuhan `communication_logs` tanpa kebijakan retensi. |
| **Low** | `ApiController` god-controller menghambat refactor cepat (debt). |
| **Low** | Provider eksternal (email/WhatsApp) belum diimplementasi (NullProvider). |
| **Low** | Drift PRD/ADR (`revoked` vs `cancelled`, `completed`, QR payload). |
| **Low** | Belum ada otomasi test suite di repo (regression manual). |

Tidak ada risiko berdampak produksi untuk lingkup v2.1.0-beta1.

---

# 16. Recommendation

1. **PRIORITAS:** bangun automated test suite (feature test): verifikasi payment, penerbitan ticket, dispatch komunikasi + idempotency — mengotomasi regression yang saat ini manual.
2. **Refactor** `ApiController` dan controller admin menjadi service/controller kecil mengikuti ADR-004 (prioritas pada sprint maintenance).
3. **Sync** ADR-011 & ADR-017 ke `docs/ADR.md` dan selaraskan istilah PRD (`revoked`, tanpa `completed`, QR bare-UUID).
4. **Monitoring queue:** tambahkan UI/list untuk `failed_jobs` + retry, serta alerting saat job gagal.
5. **Retensi log:** kebijakan arsip/purge untuk `communication_logs`.
6. **Sprint 5:** sambungkan provider nyata (Email/WhatsApp) via `CommunicationDispatcher` yang sudah siap.

---

# 17. Architecture Score

Skala 0–100 per area; bobot seimbang.

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

> Skor **81/100** = arsitektur solid, siap untuk produksi beta, dengan sejumlah technical debt non-blocking.

---

# 18. Production Readiness

**Production Ready untuk lingkup v2.1.0-beta1:** ✅

- Seluruh milestone (Sprint 1 → 4) **PASS** dan terverifikasi di production (`mzt-deploy` build #26, #30, #33, #37, #41).
- Semua gate arsitektur tuntas: instan Payment→Ticket, `after_commit`, queue.
- Regression: **27** (Sprint 3), **36** (Sprint 3.5), **16** (Sprint 4) — semua PASS dengan zero artifacts.
- Keamanan dasar (RBAC, validasi input, upload, throttle) terkunci.
- Sistem siap produksi untuk **Payment + Ticket + Notification internal (in-app)**.
- Provider eksternal (email/WhatsApp) belum aktif → batas produksi adalah komunikasi **in-app** saja.

---

# 19. Final Verdict

**PASS** ✅ — **Phase 2B (v2.1.0-beta1) dinyatakan Production Ready** untuk Payment Engine, Ticket Engine, dan Communication (in-app).

**Lingkup produksi:** Payment, Ticket, Notification Center, Communication Log (internal).  
**Lingkup lanjutan (belum produksi):** provider Email/WhatsApp, Dashboard Finance, Broadcast/Reminder, QR Check-In/Attendance — dijadwalkan Sprint 5 ke depan.

Arsitektur menjadi fondasi untuk sprint berikutnya **tanpa perubahan struktural besar**.

---

*Dokumen ini merupakan bagian dari Phase 2B Closure. Fakta bersumber dari implementasi aktual dan Sprint 1–4 Verification Report. Tidak ada kode aplikasi, PRD, maupun ADR yang diubah.*