# MZT APPS — Project Summary

> **Tanggal**: 21 Juni 2026
> **Status**: Running (Laravel Backend ✅ | OrgOS Frontend Prototype ✅)
> **Lokasi**: `D:\MZT APPS\laravel-mzt`

---

## Daftar Isi

1. [Ringkasan Eksekutif](#1-ringkasan-eksekutif)
2. [Project 1: MZT APPS (Laravel 9)](#2-project-1-mzt-apps-laravel-9)
3. [Project 2: OrgOS Frontend (TanStack Start)](#3-project-2-orgos-frontend-tanstack-start)
4. [Rencana Integrasi](#4-rencana-integrasi)
5. [Troubleshooting Log](#5-troubleshooting-log)
6. [Status Deployment](#6-status-deployment)
7. [Dokumen Terkait](#7-dokumen-terkait)

---

## 1. Ringkasan Eksekutif

MZT APPS (Maziltu Tholiban) adalah sistem informasi manajemen organisasi pesantren berbasis web yang terdiri dari dua komponen:

| Komponen | Stack | Status | URL |
|----------|-------|--------|-----|
| **Backend (Existing)** | Laravel 9 + PHP 8.2 + MySQL | ✅ Running | `http://localhost:8000` |
| **Frontend Prototype** | TanStack Start + React 19 + TypeScript | ✅ Running | `http://localhost:3000` |

Backend mengelola keanggotaan, event, presensi, berita, pembayaran (Midtrans), ID Card, dan konten website publik. Frontend prototype (OrgOS) adalah UI admin dashboard modern yang menggunakan mock data, siap diintegrasikan ke backend API.

### Fitur Utama

| # | Fitur | Backend | Frontend Prototype |
|---|-------|---------|-------------------|
| 1 | Dashboard & Statistik | ✅ | ✅ |
| 2 | Manajemen Anggota (CRUD + Barcode + KTA) | ✅ | ✅ |
| 3 | Manajemen Event (CRUD + Multi-date) | ✅ | ✅ |
| 4 | Presensi Kehadiran | ✅ | ✅ |
| 5 | Transaksi & Pembayaran (Midtrans) | ✅ | ✅ |
| 6 | Berita & Pengumuman | ✅ | ✅ |
| 7 | ID Card Generator | ✅ | ✅ |
| 8 | Manajemen Konten (Carousel, Info Pesantren, MZT) | ✅ | ✅ |
| 9 | Activity Log | ✅ | ✅ |
| 10 | Role-Based Access Control | ✅ | ⚠️ UI only |
| 11 | Website Publik | ✅ | ❌ (admin only) |
| 12 | Campaign & Donasi | ❌ | ✅ (mock) |
| 13 | Certificates | ❌ | ✅ (mock) |
| 14 | Broadcast (WA/Email) | ⚠️ WA only | ✅ (mock) |
| 15 | Settings Organisasi | ❌ | ✅ (mock) |

---

## 2. Project 1: MZT APPS (Laravel 9)

### 2.1 Tech Stack

| Komponen | Teknologi |
|----------|----------|
| Framework | Laravel 9.x |
| Bahasa | PHP 8.0.2+ (server: PHP 8.2.12 via XAMPP) |
| Database | MySQL 8 (XAMPP local) |
| Template Engine | Blade |
| Admin UI | Stisla (Bootstrap 4) |
| Frontend | jQuery, Bootstrap, CKEditor, DataTables |
| Payment | Midtrans (Sandbox) |
| WhatsApp | Zenziva API |
| PDF | DomPDF, FPDF |
| Barcode | Milon Barcode (Code39) |
| Image | Intervention Image |

### 2.2 Struktur Database (19 tabel + 1 VIEW)

```
users              -> Data autentikasi (id_anggota, name, email, password)
data_users         -> Detail anggota (alamat, foto, barcode, niqobah, dll)
role_user          -> Master role
hak_akses_role     -> Mapping akses user ke role
events             -> Data event/kegiatan
tanggal_events     -> Detail jadwal per tanggal event
event_status       -> MySQL VIEW (status: Ongoing/Upcomming/Complate)
beritas            -> Data berita/artikel
carosels           -> Gambar carousel homepage
info_pesantrens    -> Info pesantren
tentang_mzts       -> Info tentang MZT
prisensi_kehadiran -> Data presensi kehadiran
m_transaksi_events -> Transaksi pembayaran event
activitas_logs     -> Log aktivitas user
template_id_card   -> Template ID card
component_template_id_card -> Komponen layout ID card
personal_access_tokens     -> API tokens (Sanctum)
password_resets    -> Reset password tokens
failed_jobs        -> Failed queue jobs
migrations         -> Laravel migration tracker
```

### 2.3 Controller (13 total)

| Controller | Fungsi |
|-----------|--------|
| `Login` | Autentikasi (id_anggota + password), logout |
| `Dashboard` | Statistik event/anggota, kalender event |
| `C_Anggota` | CRUD anggota, generate ID, barcode, foto, export KTA |
| `C_profil` | Edit profil user (max 3x edit) |
| `C_Event` | CRUD event, generate tanggal range, banner |
| `C_event_detail` | Setting jam per tanggal event |
| `C_prisensi` | Pencatatan presensi per event/tanggal |
| `C_transaksi` | Transaksi, webhook Midtrans, verifikasi, notifikasi WA |
| `C_Berita` | CRUD berita, upload foto, slug |
| `C_Tampilan` | Edit konten: pesantren, MZT, carousel |
| `C_Aktivitas_log` | Log aktivitas seluruh user |
| `C_ID_Card` | Template ID card, setting komponen, cetak |
| `HomeViews` | Halaman publik (homepage, berita, event, pembayaran) |

### 2.4 Route Summary

- **Route Publik**: 10 route (homepage, berita, pengumuman, pembayaran, logout)
- **Route Auth**: 2 route (login form, login action)
- **Route Admin**: ~50 route (dashboard, anggota, event, presensi, transaksi, berita, tampilan, log, ID card, profil)
- **Route API**: 2 route (user info, Midtrans webhook)

### 2.5 Storage

| Folder | Isi | Jumlah File |
|--------|-----|-------------|
| `storage/app/public/image/anggota/` | Foto anggota | 579 |
| `storage/app/public/image/barcode/` | Barcode PNG | 1,048 |
| `storage/app/public/image/berita/` | Foto berita | 5 |
| `storage/app/public/image/carosel/` | Foto carousel | 2 |
| `storage/app/public/image/event/` | Banner event | 9 |
| `storage/app/public/image/id-card/` | Template ID card | 4 |
| `storage/app/public/image/mzt/` | Foto MZT | 1 |
| `storage/app/public/image/pesantren/` | Foto pesantren | 1 |

---

## 3. Project 2: OrgOS Frontend (TanStack Start)

### 3.1 Info Repository

| Item | Keterangan |
|------|----------|
| **URL** | `https://github.com/Misyad/bloom-unity-flow.git` |
| **Lokasi Lokal** | `D:\MZT APPS\laravel-mzt\_temp_clone` |
| **Produk** | OrgOS - Organization Management Platform |
| **Dibuat via** | Lovable.dev (AI app builder) |
| **Status** | Prototype UI (mock data, belum ada backend) |

### 3.2 Tech Stack

| Komponen | Teknologi |
|----------|----------|
| Framework | TanStack Start (fullstack React) |
| Router | TanStack Router (file-based) |
| Data Fetching | TanStack React Query v5 |
| UI Library | React 19.2 |
| Styling | Tailwind CSS v4 + shadcn/ui |
| Charts | Recharts |
| Animations | Framer Motion |
| Forms | React Hook Form + Zod |
| Icons | Lucide React |
| Build | Vite 8 + TypeScript 5.8 |
| Runtime | Bun / npm |
| Server | Nitro (SSR) |

### 3.3 Halaman (12 routes)

| Route | Halaman | Fitur |
|-------|---------|-------|
| `/` | Dashboard | Stat cards, charts (attendance + revenue), activity feed |
| `/members` | Members | Tabel + search/filter/bulk select |
| `/members/$id` | Member Detail | Profil lengkap, tabs |
| `/events` | Events | Grid/list view, progress bar kapasitas |
| `/events/$id` | Event Detail | Banner, stats, participants |
| `/attendance` | Attendance | Live session, QR scan, stats |
| `/finance` | Finance | Revenue chart, campaigns, transactions |
| `/certificates` | Certificates | Templates, issued, verify |
| `/id-cards` | Digital ID Cards | Templates, issued, verify |
| `/cms` | CMS | Articles, announcements, media |
| `/communications` | Communications | Broadcasts (WA, Email, Push) |
| `/settings` | Settings | Organization, branding, domains, users, roles |

### 3.4 Komponen UI

- **48 shadcn/ui components** (accordion, dialog, dropdown, table, chart, dll)
- **4 custom components**: AppSidebar, TopNav, StatCard, PageHeader
- **Command Palette** (Ctrl+K) untuk navigasi cepat
- **Dark/Light mode** toggle
- **Notifications** popover
- **Responsive** sidebar (collapsible)

### 3.5 Mock Data

Semua data di `src/lib/mock-data.ts`:
- 48 anggota (generated dari kombinasi nama)
- 15 events
- 30 transactions
- 4 campaigns
- 12 certificates
- 4 ID templates
- 4 articles
- 4 broadcasts
- 12 bulan attendance & revenue trends

---

## 4. Rencana Integrasi

### 4.1 Arsitektur Target

```
+-------------------------------------------+
|  ORGOS FRONTEND (React 19 + TypeScript)   |
|  TanStack Start + shadcn/ui + Tailwind    |
|  Deploy: Vercel / Cloudflare Pages         |
+------------------+-------------------------+
                   | HTTPS + Bearer Token
                   | CORS enabled
                   v
+-------------------------------------------+
|  MZT BACKEND (Laravel 9 + PHP 8.2)        |
|  + Sanctum API Auth (baru)                |
|  + REST API Routes (baru)                 |
|  + API Resources (baru)                   |
|  Deploy: cPanel maziltutholiban.com        |
+------------------+-------------------------+
                   |
                   v
+-------------------------------------------+
|  MySQL DATABASE                           |
|  19 tabel existing + 4 tabel baru         |
+-------------------------------------------+
```

### 4.2 Mapping Fitur: Frontend vs Backend

| Halaman Frontend | Backend | Status | Gap |
|-----------------|---------|--------|-----|
| Dashboard | Dashboard controller | ✅ Tersedia | Perlu endpoint statistik/trend |
| Members | C_Anggota (full CRUD) | ✅ Tersedia | Wrap sebagai API |
| Events | C_Event (full CRUD) | ✅ Tersedia | Tambah kolom: type, capacity |
| Attendance | C_prisensi | ✅ Tersedia | Wrap sebagai API |
| Finance | C_transaksi | ⚠️ Parsial | Perlu modul campaigns |
| ID Cards | C_ID_Card | ✅ Tersedia | Tambah kolom: name, color |
| CMS | C_Berita + C_Tampilan | ⚠️ Parsial | Tambah kolom: category, views |
| Communications | Helper sendWa | ❌ Belum ada | Perlu modul broadcasts |
| Certificates | - | ❌ Belum ada | Perlu modul baru |
| Settings | - | ❌ Belum ada | Perlu tabel org_settings |

### 4.3 Yang Perlu Dibuat

#### Backend (Laravel)

| Item | Detail | Effort |
|------|--------|--------|
| API Auth (Sanctum) | Login/logout/token endpoint | 0.5 hari |
| ~35 API endpoints | Wrapper dari controller existing | 3-5 hari |
| API Resources | Response transformer (7 file) | 1 hari |
| CORS config | Izinkan frontend origin | 0.5 jam |
| Migration baru | 3 alter table + 4 create table | 1 hari |
| MySQL VIEW formalisasi | event_status ke migration | 0.5 jam |

#### Frontend (React)

| Item | Detail | Effort |
|------|--------|--------|
| API client layer | `src/lib/api.ts` (fetch wrapper) | 0.5 hari |
| Auth system | Login page + token manager + guard | 1 hari |
| Custom hooks | 7 hooks (use-members, use-events, dll) | 2 hari |
| Update routes | 12 route files (mock -> API) | 3-4 hari |

#### Database (Tabel Baru)

| Tabel | Kolom Utama |
|-------|-------------|
| `campaigns` | name, target_amount, raised_amount, donors_count, start_date, end_date, status |
| `certificates` | certificate_id, id_event, id_anggota, template_name, issued_date, verification_code |
| `broadcasts` | title, message, channel (wa/email/push), audience_count, sent_count, status |
| `org_settings` | key, value, group |

#### Database (Alter Table)

| Tabel | Kolom Baru |
|-------|-----------|
| `events` | `type` VARCHAR(50), `capacity` INT |
| `beritas` | `category` VARCHAR(50), `views` INT |
| `template_id_card` | `name` VARCHAR(100), `color` VARCHAR(20) |

### 4.4 Estimasi Timeline

| Minggu | Fokus | Deliverable |
|--------|-------|-------------|
| **W1** | Backend API Foundation | Semua endpoint berfungsi + Postman collection |
| **W2** | Frontend Auth + Core Pages | 6 halaman utama terkoneksi API |
| **W3** | Remaining Pages + Polish | Semua halaman + error handling |
| **W4** | Deployment + Hardening | Live di production |

---

## 5. Troubleshooting Log

Selama menjalankan kedua web secara lokal, ditemukan dan diperbaiki beberapa masalah:

### 5.1 Laravel Backend Issues

| # | Issue | Root Cause | Fix |
|---|-------|-----------|-----|
| 1 | **PHP tidak ditemukan** | PHP (XAMPP) tidak di system PATH | `$env:PATH = "C:\xampp\php;" + $env:PATH` |
| 2 | **DB auth error (1045)** | `.env` berisi credentials production (`mazw9983_ades`) | Update `.env`: `DB_USERNAME=root`, `DB_PASSWORD=` |
| 3 | **DB auth masih error** | Environment variable `DB_USERNAME` ter-set di PowerShell session (override `.env`) | `Remove-Item Env:DB_USERNAME` + start process dengan explicit env vars |
| 4 | **Login 500 error** | File `Login.php` memiliki BOM (Byte Order Mark: `EF BB BF`) di awal file | Hapus 3 byte BOM via binary read/write |
| 5 | **Pengumunan 500 error** | MySQL VIEW `event_status` punya DEFINER `mazw9983@localhost` yang tidak ada di lokal | Recreate VIEW dengan `SQL SECURITY INVOKER` |
| 6 | **Collation error (1253)** | VIEW di-recreate via mysql CLI yang pakai charset `cp850` | Recreate ulang dengan `--default-character-set=utf8mb4` |
| 7 | **Gambar tidak tampil (404)** | Symlink `public/storage` masih mengarah ke path backup lama | Hapus symlink lama + `php artisan storage:link` |

### 5.2 OrgOS Frontend Issues

| # | Issue | Root Cause | Fix |
|---|-------|-----------|-----|
| 1 | **npm install timeout** | Banyak dependencies (458 packages) | Jalankan di background process |
| 2 | **Rolldown native binding error** | `npm install --no-optional` skip `@rolldown/binding-win32-x64-msvc` | Reinstall tanpa `--no-optional` |

### 5.3 Konfigurasi Environment Lokal

```env
# .env (Laravel) - untuk development lokal
APP_NAME=Laravel
APP_ENV=local
APP_KEY=base64:4b6X+FVw3zgfJZtM7t8Xa/wnPI1fFrjgTfPeOJh5uUA=
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mazw9983_alvinade_maziltu
DB_USERNAME=root
DB_PASSWORD=

MIDTRANS_SERVER_KEY=SB-Mid-server-kM8hioOK3S2yacxwb2HYlEMc
MIDTRANS_CLIENT_KEY=SB-Mid-client-eshaE1nm4ydfWXzq
MIDTRANS_URL=https://app.sandbox.midtrans.com/snap/snap.js
```

```env
# .env (Frontend OrgOS)
VITE_API_URL=http://localhost:8000/api
```

---

## 6. Status Deployment

### 6.1 Lokal (Development)

| Service | URL | Status |
|---------|-----|--------|
| Laravel Backend | `http://localhost:8000` | ✅ Running |
| OrgOS Frontend | `http://localhost:3000` | ✅ Running |
| MySQL Database | `localhost:3306` | ✅ Running (XAMPP) |

### 6.2 Halaman Laravel (Tested)

| URL | Status | Content |
|-----|--------|--------|
| `/` | 200 OK | Homepage Maziltu Tholiban |
| `/login` | 200 OK | Halaman login admin |
| `/berita` | 200 OK | Daftar berita |
| `/pengumunan` | 200 OK | Daftar pengumuman/event |
| `/tentang-mzt` | 200 OK | Halaman tentang MZT |

### 6.3 Production (Existing)

| Item | Keterangan |
|------|----------|
| Domain | `maziltutholiban.com` |
| Hosting | cPanel shared hosting |
| Database | `mazw9983_alvinade_maziltu` |
| DB User | `mazw9983_ades` |

### 6.4 Cara Menjalankan

**Backend (Laravel):**
```powershell
# Set PATH
$env:PATH = "C:\xampp\php;C:\xampp\mysql\bin;" + $env:PATH

# Clear env vars yang mungkin interfere
Remove-Item Env:DB_USERNAME -ErrorAction SilentlyContinue
Remove-Item Env:DB_PASSWORD -ErrorAction SilentlyContinue

# Start server
cd "D:\MZT APPS\laravel-mzt"
php artisan serve --port=8000 --host=0.0.0.0
```

**Frontend (OrgOS):**
```powershell
cd "D:\MZT APPS\laravel-mzt\_temp_clone"
npm install   # pertama kali saja
npm run dev   # atau: npx vite dev --port 3000 --host
```

---

## 7. Dokumen Terkait

| File | Isi |
|------|-----|
| `docs/PROJECT_OVERVIEW.md` | Analisis lengkap backend Laravel (883 baris) — tech stack, struktur folder, database, routes, controllers, models, storage, known issues, improvement suggestions |
| `docs/INTEGRATION_GUIDE.md` | Panduan integrasi frontend OrgOS + backend Laravel (1114 baris) — data mapping, API endpoints, contoh kode, checklist, timeline |
| `docs/PROJECT_SUMMARY.md` | Dokumen ini — ringkasan keseluruhan project |

### File Konfigurasi Penting

| File | Fungsi |
|------|--------|
| `.env` | Environment variables (DB, Midtrans, app config) |
| `config/cors.php` | CORS settings (perlu update untuk frontend) |
| `config/sanctum.php` | Sanctum API auth settings |
| `config/database.php` | Database connection config |
| `config/filesystems.php` | File storage & symlink config |
| `routes/web.php` | Web routes (halaman publik + admin) |
| `routes/api.php` | API routes (baru 2 endpoint) |

### Perintah Penting

```bash
# Laravel
php artisan serve                    # Start dev server
php artisan migrate                  # Run migrations
php artisan storage:link             # Create storage symlink
php artisan config:clear             # Clear config cache
php artisan cache:clear              # Clear application cache
php artisan route:list               # List all routes
php artisan tinker                   # Interactive REPL

# Frontend
npm install                          # Install dependencies
npm run dev                          # Start dev server (Vite)
npm run build                        # Build for production
npm run lint                         # Run ESLint
npm run format                       # Run Prettier
```

---

## 8. Known Issues & Catatan

### Dari Analisis Backend (30 issues ditemukan)

**Kritis:**
- Kredensial API (Zenziva, Midtrans, DB) terekspos/hardcoded di source code
- MySQL VIEW `event_status` tidak terdokumentasi di migration
- Tidak ada foreign key constraint di database

**Sedang:**
- Banyak typo konsisten: `Prisensi` (seharusnya Presensi), `Complate` (Complete), `Upcomming` (Upcoming), `Chackrole` (CheckRole)
- `$_FILES` digunakan langsung alih-alih `$request->hasFile()`
- Relasi model tidak didefinisikan formal di Eloquent
- Kolom `harga` dan `jatah_edit` tidak ada di migration tapi dipakai di controller

**Minor:**
- Dead code di beberapa controller
- Tidak ada unit/feature test
- Folder `_archive` dan `vendor/` ter-commit

### Rekomendasi Prioritas

1. 🔴 **Pindahkan API key ke `.env`** — Zenziva userkey/passkey masih hardcoded
2. 🔴 **Jangan commit `.env`** — pastikan di `.gitignore`
3. 🟡 **Tambahkan foreign key** di semua migration relasi
4. 🟡 **Formal VIEW ke migration** agar deployment bisa otomatis
5. 🟡 **Tulis unit test** untuk fitur utama
6. 🟢 **Fix typo** di seluruh codebase

---

*Dokumen ini dibuat pada 21 Juni 2026. Tidak ada source code yang diubah selama proses dokumentasi (kecuali fix yang diperlukan untuk menjalankan web secara lokal).*
