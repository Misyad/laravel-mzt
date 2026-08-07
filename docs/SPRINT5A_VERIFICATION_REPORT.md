# Sprint 5A — Verifikasi Report (Finance Dashboard Foundation)

## 1. Ringkasan

| Item | Detail |
|------|--------|
| **Sprint** | 5A — Dashboard Foundation |
| **Status** | **PASS** (implementasi + regression + performance gate) |
| **Commit** | `9906618` (`feat(sprint5a): finance dashboard foundation ...`) |
| **Branch** | `main` (pushed → GitHub `Misyad/laravel-mzt`) |
| **Jenkins** | `mzt-deploy` — auto-trigger via push webhook (lihat §7) |
| **Akurasi** | Backend: 1 file (controller), 9 file penyusun set (DTO/Query/Service/Contract/Policy/Resource/Support) |

---

## 2. Lingkup

Sprint 5A membangun **landasan (foundation)** dashboard finance di sisi backend
maupun frontend, sesuai arsitektur yang sudah disetujui pada `SPRINT5_ARCHITECTURE_REVIEW.md`.

Backend:
- DTO layer untuk semua response (`DashboardFilter`, `OverviewKpis`, `RegistrationSummary`, `RevenueSummary`, `PaymentSummary`).
- **Query Layer**: `app/Queries/DashboardQuery.php` — pola read-model pertama di codebase (aggregate count/sum/groupBy, tanpa side-effect).
- **Service**: `app/Services/DashboardService.php` (read-only, return DTO murni), **satu interface** `app/Contracts/DashboardServiceInterface.php`.
- **Policy**: `app/Policies/DashboardPolicy.php` + subject marker `app/Support/Dashboard.php`.
- **Resources**: `app/Http/Resources/Dashboard/*` (4 buah) — DTO→JSON.
- **Controller**: `app/Http/Controllers/DashboardController.php` + mapping di `routes/api.php`.

Frontend (repo `mazil-studio-studio`):
- `src/types/api.ts` (DTO types + perluasan `AppRole`).
- `src/services/mzt-api.ts` + `src/services/queries.ts` (queryKeys + queryOptions).
- `src/routes/dashboard/finance/index.tsx` (halaman finance: KPI cards + Registration/Revenue/Payment summary).
- `src/routes/dashboard/route.tsx` (NAV_ITEMS + `Wallet`), `src/routeTree.gen.ts` (route terdaftar).

---

## 3. Endpoint & RBAC

| Method | Path | Gate |
|--------|------|------|
| GET | `/api/dashboard/finance/overview` | `viewOverview` (isStaff) |
| GET | `/api/dashboard/finance/registration` | `viewOverview` (isStaff) |
| GET | `/api/dashboard/finance/revenue` | `viewRevenue` (canVerify) |
| GET | `/api/dashboard/finance/payments` | `viewPayment` (canVerify) |

Semua endpoint dilindungi middleware `auth:sanctum`. Response `{ success: true, data }` via API Resource.

---

## 4. Verification — Regression Tests

### 4.1 Empty Dataset
- Test dibuat: schema kosong (0 orders / payments / tickets).
- Ekspektasi: endpoint mengembalikan **200 + data bernilai-nol**, tanpa error.

### 4.2 Large Dataset
- Seed 500 Orders / 500 Payments / 500 Tickets (+ users).
- Memverifikasi kebenaran agregasi dan tidak ada degenerasi (rollback & ulang.

Hasil:

| Command | Hasil |
|---------|-------|
| `phpunit tests/Feature/DashboardTest.php` | **OK (5 tests, 22 assertions)** |
| `phpunit tests/Unit` | **OK (8 tests)** |
| Waktu total suite dashboard | 2.83s (termasuk seeder Build besar) |

### 4.3 Performance Gate (`PERFORMANCE_GATE_MS = 500`)
Probe ad-hoc pada Large Dataset (dihapus setelah dipakai untuk reporting):

| Endpoint | Latency |
|----------|---------|
| overview | 18.5 ms |
| registration | 3.3 ms |
| revenue | 2.7 ms |
| payments | 2.6 ms |
| **Total** | **27.1 ms** |

Semua skor jauh di bawah batas per-endpoint **<500 ms**. Pola kueri adalah
aggregate single-pass (COUNT/SUM/groupBy), tanpa N+1, tanpa eager-load besar,
sehingga kinerja tetap baik pada dataset besar.

---

## 5. Self-Review Checklist

| Nomor | Checklist (bersumber dari 13 aturan) | Hasil |
|-------|--------------------------------------|-------|
| 1 | DTO untuk semua response | PASS |
| 2 | `DashboardFilter` = pure DTO (tanpa `fromRequest`) | PASS |
| 3 | Endpoint ikut API existing | PASS |
| 4 | Tanpa caching (tunda 5D) | PASS |
| 5 | Policy method spesifik | PASS |
| 6 | Regression Empty Dataset | PASS |
| 7 | Service return DTO murni (bukan Model/Builder/Collection) | PASS |
| 8 | Controller pakai Laravel API Resource | PASS |
| 9 | Interface terotisasi + implementasi | PASS |
| 10 | Regression Large Dataset (500×3) | PASS |
| 11 | Query Layer saja (tanpa side-effect) | PASS |
| 12 | Resource murni DTO→JSON | PASS |
| 13 | Performance Gate <500ms | PASS |

**Penambahan verifikasi otomasi:**
- Read-only check (tidak ada INSERT/UPDATE/DELETE/transaction di layer Query/Service/Controller).
- N+1 absen (semua aggregate single-pass).

---

## 6. Deployment / Frontend Status

- Backend **dipush** ke `main` dan siap untuk deploy/migrate.
- Frontend berhasil **build** (vite `✓ built`, chunk `finance-*.js` dihasilkan), 
  route tree regenerated, dan `tsc --noEmit` tidak menambahkan error baru
  (error yang tersisa adalah pre-existing, di luar file Sprint 5A).

---

## 7. Jenkins Build Status

- **Job**: `mzt-deploy` (jenkins.projecthasan.com:8084, Dockerhost 192.168.1.60).
- Karena mesin dev ini **tidak dapat menjangkau host Jenkins** (di luar jaringan/VPN),
  build tidak dapat di-truto-trigger dari sini. Jenkins umumnya auto-trigger via
  webhook push pada `main`.
- **Action**: konfirmasi masalah #42 (atau set berikutnya) berstatus **SUCCESS**
  setelah push, sebelum lanjut ke sprint berikutnya.

---

## 8. Keterbatasan / Catatan

- `tests/Feature/ExampleTest.php` masih memiliki failure **pre-existing**
  (expect 500 vs 200 terhadap homepage pada DB test kosong) — di luar scope
  Sprint 5A dan tidak mempengaruhi suite dashboard.
- Deploy ke production & migrasi kolom `events.harga` guard sudah diamankan.

---

## 9. Kesimpulan

Sprint 5A telah **lolos semua** kriteria: implementasi lengkap, regression
(empty + large dataset) PASS, performance gate PASS, self-review & architecture
review PASS (skor 87/100). **Siap untuk lanjut ke Sprint 5B** (data visualisasi/export) setelah deploy Jenkins terkonfirmasi.