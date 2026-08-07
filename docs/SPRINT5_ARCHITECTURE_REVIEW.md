# MZT Apps — Sprint 5 Architecture Review

**Project** : Maziltutholiban Members Platform (MZT Apps)  
**Sprint** : Sprint 5 — Business / Reporting Layer (Finance · Reporting · Analytics · Operational Monitoring)  
**Milestone** : v2.1.0-rc1  
**Ruang Review** : `docs/SPRINT5_PLANNING.md` (v1.0, perencanaan)  
**Status** : **PASS — siap untuk implementasi**  
**Tanggal Review** : 07 Agustus 2026  
**Acuan** : SPRINT5_PLANNING.md, PHASE2B_ARCHITECTURE_REVIEW.md, ADR-001 s.d. ADR-017, PRD, implementasi aktual di `Misyad/laravel-mzt` (RoleGuard, Service Layer, routes)

---

# 1. Metadata

| Item | Nilai |
|------|-------|
| Nama Produk | Maziltutholiban Members Platform (MZT Apps) — Event Management System |
| Repositori Backend | `Misyad/laravel-mzt` |
| Repositori Frontend | `Misyad/maziltu-design-studio` |
| Dokumen yang direview | `docs/SPRINT5_PLANNING.md` (v1.0, 20 bab) |
| Metode Review | Static review perencanaan vs PRD, ADR, arsitektur Phase 2B |
| Reviewed By | Sprint 5 Architecture Review |
| Scope Review | Perencanaan (read-only) — tanpa implementasi |

### Konteks baseline
- Fase sebelumnya: **Phase 2B selesai & dinyatakan Production Ready (v2.1.0-beta1)**.
- Sprint 5 menambah **Business Layer baca-saja** di atas Order/Payment/Ticket/Communication.
- Sprint 5 bersifat **planning**: dokumen ini menilai/menyetujui rencana, bukan implementasi.

---

# 2. Executive Summary

`SPRINT5_PLANNING.md` menyusun rencana pembangunan **Business / Reporting Layer** di atas fondasi transaksional Phase 2B yang stabil. Rencana ini:

- **Read-only murni** — tidak ada mutasi pada Order/Payment/Ticket.
- **Menghormati Aggregate Root (ADR-001)** — dashboard membaca data, tidak menulis ulang entitas.
- **Service Layer dipertahankan (ADR-004)** — seluruh agregasi di Service, controller tipis.
- **Tanpa konflik PRD/ADR yang signifikan** — matrix peran konsisten dengan `RoleGuard`.
- **Tanpa scope creep** — daftar Out of Scope tegas (email/WhatsApp/broadcast/reminder/campaign/attendance/QR/mobile/AI).
- **Database tanpa migration baru** — keputusan eksplisit, optimasi ditangguhkan ke ADR bila target tak tercapai.

**Keputusan:** rencana disetujui. Tidak ada temuan **Critical / Major**. Terdapat **Minor** yang bersifat rekomendasi implementasi, bukan blocker.

---

# 3. Scope Review

| Area | Status |
|------|--------|
| Finance Dashboard | ✅ dibaca dari Order/Payment |
| Revenue Summary (per periode & event) | ✅ |
| Registration Summary | ✅ read-only |
| Payment Monitoring | ✅ |
| Ticket Monitoring | ✅ |
| Operational Dashboard lintas entitas | ✅ |
| Export Report (CSV/Excel) | ✅ |
| Analytics dasar (tren, distribusi, konversi) | ✅ |

**Verdict scope:** Tepat, tanpa fitur yang melebar. **Tidak ada scope creep.**

---

# 4. Objectives Review

Seluruh 8 objectives **berorientasi baca** (dashboard/summary/monitoring/export/analytics). Tidak ada objective yang menyentuh mutation domain. Pernyataan "Seluruh objectives read-only, dipisahkan dari operasi mutation domain" **konsisten** dengan fase Business Layer.

**Verdict:** Objectives realistis, terukur, dan selaras dengan arah arsitektur.

---

# 5. Architecture Review

- Diagram berlapis jelas: **Domain Layer (Phase 2B) → Business Layer (Service) → Dashboard API tipis → Frontend**.
- Agregasi **read-only** dipisah dari operasi mutation — tanpa jalur tulis kembali ke domain.
- Controller didefinisikan sebagai "validasi + otorisasi + serialisasi" — mematuhi ADR-004 (no business logic in Controller).
- Dapat memanfaatkan endpoint yang sudah ada (`/dashboard/stats`, `/public/stats`) tanpa duplikasi.

**Temuan (Minor):** Rencana menempatkan agregasi di Service, namun detail interface antar-Service (DTO, konteks tanggal/periode) belum dijelaskan. Disarankan saat implementasi didefinisikan kontrak kecil dan konsisten. **Non-blocking.**

---

# 6. Backend Design Review

| Service | Tanggung Jawab |
|---------|----------------|
| `DashboardService` | Agregasi ringkasan global / operasional (ADR-004 sesuai) |
| `FinanceReportService` | Pendapatan, outstanding, metode pembayaran |
| `AnalyticsService` | Tren, distribusi, konversi |
| `ExportService` | Serialisasi (CSV/Excel) |

