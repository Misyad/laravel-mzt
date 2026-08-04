# MZT APPS — Maziltu Tholiban

> Sistem Informasi Manajemen Organisasi Pesantren

![Laravel](https://img.shields.io/badge/Laravel-9.x-FF2D20?style=flat&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=flat&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat&logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/License-Proprietary-blue)

Aplikasi manajemen organisasi pesantren berbasis web untuk mengelola keanggotaan, kegiatan, presensi, keuangan, dan publikasi. Terdiri dari **backend Laravel 9** (production) dan **frontend prototype OrgOS** (React/TypeScript, dalam pengembangan).

🌐 **Production**: [maziltutholiban.com](https://maziltutholiban.com)

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

---

## 🛠 Tech Stack

| Komponen | Teknologi |
|----------|-----------|
| Framework | **Laravel 9.x** |
| Bahasa | **PHP 8.0+** |
| Database | **MySQL 8** |
| Admin UI | **Stisla** (Bootstrap 4) |
| Payment | **Midtrans** (Snap API) |
| WhatsApp | **Zenziva** API |
| PDF | DomPDF, FPDF |
| Barcode | Milon Barcode (Code39) |
| Image | Intervention Image |
| Rich Text | CKEditor |

### Frontend Prototype (OrgOS — Dalam Pengembangan)

| Komponen | Teknologi |
|----------|-----------|
| Framework | **TanStack Start** (React 19) |
| UI | **shadcn/ui** + Tailwind CSS v4 |
| Build | Vite 8 + TypeScript 5.8 |
| Repo | [github.com/Misyad/bloom-unity-flow](https://github.com/Misyad/bloom-unity-flow) |
---

## 📁 Struktur Project

```
laravel-mzt/
├── app/
│   ├── Http/Controllers/
│   │   ├── admin/          # 12 controller panel admin
│   │   └── home/           # 1 controller halaman publik
│   ├── Helpers/            # DataRangeHelper (dateRange, activityLog, sendWa)
│   ├── Http/Middleware/    # Chackrole, RevalidateBackHistory
│   ├── Models/             # 16 Eloquent model
│   └── Providers/          # DataPickerServiceProvider
├── config/                 # 14 file konfigurasi
├── database/migrations/    # 22 migration file
├── public/                 # Web root + static assets (Stisla, CKEditor, DataTables)
├── resources/views/
│   ├── admin/              # 16 view admin + IdCard (3 view)
│   └── home/               # 8 view halaman publik
├── routes/
│   ├── web.php             # Route utama (~60 route)
│   └── api.php             # Route API (2 endpoint)
├── storage/app/public/image/  # Upload files (anggota, barcode, berita, event, dll)
├── docs/                   # Dokumentasi project
└── _temp_clone/            # Frontend prototype (OrgOS)
```

---

## 🚀 Instalasi

### Prasyarat

- PHP >= 8.0.2 (ekstensi: BCMath, Ctype, JSON, Mbstring, OpenSSL, PDO, MySQL, cURL, GD)
- Composer 2.x
- MySQL >= 5.7
- Node.js >= 14.x (opsional, untuk build asset)

### Langkah-langkah

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
#    http://localhost:8000        -> Halaman publik
#    http://localhost:8000/login  -> Login admin
```

---

## 🔐 Autentikasi

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

---

## 🗄️ Database

19 tabel + 1 MySQL VIEW:

```
users · data_users · role_user · hak_akses_role
events · tanggal_events · event_status (VIEW)
prisensi_kehadiran · m_transaksi_events
beritas · carosels · info_pesantrens · tentang_mzts
activitas_logs · template_id_card · component_template_id_card
personal_access_tokens · password_resets · failed_jobs · migrations
```

Detail relasi dan field: lihat [`docs/PROJECT_OVERVIEW.md`](docs/PROJECT_OVERVIEW.md)

---

## 📄 Dokumentasi

| Dokumen | Deskripsi |
|---------|-----------|
| [`docs/PROJECT_SUMMARY.md`](docs/PROJECT_SUMMARY.md) | Ringkasan keseluruhan project |
| [`docs/PROJECT_OVERVIEW.md`](docs/PROJECT_OVERVIEW.md) | Analisis mendalam backend Laravel (883 baris) |
| [`docs/INTEGRATION_GUIDE.md`](docs/INTEGRATION_GUIDE.md) | Panduan integrasi OrgOS frontend + Laravel backend (1114 baris) |

---

## 🗺️ Roadmap

- [x] Backend Laravel (production-ready)
- [x] Payment gateway (Midtrans)
- [x] WhatsApp notification (Zenziva)
- [x] ID Card generator
- [x] Role-based access control
- [ ] REST API endpoints (~35 endpoint baru)
- [ ] Sanctum token authentication
- [ ] Frontend OrgOS (React) — prototype ready, perlu integrasi
- [ ] Modul Campaign & Donasi
- [ ] Modul Certificates
- [ ] Modul Broadcast (WA/Email management)
- [ ] Unit & feature tests
- [ ] CI/CD pipeline

---

## ⚠️ Known Issues

1. MySQL VIEW `event_status` belum ada di migration — harus dibuat manual
2. Zenziva API key masih hardcoded di source code — sebaiknya dipindah ke `.env`
3. Relasi Eloquent tidak didefinisikan formal — pakai query join manual
4. Tidak ada foreign key constraint di database
5. Tidak ada unit/feature test

Detail lengkap 30 issues: lihat [`docs/PROJECT_OVERVIEW.md`](docs/PROJECT_OVERVIEW.md#12-known-issues)

---

## 📌 Environment Variables

```env
APP_NAME=Laravel
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nama_database
DB_USERNAME=root
DB_PASSWORD=

MIDTRANS_SERVER_KEY=your-server-key
MIDTRANS_CLIENT_KEY=your-client-key
MIDTRANS_URL=https://app.sandbox.midtrans.com/snap/snap.js
```

---

## 📝 License

Proprietary — All rights reserved.
