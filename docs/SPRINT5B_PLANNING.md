# Sprint 5B — Implementation Plan

---

# 1. Metadata

| Item | Nilai |
|------|-------|
| **Sprint** | Sprint 5B — Analytics, Ticket/Operational Monitoring & CSV Export |
| **Version Dokumen** | v1.0 |
| **Status** | **Planned** |
| **Parent Milestone** | v2.1.0-rc1 (Business Layer) — rilis penuh belum diputuskan |
| **Source of Truth** | SPRINT5A_PLANNING.md, SPRINT5_ARCHITECTURE_REVIEW.md, SPRINT5A_ARCHITECTURE_REVIEW.md, SPRINT5A_VERIFICATION_REPORT.md, SPRINT5A_RELEASE_NOTES.md, SPRINT5A_IMPLEMENTATION_SUMMARY.md, ADR-001 s.d. ADR-017, PRD EMS & PRD Payment/Ticket Engine, implementasi aktual kedua repo |
| **Last Updated** | 09 Agustus 2026 |
| **Author** | MZT Core Team |

---

# 2. Background

Sprint 5A (**Finance Dashboard Foundation**) telah **PASS dan CLOSED**:

- Architecture Review Sprint 5A: **92/100**.
- Backend commit `ca1600d`, Frontend commit `a777678`.
- Jenkins `mzt-deploy` #42 **SUCCESS**.
- Regression: 5 tests / 22 assertions PASS; performance total kumulatif 27,1 ms.
- Fondasi read-model sudah established: `DashboardQuery` (Query Layer) → `DashboardService` (DTO) → `DashboardPolicy` + RoleGuard → Resources → JSON.

Sprint 5A menyelesaikan **4 endpoint finance**: `overview`, `registration`, `revenue`, `payments`. Yang tersisa dari perencanaan Sprint 5 (lihat `SPRINT5_PLANNING.md` §5 & §8) adalah: **Ticket Monitoring**, **Operational Dashboard lintas entitas**, **Export Report**, dan **Analytics dasar**.

Sprint 5B melengkapi kekosongan tersebut di atas fondasi yang sama, **read-only**, tanpa mengubah domain transaksional Phase 2B.

---

# 3. Problem Statement

Setelah Dashboard Foundation tersedia, kondisi operasional belum sepenuhnya ter-cover:

1. **Tidak ada visibilitas tiket** — belum ada ringkasan tiket terbit/dipakai/dibatalkan (perlu konsisten dengan ADR-011, tanpa mapping baru).
2. **Tidak ada operasional lintas entitas** — belum ada summary yang menyatukan Orders / Payments / Tickets / verifikasi dalam satu pandangan.
3. **Tidak ada analitik dasar** — tren registrasi, konversi pembayaran/tiket, distribusi metode & event belum tersedia untuk pengambilan keputusan.
4. **Tidak ada ekspor laporan** — panitia masih harus mengolah data manual; belum ada output file (CSV) untuk laporan pertanggungjawaban.

Masalah-masalah ini adalah **backlog sah** yang sudah direncanakan pada `SPRINT5_PLANNING.md`, bukan scope baru.

---

# 4. Business Goals

1. **Visibilitas tiket:** panitia dapat membaca posisi tiket (terbit/dipakai/dibatalkan) secara cepat dan konsisten dengan state machine yang berlaku (ADR-011).
2. **Monitoring operasional:** satu pandangan ringkas lintas entitas (orders, pembayaran, verifikasi, tiket).
3. **Pengambilan keputusan:** analitik dasar (tren, konversi, distribusi) untuk keputusan event berikutnya.
4. **Efisiensi pelaporan:** ekspor CSV terstruktur untuk laporan pertanggungjawaban tanpa ketergantungan format baru.

Sprint 5B bersifat **baca** — tidak mengubah alur transaksi, tidak menambah migration tanpa verifikasi.

---

# 5. Objectives

1. Menyediakan **Ticket Monitoring** (terbit / dipakai / dibatalkan) mengikuti ADR-011.
2. Menyediakan **Operational Summary** lintas entitas.
3. Menyediakan **Analytics dasar** terbatas pada 5 dimensi:
   - Registration Trend
   - Payment Conversion
   - Ticket Conversion
   - Payment Method Distribution
   - Event Distribution
