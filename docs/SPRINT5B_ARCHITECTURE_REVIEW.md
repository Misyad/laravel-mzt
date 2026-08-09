# MZT Apps — Sprint 5B Architecture Review

**Project** : Maziltutholiban Members Platform (MZT Apps)
**Sprint** : Sprint 5B — Analytics, Ticket/Operational Monitoring & CSV Export
**Status** : **APPROVED WITH CONDITIONS** — verdict awal dipertahankan; kondisi blocking telah ditutup (lihat §26 Architecture Gate Closure)
**Tanggal Review** : 09 Agustus 2026
**Dokumen yang direview** : `docs/SPRINT5B_PLANNING.md` (v1.0, 25 bab)
**Metode** : Static review planning vs PRD, ADR, arsitektur Sprint 5A (implementasi aktual), dan source of truth
**Acuan** : SPRINT5B_PLANNING.md, SPRINT5A_ARCHITECTURE_REVIEW.md, SPRINT5A_VERIFICATION_REPORT.md, SPRINT5A_IMPLEMENTATION_SUMMARY.md, SPRINT5_PLANNING.md, ADR-001 s.d. ADR-017, PRD EMS, PRD Payment & Ticket Engine, implementasi aktual `Misyad/laravel-mzt` (main)

---

# 1. Executive Summary

Review ini menilai rencana **Sprint 5B** terhadap PRD, ADR, dan pola arsitektur
yang sudah terbukti pada Sprint 5A (Controller → Service Interface → Service →
Query Layer → DTO → Resource → JSON). Sprint 5B melanjutkan backlog sah Sprint 5:
Ticket Monitoring, Operational Summary, Analytics (5 dimensi), dan CSV Export.

