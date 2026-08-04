# Project Overview — MZT APPS (Maziltu Tholiban)

> **Ringkasan Eksekutif**
>
> MZT APPS (Maziltu Tholiban) adalah sistem informasi manajemen organisasi pesantren berbasis web yang dibangun dengan **Laravel 9** (PHP 8.0+). Aplikasi ini mengelola keanggotaan, event/kegiatan, presensi kehadiran, berita & pengumuman, pembayaran online (Midtrans), ID Card anggota, serta konten website publik (carousel, info pesantren, profil MZT). Aplikasi memiliki dua sisi: **halaman publik** (tanpa login) dan **panel admin** (dengan autentikasi & otorisasi berbasis role). Notifikasi pembayaran dikirim via WhatsApp menggunakan layanan Zenziva. Aplikasi di-deploy di cPanel hosting dengan domain `maziltutholiban.com`.

---

## Daftar Isi

1. [Ringkasan Project](#1-ringkasan-project)
2. [Tech Stack](#2-tech-stack)
3. [Struktur Folder](#3-struktur-folder)
4. [Arsitektur Aplikasi](#4-arsitektur-aplikasi)
5. [Database Analysis](#5-database-analysis)
6. [Route Analysis](#6-route-analysis)
7. [Controller Analysis](#7-controller-analysis)
8. [Model Analysis](#8-model-analysis)
9. [Storage & Upload Analysis](#9-storage--upload-analysis)
10. [Environment Requirements](#10-environment-requirements)
11. [Installation Guide](#11-installation-guide)
12. [Known Issues](#12-known-issues)
13. [Improvement Suggestions](#13-improvement-suggestions)

---

## 1. Ringkasan Project

| Item | Keterangan |
|------|-----------|
| **Nama Project** | MZT APPS (Maziltu Tholiban) |
| **Jenis Aplikasi** | Laravel 9 — Web Application (Monolithic, Server-Side Rendering) |
| **Tujuan** | Sistem informasi manajemen organisasi pesantren untuk mengelola keanggotaan, kegiatan, keuangan, dan publikasi |
| **Domain Produksi** | `maziltutholiban.com` |

### Fitur Utama

| No | Fitur | Deskripsi |
|----|-------|-----------|
| 1 | **Manajemen Anggota** | CRUD data anggota, generate ID anggota otomatis, barcode, foto profil, export KTA |
| 2 | **Manajemen Event** | CRUD event/kegiatan dengan rentang tanggal, banner, lokasi, harga, slug URL |
| 3 | **Presensi Kehadiran** | Pencatatan kehadiran per event per tanggal, scan barcode anggota |
| 4 | **Berita & Pengumuman** | CRUD berita dengan slug, foto, dan deskripsi; tampil di halaman publik |
| 5 | **Transaksi & Pembayaran** | Pembayaran online via Midtrans (Snap), verifikasi manual oleh admin, notifikasi WhatsApp |
| 6 | **ID Card Generator** | Upload template ID card, setting posisi komponen (foto, nama, niqobah), cetak ID card |
| 7 | **Dashboard** | Statistik event (Ongoing/Upcoming/Complete), kalender event, total anggota |
| 8 | **Manajemen Konten** | Edit info pesantren, profil MZT, carousel homepage |
| 9 | **Activity Log** | Pencatatan seluruh aktivitas user (halaman yang dibuka, aksi yang dilakukan) |
| 10 | **Role-Based Access** | Sistem hak akses berbasis role (dashboard, anggota, event, presensi, berita, tampilan, dll) |
| 11 | **Website Publik** | Homepage, halaman berita, pengumuman/event, detail berita, halaman pembayaran |
| 12 | **Profil Anggota** | Edit profil sendiri dengan batas jatah edit (maks 3 kali) |
---

## 2. Tech Stack

### Framework & Bahasa

| Komponen | Teknologi |
|----------|----------|
| **Framework** | Laravel 9.x (`laravel/framework: ^9.19`) |
| **Bahasa** | PHP `^8.0.2` |
| **Template Engine** | Blade (Laravel) |
| **Frontend CSS** | Bootstrap 4 (via Stisla), AOS, custom CSS |
| **Frontend JS** | jQuery, Vite (build tool) |

### Database

| Komponen | Teknologi |
|----------|----------|
| **Database** | MySQL (via `mysql` driver) |
| **ORM** | Eloquent ORM |
| **Migration** | Laravel Migration (22 file) |

### Library Penting (Composer)

| Package | Fungsi |
|---------|--------|
| `barryvdh/laravel-dompdf ^2.0` | Generate PDF (KTA, ID Card) |
| `intervention/image ^2.7` | Manipulasi & resize gambar |
| `milon/barcode ^10.0` | Generate barcode Code39 untuk ID anggota |
| `midtrans/midtrans-php ^2.5` | Payment gateway integration |
| `setasign/fpdf ^1.8` | Library PDF tambahan |
| `guzzlehttp/guzzle ^7.8` | HTTP client (API calls) |
| `laravel/sanctum ^3.0` | API authentication |
| `laravel/tinker ^2.7` | REPL untuk debugging |

### Build Tools & Dev Dependencies

| Tool | Fungsi |
|------|--------|
| **Vite 4** | Asset bundler (JS/CSS) |
| **Laravel Vite Plugin** | Integrasi Vite dengan Laravel |
| **Axios** | HTTP client untuk frontend |
| **PostCSS** | CSS processing |
| **PHPUnit 9** | Unit testing |
| **Faker** | Data dummy untuk testing |
| **Laravel Pint** | Code style fixer |
| **Spatie Ignition** | Error page |

### Frontend Assets (Public)

| Asset | Keterangan |
|-------|------------|
| **Stisla** | Admin dashboard template (Bootstrap 4 based) |
| **CKEditor** | Rich text editor untuk konten berita/event |
| **DataTables** | Tabel interaktif dengan sorting, searching, pagination |
| **SweetAlert** | Dialog/popup notifikasi |
| **Datepicker/Datetimepicker** | Input tanggal & waktu |
| **AOS** | Animate On Scroll (halaman publik) |
| **GLightbox** | Lightbox gallery |
| **Swiper** | Slider/carousel |
| **PureCounter** | Counter animation |

### Layanan Eksternal

| Layanan | Fungsi |
|---------|--------|
| **Midtrans (Sandbox)** | Payment gateway - Snap API |
| **Zenziva** | WhatsApp API untuk notifikasi pembayaran |
| **cPanel Hosting** | Deployment environment |

---

## 3. Struktur Folder

```
laravel-mzt/
+-- app/                          # Logika inti aplikasi
|   +-- Console/
|   |   +-- Kernel.php            # Penjadwalan task artisan
|   +-- Exceptions/
|   |   +-- Handler.php           # Handler exception global
|   +-- Helpers/
|   |   +-- DataRangeHelper.php   # Helper: date range, activity log, kirim WA
|   +-- Http/
|   |   +-- Controllers/
|   |   |   +-- admin/            # 12 controller panel admin
|   |   |   +-- home/             # 1 controller halaman publik
|   |   +-- Kernel.php            # Registrasi middleware
|   |   +-- Middleware/           # 10 middleware (custom: Chackrole, RevalidateBackHistory)
|   +-- Models/                   # 16 Eloquent model
|   +-- Providers/                # 6 service provider (custom: DataPickerServiceProvider)
|
+-- bootstrap/                    # Bootstrap Laravel (app.php, cache)
+-- config/                       # Konfigurasi aplikasi (14 file)
+-- database/                     # Migration (22 file), seeder, factory
+-- docs/                         # Dokumentasi project
+-- lang/                         # File bahasa (en)
+-- public/                       # Web root (entry point)
|   +-- index.php                 # Entry point aplikasi
|   +-- assets/                   # Static assets (CSS, JS, CKEditor, DataTables, dll)
|   +-- build/                    # Vite build output
|   +-- stisla/                   # Stisla admin template assets
|
+-- resources/                    # Resource aplikasi
|   +-- css/                      # CSS source
|   +-- js/                       # JS source
|   +-- views/                    # Blade templates
|       +-- admin/                # 16 view admin + folder IdCard (3 view)
|       +-- home/                 # 8 view halaman publik
|       +-- kta.blade.php         # Template KTA
|       +-- login_view.blade.php  # Halaman login
|
+-- routes/                       # Definisi route
|   +-- web.php                   # Route web (utama)
|   +-- api.php                   # Route API
|
+-- storage/                      # Penyimpanan file
|   +-- app/public/image/         # File upload (linked ke public/storage)
|   |   +-- anggota/              # Foto anggota
|   |   +-- barcode/              # Barcode ID anggota
|   |   +-- berita/               # Foto berita
|   |   +-- carosel/              # Foto carousel
|   |   +-- event/                # Banner event
|   |   +-- id-card/              # Template ID card
|   |   +-- mzt/                  # Foto MZT
|   |   +-- pesantren/            # Foto pesantren
|   +-- framework/                # Cache, sessions, views compiled
|   +-- logs/                     # Log file aplikasi
|
+-- tests/                        # Testing (Feature & Unit)
+-- vendor/                       # Composer dependencies
+-- _archive/                     # Arsip deployment & backup
|   +-- cpanel/                   # Backup konfigurasi cPanel
|   +-- database/mysql.sql        # Backup database SQL
|   +-- hosting_files/            # File hosting backup
|   +-- logs/                     # Log arsip
|
+-- .env                          # Environment variables
+-- .env.example                  # Template environment
+-- artisan                       # Laravel CLI
+-- composer.json                 # Dependensi PHP
+-- package.json                  # Dependensi Node.js
+-- phpunit.xml                   # Konfigurasi PHPUnit
+-- README.md                     # Dokumentasi default Laravel
```

### Penjelasan Folder Utama

| Folder | Fungsi |
|--------|--------|
| `app/` | Berisi seluruh logika bisnis: Controllers, Models, Middleware, Helpers, Providers |
| `bootstrap/` | File bootstrap Laravel untuk inisialisasi framework |
| `config/` | Seluruh file konfigurasi aplikasi (database, mail, filesystem, dll) |
| `database/` | Migration, seeder, dan factory untuk manajemen database |
| `public/` | Web root - file yang bisa diakses langsung oleh browser (CSS, JS, gambar statis) |
| `resources/` | View templates (Blade), CSS source, JS source |
| `routes/` | Definisi seluruh URL routing aplikasi |
| `storage/` | Penyimpanan file upload, cache, session, log, dan compiled views |
| `tests/` | Unit test dan feature test |
| `vendor/` | Seluruh dependency yang diinstall via Composer |
| `_archive/` | Arsip backup cPanel, database SQL dump, dan file hosting |
| `docs/` | Dokumentasi project |

---

## 4. Arsitektur Aplikasi

### Diagram Arsitektur Umum

```
+------------------------------------------------------------+
|                     BROWSER (Client)                        |
|   +-------------------+    +---------------------------+    |
|   |  Halaman Publik   |    |   Panel Admin (Stisla)    |    |
|   |  (Bootstrap+AOS)  |    |   (Bootstrap 4 Admin)     |    |
|   +--------+----------+    +-------------+-------------+    |
+------------|----------------------------|-------------------+
             | HTTP Requests              | HTTP Requests
             v                            v
+------------------------------------------------------------+
|                    LARAVEL 9 (PHP 8.0+)                     |
|                                                             |
|  +---------+  +--------------+  +--------------------+      |
|  | Routes  |->|  Middleware  |->|   Controllers      |      |
|  |(web.php)|  | (Chackrole,  |  | (admin/ & home/)   |      |
|  |(api.php)|  |  Revalidate) |  |                    |      |
|  +---------+  +--------------+  +---------+----------+      |
|                                            |                |
|  +-----------------------------------------+-----------+    |
|  |              Models (Eloquent ORM)                  |    |
|  |  User, DataUser, Event, Berita,                     |    |
|  |  Transaksi_event, Prisensi_kehadiran,               |    |
|  |  RoleUser, HakAksesRole, dll.                       |    |
|  +----------------------------------------------------+    |
|                                            |                |
|  +-------------+  +------------------------+-----------+    |
|  |   Helpers    |  |   External Services               |    |
|  | DataPicker   |  |  +----------+ +------------------+|    |
|  | (dateRange,  |  |  | Midtrans | |  Zenziva WA      ||    |
|  |  aktivitas,  |  |  | (Payment)| |  (Notification)  ||    |
|  |  sendWa)     |  |  +----------+ +------------------+|    |
|  +-------------+  +------------------------------------+    |
+------------------------------------------------------------+
             |
             v
+------------------------------------------------------------+
|                    MySQL DATABASE                           |
|  users, data_users, role_user, hak_akses_role,              |
|  events, tanggal_events, event_status (VIEW),               |
|  beritas, carosels, info_pesantrens, tentang_mzts,          |
|  prisensi_kehadiran, m_transaksi_events,                    |
|  aktivitas_logs, template_id_card,                          |
|  component_template_id_card                                 |
+------------------------------------------------------------+
             |
             v
+------------------------------------------------------------+
|                  FILE STORAGE                               |
|  storage/app/public/image/                                  |
|    +-- anggota/    (foto anggota, resize 300x400)           |
|    +-- barcode/    (barcode PNG Code39)                     |
|    +-- berita/     (foto berita)                            |
|    +-- carosel/    (foto carousel)                          |
|    +-- event/      (banner event)                           |
|    +-- id-card/    (template ID card)                       |
|    +-- mzt/        (foto tentang MZT)                       |
|    +-- pesantren/  (foto pesantren)                         |
|                                                             |
|  public/storage -> symlink -> storage/app/public            |
+------------------------------------------------------------+
```

### Frontend

- **Halaman Publik**: Menggunakan template kustom dengan Bootstrap, AOS (Animate On Scroll), GLightbox, Swiper, PureCounter. View berada di `resources/views/home/`.
- **Panel Admin**: Menggunakan template **Stisla** (admin dashboard Bootstrap 4). Aset Stisla berada di `public/stisla/`. View berada di `resources/views/admin/`.
- **Rich Text Editor**: CKEditor digunakan untuk input konten berita dan deskripsi event.
- **DataTables**: Digunakan di hampir semua tabel admin untuk sorting, searching, dan pagination.

### Backend

- **Framework**: Laravel 9 dengan arsitektur MVC (Model-View-Controller).
- **Controller**: Terbagi menjadi 2 namespace - `admin/` (12 controller) dan `home/` (1 controller).
- **Helper**: `DataRangeHelper` yang di-register via `DataPickerServiceProvider` sebagai facade `\DataPicker`. Menyediakan 3 fungsi: `dateRange()`, `activitas_log()`, `sendWa()`.
- **Middleware Custom**: `Chackrole` (otorisasi berbasis role) dan `RevalidateBackHistory` (mencegah cache halaman setelah logout).

### Database

- **Engine**: MySQL
- **ORM**: Eloquent dengan 16 model
- **Migration**: 22 file migration
- **View**: `event_status` - MySQL VIEW yang menghitung status event (Ongoing/Upcomming/Complate) berdasarkan tanggal_mulai dan tanggal_selesai

### API

- **API Routes** (`routes/api.php`):
  - `GET /api/user` - Mendapatkan user yang terautentikasi (middleware: `auth:sanctum`)
  - `POST /api/transaksi/pembayaran/hendle-payment` - Webhook handler untuk notifikasi pembayaran Midtrans

### Authentication

- **Driver**: Laravel default session-based authentication
- **Login**: Menggunakan field `id_anggota` (bukan email) + `password`
- **Otorisasi**: Middleware `Chackrole` memeriksa tabel `hak_akses_role` untuk menentukan apakah user memiliki akses ke route tertentu
- **Role tersedia**: `dashboard`, `anggota`, `event`, `prisensi`, `berita`, `tampilan`, `aktivitas_user`, `id_card`, `profil`
- **Session**: File-based (default), lifetime 120 menit

### File Storage

- **Driver**: Local filesystem
- **Public Disk**: `storage/app/public/` -> symlink ke `public/storage`
- **Upload Path**: `storage/app/public/image/{kategori}/`
- **Resize**: Intervention Image digunakan untuk resize foto anggota ke 300x400px

---

## 5. Database Analysis

### Daftar Tabel

| No | Nama Tabel | Migration File | Deskripsi |
|----|-----------|---------------|----------|
| 1 | `users` | `2014_10_12_000000` | Data autentikasi user (id_anggota, name, email, password, is_active) |
| 2 | `password_resets` | `2014_10_12_100000` | Token reset password |
| 3 | `failed_jobs` | `2019_08_19_000000` | Log job yang gagal |
| 4 | `personal_access_tokens` | `2019_12_14_000001` | Token API (Sanctum) |
| 5 | `role_user` | `2023_06_28_023034` | Master data role (nama_role, keterangan, is_active) |
| 6 | `hak_akses_role` | `2023_06_28_023250` | Mapping hak akses user ke role (id_users, nama_role, hak_akses) |
| 7 | `data_users` | `2023_06_28_025308` | Detail data anggota (alamat, no_hp, barcode, foto, niqobah, pekerjaan, tanggal_lahir, tahun_masuk, tahun_keluar, tempat_lahir) |
| 8 | `events` | `2023_07_05_040619` | Data event (judul, deskripsi, tanggal, lokasi, harga, slug, banner, tanggal_mulai, tanggal_selesai) |
| 9 | `tanggal_events` | `2023_07_05_040831` | Detail tanggal per event (id_event, tanggal, jam_mulai, jam_selesai, set_jam) |
| 10 | `prisensi_kehadiran` | `2023_07_05_040953` | Data kehadiran/presensi (id_event, id_tanggal, id_anggota, id_user, tanggal_kehadiran, jam_kehadiran) |
| 11 | `beritas` | `2023_07_08_033826` | Data berita (judul, deskripsi, foto, slug, create_at, edit_at) |
| 12 | `carosels` | `2023_07_10_034845` | Data carousel homepage (judul, deskripsi, foto) |
| 13 | `info_pesantrens` | `2023_07_10_035458` | Info pesantren (judul, deskripsi, alamat, foto, telpon, email) |
| 14 | `tentang_mzts` | `2023_07_10_040726` | Info tentang MZT (judul, deskripsi, alamat, foto, telpon, email) |
| 15 | `activitas_logs` | `2023_07_12_032815` | Log aktivitas user (subject, url, method, agent, user_id) |
| 16 | `m_transaksi_events` | `2023_10_23_085814` | Transaksi pembayaran event (id_anggota, id_event, order_id, gross_amount, payment_type, transaction_status, snaptoken, dll) |
| 17 | `template_id_card` | `2023_10_28_042538` | Template gambar ID card (path, status: ACTIVE/NON-ACTIVE) |
| 18 | `component_template_id_card` | `2023_10_28_042539` | Komponen layout ID card (id_template, title: Photo/Name/Niqobah, position_x, position_y) |
| 19 | `event_status` | *(MySQL VIEW)* | View yang menambah kolom status (Ongoing/Upcomming/Complate) berdasarkan tanggal event |

### Diagram Relasi Antar Tabel

```
+--------------+       1:1        +--------------+
|    users     |------------------|  data_users   |
|              |  users.id =      |              |
| id (PK)      |  data_users.     | id (PK)      |
| id_anggota   |  id_users        | id_users (FK)|
| name         |                  | no_hp        |
| email        |                  | barcode      |
| password     |                  | alamat       |
| is_active    |                  | niqobah      |
| jatah_edit   |                  | pekerjaan    |
+------+-------+                  | foto         |
       |                          | tanggal_lahir|
       | 1:N                      | tahun_masuk  |
       | users.id =               | tahun_keluar |
       | hak_akses_role.id_users  | tempat_lahir |
       v                          +--------------+
+------------------+
|  hak_akses_role  |     N:1     +--------------+
|                  |-------------|  role_user    |
| id (PK)          |  nama_role  |              |
| id_users (FK)    |             | id (PK)      |
| nama_role        |             | nama_role    |
| hak_akses        |             | keterangan   |
+------------------+             | is_active    |
                                 +--------------+

+--------------+       1:N        +------------------+
|    events    |------------------|  tanggal_events   |
|              | events.id =      |                  |
| id (PK)      | tanggal_events.  | id (PK)          |
| judul_event  | id_event         | id_event (FK)    |
| deskripsi    |                  | tanggal          |
| tanggal      |                  | jam_mulai        |
| lokasi       |                  | jam_selesai      |
| harga        |                  | set_jam           |
| slug         |                  +------------------+
| banner       |
| tanggal_mulai|       1:N        +----------------------+
| tgl_selesai  |------------------|  prisensi_kehadiran   |
| is_active    | events.id =     |                      |
+--------------+ prisensi.       | id (PK)              |
       |         id_event         | id_event (FK)        |
       |                          | id_tanggal (FK)      |
       |         1:N              | id_anggota           |
       |--------------------------| id_user              |
       | events.id =              | tanggal_kehadiran    |
       | m_transaksi_events.      | jam_kehadiran        |
       | id_event                 +----------------------+
       v
+----------------------+
| m_transaksi_events   |
|                      |
| id (PK)              |
| id_anggota           |-----> users.id_anggota
| id_event (FK)        |
| order_id             |
| gross_amount         |
| payment_type         |
| transaction_status   |
| snaptoken            |
| transaction_time     |
+----------------------+

+--------------+    +------------------+    +-----------------+
|template_id   |1:N | component_       |    | info_pesantrens |
|_card         |----| template_id_card |    |                 |
| id (PK)      |    | id_template (FK) |    | id (PK)         |
| path         |    | title            |    | judul, deskripsi|
| status       |    | position_x, y    |    | alamat, foto    |
+--------------+    +------------------+    | telpon, email   |
                                            +-----------------+
+--------------+    +--------------+
|   beritas    |    | tentang_mzts |
|              |    |              |
| id (PK)      |    | id (PK)      |
| judul        |    | judul        |
| deskripsi    |    | deskripsi    |
| foto         |    | alamat       |
| slug         |    | foto         |
| create_at    |    | telpon       |
| edit_at      |    | email        |
| is_active    |    +--------------+
+--------------+
+------------------+
|  aktivitas_logs  |
|                  |
| id (PK)          |
| subject          |
| url              |
| method           |
| agent            |
| user_id          |
+------------------+
```

### Fungsi Tiap Tabel

| Tabel | Fungsi |
|-------|--------|
| `users` | Menyimpan kredensial login dan data dasar user (nama, email, password, id_anggota) |
| `data_users` | Menyimpan detail profil anggota (alamat, foto, barcode, niqobah, pekerjaan, dll) |
| `role_user` | Master data role yang tersedia dalam sistem |
| `hak_akses_role` | Mapping akses user ke role tertentu - menentukan menu apa yang bisa diakses |
| `events` | Menyimpan data event/kegiatan termasuk judul, deskripsi, harga, lokasi, banner |
| `tanggal_events` | Memecah event multi-hari menjadi record per tanggal, dengan opsi jam mulai/selesai |
| `prisensi_kehadiran` | Mencatat kehadiran anggota di setiap tanggal event |
| `beritas` | Menyimpan data berita/artikel yang ditampilkan di halaman publik |
| `carosels` | Menyimpan gambar carousel untuk homepage |
| `info_pesantrens` | Menyimpan informasi pesantren yang ditampilkan di halaman publik |
| `tentang_mzts` | Menyimpan informasi tentang organisasi MZT |
| `activitas_logs` | Audit trail - mencatat setiap aksi yang dilakukan user |
| `m_transaksi_events` | Menyimpan data transaksi pembayaran event (online via Midtrans atau offline oleh admin) |
| `template_id_card` | Menyimpan template gambar background ID card |
| `component_template_id_card` | Menyimpan posisi elemen (foto, nama, niqobah) pada template ID card |
| `event_status` | MySQL VIEW yang menghitung status event berdasarkan tanggal |

---

## 6. Route Analysis

### Route Publik (Tanpa Autentikasi)

| Method | URI | Controller@Method | Middleware | Deskripsi |
|--------|-----|-------------------|-----------|----------|
| GET | `/` | `HomeViews@index` | web | Homepage |
| GET | `/tentang-mzt` | `HomeViews@tentagMzt` | web | Halaman tentang MZT |
| GET | `/berita` | `HomeViews@berita` | web | Daftar berita |
| GET | `/pengumunan` | `HomeViews@event` | web | Daftar pengumuman/event |
| POST | `/load-berita` | `HomeViews@loadData` | web | Load more berita (AJAX) |
| GET | `/berita/{id}` | `HomeViews@bertiaDetail` | web | Detail berita |
| GET | `/pengumunan/{id}` | `HomeViews@eventDetail` | web | Detail event |
| POST | `/pengumunan/infak/pembayaran` | `HomeViews@pembayaranInfak` | web | Proses pembayaran infak event |
| POST | `/transaksi/pembayaran` | `HomeViews@simpanPembayaran` | web | Simpan data pembayaran (Midtrans Snap) |
| GET | `/logout` | `Login@logout` | web | Logout |

### Route Auth (Guest Only)

| Method | URI | Controller@Method | Middleware | Deskripsi |
|--------|-----|-------------------|-----------|----------|
| GET | `/login` | `Login@viewLogin` | guest, revalidate | Halaman login |
| POST | `/login-aksi` | `Login@action_login` | guest, revalidate | Proses login |

### Route Admin (Terlindungi)

#### Dashboard (`checkrole:dashboard`)

| Method | URI | Controller@Method | Deskripsi |
|--------|-----|-------------------|----------|
| GET | `/dashboard` | `Dashboard@index` | Halaman dashboard |
| GET | `/dashboard/get-calender` | `Dashboard@getCalender` | Data kalender event (JSON) |

#### Anggota (`checkrole:anggota`)

| Method | URI | Controller@Method | Deskripsi |
|--------|-----|-------------------|----------|
| GET | `/tabel-anggota` | `C_Anggota@tabelAnggota` | Halaman tabel anggota |
| POST | `/tabel-anggota/store` | `C_Anggota@storeData` | Tambah anggota baru |
| GET | `/tabel-anggota/data` | `C_Anggota@getData` | Ambil data anggota (JSON) |
| POST | `/tabel-anggota/data-hak-akses` | `C_Anggota@getDataHakakses` | Ambil data hak akses anggota |
| POST | `/tabel-anggota/edit` | `C_Anggota@editData` | Edit data anggota |
| POST | `/tabel-anggota/hapus` | `C_Anggota@deleteData` | Hapus (soft delete) anggota |
| GET | `/tabel-anggota/kta/{id}` | `C_Anggota@exportPdf` | Export KTA |

#### Profil (`checkrole:profil`)

| Method | URI | Controller@Method | Deskripsi |
|--------|-----|-------------------|----------|
| GET | `/profil` | `C_profil@index` | Halaman profil user |
| POST | `/profil/edit` | `C_profil@saveData` | Simpan perubahan profil |

#### Event (`checkrole:event`)

| Method | URI | Controller@Method | Deskripsi |
|--------|-----|-------------------|----------|
| GET | `/tabel-event` | `C_Event@index` | Halaman tabel event |
| POST | `/tabel-event/store` | `C_Event@storeData` | Tambah event baru |
| GET | `/tabel-event/data` | `C_Event@getData` | Ambil data event (JSON) |
| POST | `/tabel-event/edit` | `C_Event@editData` | Edit event |
| POST | `/tabel-event/hapus` | `C_Event@delete` | Hapus (soft delete) event |
| GET | `/tabel-event/detail/{id}` | `C_event_detail@index` | Detail event |
| POST | `/tabel-event/detail/data` | `C_event_detail@getData` | Data tanggal event (JSON) |
| POST | `/tabel-event/detail/save` | `C_event_detail@saveData` | Simpan jadwal jam event |
| GET | `/tabel-event-transaksi` | `C_transaksi@index` | Halaman transaksi event |
| GET | `/tabel-event-transaksi/tabel/{id}` | `C_transaksi@tabelTransaksi` | Tabel transaksi per event |
| POST | `/tabel-event-transaksi/verifikasi` | `C_transaksi@verifikasiPendaftar` | Verifikasi pembayaran |
| POST | `/tabel-event-transaksi/tambah-transasi-anggota` | `C_transaksi@tambahTransaksiAnggota` | Tambah transaksi offline |
| GET | `/tabel-event/detail/{id}/{id2}/prisensi` | `C_prisensi@index` | Halaman presensi per tanggal |
| POST | `/data-user-prisensi` | `C_prisensi@getDataUser` | Cari user untuk presensi |
| POST | `/data-user-prisensi/send-data` | `C_prisensi@sendData` | Simpan data presensi |
| POST | `/data-user-prisensi/get-data-tabel` | `C_prisensi@getDataTabel` | Ambil data presensi (JSON) |

#### Presensi (`checkrole:prisensi`)

| Method | URI | Controller@Method | Deskripsi |
|--------|-----|-------------------|----------|
| GET | `/tabel-prisensi` | `C_prisensi@tabel_prisensi` | Halaman tabel presensi |
| GET | `/tabel-prisensi/data` | `C_Event@getData` | Data event untuk presensi |
| GET | `/tabel-prisensi/detail/{id}` | `C_event_detail@index` | Detail event presensi |
| POST | `/tabel-prisensi/detail/data` | `C_event_detail@getData` | Data tanggal event |
| GET | `/tabel-prisensi/detail/{id}/{id2}/prisensi` | `C_prisensi@index` | Presensi per tanggal |
| POST | `/data-user-prisensi-anggota` | `C_prisensi@getDataUser` | Cari user presensi |
| POST | `/data-user-prisensi-anggota/send-data` | `C_prisensi@sendData` | Simpan presensi |
| POST | `/data-user-prisensi-anggota/get-data-tabel` | `C_prisensi@getDataTabel` | Ambil data presensi |

#### Berita (`checkrole:berita`)

| Method | URI | Controller@Method | Deskripsi |
|--------|-----|-------------------|----------|
| GET | `/tabel-berita` | `C_Berita@index` | Halaman tabel berita |
| POST | `/tabel-berita/store` | `C_Berita@storeData` | Tambah berita |
| POST | `/tabel-berita/edit` | `C_Berita@editData` | Edit berita |
| POST | `/tabel-berita/delete` | `C_Berita@deleteData` | Hapus (soft delete) berita |
| GET | `/tabel-berita/data` | `C_Berita@getData` | Ambil data berita (JSON) |

#### Tampilan (`checkrole:tampilan`)

| Method | URI | Controller@Method | Deskripsi |
|--------|-----|-------------------|----------|
| GET | `/edit-info-pesantren` | `C_Tampilan@tentanPondok` | Edit info pesantren |
| GET | `/edit-info-mzt` | `C_Tampilan@tentanMzt` | Edit info MZT |
| GET | `/edit-carosel` | `C_Tampilan@carosel` | Edit carousel |
| POST | `/edit-info-pesantren/simpan` | `C_Tampilan@simpanDataPesantren2` | Simpan info pesantren |
| POST | `/edit-info-mzt/simpan` | `C_Tampilan@simpanDataMzt` | Simpan info MZT |
| POST | `/edit-carosel/simpan` | `C_Tampilan@simpanCarosel` | Simpan carousel |

#### Activity Log (`checkrole:aktivitas_user`)

| Method | URI | Controller@Method | Deskripsi |
|--------|-----|-------------------|----------|
| GET | `/tabel-log-user` | `C_Aktivitas_log@index` | Halaman log aktivitas |
| GET | `/tabel-log-user/data` | `C_Anggota@getData` | Data anggota untuk log |
| GET | `/tabel-log-user/detail/{id}` | `C_Aktivitas_log@aktivitas` | Detail aktivitas user |
| POST | `/tabel-log-user/detail/{id}/data` | `C_Aktivitas_log@dataAktivitasLog` | Data log aktivitas (JSON) |

#### ID Card (`checkrole:id_card`)

| Method | URI | Controller@Method | Deskripsi |
|--------|-----|-------------------|----------|
| GET | `/id-card` | `C_ID_Card@index` | Halaman ID card |
| POST | `/id-card/store` | `C_ID_Card@store` | Upload template ID card |
| GET | `/id-card/{id}` | `C_ID_Card@setComponent` | Setting komponen ID card |
| POST | `/id-card/store/component` | `C_ID_Card@setLayoutComponent` | Simpan posisi komponen |
| GET | `/tabel-event-transaksi/transaksi/{id_event}/{id_transaction}` | `C_ID_Card@printCard` | Cetak ID card |

### Route API

| Method | URI | Controller@Method | Middleware | Deskripsi |
|--------|-----|-------------------|-----------|----------|
| GET | `/api/user` | Closure | auth:sanctum | Get authenticated user |
| POST | `/api/transaksi/pembayaran/hendle-payment` | `C_transaksi@payment_hendler` | - | Webhook Midtrans |

### Middleware yang Digunakan

| Middleware | Alias | Fungsi |
|-----------|-------|--------|
| `Chackrole` | `checkrole` | Otorisasi berbasis role - memeriksa `hak_akses_role` untuk menentukan akses |
| `RevalidateBackHistory` | `revalidate` | Menambahkan header no-cache untuk mencegah akses halaman setelah logout |
| `guest` | `guest` | Hanya mengizinkan akses jika user belum login |
| `web` | `web` | Group middleware default (cookies, session, CSRF) |
| `auth:sanctum` | - | Autentikasi API via Sanctum token |

---

## 7. Controller Analysis

### Controller Admin

| No | Controller | File | Fungsi Utama |
|----|-----------|------|-------------|
| 1 | **Login** | `admin/Login.php` | Menampilkan halaman login, proses autentikasi (id_anggota + password), logout |
| 2 | **Dashboard** | `admin/Dashboard.php` | Menampilkan statistik event & anggota, data kalender event (JSON untuk FullCalendar) |
| 3 | **C_Anggota** | `admin/C_Anggota.php` | CRUD anggota, generate ID anggota otomatis (format: `XXXX` + `YY_lahir` + `YY_masuk` + `YY_keluar`), generate barcode Code39, resize foto (300x400), export KTA, manajemen hak akses role |
| 4 | **C_profil** | `admin/C_profil.php` | Menampilkan & mengedit profil user yang login, dengan batas jatah edit maks 3 kali |
| 5 | **C_Event** | `admin/C_Event.php` | CRUD event, generate slug, upload banner, generate tanggal range (multi-day event), soft delete |
| 6 | **C_event_detail** | `admin/C_event_detail.php` | Menampilkan detail event, setting jam per tanggal event (seharian/dijam) |
| 7 | **C_prisensi** | `admin/C_prisensi.php` | Pencatatan presensi kehadiran per event per tanggal, scan barcode anggota, ambil data presensi |
| 8 | **C_transaksi** | `admin/C_transaksi.php` | Manajemen transaksi event, webhook handler Midtrans (`payment_hendler`), verifikasi pembayaran manual, tambah transaksi offline, kirim notifikasi WhatsApp saat settlement |
| 9 | **C_Berita** | `admin/C_Berita.php` | CRUD berita, upload foto, generate slug, soft delete |
| 10 | **C_Tampilan** | `admin/C_Tampilan.php` | Edit konten website: info pesantren, tentang MZT, carousel - termasuk upload & ganti foto |
| 11 | **C_Aktivitas_log** | `admin/C_Aktivitas_log.php` | Menampilkan log aktivitas seluruh user, detail aktivitas per user (dengan DataTables server-side) |
| 12 | **C_ID_Card** | `admin/C_ID_Card.php` | Upload template ID card, setting posisi komponen (Photo, Name, Niqobah), cetak ID card per transaksi |

### Controller Home (Publik)

| No | Controller | File | Fungsi Utama |
|----|-----------|------|-------------|
| 1 | **HomeViews** | `home/HomeViews.php` | Homepage, halaman tentang MZT, daftar berita (paginated), daftar pengumuman/event (paginated), detail berita, detail event, proses pendaftaran & pembayaran event (Midtrans Snap), load more berita (AJAX), pembayaran infak |

---

## 8. Model Analysis

### Daftar Model

| No | Model | Tabel | Fillable/Guarded | Relasi |
|----|-------|-------|-----------------|--------|
| 1 | `User` | `users` | fillable: name, email, password | - |
| 2 | `DataUser` | `data_users` | guarded: [] (semua) | - |
| 3 | `RoleUser` | `role_user` | fillable: nama_role, keterangan, is_active | - |
| 4 | `HakAksesRole` | `hak_akses_role` | fillable: id_users, nama_role, hak_akses | - |
| 5 | `Event` | `events` | guarded: [] (semua) | - |
| 6 | `Event_status` | `event_status` | guarded: [] (semua) | - |
| 7 | `Tanggal_event` | `tanggal_events` | - (default) | - |
| 8 | `Prisensi_kehadiran` | `prisensi_kehadiran` | fillable: id_event, id_tanggal, id_anggota, id_user, tanggal_kehadiran, jam_kehadiran | - |
| 9 | `Berita` | `beritas` | - (default) | - |
| 10 | `Carosel` | `carosels` | - (default) | - |
| 11 | `Info_pesantren` | `info_pesantrens` | - (default) | - |
| 12 | `Tentang_mzt` | `tentang_mzts` | - (default) | - |
| 13 | `Activitas_log` | `activitas_logs` | - (default) | - |
| 14 | `Transaksi_event` | `m_transaksi_events` | guarded: [] (semua) | - |
| 15 | `TemplateIdCard` | `template_id_card` | fillable: path, status | - |
| 16 | `ComponentTemplateIdCard` | `component_template_id_card` | fillable: id_template, title, position_x, position_y | belongsTo -> TemplateIdCard |

### Catatan Relasi Model

Sebagian besar relasi **tidak didefinisikan secara formal** di model Eloquent. Relasi diimplementasikan melalui **query join manual** di controller:

- `users` <-> `data_users`: Join via `users.id = data_users.id_users`
- `users` <-> `hak_akses_role`: Join via `users.id = hak_akses_role.id_users`
- `events` <-> `tanggal_events`: Join via `events.id = tanggal_events.id_event`
- `events` <-> `m_transaksi_events`: Join via `events.id = m_transaksi_events.id_event`
- `users` <-> `m_transaksi_events`: Join via `users.id_anggota = m_transaksi_events.id_anggota`
- `beritas` <-> `users`: Join via `users.id_anggota = beritas.create_at` (bukan FK formal)
- `template_id_card` <-> `component_template_id_card`: **Relasi formal didefinisikan** - `belongsTo` di ComponentTemplateIdCard
- `activitas_logs` <-> `users`: Join via `users.id = aktivitas_logs.user_id`

---

## 9. Storage & Upload Analysis

### Lokasi Upload File

| Kategori | Path Storage | URL Publik | Keterangan |
|----------|-------------|-----------|------------|
| Foto Anggota | `storage/app/public/image/anggota/` | `/storage/image/anggota/` | Resize 300x400px via Intervention Image |
| Barcode | `storage/app/public/image/barcode/` | `/storage/image/barcode/` | PNG Code39, format: `barcode-{id_anggota}.png` |
| Foto Berita | `storage/app/public/image/berita/` | `/storage/image/berita/` | Foto thumbnail berita |
| Banner Event | `storage/app/public/image/event/` | `/storage/image/event/` | Banner/gambar event |
| Foto Carousel | `storage/app/public/image/carosel/` | `/storage/image/carosel/` | Gambar carousel homepage |
| Foto MZT | `storage/app/public/image/mzt/` | `/storage/image/mzt/` | Foto tentang MZT |
| Foto Pesantren | `storage/app/public/image/pesantren/` | `/storage/image/pesantren/` | Foto info pesantren |
| Template ID Card | `storage/app/public/image/id-card/` | `/storage/image/id-card/` | Background template ID card |

### Storage Link

```
public/storage -> symlink -> storage/app/public
```

Konfigurasi di `config/filesystems.php`:
```php
'links' => [
    public_path('storage') => storage_path('app/public'),
],
```

### Public Assets (Statis)

| Path | Konten |
|------|--------|
| `public/assets/` | CSS, JS, gambar statis, CKEditor, DataTables, datepicker, vendor libraries |
| `public/stisla/` | Template admin Stisla (CSS, JS, fonts, gambar, node_modules) |
| `public/build/` | Vite build output (app-*.js) |

---

## 10. Environment Requirements

### PHP

| Requirement | Version |
|-------------|--------|
| PHP | `^8.0.2` |
| Ekstensi PHP | BCMath, Ctype, Fileinfo, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML, cURL, GD/Imagick |

### Composer

- Composer 2.x
- Dependensi utama: Lihat `composer.json` di bagian Tech Stack

### Node.js

| Requirement | Version |
|-------------|--------|
| Node.js | >= 14.x (untuk Vite 4) |
| npm | >= 6.x |

### Database

| Requirement | Keterangan |
|-------------|----------|
| MySQL | >= 5.7 / MariaDB >= 10.3 |
| Charset | `utf8mb4_unicode_ci` |

### Server

| Requirement | Keterangan |
|-------------|----------|
| Web Server | Apache (dengan mod_rewrite) atau Nginx |
| Hosting | cPanel shared hosting |
| Domain | `maziltutholiban.com` |

---

## 11. Installation Guide

### Prasyarat

1. PHP >= 8.0.2
2. Composer
3. Node.js >= 14.x & npm
4. MySQL >= 5.7
5. Git

### Langkah Instalasi

```bash
# 1. Clone repository
git clone <repository-url> laravel-mzt
cd laravel-mzt

# 2. Install dependensi PHP
composer install

# 3. Copy file environment
cp .env.example .env

# 4. Generate application key
php artisan key:generate

# 5. Konfigurasi .env - Edit dan sesuaikan:
#   DB_CONNECTION=mysql
#   DB_HOST=127.0.0.1
#   DB_PORT=3306
#   DB_DATABASE=nama_database
#   DB_USERNAME=username
#   DB_PASSWORD=password
#   MIDTRANS_SERVER_KEY=your-server-key
#   MIDTRANS_CLIENT_KEY=your-client-key
#   MIDTRANS_URL=https://app.sandbox.midtrans.com/snap/snap.js

# 6. Jalankan migration
php artisan migrate

# 7. Buat storage link
php artisan storage:link

# 8. Install dependensi Node.js
npm install

# 9. Build asset frontend
npm run build

# 10. (Opsional) Seed data awal
php artisan db:seed

# 11. Jalankan development server
php artisan serve

# 12. Akses aplikasi di browser: http://localhost:8000
```

### Konfigurasi Tambahan

- **Midtrans**: Daftarkan akun di Midtrans Dashboard dan dapatkan Server Key & Client Key.
- **Zenziva WhatsApp**: Daftarkan akun di zenziva.net dan dapatkan userkey & passkey. Update di `app/Helpers/DataRangeHelper.php`.
- **Database View `event_status`**: Buat MySQL VIEW secara manual:

```sql
CREATE VIEW event_status AS
SELECT
    e.*,
    CASE
        WHEN CURDATE() BETWEEN e.tanggal_mulai AND e.tanggal_selesai THEN 'Ongoing'
        WHEN CURDATE() < e.tanggal_mulai THEN 'Upcomming'
        WHEN CURDATE() > e.tanggal_selesai THEN 'Complate'
    END AS status
FROM events e
WHERE e.is_active = '1';
```

### Deployment ke cPanel

1. Upload seluruh file project ke hosting
2. Import database dari `_archive/database/mysql.sql`
3. Konfigurasi `.env` sesuai environment hosting
4. Jalankan `composer install --optimize-autoloader --no-dev`
5. Jalankan `php artisan config:cache`
6. Jalankan `php artisan route:cache`
7. Jalankan `php artisan view:cache`
8. Pastikan `storage/` dan `bootstrap/cache/` writable (chmod 775)
9. Pastikan storage link aktif: `php artisan storage:link`

---

## 12. Known Issues

### Kritis

| No | Issue | Lokasi | Deskripsi |
|----|-------|--------|----------|
| 1 | **Kredensial database & API key terekspos di `.env`** | `.env` | File `.env` berisi database password, Midtrans sandbox key, dan Zenziva credential yang ter-commit di repository |
| 2 | **Zenziva API key hardcoded** | `app/Helpers/DataRangeHelper.php:54-56` | `$userkey` dan `$passkey` untuk Zenziva WhatsApp API di-hardcode langsung di source code, bukan dari `.env` |
| 3 | **Nomor telepon admin hardcoded** | `HomeViews.php`, `C_transaksi.php` | Nomor `088217784280` muncul di beberapa pesan WhatsApp template |
| 4 | **Missing Storage Link** | `public/storage` | Symlink `public/storage` tidak ada (tidak terdeteksi) - semua gambar upload tidak bisa diakses |
| 5 | **MySQL VIEW `event_status` tidak ada di migration** | - | View `event_status` dibuat manual di database, tidak terdokumentasi di migration. Deployment baru akan gagal tanpa SQL manual |

### Sedang

| No | Issue | Lokasi | Deskripsi |
|----|-------|--------|----------|
| 6 | **Typo pada nama middleware** | `Chackrole.php` | Nama class `Chackrole` seharusnya `CheckRole` |
| 7 | **Typo "Prisensi"** | Seluruh codebase | Seharusnya "Presensi" - konsisten typo di nama controller, model, view, route, tabel |
| 8 | **Typo "bertiaDetail"** | `HomeViews.php` | Method `bertiaDetail` seharusnya `beritaDetail` |
| 9 | **Typo "tentagMzt"** | `HomeViews.php` | Method `tentagMzt` seharusnya `tentangMzt` |
| 10 | **Typo status "Complate"** | `Dashboard.php`, `HomeViews.php` | Seharusnya "Complete" |
| 11 | **Typo "Upcomming"** | `Dashboard.php`, `HomeViews.php` | Seharusnya "Upcoming" |
| 12 | **`create_at` dan `edit_at` bukan timestamp** | Tabel `beritas` | Kolom `create_at` dan `edit_at` bertipe string dan menyimpan `id_anggota`, bukan timestamp. Sangat membingungkan |
| 13 | **Tidak ada foreign key constraint** | Migration files | Hampir semua relasi menggunakan `integer` tanpa `foreign()` dan `constrained()`. Hanya `component_template_id_card` yang memiliki FK formal |
| 14 | **Soft delete tidak konsisten** | Controllers | Menggunakan `is_active = 0` alih-alih Laravel SoftDeletes trait |
| 15 | **`$_FILES` digunakan langsung** | `C_Anggota.php`, `C_Event.php`, `C_Tampilan.php`, `C_profil.php` | Menggunakan `$_FILES["foto"]["name"]` alih-alih `$request->hasFile('foto')` |
| 16 | **Migration file name mengandung "copy"** | `2023_10_28_042538_create_template_id_card_table copy.php` | Nama file migration mengandung spasi dan kata "copy" |

### Minor

| No | Issue | Lokasi | Deskripsi |
|----|-------|--------|----------|
| 17 | **Dead code** | `C_Anggota.php:476-480` | Kode PDF generation di-comment out di method `exportPdf` |
| 18 | **Dead code** | `C_Tampilan.php:34-60` | Method `simpanDataPesantren` (versi lama) masih ada tapi tidak dipakai |
| 19 | **Dead code** | `routes/web.php:111,131-133` | Route yang di-comment out |
| 20 | **`deleteData` di C_ID_Card** | `C_ID_Card.php:101-115` | Method `deleteData` menggunakan model `Berita` bukan `TemplateIdCard` - copy-paste error |
| 21 | **Session files menumpuk** | `storage/framework/sessions/` | Banyak file session yang belum di-cleanup |
| 22 | **Relasi model tidak didefinisikan** | Semua Model | Hampir semua relasi diimplementasikan via query join manual di controller |
| 23 | **Tidak ada form request validation** | Controllers | Validasi dilakukan inline di controller, bukan menggunakan Form Request classes |
| 24 | **Tidak ada testing** | `tests/` | Hanya berisi ExampleTest default Laravel, tidak ada test untuk fitur |
| 25 | **Duplikasi route presensi** | `routes/web.php` | Route untuk presensi diduplikasi di group `event` dan `prisensi` dengan prefix berbeda |
| 26 | **`harga` kolom tidak ada di migration** | `events` table | Kolom `harga` digunakan di controller tapi tidak ada di migration - mungkin ditambahkan manual |
| 27 | **`jatah_edit` kolom tidak ada di migration** | `users` table | Kolom `jatah_edit` digunakan di `C_profil` tapi tidak ada di migration |
| 28 | **Folder `_archive` ter-commit** | Root | Backup cPanel, database dump, dan log ter-commit di repository |
| 29 | **`vendor/` kemungkinan ter-commit** | Root | Folder vendor seharusnya di-gitignore |
| 30 | **BOM (Byte Order Mark)** | `Login.php:1` | File dimulai dengan BOM yang bisa menyebabkan issue "headers already sent" |

---

## 13. Improvement Suggestions

### Arsitektur & Struktur

| No | Saran | Prioritas | Deskripsi |
|----|-------|-----------|----------|
| 1 | **Gunakan Eloquent Relationships** | Tinggi | Definisikan relasi formal di model (`hasMany`, `belongsTo`, `belongsToMany`) alih-alih join manual di controller |
| 2 | **Form Request Validation** | Tinggi | Pindahkan validasi ke Form Request classes untuk reusability dan clean controller |
| 3 | **Service Layer** | Sedang | Pisahkan business logic dari controller ke Service classes (e.g., `EventService`, `TransactionService`, `MemberService`) |
| 4 | **Repository Pattern** | Rendah | Pertimbangkan Repository pattern untuk abstraksi database yang lebih baik |
| 5 | **Event & Listener** | Sedang | Gunakan Laravel Events untuk notifikasi WhatsApp, activity logging, dan proses pasca-pembayaran |

### Database

| No | Saran | Prioritas | Deskripsi |
|----|-------|-----------|----------|
| 6 | **Tambahkan Foreign Key** | Tinggi | Tambahkan foreign key constraint di semua migration yang memiliki relasi |
| 7 | **Gunakan SoftDeletes** | Sedang | Gunakan Laravel SoftDeletes trait alih-alih `is_active = 0` |
| 8 | **Buat migration untuk VIEW** | Tinggi | Buat migration yang membuat MySQL VIEW `event_status` agar deployment bisa otomatis |
| 9 | **Buat migration kolom yang hilang** | Tinggi | Tambahkan migration untuk kolom `harga` di events dan `jatah_edit` di users |
| 10 | **Rename kolom `create_at`/`edit_at`** | Sedang | Rename ke `created_by`/`edited_by` karena menyimpan id_anggota, bukan timestamp |
| 11 | **Gunakan UUID untuk order_id** | Sedang | Gunakan UUID alih-alih timestamp + random number untuk mencegah collision |

### Keamanan

| No | Saran | Prioritas | Deskripsi |
|----|-------|-----------|----------|
| 12 | **Pindahkan API key ke `.env`** | Tinggi | Pindahkan Zenziva userkey/passkey dari hardcoded ke environment variables |
| 13 | **Jangan commit `.env`** | Tinggi | Pastikan `.env` ada di `.gitignore` dan hapus dari git history |
| 14 | **Rate limiting** | Sedang | Tambahkan rate limiting untuk route API dan form submission |
| 15 | **Input sanitization** | Sedang | Sanitasi input untuk mencegah XSS, terutama di field deskripsi yang menggunakan CKEditor |
| 16 | **File upload validation** | Sedang | Tambahkan validasi MIME type yang lebih ketat dan scan file upload |
| 17 | **CSRF untuk API webhook** | Sedang | Tambahkan verifikasi signature untuk webhook Midtrans (sudah ada tapi bisa ditingkatkan) |

### Code Quality

| No | Saran | Prioritas | Deskripsi |
|----|-------|-----------|----------|
| 18 | **Fix naming conventions** | Sedang | Perbaiki typo: `Chackrole` -> `CheckRole`, `Prisensi` -> `Presensi`, `Complate` -> `Complete`, `Upcomming` -> `Upcoming` |
| 19 | **Hapus dead code** | Sedang | Hapus kode yang di-comment, method yang tidak dipakai, dan route yang tidak aktif |
| 20 | **Gunakan `$request->hasFile()`** | Sedang | Ganti `$_FILES["foto"]["name"]` dengan `$request->hasFile('foto')` |
| 21 | **DRY: Extract common patterns** | Sedang | Banyak controller memiliki pattern upload-delete-update yang sama - extract ke trait atau helper |
| 22 | **Unit & Feature Tests** | Tinggi | Tulis test untuk fitur utama: login, CRUD anggota, pembayaran, presensi |
| 23 | **Gunakan Laravel Policies/Gates** | Rendah | Gunakan Authorization Gates/Policies alih-alih middleware custom untuk otorisasi |

### Performance

| No | Saran | Prioritas | Deskripsi |
|----|-------|-----------|----------|
| 24 | **Eager loading** | Sedang | Gunakan `with()` untuk eager loading relasi dan menghindari N+1 query |
| 25 | **Query caching** | Rendah | Cache query yang sering dipanggil (info_pesantren, tentang_mzt, carousel) |
| 26 | **Image optimization** | Sedang | Gunakan format WebP dan lazy loading untuk gambar |
| 27 | **CDN untuk assets** | Rendah | Pertimbangkan menggunakan CDN untuk static assets |

### Deployment & DevOps

| No | Saran | Prioritas | Deskripsi |
|----|-------|-----------|----------|
| 28 | **Hapus `_archive` dari repo** | Sedang | Pindahkan backup ke tempat terpisah, jangan di repository |
| 29 | **CI/CD Pipeline** | Sedang | Setup GitHub Actions untuk automated testing dan deployment |
| 30 | **Docker** | Rendah | Buat Dockerfile dan docker-compose.yml untuk konsistensi environment development |
| 31 | **Environment-based config** | Tinggi | Pisahkan konfigurasi sandbox dan production Midtrans menggunakan environment variables |

---

## Lampiran

### A. Generate ID Anggota

Format: `{XXXX}{YY_lahir}{YY_masuk}{YY_keluar}`

Contoh: `000123192023` = anggota ke-1, lahir tahun 2023, masuk 2019, keluar 2020

```
0001  -> Nomor urut (4 digit, padding zero)
23    -> 2 digit terakhir tahun lahir
19    -> 2 digit terakhir tahun masuk
20    -> 2 digit terakhir tahun keluar
```

### B. Alur Pembayaran Event

```
User Publik:
1. Buka halaman /pengumunan/{slug}
2. Isi form pendaftaran (id_anggota)
3. Klik bayar -> Midtrans Snap muncul
4. Selesaikan pembayaran
5. Frontend mengirim token ke /transaksi/pembayaran
6. Server verifikasi ke Midtrans API
7. Update status transaksi
8. Kirim notifikasi WhatsApp via Zenziva

Admin:
1. Buka /tabel-event-transaksi/tabel/{id}
2. Verifikasi manual atau tambah transaksi offline
3. Cetak ID card jika diperlukan
```

### C. Role yang Tersedia

| Role | Akses |
|------|-------|
| `dashboard` | Halaman dashboard & kalender |
| `anggota` | CRUD data anggota |
| `profil` | Edit profil sendiri |
| `event` | CRUD event, transaksi, presensi (full access) |
| `prisensi` | Lihat event & input presensi |
| `berita` | CRUD berita |
| `tampilan` | Edit konten website |
| `aktivitas_user` | Lihat log aktivitas |
| `id_card` | Manajemen ID card template & cetak |

### D. Daftar View (Blade Templates)

#### Admin Views (`resources/views/admin/`)

| File | Deskripsi |
|------|----------|
| `master.blade.php` | Layout utama admin |
| `dashboard.blade.php` | Halaman dashboard |
| `tabel_anggota.blade.php` | Tabel data anggota |
| `tabel_berita.blade.php` | Tabel data berita |
| `event.blade.php` | Tabel data event |
| `detail_event.blade.php` | Detail event dengan jadwal |
| `event_transaksi.blade.php` | Daftar event untuk transaksi |
| `tabel_transaksi.blade.php` | Tabel transaksi per event |
| `prisensi.blade.php` | Halaman presensi per tanggal |
| `tabel_event_prisensi.blade.php` | Daftar event untuk presensi |
| `profil.blade.php` | Halaman profil user |
| `aktivitas_user.blade.php` | Log aktivitas user |
| `detail_aktivitas_log.blade.php` | Detail aktivitas per user |
| `view_carosel.blade.php` | Edit carousel |
| `view_tenteng_pondok.blade.php` | Edit info pesantren |
| `view_tenteng_mzt.blade.php` | Edit info MZT |
| `IdCard/index.blade.php` | Daftar template ID card |
| `IdCard/component_id_card.blade.php` | Setting komponen ID card |
| `IdCard/print_id_card.blade.php` | Preview/cetak ID card |

#### Home Views (`resources/views/home/`)

| File | Deskripsi |
|------|----------|
| `master.blade.php` | Layout utama halaman publik |
| `home_views.blade.php` | Homepage |
| `berita.blade.php` | Daftar berita |
| `berita_detail.blade.php` | Detail berita |
| `pengumunan.blade.php` | Daftar pengumuman/event |
| `pengumunan_detail.blade.php` | Detail event/pengumuman |
| `pembayaran.blade.php` | Halaman pembayaran |
| `tentang_mzt.blade.php` | Halaman tentang MZT |

#### Root Views (`resources/views/`)

| File | Deskripsi |
|------|----------|
| `kta.blade.php` | Template Kartu Tanda Anggota (KTA) |
| `login_view.blade.php` | Halaman login |
| `welcome.blade.php` | Default Laravel welcome page |

---

*Dokumentasi ini dibuat berdasarkan analisis source code. Tidak ada source code yang diubah selama proses dokumentasi.*