- Service adalah lapisan agregasi sesuai ADR-004.
- Tidak ada business logic di Controller.
- Menjauh dari god-controller `ApiController` — penegasan eksplisit "jangan menambah method di `ApiController`" (menurunkan resiko debt yang sudah di-identifikasi Phase 2B).

**Temuan (Minor):** `ExportService` menampung dua format serialisasi (CSV/Excel). Disarankan pola Strategy untuk pemisahan generator format bila kompleksitas meningkat. **Non-blocking**.

---

# 7. Frontend Design Review

- Halaman baru berjumlah wajar dan seluruhnya di-drive oleh API Dashboard.

**Halaman:** Dashboard Overview, Finance Dashboard, Payment Report, Revenue Report, Analytics, Export.

- Bahasa UI Bahasa Indonesia — sesuai kebutuhan UI lokal.
- Tidak mengubah alur/mutasi domain.

**Temuan (Observation):** Rencana belum menyebut treatment saat data kosong (empty state). Acceptance Criteria (#18) menuliskan "data kosong tidak menyebabkan error"; implementasi diharapkan memasang empty state visual. **Non-blocking**.

---

# 8. API Plan Review

Endpoint dengan prefix `/dashboard/*`, seluruhnya ber-metode `GET` (read-only), dikelompokkan per modul:

- Overview `/dashboard/summary`
- Finance `/dashboard/finance/summary`, `/dashboard/finance/revenue`
- Registration `/dashboard/registration`
- Payment `/dashboard/payments`
- Ticket `/dashboard/tickets`
- Report / Export `/dashboard/report/export`
- Analytics `/dashboard/analytics/trend`, `/dashboard/analytics/distribution`

Semua endpoint di-balut `auth:sanctum` + Policy + RoleGuard dan bersifat read-only.

**Konflik endpoint:** tidak ada konflik dengan Phase 2B — path berbeda; `/dashboard/stats` existing tetap eksis.

**Temuan (Minor):** Format respons `{success, data}` baik; disarankan standarisasi kode/format error respons (401/403) sama dengan endpoint Phase 2B agar konsisten.

---

# 9. Database Strategy Review

**Keputusan resmi:** **Sprint 5 tidak merencanakan migration baru.**

- Struktur `orders`, `payments`, `tickets`, `communication_logs` + index (`id_order`, `uuid`, `status`, `created_at`) sudah mendukung agregasi read-only dasar.
- **Menghindari optimasi prematur** — hanya bila target performa Dashboard (< 2 detik) tidak tercapai barulah diputuskan via ADR baru.
- Tidak ada perubahan struktur Order/Payment/Ticket.

**Verdict:** Keputusan konservatif dan konsisten dengan prinsip "avoid premature optimization". Tidak ada pelanggaran struktur.

**Temuan (Minor):** Bila agregasi lintas volume besar diperlukan, berpotensi N+1 jika tidak melalui satu query Service. Mitigasi sudah ditulis di bagian Performance. **Non-blocking**.

---

# 10. Security Review

**Role Matrix** konsisten dengan `RoleGuard` (`STAFF_ROLES` = dashboard/event/finance/ketua/admin; verifier = finance/ketua/admin; PRD §17.12 / §21.4).

| Modul / Data | Event | Staff | Finance | Ketua | Admin |
|--------------|-------|-------|---------|-------|-------|
| Overview / Registration / Ticket | ✅ | ✅ | ✅ | ✅ | ✅ |
| Finance / Revenue / Payment / Analytics | ❌ | ❌ | ✅ | ✅ | ✅ |
| Export | ❌ | ✅ | ✅ | ✅ | ✅ |

- Data finansial eksklusif `finance/ketua/admin`.
- Rencana `DashboardPolicy` / `FinancePolicy` + RoleGuard — tepat.

**Verdict security:** Tidak ada celah; otorisasi dilapisi Policy + Role, konsisten Phase 2B.

---

# 11. Performance Review

| Komponen | Target |
|----------|--------|
| Dashboard Overview | **< 2 detik** |
| Filter / reload | **< 1 detik** |
| Export report | **< 10 detik** |

Pendekatan: paginasi, agregasi terkonsolidasi di satu lapisan Service (anti-N+1), menggunakan indeks existing.

**Verdict:** Target terukur dan realistis. Tidak ada basis index baru; bergantung pada struktur existing — oleh karena itu SLO (< 2 detik) perlu diverifikasi lewat benchmark sebagai bukti acceptance.

**Temuan (Observation):** Bila volume besar membuat target tak tercapai — pengambil keputusan via ADR baru (agregat/snapshot) sudah siap. **Non-blocking**.

---

# 12. Risks Review

| Risiko (dari plan) | Level | Mitigasi (di plan) |
|--------------------|-------|--------------------|
| Query agregat lambat (volume besar) | Medium | Service + pagination + ADR bila perlu |
| Interpretasi salah angka keuangan | Medium | Uji dengan data hasil Phase 2B |
| `ApiController` god-controller (1482 baris) | High | Service + controller terpisah; jangan tambah method |
| Format tanggal/status bervariasi | Medium | Normalisasi di Service |
| Perubahan frontend besar | Medium | Prioritaskan halaman inti |

**Verdict:** Semua risiko teridentifikasi dengan mitigasi konkret. Tidak ada risiko di luar kendali; risiko tertinggi (god-controller) ditangani dengan penghindaran.

---

# 13. Acceptance Criteria & Exit Criteria Review

**Acceptance:**
- `/dashboard/summary` read-only, format `{success, data}`.
- Performa 2 / 1 / 10 (dtk) terukur.
- Finance/Revenue → 403 untuk non `finance/ketua/admin`.
- Data kosong tidak error; zero regresi Phase 2B; no mutation dari Dashboard.

**Exit:**
- Scope inti selesai & terlihat.
- Struktur domain (Phase 2B) tidak diubah.
- Tidak ada migration baru tanpa ADR resmi.
- Performance terukur; Role Matrix 403 diterapkan.
- Arch Review Sprint 5 PASS; regresi 0; release notes diperbarui.

**Verdict:** Keduanya terukur, dapat diverifikasi, bebas over-engineering.

---

# 14. Positive Findings

1. **Read-only murni** — desain tidak menyinggung integritas domain transaksional.
2. **Aggregate Root dihormati (ADR-001)** — tidak ada penulisan ke child entity dari dashboard.
3. **Service Layer dipertahankan (ADR-004)** — agregasi terpusat, controller tipis.
4. **Anti god-controller** tegas — mitigasi `ApiController` eksplisit.
5. **Keputusan DB konservatif** — tanpa migration baru, optimasi di-defer ke ADR, hindari premature optimization.
6. **Security berlapis** (Policy + RoleGuard) konsisten PRD §17.12 / §21.4.
7. **Scope tegas** — Out of Scope eksplisit, zero scope creep.
8. **Target performa terukur** (2/1/10 dtk) menjadikan verifikasi performa objektif.

---

# 15. Architecture Score

Skala 0–10 per area; bobot seimbang.

| Area | Skor (0–10) |
|------|-------------|
| Scope | 9 |
| Objectives | 9 |
| Architecture | 9 |
| Backend Design | 9 |
| Frontend Design | 8 |
| API Plan | 9 |
| Database Strategy | 9 |
| Security | 9 |
| Performance | 8 |
| Scalability | 8 |
| Risk | 8 |
| Acceptance Criteria | 9 |
| Exit Criteria | 9 |
| **Total** | **113 / 130 → 87/100** |

> Skor **87/100** = rencana matang dan siap direvisi menjadi detail teknis saat implementasi. Tidak ada blocker.

---

# 16. Remaining Technical Considerations

Rekapitulasi temuan (semua **Minor / Observation** — non-blocking):

| ID | Level | Deskripsi |
|----|-------|-----------|
| MN-01 | Minor | Kontrak input/output antar-Service belum didefinisikan di dokumen. |
| MN-02 | Minor | `ExportService` menampung dua format; pertimbangkan pisah generator. |
| MN-03 | Minor | Standarisasi format/error respons `{success, data}` dengan Phase 2B. |
| OB-01 | Observation | Belum ada spesifikasi empty state Frontend untuk data kosong. |
| OB-02 | Observation | Keberhasilan target < 2 dtk perlu diverifikasi benchmark. |

Tidak ada temuan Critical / Major.

---

# 17. Recommendation (Rencana Lanjutan)

1. **Lanjutkan implementasi Sprint 5** sesuai rencana — scope, arsitektur Service Layer, dan target performa sudah valid.
2. Definisikan **kontrak antar Service** (input/output DTO, format tanggal/periode) saat implementasi.
3. Terapkan **empty state** pada modul dashboard frontend.
4. Standarisasi **respons error** dengan endpoint Phase 2B.
5. Pisahkan **generator format export** (CSV/Excel) agar modular.
6. Ukur performa **2/1/10** via benchmark sebagai bukti acceptance.
7. Bila target tak tercapai → terbitkan **ADR baru** untuk agregat/snapshot.

---

# 18. Final Verdict

**PASS** ✅ — **Rencana Sprint 5 disetujui untuk diimplementasikan.**

- **Tanpa blocker.** Critical = 0, Major = 0.
- Minor / Observation = rekomendasi implementasi, bukan penghambat.
- Tidak ada konflik dengan PRD / ADR / Aggregate Root.
- Dashboard **read-only**; **Business Logic tidak di Controller**; **Service Layer dipertahankan**.
- Target sprint realistis (2 / 1 / 10 detik), terukur.

**Scope implementasi yang disetujui:** Finance Dashboard, Revenue Summary, Registration Summary, Payment Monitoring, Ticket Monitoring, Operational Dashboard, Export Report, Analytics dasar — seluruhnya read-only di atas Business Layer.

> *Gate: implementasi Sprint 5 dimulai hanya setelah dokumen ini disetujui.*

---

*Dokumen ini merupakan bagian dari Sprint 5 Planning Closure. Tidak ada kode aplikasi, PRD, maupun ADR yang diubah selama review.*