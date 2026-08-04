# MZT APPS - Maziltu Tholiban

> Sistem Informasi Manajemen Organisasi Pesantren

![Laravel](https://img.shields.io/badge/Laravel-9.x-FF2D20?style=flat&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=flat&logo=php&logoColor=white)
![React](https://img.shields.io/badge/React-19-61DAFB?style=flat&logo=react&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat&logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/License-Proprietary-blue)

Aplikasi manajemen organisasi pesantren berbasis web untuk mengelola keanggotaan, kegiatan, presensi, keuangan, dan publikasi. Terdiri dari **backend Laravel 9** (production) dan **frontend OrgOS** (React/TypeScript, connected via REST API).

🌐 **Production**: [maziltutholiban.com](https://maziltutholiban.com)

---

## 📋 Daftar Isi

- [Fitur](#-fitur)
- [Tech Stack](#-tech-stack)
- [Struktur Project](#-struktur-project)
- [Instalasi](#-instalasi)
- [Menjalankan Aplikasi](#-menjalankan-aplikasi)
- [Autentikasi](#-autentikasi)
- [API Endpoints](#-api-endpoints)
- [Database](#-database)
- [UI Migration](#-ui-migration-orgos-design-system)
- [Dokumentasi](#-dokumentasi)
- [Roadmap](#️-roadmap)
- [Known Issues](#️-known-issues)

---

## ✨ Fitur

| Modul | Deskripsi |
|-------|-----------|
| 👥 **Manajemen Anggota** | CRUD data anggota, generate ID otomatis, barcode Code39, foto profil, export KTA (PDF) |
| 📅 **Manajemen Event** | CRUD event multi-hari, banner, lokasi, harga, slug URL |
| ✅ **Presensi Kehadiran** | Pencatatan kehadiran per event per tanggal, scan barcode anggota |
| 📰 **Berita & Pengumuman** | CRUD berita dengan slug, foto, tampil di halaman publik |
| 💳 **Pembayaran Online** | Integrasi Midtrans (Snap), verifikasi manual, notifikasi WhatsApp (Zenziva) |
| 🪪 **ID Card Generator** | Upload template, setting posisi komponen (foto/nama/niqobah), cetak ID card |
| 📊 **Dashboard** | Statistik event (Ongoing/Upcoming/Complete), kalender event, total anggota |
| 🎨 **Manajemen Konten** | Edit info pesantren, profil MZT, carousel homepage |
| 📝 **Activity Log** | Audit trail seluruh aktivitas user |
| 🔐 **Role-Based Access** | Hak akses berbasis role (dashboard, anggota, event, presensi, berita, dll) |
| 🌍 **Website Publik** | Homepage, berita, pengumuman, detail event, pembayaran online |
| 🔌 **REST API** | 35+ endpoints untuk integrasi frontend React (Sanctum auth) |

---

## 🛠 Tech Stack

### Backend (Laravel)

| Komponen | Teknologi |
|----------|-----------|
| Framework | **Laravel 9.x** |
| Bahasa | **PHP 8.0+** |
| Database | **MySQL 8** |
| Admin UI | **OrgOS Design System** (custom CSS) + Bootstrap 4 |
| Payment | **Midtrans** (Snap API) |
| WhatsApp | **Zenziva** API |
| API Auth | **Laravel Sanctum** (token-based) |
| PDF | DomPDF, FPDF |
| Barcode | Milon Barcode (Code39) |
| Image | Intervention Image |
| Rich Text | CKEditor |

### Frontend (OrgOS - React)

| Komponen | Teknologi |
|----------|-----------|
| Framework | **TanStack Start** (React 19) |
| UI | **shadcn/ui** + Tailwind CSS v4 |
| Build | Vite 8 + TypeScript 5.8 |
| Auth | Sanctum token (Bearer) |
| State | React Context + localStorage |

---

## 📁 Struktur Project

```
laravel-mzt/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── ApiController.php     ← REST API (35+ endpoints)
│   │   │   ├── admin/               ← 12 Controller admin
│   │   │   └── home/                ← 1 Controller halaman publik
│   │   └── Middleware/
│   │       ├── Chackrole.php         ← Role-based access
│   │       └── RevalidateBackHistory.php
│   ├── Helpers/                      ← DataRangeHelper
│   ├── Models/                       ← 16 Eloquent model
│   ├── Exceptions/Handler.php        ← JSON API error handling
│   └── Providers/
├── config/
│   ├── cors.php                      ← CORS (supports frontend)
│   └── sanctum.php                   ← API token auth
├── database/migrations/              ← 22 migration file
├── public/
│   ├── assets/css/mzt-orgos.css      ← OrgOS Design System (952 baris)
│   ├── assets/                       ← Static assets (CKEditor, DataTables, dll)
│   └── stisla/                       ← Bootstrap 4 framework
├── resources/views/
│   ├── admin/                        ← 16 view admin (OrgOS layout)
│   │   ├── master.blade.php          ← Layout utama (sidebar + topnav)
│   │   └── IdCard/                   ← 3 view ID card
│   ├── home/                         ← 8 view halaman publik
│   └── login_view.blade.php          ← Login page (OrgOS style)
├── routes/
│   ├── web.php                       ← ~60 route (Blade views)
│   └── api.php                       ← 35+ route (REST API)
├── storage/app/public/image/         ← Upload files
├── docs/                             ← Dokumentasi
├── _temp_clone/                      ← Frontend React (OrgOS)
│   ├── src/
│   │   ├── components/               ← UI components
│   │   ├── lib/
│   │   │   ├── api.ts                ← API client utility
│   │   │   └── auth.tsx              ← Auth context
│   │   └── routes/                   ← Page routes
│   └── package.json
└── _archive/                         ← Backup hosting lama
```

---

## 🚀 Instalasi

### Prasyarat

- PHP >= 8.0.2 (ekstensi: BCMath, Ctype, JSON, Mbstring, OpenSSL, PDO, MySQL, cURL, GD)
- Composer 2.x
- MySQL >= 5.7
- Node.js >= 18.x (untuk frontend React)
- npm >= 9.x

### Backend (Laravel)

```bash
# 1. Clone repository
git clone <repository-url> laravel-mzt
cd laravel-mzt

# 2. Install dependencies
composer install

# 3. Setup environment
cp .env.example .env
php artisan key:generate

# 4. Konfigurasi .env
#    Edit DB_DATABASE, DB_USERNAME, DB_PASSWORD
#    Edit MIDTRANS_SERVER_KEY, MIDTRANS_CLIENT_KEY

# 5. Jalankan migration
php artisan migrate

# 6. Buat MySQL VIEW (manual)
mysql -u root -p nama_database -e "
CREATE VIEW event_status AS
SELECT e.*,
  CASE
    WHEN CURDATE() BETWEEN e.tanggal_mulai AND e.tanggal_selesai THEN 'Ongoing'
    WHEN CURDATE() < e.tanggal_mulai THEN 'Upcomming'
    WHEN CURDATE() > e.tanggal_selesai THEN 'Complate'
  END AS status
FROM events e WHERE e.is_active = '1';
"

# 7. Buat storage link
php artisan storage:link

# 8. Jalankan development server
php artisan serve
#    http://localhost:8000        → Halaman publik + Admin (Blade)
#    http://localhost:8000/login  → Login admin
#    http://localhost:8000/api    → REST API
```

### Frontend (React OrgOS)

```bash
# 1. Masuk ke folder frontend
cd _temp_clone

# 2. Install dependencies
npm install

# 3. Jalankan dev server
npm run dev
#    http://localhost:3000 → Frontend React (connected ke API)
```

---

## ▶️ Menjalankan Aplikasi

### Opsi 1: Admin Panel (Blade/Laravel)

```bash
php artisan serve
# Buka: http://localhost:8000/login
```

### Opsi 2: Frontend React (OrgOS)

```bash
# Terminal 1: Backend
php artisan serve

# Terminal 2: Frontend
cd _temp_clone && npm run dev

# Buka: http://localhost:3000
```

---

## 🔐 Autentikasi

### Admin Panel (Blade)
Login menggunakan **`id_anggota`** + **`password`** (bukan email).

| Role | Akses |
|------|-------|
| `dashboard` | Dashboard & kalender |
| `anggota` | CRUD data anggota |
| `profil` | Edit profil sendiri |
| `event` | CRUD event, transaksi, presensi (full) |
| `prisensi` | Lihat event & input presensi |
| `berita` | CRUD berita |
| `tampilan` | Edit konten website |
| `aktivitas_user` | Lihat log aktivitas |
| `id_card` | Manajemen ID card & cetak |

### REST API (Sanctum)
Token-based authentication menggunakan Laravel Sanctum.

```
POST /api/login
Body: { "id_anggota": "1001", "password": "secret" }
Response: { "token": "1|abc123...", "user": {...} }

# Gunakan token di header:
Authorization: Bearer 1|abc123...
```

---

## 🔌 API Endpoints

### Base URL: `http://localhost:8000/api`

### Auth
| Method | Endpoint | Auth | Deskripsi |
|--------|----------|------|-----------|
| POST | `/login` | ❌ | Login (return token) |
| POST | `/logout` | ✅ | Logout (revoke token) |
| GET | `/user` | ✅ | Data user yang login |

### Dashboard
| Method | Endpoint | Auth | Deskripsi |
|--------|----------|------|-----------|
| GET | `/dashboard/stats` | ✅ | Statistik (event, anggota) |
| GET | `/dashboard/calendar` | ✅ | Data kalender event |
| GET | `/dashboard/events` | ✅ | Semua event aktif |

### Members (Anggota)
| Method | Endpoint | Auth | Deskripsi |
|--------|----------|------|-----------|
| GET | `/members` | ✅ | List semua anggota |
| GET | `/members/{id}` | ✅ | Detail anggota |
| POST | `/members` | ✅ | Tambah anggota baru |
| POST | `/members/{id}` | ✅ | Update anggota |
| DELETE | `/members/{id}` | ✅ | Hapus anggota (soft delete) |

### Events
| Method | Endpoint | Auth | Deskripsi |
|--------|----------|------|-----------|
| GET | `/events` | ✅ | List semua event |
| GET | `/events/{id}` | ✅ | Detail event |
| POST | `/events` | ✅ | Tambah event baru |
| POST | `/events/{id}` | ✅ | Update event |
| DELETE | `/events/{id}` | ✅ | Hapus event (soft delete) |

### News (Berita)
| Method | Endpoint | Auth | Deskripsi |
|--------|----------|------|-----------|
| GET | `/news` | ✅ | List semua berita |
| GET | `/news/{id}` | ✅ | Detail berita |
| POST | `/news` | ✅ | Tambah berita baru |
| POST | `/news/{id}` | ✅ | Update berita |
| DELETE | `/news/{id}` | ✅ | Hapus berita |

### Attendance (Presensi)
| Method | Endpoint | Auth | Deskripsi |
|--------|----------|------|-----------|
| GET | `/attendance/{eventId}/{tanggalId}` | ✅ | List presensi per event/tanggal |
| POST | `/attendance` | ✅ | Catat presensi (scan barcode) |

### Transactions
| Method | Endpoint | Auth | Deskripsi |
|--------|----------|------|-----------|
| GET | `/transactions/{eventId}` | ✅ | List transaksi per event |

### Content
| Method | Endpoint | Auth | Deskripsi |
|--------|----------|------|-----------|
| GET | `/carousel` | ❌ | List carousel |
| POST | `/carousel/{id}` | ✅ | Update carousel |
| GET | `/info/pesantren` | ❌ | Info pesantren |
| POST | `/info/pesantren` | ✅ | Update info pesantren |
| GET | `/info/mzt` | ❌ | Info MZT |
| POST | `/info/mzt` | ✅ | Update info MZT |

### Activity Log
| Method | Endpoint | Auth | Deskripsi |
|--------|----------|------|-----------|
| GET | `/activity-log` | ✅ | Semua log aktivitas |
| GET | `/activity-log/{userId}` | ✅ | Log per user |

### Profile
| Method | Endpoint | Auth | Deskripsi |
|--------|----------|------|-----------|
| POST | `/profile` | ✅ | Update profil sendiri |

---

## 🗄️ Database

### 19 Tabel + 1 MySQL VIEW

```
users                    → Data login (id_anggota + password)
data_users               → Profil lengkap anggota
role_user                → Role yang dimiliki user
hak_akses_role           → Mapping role → permission
events                   → Data event
tanggal_events           → Waktu/jam per event
event_status (VIEW)      → Status event (Ongoing/Upcoming/Complate)
prisensi_kehadiran       → Data kehadiran
m_transaksi_events       → Transaksi pembayaran event
beritas                  → Data berita
carosels                 → Gambar carousel
info_pesantrens          → Info tentang pesantren
tentang_mzts             → Info tentang MZT
activitas_logs           → Audit trail
template_id_card         → Template ID card
component_template_id_card → Posisi komponen ID card
personal_access_tokens   → Sanctum API tokens
password_resets          → Password reset tokens
failed_jobs              → Failed queue jobs
migrations               → Laravel migrations
```

### 16 Model Eloquent

| Model | Tabel |
|-------|-------|
| `User` | `users` |
| `DataUser` | `data_users` |
| `RoleUser` | `role_user` |
| `HakAksesRole` | `hak_akses_role` |
| `Event` | `events` |
| `Tanggal_event` | `tanggal_events` |
| `Event_status` | `event_status` (VIEW) |
| `Prisensi_kehadiran` | `prisensi_kehadiran` |
| `Transaksi_event` | `m_transaksi_events` |
| `Berita` | `beritas` |
| `Carosel` | `carosels` |
| `Info_pesantren` | `info_pesantrens` |
| `Tentang_mzt` | `tentang_mzts` |
| `Activitas_log` | `activitas_logs` |
| `TemplateIdCard` | `template_id_card` |
| `ComponentTemplateIdCard` | `component_template_id_card` |

---

## 🎨 UI Migration: OrgOS Design System

### Perubahan yang Dilakukan

| Aspek | Sebelum (Stisla) | Sesudah (OrgOS) |
|-------|------------------|-----------------|
| **CSS Framework** | Stisla (Bootstrap 4 template) | Custom OrgOS CSS + Bootstrap 4 compat |
| **Font** | Default system | **Plus Jakarta Sans** (display) + **Inter** (body) |
| **Warna Primary** | Biru (#6777ef) | **Emerald Green** (#1a8a5c) |
| **Warna Accent** | Tidak ada | **Gold** (#d4a017) |
| **Sidebar** | Stisla sidebar | Modern collapsible + footer card |
| **Top Nav** | Stisla navbar | Sticky topbar: toggle, search, theme, user dropdown |
| **Dark Mode** | ❌ | ✅ Toggle + persist localStorage |
| **Cards** | `.card` | Rounded-2xl + hover shadow + glow effects |
| **Tables** | DataTables default | Custom DataTables overrides |
| **Buttons** | `.btn-primary` | `.mzt-btn` + variants |
| **Responsive** | Basic | Slide-out sidebar + overlay + grid breakpoints |

### File yang Dimodifikasi (UI Migration)

| # | File | Deskripsi |
|---|------|-----------|
| 1 | `public/assets/css/mzt-orgos.css` | Design system CSS (952 baris) |
| 2 | `resources/views/admin/master.blade.php` | Layout utama (sidebar + topnav) |
| 3 | `resources/views/login_view.blade.php` | Login page |
| 4 | `resources/views/admin/dashboard.blade.php` | Dashboard |
| 5 | `resources/views/admin/tabel_anggota.blade.php` | Anggota |
| 6 | `resources/views/admin/event.blade.php` | Event |
| 7 | `resources/views/admin/detail_event.blade.php` | Detail Event |
| 8 | `resources/views/admin/tabel_berita.blade.php` | Berita |
| 9 | `resources/views/admin/tabel_event_prisensi.blade.php` | List Presensi |
| 10 | `resources/views/admin/event_transaksi.blade.php` | List Transaksi |
| 11 | `resources/views/admin/tabel_transaksi.blade.php` | Detail Transaksi |
| 12 | `resources/views/admin/prisensi.blade.php` | Scan Presensi |
| 13 | `resources/views/admin/profil.blade.php` | Profil |
| 14 | `resources/views/admin/view_carosel.blade.php` | Carousel |
| 15 | `resources/views/admin/view_tenteng_pondok.blade.php` | Tentang Pesantren |
| 16 | `resources/views/admin/view_tenteng_mzt.blade.php` | Tentang MZT |
| 17 | `resources/views/admin/aktivitas_user.blade.php` | Activity Log |
| 18 | `resources/views/admin/detail_aktivitas_log.blade.php` | Detail Log |
| 19 | `resources/views/admin/IdCard/index.blade.php` | ID Card |
| 20 | `resources/views/admin/IdCard/component_id_card.blade.php` | ID Card Layout |

### File yang Dibuat (Frontend React)

| # | File | Deskripsi |
|---|------|-----------|
| 1 | `_temp_clone/src/lib/api.ts` | API client utility |
| 2 | `_temp_clone/src/lib/auth.tsx` | Auth context provider |
| 3 | `_temp_clone/src/routes/login.tsx` | Login page |
| 4 | `_temp_clone/src/routes/_app.tsx` | Auth guard layout |
| 5 | `_temp_clone/src/routes/__root.tsx` | Root with AuthProvider |
| 6 | `_temp_clone/src/routes/_app.index.tsx` | Dashboard (API) |
| 7 | `_temp_clone/src/routes/_app.members.tsx` | Members (API) |
| 8 | `_temp_clone/src/routes/_app.events.tsx` | Events (API) |
| 9 | `_temp_clone/src/routes/_app.news.tsx` | News (API) |
| 10 | `_temp_clone/src/routes/_app.activity-log.tsx` | Activity Log (API) |
| 11 | `_temp_clone/src/routes/_app.carousel.tsx` | Carousel (API) |
| 12 | `_temp_clone/src/routes/_app.about.pesantren.tsx` | About Pesantren (API) |
| 13 | `_temp_clone/src/routes/_app.about.mzt.tsx` | About MZT (API) |
| 14 | `_temp_clone/src/routes/_app.profile.tsx` | Profile (API) |
| 15 | `_temp_clone/src/routes/_app.transactions.tsx` | Transactions |
| 16 | `_temp_clone/src/routes/_app.attendance.tsx` | Attendance |
| 17 | `_temp_clone/src/routes/_app.id-cards.tsx` | ID Cards |
| 18 | `_temp_clone/src/components/top-nav.tsx` | Top nav (real user + logout) |

### File yang Dibuat (Backend API)

| # | File | Deskripsi |
|---|------|-----------|
| 1 | `app/Http/Controllers/ApiController.php` | 35+ API endpoints |
| 2 | `routes/api.php` | API route definitions |
| 3 | `config/cors.php` | CORS configuration (updated) |
| 4 | `app/Exceptions/Handler.php` | JSON error handling (updated) |

---

## 📄 Dokumentasi

| Dokumen | Deskripsi |
|---------|-----------|
| [`docs/PROJECT_SUMMARY.md`](docs/PROJECT_SUMMARY.md) | Ringkasan keseluruhan project |
| [`docs/PROJECT_OVERVIEW.md`](docs/PROJECT_OVERVIEW.md) | Analisis mendalam backend Laravel |
| [`docs/INTEGRATION_GUIDE.md`](docs/INTEGRATION_GUIDE.md) | Panduan integrasi frontend + backend |

---

## 🗺️ Roadmap

- [x] Backend Laravel (production-ready)
- [x] Payment gateway (Midtrans)
- [x] WhatsApp notification (Zenziva)
- [x] ID Card generator
- [x] Role-based access control
- [x] UI Migration ke OrgOS Design System
- [x] REST API endpoints (35+ endpoints)
- [x] Sanctum token authentication
- [x] Frontend OrgOS (React) - connected ke API
- [ ] Modul Campaign & Donasi
- [ ] Modul Certificates
- [ ] Modul Broadcast (WA/Email management)
- [ ] Unit & feature tests
- [ ] CI/CD pipeline
- [ ] Create/Update/Delete modals di frontend React
- [ ] File upload di frontend React

---

## ⚠️ Known Issues

1. MySQL VIEW `event_status` belum ada di migration — harus dibuat manual
2. Zenziva API key masih hardcoded di source code — sebaiknya dipindah ke `.env`
3. Relasi Eloquent tidak didefinisikan formal — pakai query join manual
4. Tidak ada foreign key constraint di database
5. Tidak ada unit/feature test
6. Frontend React belum implementasi CRUD modals (masih read-only)
7. File upload dari frontend React belum diimplementasi

---

## 📌 Environment Variables

```env
APP_NAME=Laravel
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nama_database
DB_USERNAME=root
DB_PASSWORD=

MIDTRANS_SERVER_KEY=your-server-key
MIDTRANS_CLIENT_KEY=your-client-key
MIDTRANS_URL=https://app.sandbox.midtrans.com/snap/snap.js

SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000,127.0.0.1,127.0.0.1:8000
```

---

## 🏗️ 12 Controller Admin

| # | Controller | Fungsi |
|---|-----------|--------|
| 1 | `Dashboard.php` | Dashboard + Calendar event |
| 2 | `Login.php` | Login/Logout (pakai ID Anggota) |
| 3 | `C_Anggota.php` | CRUD Anggota + export KTA PDF |
| 4 | `C_profil.php` | Edit profil sendiri |
| 5 | `C_Event.php` | CRUD Event (multi-hari) |
| 6 | `C_event_detail.php` | CRUD waktu/jam per event |
| 7 | `C_prisensi.php` | Scan barcode presensi |
| 8 | `C_Berita.php` | CRUD Berita |
| 9 | `C_Tampilan.php` | Edit carousel, info pesantren, info MZT |
| 10 | `C_transaksi.php` | Kelola transaksi event (Midtrans) |
| 11 | `C_Aktivitas_log.php` | Lihat log aktivitas user |
| 12 | `C_ID_Card.php` | Kelola template & cetak ID Card |

---

## 📝 License

Proprietary — All rights reserved.