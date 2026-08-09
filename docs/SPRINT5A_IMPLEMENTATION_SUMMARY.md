# MZT Apps — Sprint 5A Implementation Summary

**Sprint** : Sprint 5A — Finance Dashboard Foundation
**Status** : **Selesai (PASS)**
**Tanggal** : 09 Agustus 2026
**Commit Backend** : `07908f9`
**Commit Frontend** : `e46be66`
**Jenkins Build** : `mzt-deploy` #42 — SUCCESS
**Milestone** : v2.1.0-rc1 (Business Layer) — rilis penuh belum diputuskan

---

# 1. Metadata

| Item | Nilai |
|------|-------|
| Produk | Maziltutholiban Members Platform (MZT Apps) |
| Repositori Backend | `Misyad/laravel-mzt` (`main`) |
| Repositori Frontend | `Misyad/maziltu-design-studio` (`main`) |
| Dokumen Acuan | `SPRINT5A_VERIFICATION_REPORT.md` (PASS) |
| Hasil Regression | 5 tests / 22 assertions — PASS |
| Performance Gate | Setiap endpoint < 500 ms — PASS (total kumulatif 27,1 ms) |
| Health Check | HTTP 200 |
| Sprint 5B | Belum dimulai |

---

# 2. Scope

Sprint 5A membangun **fondasi Dashboard Finance** — subset pertama dari
Business/Reporting Layer Sprint 5 — secara **read-only** di atas fondasi
transaksional Phase 2B:

| Fitur | Status | Endpoint |
|-------|--------|----------|
| Overview (KPI keuangan/operasional) | Selesai | `/api/dashboard/finance/overview` |
| Ringkasan Registrasi | Selesai | `/api/dashboard/finance/registration` |
| Ringkasan Pendapatan | Selesai | `/api/dashboard/finance/revenue` |
| Ringkasan Pembayaran | Selesai | `/api/dashboard/finance/payments` |
| Keamanan finansial (RBAC) | Selesai | Policy + RoleGuard pada rute |

Di luar scope Sprint 5A (per perencanaan Sprint 5): Export report, Analytics,
visualisasi chart/grafik, caching, serta seluruh fitur Out of Scope Sprint 5
(email/WhatsApp/broadcast/reminder/attendance/QR/mobile/AI).

---

# 3. Completed Backend

Komponen baru (repo `laravel-mzt`):
- `app/Contracts/DashboardServiceInterface.php` — kontrak layanan agregasi.
- `app/DTO/DashboardFilter.php` — pure DTO filter (tanpa `fromRequest`).
- `app/DTO/OverviewKpis.php`, `app/DTO/RegistrationSummary.php`,
  `app/DTO/RevenueSummary.php`, `app/DTO/PaymentSummary.php` — DTO respons.
- `app/Queries/DashboardQuery.php` — Read Model, kueri agregat single-pass.
- `app/Services/DashboardService.php` — implementasi agregasi (return DTO).
- `app/Policies/DashboardPolicy.php` — otorisasi spesifik (`viewOverview`/`viewRevenue`/`viewPayment`).
- `app/Support/Dashboard.php` — marker subject untuk policy.
- `app/Http/Controllers/DashboardController.php` — controller tipis.
- `app/Http/Resources/Dashboard/*` — 4 API Resource (DTO → JSON).
- `tests/Feature/DashboardTest.php` — regression (empty + large dataset).

Diubah:
- `app/Providers/AppServiceProvider.php` — bind `DashboardServiceInterface` → `DashboardService`.
- `app/Providers/AuthServiceProvider.php` — register policy `Dashboard::class`.
- `routes/api.php` — 4 endpoint baru.
- `database/migrations/2026_08_10_000001_add_registration_columns_to_events_table.php` — guard `harga` (aman pada fresh DB).
- `tests/Unit/TicketNumberServiceTest.php` — perbaikan pre-existing agar suite dapat dijalankan.

---

# 4. Completed Frontend

Komponen baru/diubah (repo `maziltu-design-studio`):
- `src/types/api.ts` — tipe DTO dashboard + perluasan `AppRole`
  (`"finance" | "ketua" | "admin"`).
- `src/services/mzt-api.ts` — 4 fungsi fetch dashboard.
- `src/services/queries.ts` — queryKeys + 4 queryOptions.
- `src/routes/dashboard/finance/index.tsx` — halaman Finance (KPI cards +
  ringkasan Registration/Revenue/Payments, format IDR).
- `src/routes/dashboard/route.tsx` — item navigasi Finance (`Wallet`) dengan
  `roles: ["finance", "ketua", "admin"]`.
- `src/routeTree.gen.ts` — route `/dashboard/finance` terdaftar (build-generated).

---

# 5. API Coverage

| Method | Path | Gate |
|--------|------|------|
| GET | `/api/dashboard/finance/overview` | `viewOverview` (staff) |
| GET | `/api/dashboard/finance/registration` | `viewOverview` (staff) |
| GET | `/api/dashboard/finance/revenue` | `viewRevenue` (verifier) |
| GET | `/api/dashboard/finance/payments` | `viewPayment` (verifier) |

- Semua endpoint: GET, read-only, `auth:sanctum` + Policy + RoleGuard.
- Format respons: `{ success: true, data }` via API Resource.
- Data finansial hanya untuk verifier (`finance`/`ketua`/`admin`); selainnya 403.

---

# 6. Architecture Coverage

Kesesuaian implementasi terhadap **13 aturan Sprint 5A** yang telah dikunci:

| # | Aturan | Status | Bukti |
|---|--------|--------|-------|
| 1 | DTO untuk semua response | PASS | 4 DTO respons + `DashboardFilter` |
| 2 | `DashboardFilter` pure DTO (tanpa `fromRequest`) | PASS | Mapping di Controller |
| 3 | Endpoint ikut API existing | PASS | Prefix `/api/dashboard/finance/*` |
| 4 | Tanpa caching (tunda 5D) | PASS | Tidak ada caching |
| 5 | DashboardPolicy method spesifik | PASS | `viewOverview`/`viewRevenue`/`viewPayment` |
| 6 | Regression Empty Dataset | PASS | Endpoint mengembalikan data bernilai-nol |
| 7 | Service return DTO murni | PASS | Bukan Model/Builder/Collection/Paginator |
| 8 | Controller pakai Laravel API Resource | PASS | `app/Http/Resources/Dashboard/*` |
| 9 | Interface + implementasi | PASS | `DashboardServiceInterface` ↔ `DashboardService` |
| 10 | Regression Large Dataset (500×3) | PASS | Kebenaran agregasi terverifikasi |
| 11 | Query Layer saja (tanpa side-effect) | PASS | `DashboardQuery` read-only |
| 12 | Resource murni DTO→JSON | PASS | Tanpa query/transform/recalc |
| 13 | Performance Gate < 500 ms | PASS | Total kumulatif 27,1 ms |

Verifikasi tambahan: read-only check (tidak ada INSERT/UPDATE/DELETE/transaction
di Query/Service/Controller) dan tidak ada N+1 (agregasi single-pass).

---

# 7. Test Coverage

| Command | Hasil |
|---------|-------|
| `phpunit tests/Feature/DashboardTest.php` | **OK (5 tests, 22 assertions)** |
| `phpunit tests/Unit` | **OK (8 tests)** |

Skenario yang dicakup:
- **Empty Dataset** — schema kosong; endpoint mengembalikan 200 + data bernilai-nol tanpa error.
- **Large Dataset** — seed 500 Orders / 500 Payments / 500 Tickets; kebenaran agregasi diverifikasi.

*Nama test individual tidak dicantumkan di luar hasil PHPUnit di atas.*

---

# 8. Performance Verification

Gate: **setiap endpoint < 500 ms**. Probe pada Large Dataset (500×3):

| Endpoint | Latency |
|----------|---------|
| overview | 18,5 ms |
| registration | 3,3 ms |
| revenue | 2,7 ms |
| payments | 2,6 ms |
| **Total kumulatif** | **27,1 ms** |

Seluruh endpoint jauh di bawah batas per-endpoint < 500 ms. Pola kueri
single-pass menjamin kinerja stabil pada dataset besar.

---

# 9. Deployment

- Jenkins `mzt-deploy` **#42 SUCCESS** (auto-trigger via push webhook).
- Backend & frontend build sukses; container di-recreate (backend, frontend, worker, caddy).
- Migrasi: `Nothing to migrate` (Sprint 5A tidak menambah migrasi baru).
- Backup pre-migrate dibuat & tervalidasi.
- Health check `/api/public/stats` → **HTTP 200**, `success: true`.

---

# 10. Documentation

- `docs/SPRINT5A_VERIFICATION_REPORT.md` — PASS (regression + performance).
- `docs/SPRINT5A_ARCHITECTURE_REVIEW.md` — review arsitektur implementasi (skor 92/100, hasil penilaian review Sprint 5A).
- `docs/SPRINT5A_RELEASE_NOTES.md` — catatan rilis Sprint 5A.
- `docs/SPRINT5A_IMPLEMENTATION_SUMMARY.md` — dokumen ini.
- Acuan: `SPRINT5_PLANNING.md`, `SPRINT5_ARCHITECTURE_REVIEW.md`, `ADR.md`.

---

# 11. Known Limitations

- **Visualisasi chart/grafik** belum tersedia (sesuai scope fondasi Sprint 5A).
- **Export report (CSV/Excel)** belum diimplementasikan.
- **Tanpa caching** pada agregasi (sengaja; ditunda ke Sprint 5D).
- **Debt pre-existing** (bukan dari Sprint 5A):
  - `tests/Feature/ExampleTest.php` masih gagal terhadap homepage pada DB test kosong.
  - Beberapa error TypeScript pre-existing pada file frontend di luar file Sprint 5A
    (Sprint 5A tidak menambahkan error baru).
- Versi rilis resmi belum diputuskan.

---

# 12. Next Milestone

- **Next Milestone: Sprint 5B** — sesuai roadmap yang akan direncanakan dan
  direview secara terpisah. Scope final Sprint 5B belum ditetapkan sebelum
  `SPRINT5B_PLANNING.md` dibuat dan disetujui.

---

# 13. Kesimpulan

Sprint 5A selesai 100%: implementasi lengkap, regression (empty + large dataset)
PASS, performance gate PASS (total kumulatif 27,1 ms), dan deployment produksi
sehat (Jenkins #42 SUCCESS, health check 200). Seluruh 13 aturan arsitektur
terpenuhi. Fondasi Dashboard Finance siap mendukung langkah lanjutan Business
Layer yang akan direncanakan secara terpisah.

---

*Dokumen ini merupakan bagian dari Sprint 5A Closure Documentation. Tidak ada kode aplikasi, PRD, maupun ADR yang diubah selama penulisan.*