Secara keseluruhan rencana **kuat dan konsisten**: read-only murni, memakai pola
layering Sprint 5A yang sudah diverifikasi (27,1 ms, 5 tests / 22 assertions,
Jenkins #42 SUCCESS), endpoint PROPOSED ditandai jelas, tanpa migration yang
diasumsikan, dan performance gate konkret (< 500 ms; export < 10 detik).

Namun ditemukan **dua temuan Major** yang **wajib diselesaikan sebelum
implementasi**:

1. **Konflik state machine tiket** antara ADR-011, PRD, dan implementasi aktual
   (`app/Enums/TicketStatus.php`) — FR-01 merujuk ADR-011 yang berisi
   `not_generated/generated/used/cancelled`, padahal sistem aktual menggunakan
   `draft/issued/checked_in/finished/cancelled/revoked`.
2. **Ambiguitas otorisasi CSV Export** — Role Matrix memberikan Export ke seluruh
   staff, sementara Analytics (data finansial) dibatasi verifier; berisiko
   kebocoran data finansial via CSV oleh non-verifier.

Tidak ada temuan **Critical**. Dengan menyelesaikan kedua kondisi Major tersebut,
rencana layak diimplementasikan.

---

# 2. Architecture Score

Hasil penilaian review **Sprint 5B Planning** (skala 0–10 per area; bobot
seimbang). Skor ini adalah hasil review, berdasarkan evidence dari source of truth.

| Area | Skor (0–10) |
|------|-------------|
| Scope Compliance | 9 |
| Domain & Aggregate Compliance | 8 |
| Layer Architecture (Controller→Service→Query→DTO→Resource) | 9 |
| Ticket Monitoring Architecture | 6 |
| Analytics Architecture | 8 |
| CSV Export Architecture | 8 |
| API Contract | 9 |
| Database Impact | 8 |
| Authorization & Security | 7 |
| Performance & Scalability | 9 |
| Observability & Audit | 7 |
| Frontend Architecture | 8 |
| Risks, Tech Debt, Acceptance/Exit | 8 |
| **Total** | **104 / 130 → 80 / 100** |

> Skor **80/100** = rencana matang dan layak, dengan catatan dua temuan Major
> yang harus dituntaskan. (Bandingkan: Architecture Review planning Sprint 5 =
> 87/100; Architecture Review implementasi Sprint 5A = 92/100.)

---

# 3. Scope Compliance

| Area | Status |
|------|--------|
| Ticket Monitoring (ADR-011) | ⚠️ ada konflik state machine (lihat §6, §22) |
| Operational Summary | ✅ |
| Analytics 5 dimensi | ✅ |
| CSV Export (CSV saja) | ✅ |
| Frontend (page, query, export, role gating) | ✅ |

- Scope Sprint 5B = backlog sah `SPRINT5_PLANNING.md` §5 & §8, tanpa fitur baru di luar rencana Sprint 5.
- Out of Scope tegas (XLSX, komunikasi eksternal, attendance, caching) — **tidak ada scope creep**.

**Verdict scope:** tepat, kecuali penegasan canonical ticket state yang perlu diluruskan.

---

# 4. Domain & Aggregate Compliance

- Seluruh fitur Sprint 5B **read-only** — tidak ada mutasi ke Order/Payment/Ticket.
- **ADR-001 (Order as Aggregate Root)** dihormati: membaca via `orders.id_event`,
  tidak menulis ke entitas child.
- **ADR-002 (Immutable Snapshot)** didukung: `event_name`/`event_price` pada
  orders mendukung Event Distribution tanpa join berat.
- **ADR-010 (Payment lifecycle)** konsisten: enum `PaymentStatus`
  (`pending/waiting_verification/paid/rejected/refund`) cocok dengan FR-03.
- **ADR-011 (Ticket lifecycle)** — **KONFLIK** (detail §22).

**Verdict:** tanpa temuan konflik, semua kepatuhan domain terpenuhi.

---

# 5. Controller → Service → Query → DTO → Resource Architecture

Pola yang direncanakan identik dengan pola Sprint 5A yang sudah terbukti
(lihat implementasi aktual `DashboardController`, `DashboardService`,
`DashboardQuery`, `DashboardFilter`, Resources):

- Controller tipis: mapping Request→DTO, authorize via Gate, panggil interface, return Resource. ✅
- Service return DTO murni; tidak bergantung pada service side-effect (PaymentService/TicketService/CommunicationDispatcher). ✅
- Query Layer adalah satu-satunya akses baca agregat; return scalar array; anti-N+1. ✅
- Resource murni DTO→JSON. ✅

**Catatan tambahan (Observation):** `DashboardFilter` (Sprint 5A) memiliki
`eventId` dan `status`, tetapi `DashboardQuery` Sprint 5A saat ini **hanya
memakai `start`/`end`** (mengabaikan `eventId`/`status`). Sprint 5B (Event
Distribution, filter status) wajib memastikan query benar-benar mengonsumsi
filter tersebut. Non-blocking, tetapi perlu regression test khusus filter.

---

# 6. Ticket Monitoring Architecture

FR-01 merencanakan ringkasan tiket **per status mengikuti ADR-011**
(`not_generated/generated/used/cancelled`) dan melarang pembuatan mapping baru.

**Temuan (Major):**
- ADR-011 (docs frontend) mencantumkan `not_generated → generated → used → cancelled` dan berstatus *Planned*.
- Namun implementasi aktual `app/Enums/TicketStatus.php` = `draft, issued, checked_in, finished, cancelled, revoked` (docblock merujuk PRD §A16.8/§A10.2), dan kolom `tickets.status` default `'issued'`.
- Jika FR-01 digrouping dengan key `not_generated/generated/used/cancelled` sementara data tersimpan sebagai `draft/issued/checked_in/finished/cancelled/revoked`, hasil monitoring akan salah/kosong tanpa mapping — dan mapping baru dilarang FR-01.

Ini **konflik tiga dokumen**: ADR-011 vs PRD vs implementasi aktual. Tidak boleh
diselesaikan diam-diam; butuh keputusan (lihat §22 & §24).

---

# 7. Operational Summary Architecture

- Ringkasan lintas entitas (orders, payments, tickets, verifikasi) — feasible.
- Semua angka via Query Layer agregat (COUNT/SUM/groupBy); tanpa N+1.
- Data tersedia dari kolom existing: `orders.total_amount`, `orders.status_registrasi`,
  `payments.status`, `tickets.status`.

**Verdict:** arsitektur solid; tanpa temuan.

---

# 8. Analytics Architecture

5 dimensi dibatasi dengan jelas: Registration Trend, Payment Conversion, Ticket
Conversion, Payment Method Distribution, Event Distribution.

- Registration Trend: `orders.created_at` grouping — feasible, perlu index `created_at` (sudah ada).
- Payment Conversion: `payments.status` — konsisten ADR-010.
- Ticket Conversion: bergantung resolusi §6 (state machine).
- Payment Method Distribution: `payments.method` (terindex di migration).
- Event Distribution: `orders.id_event` (terindex) + snapshot `event_name` (ADR-002).

**Verdict:** feasible dengan struktur existing. Dependensi pada resolusi ticket state.

---

# 9. CSV Export Architecture

- **CSV saja, bukan XLSX** — keputusan resmi dihormati.
- Output **file stream** (`Content-Type: text/csv`), bukan JSON Resource — alur
  terminal berbeda dijelaskan dengan alasan yang valid.
- **Streaming/chunking** direncanakan untuk dataset besar — sesuai.
- Bagian hulu (Query → Service → DTO) tetap memakai pola Sprint 5A.

**Catatan (Minor):** detail konkret belum ditentukan — ukuran chunk, penanganan
abort/timeout klien, dan konsistensi header kolom. Perlu diselesaikan saat
implementasi (bukan blocker arsitektur).

---

# 10. API Contract

- **EXISTING** (4 endpoint Sprint 5A) diidentifikasi dan tidak diubah. ✅
- **PROPOSED** (3 endpoint baru) ditandai eksplisit "belum ada". ✅
- Gate terpasang: `viewTickets` (staff), `viewAnalytics` (verifier), `viewExport` (staff/verifier).

**Catatan (Minor):** nama ability policy (`viewTickets`, `viewAnalytics`,
`viewExport`) belum terdefinisi di `DashboardPolicy` aktual (hanya
`viewOverview/viewRevenue/viewPayment`). Ini wajar (belum diimplementasi), hanya
perlu dipastikan konsisten saat implementasi.

---

# 11. Database Impact

- **Tidak ada migration yang diasumsikan** — konsisten dengan `SPRINT5_PLANNING.md` §12.
- Kebutuhan migration/index **diverifikasi pada Architecture Review** — baik.
- Evidence index existing:
  - `orders`: index `id_event`, `status_registrasi`, `nomor_order`; kolom `event_name`, `event_price`, `total_amount`.
  - `payments`: index `id_order`, `status`, `method`, `paid_at`, `verified_at`.
  - `tickets`: index `id_order`, `status`.
- Jika ada kebutuhan tambahan, hanya **additive & backward-compatible** (ADR-005, ADR-013) via change control.

**Verdict:** tidak diperlukan migration untuk scope rencana. Index existing
cukup untuk agregasi yang direncanakan.

---

# 12. Authorization & RBAC

- Memakai RoleGuard + Policy existing (pola Sprint 5A).
- Role staff (`dashboard/event/finance/ketua/admin`) dan verifier (`finance/ketua/admin`) konsisten dengan `RoleGuard::STAFF_ROLES` / `VERIFIER_ROLES`.

**Temuan (Major) — otorisasi Export vs Analytics:**
Role Matrix memberi **CSV Export: Staff ✅**, sementara **Analytics: Staff ❌**.
Jika CSV mencakup kolom finansial (revenue/outstanding) dan staff non-verifier
(`dashboard`/`event`) dapat mengekspor, hal ini **mengalahkan pembatasan
analytics** dan berisiko kebocoran data finansial. Kalimat mitigasi "Export
terbatas pada data yang boleh dilihat role tersebut" perlu dipertegas menjadi
rule eksplisit: **export data finansial hanya untuk verifier**, atau scope
kolom ekspor dibedakan per role.

---

# 13. Security

- Semua endpoint: `auth:sanctum` + Gate policy — konsisten Sprint 5A.
- Data finansial hanya verifier; non-verifier → 403.
- **Risiko tersisa:** kebocoran finansial via CSV (lihat §12). Setelah §12
  dituntaskan, keamanan solid.
- Tidak ada input tulis; risiko injeksi rendah (agregasi via query builder).

---

# 14. Performance

| Gate | Threshold | Status rencana |
|------|-----------|----------------|
| Ticket Monitoring | < 500 ms | ✅ |
| Operational Summary | < 500 ms | ✅ |
| Analytics | < 500 ms | ✅ |
| CSV Export | < 10 detik | ✅ |

- Pendekatan anti-N+1, default period untuk analytics, streaming/chunking untuk export — konsisten dengan bukti Sprint 5A (27,1 ms total).
- Seluruh threshold dapat diverifikasi di regression/performance test.

**Verdict:** gate realistis dan terukur.

---

# 15. Scalability

- Agregasi single-pass di Query Layer; tanpa pembacaan relasi besar.
- Export dengan chunking → memori stabil pada volume besar.
- Tanpa caching (sengaja ditunda ke 5D) — diterima untuk tahap ini.
- Analytics dengan default period membatasi rentang scan.

**Verdict:** memadai untuk skala rencana; opsi optimasi (index/chunk tuning) terbuka tanpa mengubah arsitektur.

---

# 16. Observability & Audit

- Endpoint read-only → tidak ada audit mutasi baru (tepat).
- Logging permintaan agregasi/export direncanakan — baik, terutama untuk mencegah
  penumpukan export.

**Catatan (Minor):** perlu ditetapkan detail logging (level, retensi, batas
konkurensi export) saat implementasi.

---

# 17. Frontend Architecture

- Route/page Finance existing diperluas + halaman baru (`/dashboard/finance/tickets`, `/dashboard/finance/analytics`).
- `src/services/mzt-api.ts` + `queries.ts` diperluas — konsisten pola Sprint 5A.
- State loading/empty/error/success wajib pada tiap blok — sesuai AC.
- Chart library `recharts ^2.15.4` sudah ada sebagai dependency — cocok untuk analytics.
- Role gating mengikuti Role Matrix.

**Verdict:** konsisten dengan arsitektur frontend Sprint 5A.

---

# 18. Technical Debt

- Debt pre-existing (`tests/Feature/ExampleTest.php`, TS errors di `account-dialog.tsx`/`home-news.tsx`/`content/index.tsx`) **tidak dianggap regression baru** — benar dan konsisten.
- Aturan "regression/error BARU akibat Sprint 5B wajib diperbaiki" — baik.
- Tidak ada debt baru yang diciptakan tanpa evidence.

---

# 19. Risks

| Risiko | Level | Status |
|--------|-------|--------|
| Query cost analytics/trend | Medium | Terdampak §8; mitigasi ada |
| Data volume export | Medium | Mitigasi streaming/chunking |
| Controller bloat / god-controller | High | Mitigasi: controller terpisah/tipis; jangan tambah ke `ApiController` |
| Authorization salah gate (analytics/export) | **High** | **Temuan Major §12** — perlu diselesaikan |
| Ticket state mismatch | **High** | **Temuan Major §6** — perlu diselesaikan |
| Regression dashboard existing | Medium | Regression suite diperluas; endpoint existing tidak diubah |

---

# 20. Positive Findings

1. **Read-only murni** — tidak menyinggung integritas domain transaksional (ADR-001).
2. **Pola arsitektur Sprint 5A terbukti** dipertahankan untuk semua area baru.
3. **Endpoint PROPOSED vs EXISTING** dibedakan tegas; tidak ada klaim endpoint sudah hidup.
4. **CSV-only + file stream + chunking** — keputusan tepat, YAGNI terhormat.
5. **Tanpa migration diasumsikan** — konservatif, diverifikasi di review.
6. **Performance gate konkret & terukur** (< 500 ms; < 10 detik).
7. **Debt pre-existing ditangani benar** — non-blocking, tidak dianggap regression baru.
8. **Governance/change control** ada untuk perubahan PRD/ADR.
9. **Index existing cukup** untuk agregasi rencana (evidence dari migration).

---

# 21. Critical Findings

**Tidak ada.**

---

# 22. Major Findings

### MAJ-01 — Konflik state machine tiket (ADR-011 vs PRD vs implementasi)

- **Sumber konflik:** ADR-011 (docs frontend) menulis `not_generated → generated → used → cancelled`; PRD §A16.8/§A10.2 & implementasi `app/Enums/TicketStatus.php` memakai `draft/issued/checked_in/finished/cancelled/revoked`; kolom `tickets.status` default `'issued'`.
- **Dampak:** FR-01 (Ticket Monitoring) akan grouping dengan key yang tidak cocok dengan data aktual; mapping baru dilarang FR-01 → deadlock.
- **Wajib:** keputusan kanonik state tiket untuk monitoring — (a) selaraskan rencana dengan enum aktual, dan/atau (b) terbitkan **ADR amendment** untuk mengoreksi ADR-011. **Tidak boleh memilih diam-diam.**

### MAJ-02 — Ambiguitas otorisasi CSV Export vs Analytics

- **Sumber konflik:** Role Matrix §13 memberi Export ke semua staff, sementara Analytics dibatasi verifier.
- **Dampak:** non-verifier (`dashboard`/`event`) berpotensi mengekspor data finansial via CSV → membocorkan data yang seharusnya hanya untuk verifier.
- **Wajib:** tetapkan rule eksplisit — export data finansial hanya untuk verifier, atau kolom ekspor dibatasi per role; sertakan regression test 403.

---

# 23. Minor Findings

### MIN-01 — `DashboardFilter.eventId` / `status` belum dikonsumsi Query Layer
Query Sprint 5A hanya memakai `start`/`end`. Sprint 5B (Event Distribution,
filter status) harus memastikan filter dipakai + regression test khusus.

### MIN-02 — Detail CSV export belum konkret
Ukuran chunk, penanganan timeout/abort, dan konsistensi header kolom perlu
ditetapkan saat implementasi.

### MIN-03 — Ability policy baru belum didefinisikan
`viewTickets`, `viewAnalytics`, `viewExport` perlu konsisten dengan
`DashboardPolicy` aktual saat implementasi.

### MIN-04 — Observability export belum detail
Level logging, retensi, batas konkurensi export perlu ditetapkan.

---

# Observation

### OBS-01 — ADR-011 berstatus Planned
ADR-011 tercatat *Planned (Phase 2B)* di ADR.md, sementara implementasi aktual
sudah melampaui dokumen tersebut. Mendukung kebutuhan MAJ-01 untuk ADR amendment.

### OBS-02 — `recharts` tersedia
Chart library `recharts ^2.15.4` sudah dependency → tidak perlu library baru untuk analytics.

---

# 24. Recommendation

1. **Selesaikan MAJ-01 terlebih dahulu:** tentukan canonical ticket state untuk
   monitoring. Opsi terpilih (pilih salah satu, atau gabung):
   - Gunakan nilai enum aktual (`draft/issued/checked_in/finished/cancelled/revoked`) sebagai basis Ticket Monitoring.
   - Terbitkan **ADR amendment** untuk menyelaraskan ADR-011 dengan PRD & implementasi.
2. **Selesaikan MAJ-02:** tetapkan scope otorisasi export (verifier-only untuk
   data finansial, atau kolom-scoped per role) + regression test 403.
3. Terapkan MIN-01..04 sebagai detail implementasi (filter query, chunk CSV,
   ability policy, observability).
4. Lanjutkan ke implementasi hanya setelah MAJ-01 & MAJ-02 dituntaskan.

---

# 25. Final Verdict

**APPROVED WITH CONDITIONS**

Rencana Sprint 5B secara arsitektur **layak**, mempertahankan pola terbukti
Sprint 5A, read-only, tanpa scope creep, tanpa migration yang diasumsikan, dan
dengan performance gate yang terukur.

**Syarat blocking sebelum implementasi dimulai:**
- [ ] **MAJ-01** diselesaikan — keputusan canonical ticket state (ADR amendment atau penyelarasan rencana dengan enum aktual).
- [ ] **MAJ-02** diselesaikan — rule otorisasi export vs analytics ditetapkan + test 403.

Setelah kedua kondisi di atas terpenuhi dan terdokumentasi, implementasi Sprint
5B boleh dimulai. Tidak ada temuan Critical.

---

*Dokumen ini merupakan bagian dari Sprint 5B Architecture Review. Tidak ada kode aplikasi, PRD, maupun ADR yang diubah selama review. Konflik yang ditemukan dilaporkan sebagai temuan, bukan diselesaikan diam-diam.*

---

# 26. Architecture Gate Closure

**Tanggal** : 09 Agustus 2026
**Pemutus** : MZT Core Team (keputusan Architecture Gate Closure disetujui)
**Acuan** : ADR-011 amendment, `SPRINT5B_PLANNING.md` (amended), evidence implementasi aktual (Sprint 3 Verification Report, `app/Enums/TicketStatus.php`, `TicketService`, `TicketLifecycleService`, `TicketPolicy`, `RoleGuard`, migration `tickets`)

Bagian ini mendokumentasikan penutupan dua kondisi blocking (**MAJ-01** dan
**MAJ-02**) yang ditetapkan pada §25. Verdict awal **APPROVED WITH CONDITIONS**
tetap dipertahankan sebagai catatan historis; penutupan kondisi ditambahkan di
bawah ini secara transparan, tanpa mengubah hasil review yang telah ditulis.

## 26.1 MAJ-01 — RESOLVED via ADR-011 Amendment

- **Keputusan:** ADR-011 di-amend dari **Planned** menjadi **Accepted** dengan
  canonical ticket state `draft / issued / checked_in / finished / cancelled /
  revoked` (bukan `not_generated / generated / used`).
- **Bukti:** ADR-011 amendment mendefinisikan arti status, valid transition,
  terminal state (`finished`, `cancelled`, `revoked`), makna cancelled vs
  revoked, QR/ticket validation behavior, hubungan dengan Phase 2C Check-In, dan
  pernyataan **tidak ada status mapping baru**.
- **Konsistensi:** canonical state selaras dengan PRD §10.2 / §16.8 / §17.14.4,
  `app/Enums/TicketStatus.php`, `TicketService` (idempoten, `issued`),
  `TicketLifecycleService` (`canRevoke` untuk draft/issued/checked_in), dan
  Sprint 3 Verification Report. Tidak ada perubahan kode, enum, maupun migration.
- **Dampak:** FR-01 (Ticket Monitoring) kini grouping langsung memakai nilai
  canonical ADR-011 — tanpa deadlock mapping.

## 26.2 MAJ-02 — RESOLVED via Explicit Verifier-Only Export Authorization

- **Keputusan:** CSV Export (data finansial) **hanya untuk role verifier**.
- **Canonical rule:** `viewExport(User $user) → RoleGuard::canVerify($user)`.
- **Bukti:** `SPRINT5B_PLANNING.md` §11 (gate `viewExport` → verifier) dan §13
  (Role Matrix export hanya Finance/Ketua/Admin + matriks respons eksplisit)
  di-amend sesuai keputusan.
- **Matriks respons eksplisit:**

  | Role / Kondisi | `GET /api/dashboard/finance/export` |
  |----------------|--------------------------------------|
  | Alumni | **403** |
  | Staff biasa (`dashboard` / `event`) | **403** |
  | Finance | **200** |
  | Ketua | **200** |
  | Admin | **200** |
  | Unauthenticated | **401** |

- **Dampak:** konsisten dengan `viewAnalytics`, `viewRevenue`, `viewPayment`
  (verifier-only); risiko kebocoran data finansial via CSV oleh non-verifier
  ditutup. Regression test 401/403/200 ditambahkan pada `SPRINT5B_PLANNING.md`
  §18 (Acceptance Criteria) sebagai requirement.

## 26.3 Conditions for Implementation — FULFILLED

| Condition | Status | Bukti |
|-----------|--------|-------|
| MAJ-01 diselesaikan — canonical ticket state (ADR amendment / penyelarasan dengan enum aktual) | ✅ **FULFILLED** | ADR-011 amendment → **Accepted** (canonical `draft/issued/checked_in/finished/cancelled/revoked`) |
| MAJ-02 diselesaikan — rule otorisasi export vs analytics + test 403 | ✅ **FULFILLED** | `viewExport` = `RoleGuard::canVerify` (verifier-only); matriks 401/403/200 terdokumentasi + regression requirement di Planning |

## 26.4 Sprint 5B — ELIGIBLE FOR IMPLEMENTATION

- Kedua kondisi blocking pada §25 **telah terpenuhi dan terdokumentasi**.
- **Sprint 5B kini READY FOR IMPLEMENTATION** (status: NOT STARTED).
- Implementasi hanya boleh dimulai setelah tahap ini disetujui; seluruh aturan
  implementasi Sprint 5B (read-only, tanpa migration tanpa keputusan, endpoint
  PROPOSED tidak diklaim EXISTING, performance gate, regression) tetap berlaku.

## 26.5 Follow-up Governance (tidak memblokir implementasi)

- **PRD amendment §16.8 & §17.14.4** (menambahkan status `revoked` ke daftar
  status Ticket) **dicatat sebagai follow-up governance item** dan **tidak**
  dibuat pada Gate Closure ini. Akan diproses sebagai keputusan terpisah.

## 26.6 Scope Gate Closure

- Yang berubah: ADR-011 (docs), `SPRINT5B_PLANNING.md` (docs), dokumen ini
  (docs).
- Yang **tidak** berubah: kode aplikasi, `TicketStatus.php`, database/migration,
  PRD, verdict awal review, deployment.
