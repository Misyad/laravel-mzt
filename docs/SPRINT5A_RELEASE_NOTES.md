# MZT Apps — Sprint 5A Release Notes

**Rilis** : Sprint 5A — Finance Dashboard Foundation
**Status Rilis** : Development Snapshot / RC (untuk Sprint 5A)
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
| Sprint | Sprint 5A — Finance Dashboard Foundation |
| Repositori Backend | `Misyad/laravel-mzt` (`main`) |
| Repositori Frontend | `Misyad/maziltu-design-studio` (`main`) |
| Status | Development Snapshot / RC (belum ada keputusan versi rilis resmi) |
| Dokumen Acuan | `SPRINT5A_VERIFICATION_REPORT.md` (PASS) |

---

# 2. Ringkasan

Sprint 5A meluncurkan **fondasi Dashboard Finance** pada platform MZT Apps:
lapisan baca-saja (read-only) untuk membaca kondisi keuangan dan operasional —
pendapatan, pendaftaran, dan pembayaran — secara agregat. Rilis ini adalah
**subset pertama** dari rencana Business/Reporting Layer Sprint 5, di atas
fondasi transaksional Phase 2B yang stabil.

---

# 3. Highlights

- **Read Model pertama** di codebase (`app/Queries/DashboardQuery.php`) — kueri
  agregat single-pass (COUNT / SUM / groupBy), read-only, tanpa N+1.
- **DTO murni** untuk semua respons; Service mengembalikan DTO, bukan Model.
- **Otorisasi berlapis** (DashboardPolicy + RoleGuard): data finansial hanya untuk
  `finance` / `ketua` / `admin`.
- **Performa sangat baik** — total kumulatif 27,1 ms (setiap endpoint < 500 ms).
- **Regression PASS** — 5 tests / 22 assertions (Empty + Large Dataset).
- **Halaman Finance baru** di frontend (KPI cards + ringkasan Registration/Revenue/Payments).

---

# 4. Architecture

- **Read Model**: `app/Queries/DashboardQuery.php` — kueri agregat read-only.
- **Layer**: Controller tipis → Service (DTO) → Query Layer → API Resource.
- **Service Interface**: `app/Contracts/DashboardServiceInterface.php` + implementasi
  `DashboardService` (ADR-004: business logic di Service).
- **Tanpa business logic di Controller**; tanpa penambahan method pada god-controller `ApiController`.
- **Tanpa migration baru**; migration existing diberi guard agar aman pada fresh DB.
- **Tanpa caching** (sengaja; ditunda ke Sprint 5D).

---

# 5. Backend

Komponen baru (repo `laravel-mzt`):
- `app/Contracts/DashboardServiceInterface.php`
- `app/DTO/DashboardFilter.php`, `app/DTO/OverviewKpis.php`,
  `app/DTO/RegistrationSummary.php`, `app/DTO/RevenueSummary.php`,
  `app/DTO/PaymentSummary.php`
- `app/Queries/DashboardQuery.php`
- `app/Services/DashboardService.php`
- `app/Policies/DashboardPolicy.php`
- `app/Support/Dashboard.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Resources/Dashboard/*` (4 Resource)
- `tests/Feature/DashboardTest.php`

Diubah:
- `app/Providers/AppServiceProvider.php` (bind interface → impl)
- `app/Providers/AuthServiceProvider.php` (register policy)
- `routes/api.php` (4 endpoint baru)
- `database/migrations/2026_08_10_000001_add_registration_columns_to_events_table.php` (guard)
- `tests/Unit/TicketNumberServiceTest.php` (perbaikan pre-existing, agar suite dapat dijalankan)

---

# 6. Frontend

Komponen baru/diubah (repo `maziltu-design-studio`):
- `src/types/api.ts` — tipe DTO dashboard + perluasan `AppRole`
  (`"finance" | "ketua" | "admin"`)
- `src/services/mzt-api.ts` — 4 fungsi fetch dashboard
- `src/services/queries.ts` — queryKeys + 4 queryOptions
- `src/routes/dashboard/finance/index.tsx` — halaman Finance (KPI cards +
  ringkasan Registration/Revenue/Payments, format IDR)
- `src/routes/dashboard/route.tsx` — item navigasi Finance (`Wallet`) dengan
  `roles: ["finance", "ketua", "admin"]`
- `src/routeTree.gen.ts` — route terdaftar

---

# 7. API

Semua endpoint **GET**, read-only, dilindungi `auth:sanctum` + Policy:

| Method | Path | Gate | Keterangan |
|--------|------|------|------------|
| GET | `/api/dashboard/finance/overview` | `viewOverview` (staff) | KPI ringkasan keuangan/operasional |
| GET | `/api/dashboard/finance/registration` | `viewOverview` (staff) | Ringkasan status registrasi |
| GET | `/api/dashboard/finance/revenue` | `viewRevenue` (verifier) | Ringkasan pendapatan |
| GET | `/api/dashboard/finance/payments` | `viewPayment` (verifier) | Ringkasan status pembayaran |

Format respons: `{ success: true, data }` via Laravel API Resource.

---

# 8. Security

- Autentikasi token via **Laravel Sanctum**.
- Otorisasi berlapis: **DashboardPolicy** + RoleGuard.
  - Staff: `dashboard` / `event` / `finance` / `ketua` / `admin`.
  - Verifier (data finansial): `finance` / `ketua` / `admin`.
- Data finansial (revenue/payments) hanya untuk verifier; role lain → 403.
- Navigasi frontend memfilter item Finance berdasarkan peran pengguna.

---

# 9. Performance

Gate: **setiap endpoint < 500 ms**. Hasil probe pada Large Dataset (500×3):

| Endpoint | Latency |
|----------|---------|
| overview | 18,5 ms |
| registration | 3,3 ms |
| revenue | 2,7 ms |
| payments | 2,6 ms |
| **Total kumulatif** | **27,1 ms** |

Seluruh endpoint jauh di bawah batas per-endpoint < 500 ms.

---

# 10. Deployment

- Pipeline Jenkins `mzt-deploy` **#42 SUCCESS**.
- Frontend build berhasil (vite `✓ built`; chunk `finance-*.js` dihasilkan);
  route tree regenerated; `tsc --noEmit` tidak menambahkan error baru.
- Migrasi: `Nothing to migrate` (Sprint 5A tidak menambah migrasi baru).
- Health check: `/api/public/stats` → **HTTP 200**, `success: true`.
- Backup pre-migrate dibuat & tervalidasi sebelum deploy.

---

# 11. Verification

- Regression: `tests/Feature/DashboardTest.php` → **OK (5 tests, 22 assertions)**.
- Unit: `tests/Unit` → **OK (8 tests)**.
- Performance gate: **PASS** (total kumulatif 27,1 ms).
- Deployment: Jenkins #42 SUCCESS, health check 200.
- Dokumen acuan: `docs/SPRINT5A_VERIFICATION_REPORT.md` (PASS).

---

# 12. Breaking Changes

- **Tidak ada.** Seluruh fungsionalitas Phase 2B tetap berjalan tanpa regresi;
  tidak ada perubahan kontrak API lama; tidak ada perubahan struktur domain.

---

# 13. Known Limitations

- **Visualisasi chart/grafik** belum tersedia pada halaman finance (sesuai scope fondasi Sprint 5A).
- **Export report (CSV/Excel)** belum diimplementasikan (di luar scope Sprint 5A).
- **Debt pre-existing** (bukan dari Sprint 5A):
  - `tests/Feature/ExampleTest.php` masih gagal terhadap homepage pada DB test kosong.
  - Beberapa error TypeScript pre-existing pada file frontend di luar file Sprint 5A.
- **Tanpa caching** pada agregasi (sengaja; ditunda ke Sprint 5D).

---

# 14. Next Milestone

- **Next Milestone: Sprint 5B** — sesuai roadmap yang akan direncanakan dan
  direview secara terpisah. Scope final Sprint 5B (mis. visualisasi data,
  analytics, export) belum ditetapkan sebelum `SPRINT5B_PLANNING.md` dibuat dan disetujui.

---

# 15. Release Status

| Status | Nilai |
|--------|-------|
| Status Rilis | **Development Snapshot / RC** (untuk Sprint 5A) |
| Versi Rilis Resmi | Belum diputuskan (tidak dibuat tanpa keputusan release resmi) |
| Sprint | Sprint 5A — Finance Dashboard Foundation |
| Implementasi | 100% selesai, PASS |
| Deployment | Jenkins #42 SUCCESS |

---

# 16. Penutup

Sprint 5A telah selesai dan lolos seluruh kriteria: implementasi lengkap,
regression (empty + large dataset) PASS, performance gate PASS, dan deployment
produksi sehat. Rilis ini menjadi fondasi untuk langkah lanjutan Business Layer
Sprint 5, yang akan direncanakan dan direview secara terpisah.

---

*Dokumen ini merupakan bagian dari Sprint 5A Closure Documentation. Tidak ada kode aplikasi, PRD, maupun ADR yang diubah selama penulisan.*