4. Menyediakan **CSV Export** untuk laporan (format CSV saja; **bukan XLSX**).
5. Menerapkan pola arsitektur yang sama seperti Sprint 5A untuk seluruh area baru.

---

# 6. Scope

### In Scope

| Area | Detail |
|------|--------|
| Ticket Monitoring | Ringkasan tiket per status sesuai ADR-011 |
| Operational Summary | Ringkasan lintas entitas (orders/payments/tickets/verifikasi) |
| Analytics | Registration Trend, Payment Conversion, Ticket Conversion, Payment Method Distribution, Event Distribution |
| CSV Export | Ekspor data finance/registrasi dalam format CSV via file stream |
| Frontend | Halaman/lajur baru: Tickets, Analytics, dan tombol Export CSV dengan state loading/empty/error/success |

### Out of Scope

Berikut **tidak** dikerjakan di Sprint 5B (per `SPRINT5_PLANNING.md` §6):

- Email / WhatsApp / Push Notification / Broadcast / Reminder / Campaign (Sprint 6)
- Attendance Check-In / QR Gate / Mobile App (Sprint 7)
- AI Feature
- **XLSX/Excel export** (keputusan: CSV saja)
- Agregasi/snapshot DB berbayar
- Caching agregat (ditunda ke Sprint 5D)

---

# 7. Functional Requirements

Seluruh fitur read-only dan acceptance-oriented.

### FR-01 — Ticket Monitoring
- Sistem menyediakan ringkasan jumlah tiket **per status**.
- Status mengikuti **canonical state machine ADR-011** (`draft`, `issued`, `checked_in`, `finished`, `cancelled`, `revoked` — ADR-011 telah di-amend menjadi **Accepted** pada Architecture Gate Closure Sprint 5B). **Tidak boleh dibuat mapping status baru** tanpa keputusan ADR.
- Filter periode (start/end) opsional.

### FR-02 — Operational Summary
- Sistem menyediakan ringkasan lintas entitas: total orders, total pembayaran lunas, outstanding, menunggu verifikasi, total tiket.
- Semua angka berasal dari Query Layer; tanpa N+1.

### FR-03 — Analytics (5 dimensi saja)
- **Registration Trend:** jumlah order per rentang waktu (default period, dapat difilter).
- **Payment Conversion:** rasio per status pembayaran terhadap total (mis. % paid, % waiting_verification, % pending, % rejected, % refund — mengikuti ADR-010).
- **Ticket Conversion:** rasio tiket per status terhadap total tiket (ADR-011).
- **Payment Method Distribution:** distribusi pembayaran per metode.
- **Event Distribution:** distribusi order/registrasi per event.

### FR-04 — CSV Export
- Sistem menghasilkan file **CSV** dari data yang sedang dilihat (finance/registrasi).
- Output berupa **file stream** (bukan JSON Resource).
- Header kolom konsisten dan ditentukan eksplisit.
- Bekerja pada dataset besar dengan **streaming/chunking** (hindari build array raksasa di memori).

### FR-05 — Frontend
- Menampilkan data ticket/operational/analytics dengan **state loading / empty / error / success**.
- Tombol Export CSV dengan penanganan loading/error.
- Role gating mengikuti Role Matrix (lihat §13).

---

# 8. Architecture

Menggunakan **pola yang terbukti di Sprint 5A** untuk seluruh area baru:

```
Controller
  → Service Interface
    → Service
      → Query Layer
        → DTO
          → Resource
            → JSON
```

- **Query Layer** (`app/Queries/...`): satu-satunya tempat akses baca agregat; return array scalar, tanpa side-effect.
- **Service**: return DTO murni; tanpa business logic di Controller (ADR-004).
- **Resource**: murni DTO → JSON.
- **Policy**: method spesifik (ADR-004 / pola Sprint 5A).

**Khusus CSV Export** — alur berbeda pada terminal output:

```
Controller
  → Service Interface
    → Service
      → Query Layer
        → DTO / Rows (plain)
          → CSV Streamer (file stream, bukan JSON Resource)
            → HTTP response (Content-Type: text/csv)
```

