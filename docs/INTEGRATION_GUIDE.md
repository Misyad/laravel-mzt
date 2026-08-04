# Panduan Integrasi: OrgOS Frontend + MZT Laravel Backend

> **Tujuan**: Menghubungkan frontend modern OrgOS (TanStack Start/React/TypeScript) dengan backend Laravel 9 MZT APPS yang sudah ada, sehingga admin dashboard menggunakan UI baru tanpa perlu rewrite backend dari nol.

---

## Daftar Isi

1. [Gambaran Umum](#1-gambaran-umum)
2. [Data Mapping: Frontend vs Backend](#2-data-mapping-frontend-vs-backend)
3. [Persiapan Backend (Laravel)](#3-persiapan-backend-laravel)
4. [Persiapan Frontend (React)](#4-persiapan-frontend-react)
5. [Persiapan Database](#5-persiapan-database)
6. [Persiapan Infrastructure](#6-persiapan-infrastructure)
7. [Persiapan Development Environment](#7-persiapan-development-environment)
8. [Checklist Langkah Demi Langkah](#8-checklist-langkah-demi-langkah)
9. [Testing & QA](#9-testing--qa)
10. [Timeline & Prioritas](#10-timeline--prioritas)

---

## 1. Gambaran Umum

### Arsitektur Target

```
+-------------------------------------------+
|  ORGOS FRONTEND (React 19 + TypeScript)   |
|  TanStack Start + shadcn/ui + Tailwind    |
|  Deploy: Vercel / Cloudflare Pages         |
|  Port: 3000 (dev)                          |
+------------------+-------------------------+
                   |
                   | HTTPS + Bearer Token
                   | CORS enabled
                   | JSON request/response
                   v
+-------------------------------------------+
|  MZT BACKEND (Laravel 9 + PHP 8.0)        |
|  + Sanctum API Auth (BARU)                |
|  + REST API Routes (BARU)                 |
|  + API Resources (BARU)                   |
|  Deploy: cPanel maziltutholiban.com        |
|  /api/* -> REST API                        |
+------------------+-------------------------+
                   |
                   v
+-------------------------------------------+
|  MySQL DATABASE (existing)                |
|  19 tabel yang sudah ada                  |
|  + tabel baru (campaigns, certificates,   |
|    broadcasts, org_settings)              |
+-------------------------------------------+
```

### Yang Sudah Ada vs Yang Perlu Dibuat

| Komponen | Status | Action |
|----------|--------|--------|
| Backend: Controller CRUD | ADA (12 controller) | Wrap sebagai API |
| Backend: Model Eloquent | ADA (16 model) | Tambah relasi formal |
| Backend: Database | ADA (19 tabel) | Tambah 4 tabel baru |
| Backend: Authentication | Session-based | Tambah Sanctum token |
| Backend: API Routes | 2 endpoint saja | Buat ~35 endpoint baru |
| Backend: CORS | Default | Konfigurasi untuk frontend |
| Frontend: UI Components | ADA (48 shadcn + 4 custom) | Tidak perlu diubah |
| Frontend: Layout (sidebar, topnav) | ADA | Tidak perlu diubah |
| Frontend: Mock Data | ADA (lib/mock-data.ts) | Ganti dengan API calls |
| Frontend: Auth flow | BELUM ADA | Buat login page + token manager |
| Frontend: API client | BELUM ADA | Buat api client layer |

---

## 2. Data Mapping: Frontend vs Backend

### 2.1 Member (Anggota)

Frontend mengharapkan data dengan struktur ini:

```
Frontend (mock-data.ts)          Backend (Laravel DB)
+-------------------------+      +---------------------------+
| Member                  |      | users + data_users         |
+-------------------------+      +---------------------------+
| id: "MZT-1000"          |  <-- | users.id_anggota           |
| name: "Ahmad Fauzi"     |  <-- | users.name                 |
| email: "ahmad@..."      |  <-- | users.email                |
| phone: "+62..."         |  <-- | data_users.no_hp           |
| role: "Anggota"         |  <-- | hak_akses_role.nama_role   |
| status: "active"        |  <-- | users.is_active (1/0)      |
| joined: "2026-01-15"    |  <-- | users.created_at           |
| avatar: "AF"            |  <-- | (generate dari name initials|
|                         |      |  atau dari data_users.foto) |
+-------------------------+      +---------------------------+
```

**API Endpoint yang dibutuhkan:**
```
GET    /api/members?page=1&search=ahmad&role=all&status=all
GET    /api/members/{id}
POST   /api/members
PUT    /api/members/{id}
DELETE /api/members/{id}
```

**Response format yang diharapkan frontend:**
```json
{
  "data": [
    {
      "id": "MZT-1000",
      "name": "Ahmad Fauzi",
      "email": "ahmad.fauzi@mzt.id",
      "phone": "+62 812 3456 7890",
      "role": "Anggota",
      "status": "active",
      "joined": "2026-01-15T00:00:00Z",
      "avatar": "AF",
      "foto": "/storage/image/anggota/foto.jpg",
      "alamat": "Jl. Merdeka No. 1",
      "niqobah": "Al-Fatihah",
      "pekerjaan": "Guru",
      "tempat_lahir": "Jakarta",
      "tanggal_lahir": "1998-05-12",
      "tahun_masuk": "2019-01-01",
      "tahun_keluar": "2023-12-31",
      "barcode": "/storage/image/barcode/barcode-MZT1000.png"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 10,
    "total": 48
  }
}
```

### 2.2 Event

```
Frontend (mock-data.ts)          Backend (Laravel DB)
+-------------------------+      +---------------------------+
| Event                   |      | events                     |
+-------------------------+      +---------------------------+
| id: "E-001"             |  <-- | events.id                  |
| title: "Kajian Akbar"   |  <-- | events.judul_event         |
| type: "Kajian"          |  <-- | (belum ada - perlu kolom)  |
| date: "2026-06-25"      |  <-- | events.tanggal_mulai       |
| location: "Masjid Al-H" |  <-- | events.lokasi              |
| capacity: 500           |  <-- | (belum ada - perlu kolom)  |
| registered: 412         |  <-- | COUNT(m_transaksi_events)  |
| revenue: 185000000      |  <-- | SUM(m_transaksi_events.gross_amount) |
| status: "upcoming"      |  <-- | event_status.status        |
| cover: "hsl(...)"       |  <-- | events.banner              |
| description: "..."      |  <-- | events.deskripsi           |
| slug: "kajian-akbar"    |  <-- | events.slug                |
| harga: 50000            |  <-- | events.harga               |
+-------------------------+      +---------------------------+
```

**Kolom yang perlu ditambahkan di tabel `events`:**
- `type` VARCHAR(50) - jenis event (Kajian, Training, Fundraiser, dll)
- `capacity` INT - kapasitas maksimum peserta

**API Endpoint:**
```
GET    /api/events?page=1&status=all
GET    /api/events/{id}
GET    /api/events/{id}/dates
GET    /api/events/{id}/participants
POST   /api/events
PUT    /api/events/{id}
DELETE /api/events/{id}
```

### 2.3 Attendance (Presensi)

```
Frontend                       Backend
+-------------------------+    +---------------------------+
| Attendance session       |   | prisensi_kehadiran         |
+-------------------------+    +---------------------------+
| event name               |<--| events.judul_event         |
| date                     |<--| tanggal_events.tanggal     |
| member name              |<--| users.name                 |
| member id                |<--| users.id_anggota           |
| check-in time            |<--| prisensi_kehadiran.        |
|                          |   |   jam_kehadiran            |
| check-in date            |<--| prisensi_kehadiran.        |
|                          |   |   tanggal_kehadiran        |
+-------------------------+    +---------------------------+
```

**API Endpoint:**
```
GET    /api/attendance?event_id={id}&date_id={id}
POST   /api/attendance  (body: {id_anggota, id_event, id_tanggal})
GET    /api/attendance/stats?event_id={id}
```

### 2.4 Finance / Transaction

```
Frontend                       Backend
+-------------------------+    +---------------------------+
| Transaction              |   | m_transaksi_events          |
+-------------------------+    +---------------------------+
| id: "TRX-50000"         |<--| m_transaksi_events.order_id  |
| date: "2026-06-21"      |<--| m_transaksi_events.          |
|                          |   |   transaction_time          |
| donor: "Ahmad Fauzi"    |<--| users.name (via id_anggota)  |
| campaign: "Beasiswa"    |<--| events.judul_event           |
|                          |   |   (via id_event)            |
| channel: "Midtrans"     |<--| m_transaksi_events.          |
|                          |   |   payment_type              |
| amount: 500000           |<--| m_transaksi_events.          |
|                          |   |   gross_amount              |
| status: "settled"       |<--| m_transaksi_events.          |
|                          |   |   transaction_status        |
+-------------------------+    +---------------------------+
```

**API Endpoint:**
```
GET    /api/transactions?page=1&event_id={id}&status=all
POST   /api/transactions/verify
GET    /api/finance/stats
GET    /api/finance/revenue-trend
```

### 2.5 CMS (Berita/Articles)

```
Frontend                       Backend
+-------------------------+    +---------------------------+
| Article                  |   | beritas                     |
+-------------------------+    +---------------------------+
| id: "A1"                |<--| beritas.id                   |
| title: "Adab Menyambut" |<--| beritas.judul                |
| author: "Ust. Abdullah" |<--| users.name (via create_at)   |
| category: "Fiqih"       |<--| (belum ada - perlu kolom)    |
| status: "published"     |<--| beritas.is_active            |
| views: 12843            |<--| (belum ada - perlu kolom)    |
| date: "2026-06-18"      |<--| beritas.created_at           |
| description: "..."      |<--| beritas.deskripsi            |
| foto: "/storage/..."    |<--| beritas.foto                 |
| slug: "adab-men..."     |<--| beritas.slug                 |
+-------------------------+    +---------------------------+
```

**Kolom yang perlu ditambahkan di tabel `beritas`:**
- `category` VARCHAR(50)
- `views` INT DEFAULT 0

**API Endpoint:**
```
GET    /api/articles?page=1&status=all&category=all
GET    /api/articles/{id}
POST   /api/articles
PUT    /api/articles/{id}
DELETE /api/articles/{id}
```

### 2.6 ID Cards

```
Frontend                       Backend
+-------------------------+    +---------------------------+
| IDTemplate               |   | template_id_card            |
+-------------------------+    +---------------------------+
| id: "T1"                |<--| template_id_card.id          |
| name: "Anggota Aktif"   |<--| (belum ada - perlu kolom)    |
| color: "emerald"        |<--| (belum ada - perlu kolom)    |
| issued: 1284            |<--| COUNT(transaksi per template)|
| path: "/storage/..."    |<--| template_id_card.path        |
| status: "ACTIVE"        |<--| template_id_card.status      |
+-------------------------+    +---------------------------+
```

**Kolom yang perlu ditambahkan di tabel `template_id_card`:**
- `name` VARCHAR(100)
- `color` VARCHAR(20)

**API Endpoint:**
```
GET    /api/id-cards/templates
POST   /api/id-cards/templates
GET    /api/id-cards/templates/{id}/components
POST   /api/id-cards/templates/{id}/components
GET    /api/id-cards/print/{transaction_id}
```

### 2.7 Dashboard Stats

```
Frontend mengharapkan:           Backend source:
+-------------------------+    +---------------------------+
| stats.totalMembers      |<--| COUNT(data_users WHERE      |
|                         |   |   is_active=1)              |
| stats.activeEvents      |<--| COUNT(events WHERE          |
|                         |   |   is_active=1)              |
| stats.attendanceRate    |<--| Calculated: total_presensi/ |
|                         |   |   total_tanggal_event       |
| stats.revenue           |<--| SUM(m_transaksi_events.     |
|                         |   |   gross_amount WHERE        |
|                         |   |   transaction_status=       |
|                         |   |   settlement)               |
| attendanceTrend[]       |<--| GROUP BY month dari         |
|                         |   |   prisensi_kehadiran        |
| revenueTrend[]          |<--| GROUP BY month dari         |
|                         |   |   m_transaksi_events        |
| recentActivity[]        |<--| aktivitas_logs (latest 10)  |
| upcomingEvents[]        |<--| event_status WHERE          |
|                         |   |   status=Upcomming/Ongoing  |
+-------------------------+    +---------------------------+
```

**API Endpoint:**
```
GET    /api/dashboard/stats
GET    /api/dashboard/calendar         (sudah ada)
GET    /api/dashboard/attendance-trend
GET    /api/dashboard/revenue-trend
GET    /api/dashboard/recent-activity
GET    /api/dashboard/upcoming-events
```

---

## 3. Persiapan Backend (Laravel)

### 3.1 Install & Konfigurasi Sanctum (API Auth)

Sanctum sudah ada di composer.json tapi belum digunakan untuk API auth.

**File yang perlu diubah/dibuat:**

```
app/
+-- Http/
|   +-- Controllers/
|   |   +-- api/                    # BARU - Folder API controllers
|   |   |   +-- AuthController.php  # BARU - Login, logout, user
|   |   |   +-- ApiMemberController.php
|   |   |   +-- ApiEventController.php
|   |   |   +-- ApiAttendanceController.php
|   |   |   +-- ApiTransactionController.php
|   |   |   +-- ApiArticleController.php
|   |   |   +-- ApiIdCardController.php
|   |   |   +-- ApiDashboardController.php
|   +-- Resources/                  # BARU - API Resource transformers
|   |   +-- MemberResource.php
|   |   +-- EventResource.php
|   |   +-- TransactionResource.php
|   |   +-- ArticleResource.php
|   |   +-- AttendanceResource.php
|   |   +-- DashboardStatsResource.php
|   +-- Middleware/
|       +-- EnsureJsonResponse.php   # BARU - Force JSON response

routes/
+-- api.php                         # UBAH - Tambah semua API routes

config/
+-- cors.php                        # UBAH - Izinkan frontend origin
+-- sanctum.php                     # CEK - Sudah ada

app/Models/
+-- User.php                        # UBAH - Tambah HasApiTokens (sudah ada)
                                      + relasi ke DataUser, HakAksesRole
+-- Event.php                       # UBAH - Tambah relasi
+-- DataUser.php                    # UBAH - Tambah relasi
```

**Contoh implementasi AuthController:**

```php
// app/Http/Controllers/api/AuthController.php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\DataUser;
use App\Models\HakAksesRole;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'id_anggota' => 'required',
            'password' => 'required',
        ]);

        $user = User::where('id_anggota', $request->id_anggota)
                    ->where('is_active', '1')
                    ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'ID Anggota atau password salah'
            ], 401);
        }

        // Revoke old tokens
        $user->tokens()->delete();

        // Create new token
        $token = $user->createToken('orgos-frontend')->plainTextToken;

        // Get user roles
        $roles = HakAksesRole::where('id_users', $user->id)
                ->pluck('nama_role')->toArray();

        // Get user profile
        $profile = DataUser::where('id_users', $user->id)->first();

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'id_anggota' => $user->id_anggota,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $roles,
                'foto' => $profile?->foto,
                'no_hp' => $profile?->no_hp,
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['success' => true]);
    }

    public function user(Request $request)
    {
        $user = $request->user();
        $roles = HakAksesRole::where('id_users', $user->id)
                ->pluck('nama_role')->toArray();
        $profile = DataUser::where('id_users', $user->id)->first();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'id_anggota' => $user->id_anggota,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $roles,
                'foto' => $profile?->foto,
                'no_hp' => $profile?->no_hp,
            ]
        ]);
    }
}
```

**Contoh routes/api.php baru:**

```php
use App\Http\Controllers\api\*;

// Public
Route::post('/auth/login', [AuthController::class, 'login']);

// Protected (butuh token)
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);

    // Dashboard
    Route::get('/dashboard/stats', [ApiDashboardController::class, 'stats']);
    Route::get('/dashboard/calendar', [ApiDashboardController::class, 'calendar']);
    Route::get('/dashboard/attendance-trend', [ApiDashboardController::class, 'attendanceTrend']);
    Route::get('/dashboard/revenue-trend', [ApiDashboardController::class, 'revenueTrend']);
    Route::get('/dashboard/recent-activity', [ApiDashboardController::class, 'recentActivity']);
    Route::get('/dashboard/upcoming-events', [ApiDashboardController::class, 'upcomingEvents']);

    // Members
    Route::apiResource('/members', ApiMemberController::class);
    Route::get('/members/{id}/hak-akses', [ApiMemberController::class, 'hakAkses']);

    // Events
    Route::apiResource('/events', ApiEventController::class);
    Route::get('/events/{id}/dates', [ApiEventController::class, 'dates']);
    Route::get('/events/{id}/participants', [ApiEventController::class, 'participants']);

    // Attendance
    Route::get('/attendance', [ApiAttendanceController::class, 'index']);
    Route::post('/attendance', [ApiAttendanceController::class, 'store']);
    Route::get('/attendance/stats', [ApiAttendanceController::class, 'stats']);

    // Transactions / Finance
    Route::get('/transactions', [ApiTransactionController::class, 'index']);
    Route::post('/transactions/verify', [ApiTransactionController::class, 'verify']);
    Route::get('/finance/stats', [ApiTransactionController::class, 'stats']);
    Route::get('/finance/revenue-trend', [ApiTransactionController::class, 'revenueTrend']);

    // Articles
    Route::apiResource('/articles', ApiArticleController::class);

    // ID Cards
    Route::get('/id-cards/templates', [ApiIdCardController::class, 'templates']);
    Route::post('/id-cards/templates', [ApiIdCardController::class, 'storeTemplate']);
    Route::get('/id-cards/print/{transaction_id}', [ApiIdCardController::class, 'print']);

    // Content
    Route::get('/content/pesantren', [ApiContentController::class, 'pesantren']);
    Route::put('/content/pesantren', [ApiContentController::class, 'updatePesantren']);
    Route::get('/content/mzt', [ApiContentController::class, 'mzt']);
    Route::put('/content/mzt', [ApiContentController::class, 'updateMzt']);

    // Upload
    Route::post('/upload', [ApiUploadController::class, 'store']);
});

// Webhook (tidak perlu auth, pakai signature verification)
Route::post('/webhook/midtrans', [ApiTransactionController::class, 'midtransWebhook']);
```

### 3.2 Konfigurasi CORS

**File: `config/cors.php`**
```php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://localhost:3000',       // Dev frontend
        'http://localhost:5173',       // Vite dev
        'https://orgos-mzt.vercel.app', // Production (contoh)
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
```

### 3.3 Konfigurasi Sanctum

**File: `config/sanctum.php`** - pastikan stateful domains:
```php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
    '%s%s',
    'localhost,localhost:3000,localhost:5173,127.0.0.1,127.0.0.1:8000,::1',
    Sanctum::currentApplicationUrlWithPort()
))),
```

### 3.4 Kernel Middleware

**File: `app/Http/Kernel.php`** - pastikan api middleware group ada:
```php
'api' => [
    \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    'throttle:api',
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
],
```

---

## 4. Persiapan Frontend (React)

### 4.1 File yang Perlu Dibuat/Diubah

```
src/
+-- lib/
|   +-- api.ts                    # BARU - API client (fetch wrapper)
|   +-- auth.ts                   # BARU - Token management
|   +-- mock-data.ts              # UBAH - Tetap untuk fallback/dev
|
+-- hooks/
|   +-- use-auth.ts              # BARU - Auth hook (login, logout, user)
|   +-- use-members.ts           # BARU - Members data hook
|   +-- use-events.ts            # BARU - Events data hook
|   +-- use-attendance.ts        # BARU - Attendance data hook
|   +-- use-transactions.ts      # BARU - Transactions data hook
|   +-- use-dashboard.ts         # BARU - Dashboard stats hook
|   +-- use-articles.ts          # BARU - Articles data hook
|
+-- components/
|   +-- login-form.tsx            # BARU - Login page component
|   +-- auth-guard.tsx            # BARU - Protected route wrapper
|
+-- routes/
|   +-- _login.tsx                # BARU - Login route
|   +-- _app.tsx                  # UBAH - Tambah auth check
|   +-- _app.index.tsx            # UBAH - Ganti mock dengan API
|   +-- _app.members.tsx          # UBAH - Ganti mock dengan API
|   +-- _app.events.tsx           # UBAH - Ganti mock dengan API
|   +-- (semua route lainnya)     # UBAH - Ganti mock dengan API
|
+-- .env                          # BARU - Environment variables
+-- .env.example                  # BARU - Template
```

### 4.2 API Client Layer

**File baru: `src/lib/api.ts`**
```typescript
const API_BASE = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';

interface ApiResponse<T> {
  success: boolean;
  data: T;
  meta?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  message?: string;
}

async function request<T>(
  endpoint: string,
  options: RequestInit = {}
): Promise<ApiResponse<T>> {
  const token = localStorage.getItem('orgos-token');

  const res = await fetch(`${API_BASE}${endpoint}`, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...(token && { Authorization: `Bearer ${token}` }),
      ...options.headers,
    },
  });

  if (res.status === 401) {
    localStorage.removeItem('orgos-token');
    window.location.href = '/login';
    throw new Error('Unauthorized');
  }

  if (!res.ok) {
    const error = await res.json().catch(() => ({}));
    throw new Error(error.message || `HTTP ${res.status}`);
  }

  return res.json();
}

// Convenience methods
export const api = {
  get: <T>(url: string) => request<T>(url),

  post: <T>(url: string, body: unknown) =>
    request<T>(url, { method: 'POST', body: JSON.stringify(body) }),

  put: <T>(url: string, body: unknown) =>
    request<T>(url, { method: 'PUT', body: JSON.stringify(body) }),

  delete: <T>(url: string) =>
    request<T>(url, { method: 'DELETE' }),

  upload: <T>(url: string, formData: FormData) =>
    request<T>(url, {
      method: 'POST',
      body: formData,
      headers: {
        Authorization: `Bearer ${localStorage.getItem('orgos-token')}`,
      },
    }),
};
```

### 4.3 Auth Hook

**File baru: `src/hooks/use-auth.ts`**
```typescript
import { useState, useEffect } from 'react';
import { api } from '@/lib/api';

interface User {
  id: number;
  id_anggota: string;
  name: string;
  email: string;
  roles: string[];
  foto: string | null;
  no_hp: string | null;
}

export function useAuth() {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const token = localStorage.getItem('orgos-token');
    if (token) {
      api.get<{ user: User }>('/auth/user')
        .then((res) => setUser(res.data.user))
        .catch(() => {
          localStorage.removeItem('orgos-token');
        })
        .finally(() => setLoading(false));
    } else {
      setLoading(false);
    }
  }, []);

  const login = async (id_anggota: string, password: string) => {
    const res = await api.post<{ token: string; user: User }>(
      '/auth/login',
      { id_anggota, password }
    );
    localStorage.setItem('orgos-token', res.data.token);
    setUser(res.data.user);
    return res.data;
  };

  const logout = async () => {
    await api.post('/auth/logout', {}).catch(() => {});
    localStorage.removeItem('orgos-token');
    setUser(null);
  };

  return { user, loading, login, logout, isAuthenticated: !!user };
}
```

### 4.4 Data Hooks (Contoh: Members)

**File baru: `src/hooks/use-members.ts`**
```typescript
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';

interface Member {
  id: string;
  name: string;
  email: string;
  phone: string;
  role: string;
  status: string;
  joined: string;
  avatar: string;
  foto: string | null;
  // ... other fields
}

interface MemberListParams {
  page?: number;
  search?: string;
  role?: string;
  status?: string;
}

export function useMembers(params: MemberListParams = {}) {
  const searchParams = new URLSearchParams();
  if (params.page) searchParams.set('page', String(params.page));
  if (params.search) searchParams.set('search', params.search);
  if (params.role && params.role !== 'all') searchParams.set('role', params.role);
  if (params.status && params.status !== 'all') searchParams.set('status', params.status);

  return useQuery({
    queryKey: ['members', params],
    queryFn: () => api.get<Member[]>(`/members?${searchParams}`),
  });
}

export function useMember(id: string) {
  return useQuery({
    queryKey: ['members', id],
    queryFn: () => api.get<Member>(`/members/${id}`),
    enabled: !!id,
  });
}

export function useCreateMember() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: FormData) => api.upload<Member>('/members', data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['members'] }),
  });
}

export function useDeleteMember() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => api.delete(`/members/${id}`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['members'] }),
  });
}
```

### 4.5 Environment Variables

**File baru: `src/.env`** (atau root `.env`)
```bash
# API Backend URL
VITE_API_URL=http://localhost:8000/api

# Untuk production:
# VITE_API_URL=https://maziltutholiban.com/api
```

### 4.6 Ubah Route untuk Pakai API Data

**Contoh perubahan `_app.members.tsx`:**
```typescript
// SEBELUM (mock data):
import { members } from "@/lib/mock-data";
function MembersPage() {
  const filtered = members.filter(...)
  ...
}

// SESUDAH (API data):
import { useMembers } from "@/hooks/use-members";
function MembersPage() {
  const [query, setQuery] = useState("");
  const [role, setRole] = useState("all");
  const [status, setStatus] = useState("all");
  const [page, setPage] = useState(1);

  const { data, isLoading, error } = useMembers({
    page, search: query, role, status
  });

  if (isLoading) return <MembersSkeleton />;
  if (error) return <ErrorMessage error={error} />;

  const members = data?.data ?? [];
  const meta = data?.meta;
  ...
}
```

---

## 5. Persiapan Database

### 5.1 Migration Baru yang Perlu Dibuat

```bash
# Di folder Laravel project
php artisan make:migration add_type_and_capacity_to_events_table --table=events
php artisan make:migration add_category_and_views_to_beritas_table --table=beritas
php artisan make:migration add_name_and_color_to_template_id_card_table --table=template_id_card
php artisan make:migration create_campaigns_table
php artisan make:migration create_certificates_table
php artisan make:migration create_broadcasts_table
php artisan make:migration create_org_settings_table
```

### 5.2 Detail Migration

**a) Tambah kolom di `events`:**
```php
Schema::table('events', function (Blueprint $table) {
    $table->string('type')->nullable()->after('judul_event');
    $table->integer('capacity')->default(0)->after('lokasi');
});
```

**b) Tambah kolom di `beritas`:**
```php
Schema::table('beritas', function (Blueprint $table) {
    $table->string('category')->nullable()->after('slug');
    $table->integer('views')->default(0)->after('is_active');
});
```

**c) Tambah kolom di `template_id_card`:**
```php
Schema::table('template_id_card', function (Blueprint $table) {
    $table->string('name')->nullable()->after('id');
    $table->string('color')->default('emerald')->after('status');
});
```

**d) Tabel baru `campaigns`:**
```php
Schema::create('campaigns', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->text('description')->nullable();
    $table->bigInteger('target_amount')->default(0);
    $table->bigInteger('raised_amount')->default(0);
    $table->integer('donors_count')->default(0);
    $table->date('start_date');
    $table->date('end_date');
    $table->string('banner')->nullable();
    $table->enum('status', ['active', 'completed', 'draft'])->default('draft');
    $table->enum('is_active', ['1', '0'])->default('1');
    $table->timestamps();
});
```

**e) Tabel baru `certificates`:**
```php
Schema::create('certificates', function (Blueprint $table) {
    $table->id();
    $table->string('certificate_id')->unique();
    $table->integer('id_event')->nullable();
    $table->string('id_anggota');
    $table->string('recipient_name');
    $table->string('template_name');
    $table->text('template_path')->nullable();
    $table->date('issued_date');
    $table->string('verification_code')->unique();
    $table->enum('is_active', ['1', '0'])->default('1');
    $table->timestamps();
});
```

**f) Tabel baru `broadcasts`:**
```php
Schema::create('broadcasts', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('message');
    $table->enum('channel', ['whatsapp', 'email', 'push'])->default('whatsapp');
    $table->string('audience')->default('all'); // all, role:xxx, event:xxx
    $table->integer('audience_count')->default(0);
    $table->integer('sent_count')->default(0);
    $table->integer('opened_count')->default(0);
    $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'failed'])->default('draft');
    $table->timestamp('scheduled_at')->nullable();
    $table->timestamp('sent_at')->nullable();
    $table->string('created_by')->nullable();
    $table->enum('is_active', ['1', '0'])->default('1');
    $table->timestamps();
});
```

**g) Tabel baru `org_settings`:**
```php
Schema::create('org_settings', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();
    $table->text('value')->nullable();
    $table->string('group')->default('general');
    $table->timestamps();
});
```

### 5.3 MySQL VIEW `event_status` (Formalisasi)

Buat migration untuk VIEW yang selama ini dibuat manual:
```php
public function up()
{
    DB::statement('
        CREATE OR REPLACE VIEW event_status AS
        SELECT
            e.*,
            CASE
                WHEN CURDATE() BETWEEN e.tanggal_mulai AND e.tanggal_selesai
                    THEN "Ongoing"
                WHEN CURDATE() < e.tanggal_mulai
                    THEN "Upcomming"
                WHEN CURDATE() > e.tanggal_selesai
                    THEN "Complate"
            END AS status
        FROM events e
    ');
}
```

---

## 6. Persiapan Infrastructure

### 6.1 SSL/HTTPS

- Frontend dan backend **WAJIB** pakai HTTPS karena token Sanctum dikirim via header.
- cPanel hosting (`maziltutholiban.com`) biasanya sudah punya AutoSSL/Let''s Encrypt.
- Untuk frontend di Vercel/Cloudflare, HTTPS otomatis.

### 6.2 Domain & DNS

```
Opsi A: Same domain (recommended untuk simplicity)
  maziltutholiban.com       -> Laravel (cPanel)
  maziltutholiban.com/admin -> Frontend React (subdirectory)
  maziltutholiban.com/api   -> Laravel API

Opsi B: Subdomain
  app.maziltutholiban.com   -> Frontend React (Vercel)
  api.maziltutholiban.com   -> Laravel API (cPanel)

Opsi C: Separate domain
  orgos-mzt.vercel.app      -> Frontend React (Vercel)
  maziltutholiban.com       -> Laravel API (cPanel)
```

### 6.3 Deployment Frontend

**Opsi 1: Vercel (Recommended)**
```bash
# Di folder frontend (bloom-unity-flow)
vercel login
vercel --prod

# Set environment variable di Vercel Dashboard:
# VITE_API_URL = https://maziltutholiban.com/api
```

**Opsi 2: Cloudflare Pages**
```bash
# Connect GitHub repo, set build command:
# Build: npm run build
# Output: .output/public
# Env: VITE_API_URL = https://maziltutholiban.com/api
```

**Opsi 3: Build static, upload ke cPanel**
```bash
npm run build
# Upload folder .output/ ke cPanel subdirectory /admin
```

### 6.4 Deployment Backend (Update)

Backend Laravel tidak perlu deploy ulang secara keseluruhan.
Hanya perlu:
1. Upload file baru (API controllers, resources, routes/api.php)
2. Jalankan `php artisan migrate` untuk tabel baru
3. Jalankan `php artisan config:cache` dan `php artisan route:cache`
4. Pastikan `storage:link` aktif

---

## 7. Persiapan Development Environment

### 7.1 Tools yang Dibutuhkan

| Tool | Versi | Install | Kegunaan |
|------|-------|---------|----------|
| PHP | >= 8.0.2 | Sudah ada | Backend |
| Composer | 2.x | Sudah ada | PHP dependencies |
| Node.js | >= 18.x | `nodejs.org` | Frontend build |
| Bun | latest | `bun.sh` | Frontend package manager |
| MySQL | >= 5.7 | Sudah ada | Database |
| Git | latest | Sudah ada | Version control |
| Postman/Insomnia | latest | `postman.com` | API testing |

### 7.2 Setup Development Lokal

**Terminal 1 — Backend (Laravel):**
```bash
cd laravel-mzt

# Pastikan dependencies terinstall
composer install

# Jalankan migration baru
php artisan migrate

# Buat storage link
php artisan storage:link

# Jalankan server
php artisan serve --port=8000

# Backend berjalan di: http://localhost:8000
# API endpoint: http://localhost:8000/api
```

**Terminal 2 — Frontend (React):**
```bash
cd bloom-unity-flow

# Install dependencies
bun install
# atau: npm install

# Buat file .env
echo "VITE_API_URL=http://localhost:8000/api" > .env

# Jalankan dev server
bun dev
# atau: npm run dev

# Frontend berjalan di: http://localhost:3000
```

**Terminal 3 — Database (MySQL):**
```bash
# Pastikan MySQL berjalan
# Bisa pakai XAMPP, Laravel Herd, atau Docker:
docker run -d --name mzt-mysql -p 3306:3306 -e MYSQL_ROOT_PASSWORD=root -e MYSQL_DATABASE=mzt mysql:8
```

### 7.3 Testing API dengan cURL

```bash
# Test login
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"id_anggota": "0001231920", "password": "password123"}'

# Test get members (pakai token dari login)
curl http://localhost:8000/api/members \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"

# Test dashboard stats
curl http://localhost:8000/api/dashboard/stats \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

---

## 8. Checklist Langkah Demi Langkah

Gunakan checklist ini untuk tracking progress integrasi.

### Phase 1: Backend API Foundation (3-5 hari)

- [ ] **1.1** Buat migration baru (events.type, events.capacity, beritas.category, beritas.views, template_id_card.name, template_id_card.color)
- [ ] **1.2** Buat tabel baru: campaigns, certificates, broadcasts, org_settings
- [ ] **1.3** Formalisasi MySQL VIEW event_status ke migration
- [ ] **1.4** Jalankan `php artisan migrate` dan verifikasi semua tabel
- [ ] **1.5** Konfigurasi Sanctum di `config/sanctum.php`
- [ ] **1.6** Konfigurasi CORS di `config/cors.php`
- [ ] **1.7** Buat `AuthController` (login, logout, user)
- [ ] **1.8** Test login API dengan Postman/cURL
- [ ] **1.9** Buat `ApiDashboardController` (stats, trends, activity)
- [ ] **1.10** Buat `ApiMemberController` + `MemberResource`
- [ ] **1.11** Buat `ApiEventController` + `EventResource`
- [ ] **1.12** Buat `ApiAttendanceController`
- [ ] **1.13** Buat `ApiTransactionController` + `TransactionResource`
- [ ] **1.14** Buat `ApiArticleController`
- [ ] **1.15** Buat `ApiIdCardController`
- [ ] **1.16** Buat `ApiContentController` (pesantren, mzt, carousel)
- [ ] **1.17** Buat `ApiUploadController` (file upload via API)
- [ ] **1.18** Tulis semua routes di `routes/api.php`
- [ ] **1.19** Test semua endpoint dengan Postman
- [ ] **1.20** Deploy API ke cPanel (upload file baru + migrate)

### Phase 2: Frontend Auth & API Layer (2-3 hari)

- [ ] **2.1** Clone repo bloom-unity-flow secara terpisah
- [ ] **2.2** Buat `src/lib/api.ts` (API client wrapper)
- [ ] **2.3** Buat `src/lib/auth.ts` (token management)
- [ ] **2.4** Buat `src/hooks/use-auth.ts` (auth hook)
- [ ] **2.5** Buat halaman login (`src/routes/_login.tsx`)
- [ ] **2.6** Buat auth guard component (`src/components/auth-guard.tsx`)
- [ ] **2.7** Setup `.env` dengan `VITE_API_URL`
- [ ] **2.8** Test login flow end-to-end
- [ ] **2.9** Test auto-redirect ke login jika token expired

### Phase 3: Connect Frontend Pages ke API (5-7 hari)

- [ ] **3.1** Buat `src/hooks/use-members.ts` + ubah `_app.members.tsx`
- [ ] **3.2** Buat `src/hooks/use-events.ts` + ubah `_app.events.tsx`
- [ ] **3.3** Buat `src/hooks/use-dashboard.ts` + ubah `_app.index.tsx`
- [ ] **3.4** Buat `src/hooks/use-attendance.ts` + ubah `_app.attendance.tsx`
- [ ] **3.5** Buat `src/hooks/use-transactions.ts` + ubah `_app.finance.tsx`
- [ ] **3.6** Buat `src/hooks/use-articles.ts` + ubah `_app.cms.tsx`
- [ ] **3.7** Ubah `_app.id-cards.tsx` untuk pakai API
- [ ] **3.8** Ubah `_app.members.$id.tsx` untuk pakai API
- [ ] **3.9** Ubah `_app.events.$id.tsx` untuk pakai API
- [ ] **3.10** Ubah `_app.settings.tsx` untuk pakai API
- [ ] **3.11** Ubah `_app.communications.tsx` (stub untuk sekarang)
- [ ] **3.12** Ubah `_app.certificates.tsx` (stub untuk sekarang)
- [ ] **3.13** Update `app-sidebar.tsx` dengan nama organisasi dari API
- [ ] **3.14** Update `top-nav.tsx` dengan data user dari API

### Phase 4: Polish & Deploy (2-3 hari)

- [ ] **4.1** Loading states & skeleton screens di semua halaman
- [ ] **4.2** Error handling & toast notifications
- [ ] **4.3** File upload (foto anggota, banner event, artikel)
- [ ] **4.4** Image display (pastikan storage link aktif)
- [ ] **4.5** Pagination di semua tabel
- [ ] **4.6** Search & filter testing
- [ ] **4.7** Responsive testing (mobile/tablet)
- [ ] **4.8** Dark mode testing
- [ ] **4.9** Build production: `npm run build`
- [ ] **4.10** Deploy frontend ke Vercel/Cloudflare
- [ ] **4.11** Set environment variable production
- [ ] **4.12** End-to-end testing di production

---

## 9. Testing & QA

### 9.1 Test Cases per Fitur

| Fitur | Test Case | Expected Result |
|-------|-----------|----------------|
| **Login** | Input id_anggota + password yang valid | Redirect ke dashboard, token tersimpan |
| **Login** | Input password salah | Error message muncul |
| **Login** | Token expired | Auto-redirect ke login |
| **Dashboard** | Load halaman | Stats muncul, charts ter-render |
| **Members** | Load tabel | Data anggota tampil, paginasi aktif |
| **Members** | Search nama | Hasil filter sesuai query |
| **Members** | Filter role/status | Hasil filter sesuai selection |
| **Members** | Tambah anggota baru | Form submit, data masuk ke DB |
| **Members** | Upload foto anggota | Foto ter-resize 300x400, tersimpan |
| **Events** | Load daftar event | Data event tampil dengan status |
| **Events** | Buat event baru | Event tersimpan + tanggal_events ter-generate |
| **Attendance** | Scan barcode anggota | Data kehadiran tersimpan |
| **Finance** | Load transaksi | Data transaksi tampil |
| **Finance** | Verifikasi pembayaran | Status berubah ke settlement |
| **CMS** | Buat artikel baru | Artikel tersimpan + foto upload |
| **ID Card** | Upload template | Template tersimpan |
| **ID Card** | Cetak ID card | Preview muncul dengan data anggota |

### 9.2 Checklist QA

- [ ] Login berhasil dan token tersimpan
- [ ] Logout menghapus token dan redirect
- [ ] Semua halaman load tanpa error console
- [ ] Data dari API sesuai dengan data di database
- [ ] File upload berfungsi (foto, banner, template)
- [ ] Gambar dari storage tampil dengan benar
- [ ] Pagination bekerja di semua tabel
- [ ] Search dan filter berfungsi
- [ ] Dark mode tidak ada visual glitch
- [ ] Responsive di mobile (sidebar collapse)
- [ ] Command palette (Cmd+K) berfungsi
- [ ] Notifikasi toast muncul saat CRUD berhasil/gagal

---

## 10. Timeline & Prioritas

### Sprint Plan (Total: ~3-4 minggu)

```
MINGGU 1: Backend API Foundation
+------------------------------------------+
| Day 1-2: Migration + Sanctum + Auth      |
| Day 3:   Dashboard API + Members API     |
| Day 4:   Events API + Attendance API     |
| Day 5:   Transactions + Articles API     |
| Day 6-7: ID Cards + Content + Upload API |
+------------------------------------------+
Deliverable: Semua API endpoint berfungsi
             Postman collection lengkap

MINGGU 2: Frontend Auth + Core Pages
+------------------------------------------+
| Day 1-2: API client + Auth flow + Login  |
| Day 3:   Dashboard page connected        |
| Day 4:   Members page connected          |
| Day 5:   Events page connected           |
| Day 6-7: Attendance + Finance connected  |
+------------------------------------------+
Deliverable: 6 halaman utama terkoneksi API

MINGGU 3: Remaining Pages + Polish
+------------------------------------------+
| Day 1:   CMS + ID Cards connected        |
| Day 2:   Settings + Communications       |
| Day 3:   Certificates (stub)             |
| Day 4:   Error handling + loading states |
| Day 5:   File upload di semua form       |
| Day 6-7: Testing + bugfix                |
+------------------------------------------+
Deliverable: Semua halaman terkoneksi

MINGGU 4: Deployment + Hardening
+------------------------------------------+
| Day 1:   Deploy backend API ke cPanel    |
| Day 2:   Deploy frontend ke Vercel       |
| Day 3:   End-to-end testing production   |
| Day 4:   Fix production issues           |
| Day 5:   Documentation + handover        |
+------------------------------------------+
Deliverable: Aplikasi live di production
```

### Prioritas Halaman

| Prioritas | Halaman | Alasan |
|-----------|---------|--------|
| P0 (Week 1) | Dashboard | Halaman pertama yang dilihat admin |
| P0 (Week 1) | Members | CRUD inti, fitur paling sering dipakai |
| P0 (Week 1) | Events | CRUD inti, terkait transaksi & presensi |
| P1 (Week 2) | Attendance | Terkait langsung dengan events |
| P1 (Week 2) | Finance | Pembayaran & verifikasi |
| P2 (Week 3) | CMS (Berita) | Penting tapi tidak urgent |
| P2 (Week 3) | ID Cards | Fitur sudah ada di backend |
| P3 (Week 3) | Settings | Bisa ditunda, data bisa edit manual |
| P3 (Week 3) | Communications | Butuh modul broadcast baru |
| P3 (Week 3) | Certificates | Butuh modul baru, bisa di-stub dulu |

---

## Ringkasan Persiapan

| Kategori | Item | Effort |
|----------|------|--------|
| **Backend** | ~10 file baru (controllers + resources) | 3-5 hari |
| **Backend** | ~35 API endpoint baru | included |
| **Backend** | Sanctum + CORS config | 0.5 hari |
| **Database** | 7 migration baru (3 alter + 4 create) | 1 hari |
| **Frontend** | API client + Auth layer | 1-2 hari |
| **Frontend** | 7 custom hooks (use-members, dll) | 2 hari |
| **Frontend** | Update 12 route files | 3-4 hari |
| **Frontend** | Login page + auth guard | 1 hari |
| **Infra** | Deploy frontend + CORS | 0.5 hari |
| **QA** | Testing semua fitur | 2-3 hari |
| **TOTAL** | | **~3-4 minggu** |

---

*Dokumen ini dibuat sebagai panduan implementasi. Tidak ada source code yang diubah selama pembuatan dokumen ini.*