Alasan: output ekspor adalah **file stream**, bukan JSON Resource. Seluruh bagian hulu (Query → Service → DTO) tetap memakai pola yang sama; hanya serialisasi akhir yang berbeda. Ini konsisten dengan aturan bahwa Resource hanya menerjemahkan DTO→JSON — untuk export tidak ada Resource JSON, melainkan streamer CSV.

---

# 9. Backend Design

Kandidat komponen (hanya desain, **bukan implementasi**):

| Layer | Kandidat | Keterangan |
|-------|----------|------------|
| Contract | `DashboardServiceInterface` (diperluas) | Tambah method untuk ticket/operational/analytics/export |
| Service | `DashboardService` (diperluas) | Implementasi method baru, return DTO |
| Query | `DashboardQuery` (diperluas) atau `TicketQuery`/`AnalyticsQuery` | Query agregat tiket & analytics; tetap single-pass |
| DTO | `TicketSummary`, `OperationalSummary`, `AnalyticsSummary` (sub-DTO: RegistrationTrend, PaymentConversion, TicketConversion, MethodDistribution, EventDistribution), `ExportRow` (plain) | Kontrak ter-tipe |
| Resource | `TicketSummaryResource`, `OperationalSummaryResource`, `AnalyticsSummaryResource` | DTO → JSON |
| Policy | `DashboardPolicy` (diperluas) | Method spesifik per area baru |
| Controller | `DashboardController` (diperluas) atau controller terpisah | Mapping → service → resource; untuk export → stream CSV |
| Export | `CsvExporter` / `DashboardExportService` (strategi file stream) | Serialisasi CSV via stream/chunking |

Endpoint usulan: lihat §11.

---

# 10. Frontend Design

Kandidat (desain, bukan implementasi):

| Area | Desain |
|------|--------|
| Route/Page | Perluasan halaman Finance atau halaman baru (`/dashboard/finance/tickets`, `/dashboard/finance/analytics`) |
| API service | `src/services/mzt-api.ts` — tambah fungsi fetch untuk tickets/analytics/export |
| queryOptions | `src/services/queries.ts` — tambah queryKeys + queryOptions untuk area baru |
| Component | Kartu KPI / tabel status tiket; chart analytics (trend, distribusi, konversi) |
| Role gating | Sesuai Role Matrix §13 (ticket/operational: staff; analytics: verifier) |
| State handling | loading / empty / error / success pada tiap blok data |
| Export | Tombol download CSV; state loading/error/success |

---

# 11. API Contract

### EXISTING — digunakan kembali (dari Sprint 5A)

| Method | Path | Fungsi |
|--------|------|--------|
| GET | `/api/dashboard/finance/overview` | KPI ringkasan (existing) |
| GET | `/api/dashboard/finance/registration` | Ringkasan registrasi (existing) |
| GET | `/api/dashboard/finance/revenue` | Ringkasan pendapatan (existing) |
| GET | `/api/dashboard/finance/payments` | Ringkasan pembayaran (existing) |

### PROPOSED — baru di Sprint 5B (belum ada)

| Method | Path | Fungsi | Gate |
|--------|------|--------|------|
| GET | `/api/dashboard/finance/tickets` | Ticket monitoring per status (ADR-011) | `viewTickets` (staff) |
| GET | `/api/dashboard/finance/analytics` | Analytics 5 dimensi (trend/conversion/distribution) | `viewAnalytics` (verifier) |
| GET | `/api/dashboard/finance/export` | CSV export (file stream) | `viewExport` (verifier) |

Catatan authorization: CSV Export berisi data finansial → **hanya verifier** (`RoleGuard::canVerify`), konsisten dengan `viewAnalytics` (lihat §13).

Catatan: **endpoint PROPOSED belum ada** — tidak diklaim sebagai endpoint yang sudah hidup.

---

# 12. Database Impact

**Keputusan awal: tidak diasumsikan ada migration.**

- Sprint 5A berjalan tanpa migration baru; struktur `orders`/`payments`/`tickets` + index existing mendukung agregasi read-only (lihat `SPRINT5_PLANNING.md` §12).
- Sprint 5B **tidak menentukan kebutuhan migration/index terlebih dahulu**. Kebutuhan ini **diverifikasi pada Architecture Review Sprint 5B** dengan evidence (rencana query + estimasi volume).
- Jika Architecture Review menemukan kebutuhan index/migration, hanya **additive** dan **backward-compatible** (ADR-005, ADR-013), serta **diputuskan melalui change control** (lihat §25).
- Tidak ada perubahan struktur Order/Payment/Ticket.

---

# 13. Authorization & Security

Menggunakan **RoleGuard + Policy yang sudah ada** (konsisten Sprint 5A & `SPRINT5_PLANNING.md` §13).

Role staff: `dashboard` / `event` / `finance` / `ketua` / `admin`. Verifier: `finance` / `ketua` / `admin`.

**Role Matrix:**

| Area / Data | Alumni | Staff biasa (dashboard/event) | Finance | Ketua | Admin |
|-------------|--------|-------------------------------|---------|-------|-------|
| Ticket Monitoring | ❌ | ✅ | ✅ | ✅ | ✅ |
| Operational Summary | ❌ | ✅ | ✅ | ✅ | ✅ |
| Analytics | ❌ | ❌ | ✅ | ✅ | ✅ |
| CSV Export | ❌ | ❌ | ✅ | ✅ | ✅ |

- Semua endpoint: `auth:sanctum` + Policy method spesifik.
- Data finansial/analytics hanya untuk role yang berhak; selainnya **403**.
- Export terbatas pada data yang boleh dilihat role tersebut.

**CSV Export — canonical authorization (Architecture Gate Closure MAJ-02):**

```
viewExport(User $user) → RoleGuard::canVerify($user)
```

CSV Export dapat mengandung data finansial sehingga **hanya role verifier**
(`finance`/`ketua`/`admin`) yang boleh mengaksesnya. Matriks respons eksplisit:

| Role / Kondisi | `GET /api/dashboard/finance/export` |
|----------------|--------------------------------------|
| Alumni | **403** |
| Staff biasa (`dashboard` / `event`) | **403** |
| Finance | **200** |
| Ketua | **200** |
| Admin | **200** |
| Unauthenticated | **401** |

---

# 14. Performance Requirements

| Komponen | Target |
|----------|--------|
| Ticket Monitoring | **< 500 ms** |
| Operational Summary | **< 500 ms** |
| Analytics | **< 500 ms** |
| CSV Export | **< 10 detik** |

Pendekatan:
- Agregasi via Query Layer (COUNT/SUM/groupBy) — anti-N+1.
- Analytics dengan default period untuk membatasi rentang scan.
- Export menggunakan **streaming/chunking** agar tidak membebani memori.
- Semua target diverifikasi lewat performance/regression test (lihat §19).

---

# 15. Observability & Audit

- Seluruh endpoint read-only; tidak ada mutasi, sehingga **tidak ada audit trail mutasi baru**.
- Logging permintaan agregasi/export diperlukan untuk pemantauan beban (khususnya export CSV agar tidak menumpuk).
- Nominal tetap IDR, tanggal konsisten, UI Bahasa Indonesia (konsisten NFR Sprint 5).

---

# 16. Risks & Mitigations

| Risiko | Level | Mitigasi |
|--------|-------|----------|
| Query cost analytics/trend pada volume besar | Medium | Query Layer + default period + agregasi DB single-pass |
| Data volume besar saat export | Medium | Streaming/chunking; performance gate export < 10 dtk |
| Controller bloat / god-controller `ApiController` | High | Controller terpisah/tipis; jangan tambah method di `ApiController` |
| Authorization salah gate (analytics/export) | Medium | Policy method spesifik + regression test 403 |
| Export/report workload | Medium | CSV stream; timeout & size handling |
| Regression terhadap dashboard existing (Sprint 5A) | Medium | Regression suite diperluas; endpoint existing tidak diubah |

---

# 17. Technical Debt

Menghubungkan dengan debt yang sudah tercatat:

- **Backend:** `tests/Feature/ExampleTest.php` — failure **pre-existing** (expect 500 vs 200 terhadap homepage pada DB test kosong). **Known Limitation non-blocking**; tetap.
- **Frontend:** error TypeScript **pre-existing** di beberapa file di luar file Sprint 5A (`account-dialog.tsx`, `home-news.tsx`, `content/index.tsx`). **Known Limitation non-blocking**; tetap.
- **Aturan:** regression/error **BARU** akibat Sprint 5B **wajib diperbaiki** sebelum closure.
- Tidak ada debt baru yang diciptakan tanpa evidence.

---

# 18. Acceptance Criteria

Checklist yang dapat diuji:

- [ ] `/api/dashboard/finance/tickets` tersedia, read-only, format `{success, data}`, status mengikuti ADR-011 canonical (`draft/issued/checked_in/finished/cancelled/revoked`) **tanpa mapping status baru**.
- [ ] `/api/dashboard/finance/analytics` tersedia, read-only, mencakup 5 dimensi analytics.
- [ ] `/api/dashboard/finance/export` mengembalikan **CSV file stream** (Content-Type `text/csv`), bukan JSON.
- [ ] Role gate diterapkan: analytics → verifier (403 untuk non-verifier); ticket/operational → staff.
- [ ] **MAJ-01 (ADR-011):** ADR-011 berstatus **Accepted** dengan canonical state, valid transition, terminal state, dan definisi cancelled vs revoked terdokumentasi (di-amend pada Architecture Gate Closure).
- [ ] **MAJ-02 (export):** `viewExport` = `RoleGuard::canVerify`; **CSV Export hanya untuk verifier**.
- [ ] Data kosong tidak menyebabkan error (nilai kosong/0).
- [ ] Tidak ada mutasi terhadap Order/Payment/Ticket dari endpoint baru.
- [ ] Tidak ada migration baru tanpa keputusan Architecture Review + change control.
- [ ] Regression suite Sprint 5B lulus (termasuk 403 & empty dataset).
- [ ] **Regression authorization export:** unauthenticated → **401**; Alumni & staff biasa (`dashboard`/`event`) → **403**; Finance/Ketua/Admin → **200**.
- [ ] Tidak ada error TypeScript/regression **baru** yang tersisa; debt pre-existing tetap non-blocking.

---

# 19. Performance Gate

Threshold konkret, terverifikasi dalam regression/performance test:

| Endpoint/Area | Threshold |
|---------------|-----------|
| Ticket Monitoring | **< 500 ms** |
| Operational Summary | **< 500 ms** |
| Analytics | **< 500 ms** |
| CSV Export | **< 10 detik** |

- Setiap threshold diukur pada dataset regression (Large Dataset sesuai pola Sprint 5A: 500×3 atau yang ditentukan pada Architecture Review).
- Gate gagal → blokir closure sampai dioptimalkan.

---

# 20. Exit Criteria

Sprint 5B dianggap selesai hanya jika **seluruhnya** terpenuhi:

- [ ] Implementation complete (backend + frontend + export CSV).
- [ ] Architecture Review Sprint 5B **PASS**.
- [ ] Regression **PASS** (empty + large dataset; 403; zero regresi Sprint 5A).
- [ ] Performance Gate **PASS** (<500 ms / <10 dtk sesuai §19).
- [ ] Security/Authorization **PASS** (Role Matrix §13 diverifikasi).
- [ ] Deployment **PASS** (Jenkins build SUCCESS, health check).
- [ ] Verification report dibuat (`SPRINT5B_VERIFICATION_REPORT.md`).
- [ ] Closure documentation dibuat (Architecture Review / Release Notes / Implementation Summary).
- [ ] Tidak ada migration tanpa keputusan Architecture Review + change control.

---

# 21. Dependencies

- **Sprint 5A** — `DashboardFilter`, `DashboardServiceInterface`, `DashboardQuery`, `DashboardPolicy`, binding di AppServiceProvider; endpoint finance existing.
- **Payment Engine (Phase 2B)** — status pembayaran (ADR-010) untuk conversion & distribution.
- **Ticket Engine (Phase 2B)** — status tiket (ADR-011) untuk ticket monitoring & conversion.
- **Communication Engine** — tidak dipakai di Sprint 5B (read-only, tanpa trigger komunikasi).
- **Existing Dashboard API** — endpoint EXISTING digunakan kembali; tidak diubah.
- **Frontend libraries** — `@tanstack/react-query`, chart library yang sudah ada di project.
- **PRD/ADR** — ADR-001 (Order aggregate root), ADR-004 (Service Layer), ADR-005 (Evolution First), ADR-010/011 (state machines), ADR-013 (backward compat), ADR-014 (production safety).

---

# 22. Non-Goals

Fitur yang **sengaja tidak** masuk Sprint 5B:

- XLSX/Excel export (CSV saja).
- Email/WhatsApp/broadcast/reminder/campaign.
- Attendance/QR gate/mobile app/AI.
- Caching agregat (Sprint 5D).
- Agregasi/snapshot DB.
- Perubahan state machine tiket atau payment (tanpa ADR baru).
- Perbaikan wajib debt pre-existing (tetap non-blocking).

---

# 23. Sprint Roadmap

```
Sprint 5  — Business Layer
             Finance · Reporting · Analytics · Operational Monitoring
        |
        ├─ Sprint 5A  Finance Dashboard Foundation        [CLOSED]
        ├─ Sprint 5B  Analytics, Ticket/Operational & CSV  [PLANNED]
        ├─ Sprint 5C  (lanjutan reporting/analytics sesuai review)
        └─ Sprint 5D  Caching / optimasi (bila perlu)
        |
        v
Sprint 6  — External Communication
             Email / WhatsApp Provider · Broadcast · Reminder
        |
        v
Sprint 7  — Check-In & Attendance
             QR Gate · Mobile App · AI
```

---

# 24. Implementation Sequence

```
Planning (dokumen ini)
  → Architecture Review (PASS gate)
  → Backend (Query → DTO → Service → Policy → Resource → Controller → routes)
  → Frontend (types → api → queries → components → route/page)
  → Self Review
  → Regression
  → Performance
  → Deployment (Jenkins)
  → Verification (SPRINT5B_VERIFICATION_REPORT.md)
  → Closure (Architecture Review / Release Notes / Implementation Summary)
```

---

# 25. Governance / Change Control

Jika implementasi menemukan kebutuhan perubahan PRD/ADR:

1. **STOP** fitur terkait.
2. **Jangan mengubah** dokumen arsitektur (PRD/ADR) secara diam-diam.
3. Buat **change proposal / ADR amendment** terlebih dahulu.
4. Fitur dilanjutkan hanya setelah perubahan disetujui.

Kebutuhan migration/index (jika muncul) juga melalui jalur ini: dibahas pada Architecture Review, dan hanya diterima jika **additive & backward-compatible** (ADR-005, ADR-013).

---

# Self Review

- Struktur sesuai template 25 bab.
- Seluruh keputusan 7 adjustment diterapkan (endpoint, CSV-only, debt non-blocking, ADR-011 state machine, database verified di Arch Review, analytics 5 dimensi, performance gate).
- Konsisten dengan Sprint 5A (pola arsitektur, commit `ca1600d`/`a777678`, performance 27,1 ms, Jenkins #42).
- Endpoint PROPOSED ditandai jelas; tidak diklaim sudah ada.
- Tidak ada scope creep; Sprint 5B tetap read-only.
- Tidak menghasilkan kode.

# Amendment — Architecture Gate Closure (MAJ-01 & MAJ-02)

Di-amend setelah Architecture Review Sprint 5B (verdict **APPROVED WITH CONDITIONS**) untuk menuntaskan dua kondisi blocking:

- **MAJ-01 — canonical ticket state:** FR-01, §6, §7, §18 kini mengacu pada **ADR-011 yang di-amend ke Accepted** (canonical `draft/issued/checked_in/finished/cancelled/revoked`). Tidak ada status mapping baru.
- **MAJ-02 — CSV export authorization:** §11 & §13 kini menetapkan `viewExport` = `RoleGuard::canVerify` (**verifier-only**); matriks respons eksplisit **401 / 403 / 200** ditambahkan di §13, regression requirement di §18 & §19.

Tidak ada perubahan kode, migration, PRD, maupun scope lain selama amendment ini.

---

*Dokumen ini merupakan perencanaan Sprint 5B; implementasi dimulai hanya setelah dokumen disetujui dan Architecture Review Sprint 5B PASS. Tidak ada kode aplikasi, PRD, maupun ADR yang diubah dalam proses penulisan.*
