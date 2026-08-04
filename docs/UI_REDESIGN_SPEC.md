# MZT APPS — UI Redesign Specification

> **Purpose:** Build a new, professional React/Next.js frontend for **MZT APPS (Maziltu Tholiban)**, a pesantren (Islamic boarding school) organization management system. The new frontend consumes the existing Laravel REST API.
>
> **Audience:** Lovable / frontend developers.
> **Status:** v1.0 — Draft for implementation.

---

## 1. Project Overview

**MZT APPS** is a web application that manages the organization of an Islamic boarding school: membership, events, attendance, payments, content publishing, and member cards.

### Current architecture
- **Backend:** Laravel 9 (PHP 8, MySQL 8) — production ready.
- **Legacy admin UI:** Laravel Blade + Bootstrap 4 + jQuery (kept as a fallback; not part of this redesign).
- **REST API:** Laravel Sanctum (token auth) — this is what the new frontend will consume.
- **New frontend (this spec):** React (Next.js recommended) + Tailwind CSS, fully responsive.

### Current modules
| Module | Description |
|---|---|
| Members | CRUD members, auto-generated ID, Code39 barcode, photo, KTA (member card) export |
| Events | Multi-day events, banner, location, price, slug URL |
| Attendance | Per-event per-date attendance, barcode scanning |
| News & Announcements | CRUD news, published on public site |
| Online Payment | Midtrans Snap integration, manual verification |
| ID Card Generator | Template upload, component positioning, printing |
| Dashboard | Event statistics, calendar, member totals |
| Content Management | Pesantren info, MZT profile, homepage carousel |
| Activity Log | Audit trail of all user actions |
| Role-Based Access | Menu visibility driven by user roles |
| Public Website | Homepage, news, announcements, event detail, online payment |

---

## 2. Technical Context for the New Frontend

### 2.1 Backend API
- **Base URL:** `http://localhost:8000/api` (production: `https://maziltutholiban.com/api`)
- **Response envelope:** JSON, always `{ success: bool, message?, data? }`.
- **Errors:** HTTP 400 (validation), 401/419 (auth), 404, 500. Validation errors return a Laravel `errors` object.

### 2.2 Authentication (Sanctum)
- Login uses **`id_anggota` + `password`** — **NOT email**.
- Successful login returns a `token` (plain text Bearer token). Example:

```json
{
  "success": true,
  "token": "1|abc123...",
  "user": {
    "id": 25,
    "id_anggota": "0002162323",
    "name": "Admin",
    "email": "...",
    "roles": ["dashboard", "anggota", "event", "berita", "tampilan", "aktivitas_user", "id_card", "prisensi"],
    "foto": "image/anggota/xxx.jpg"
  }
}
```

- After login, send the token in every protected request:
  `Authorization: Bearer <token>`
- Use `GET /user` to restore the session on app load.
- Logout calls `POST /logout` and deletes the token.

### 2.3 Roles & menu gating
The `roles` array from `/user` (and login) controls which menus are shown:

| Role | Access |
|---|---|
| `dashboard` | Dashboard + calendar |
| `anggota` | Members management |
| `profil` | Own profile |
| `event` | Events, transactions, attendance (full) |
| `prisensi` | View events & record attendance |
| `berita` | News management |
| `tampilan` | Edit website content |
| `aktivitas_user` | Activity log |
| `id_card` | ID card management & print |

The frontend should gate routes/menus by these exact role names.

### 2.4 Media / files
- Uploaded images are served from `/storage/...` (Laravel public disk). Build media URLs as `{BASE_URL}/storage/{path}`.
- File uploads are sent as `multipart/form-data` (field names per endpoint below).
- Default avatars fallback: `/assets/avatar-1.png` when no photo.

### 2.5 CORS
Already configured to allow `localhost`, `localhost:3000`, `127.0.0.1`, `127.0.0.1:8000`. If deploying elsewhere, update `config/cors.php` / `SANCTUM_STATEFUL_DOMAINS` in `.env`.

### 2.6 Environment variables for the frontend
```
NEXT_PUBLIC_API_BASE_URL=http://localhost:8000/api
NEXT_PUBLIC_STORAGE_URL=http://localhost:8000/storage
```

---

## 3. REST API Reference

> All protected endpoints require `Authorization: Bearer <token>` unless marked **(public)**.

### 3.1 Auth
| Method | Endpoint | Auth | Body | Response |
|---|---|---|---|---|
| POST | `/login` | — | `id_anggota`, `password` | `{ success, token, user }` |
| GET | `/user` | ✅ | — | `{ success, user: { id, id_anggota, name, email, roles, foto, data } }` |
| POST | `/logout` | ✅ | — | `{ success, message }` |

### 3.2 Dashboard
| Method | Endpoint | Auth | Response `data` |
|---|---|---|---|
| GET | `/dashboard/stats` | ✅ | `{ event, event_selesai, event_mendatang, total_anggota }` |
| GET | `/dashboard/calendar` | ✅ | FullCalendar-compatible array: `[{ title, start (YYYY-MM-DD), end, backgroundColor, borderColor, textColor }]` |
| GET | `/dashboard/events` | ✅ | Event rows incl. computed `status` (`Ongoing`/`Upcomming`/`Complate`), `tanggal` formatted `d/m/Y - d/m/Y` |

### 3.3 Members (`/members`)
`GET /members` — list. **Important:** each item has both `id` (= `data_users.id`) and `id_users` (= `users.id`). **Always use `id_users` for detail/update/delete and for KTA/ID card.** Fields: `id, id_users, id_anggota, nama, email, no_hp, alamat, niqobah, pekerjaan, foto, tahun_masuk, tahun_keluar, tempat_lahir, tanggal_lahir`.

| Method | Endpoint | Auth | Notes |
|---|---|---|---|
| GET | `/members` | ✅ | List |
| GET | `/members/{id_users}` | ✅ | Detail |
| POST | `/members` | ✅ | Create. `multipart`. Fields: `nama*, alamat*, niqobah*, pekerjaan*, tanggal_lahir*, tahun_masuk*, tahun_keluar*, no_hp*, password* (min 6)`, optional `email, tempat_lahir, roles[]`, `foto` (file) |
| POST | `/members/{id_users}` | ✅ | Update. Same fields minus required password (optional `password` resets it), `foto` optional |
| DELETE | `/members/{id_users}` | ✅ | Soft delete (`is_active=0`) |

**Create flow (mirror backend behavior):**
1. Backend generates `id_anggota` = last id_anggota + 1, and a barcode string (`MZT` + zero-padded id).
2. Creates a `users` row + `data_users` row.
3. Roles are set from `roles[]`; the role `profil` is always implied.

### 3.4 Events (`/events`)
`GET /events` — list. Fields include: `id, judul_event, slug, lokasi, harga, deskripsi, banner, tanggal_mulai, tanggal_selesai, is_active`.

| Method | Endpoint | Auth | Notes |
|---|---|---|---|
| GET | `/events` | ✅ | List |
| GET | `/events/{id}` | ✅ | Detail |
| POST | `/events` | ✅ | `multipart`. Fields: `judul_event*, slug* (unique), lokasi*, harga* (numeric), deskripsi*, tanggal*` format **`d/m/Y - d/m/Y`**; optional `banner` (file) |
| POST | `/events/{id}` | ✅ | Update. Same fields; `banner` optional |
| DELETE | `/events/{id}` | ✅ | Soft delete |

### 3.5 News (`/news`)
`GET /news` — list. Fields: `id, judul, slug, deskripsi, foto, pembuat, created_at`.

| Method | Endpoint | Auth | Notes |
|---|---|---|---|
| GET | `/news` | ✅ | List |
| GET | `/news/{id}` | ✅ | Detail |
| POST | `/news` | ✅ | `multipart`. Fields: `judul*, slug*, deskripsi*`; optional `foto` |
| POST | `/news/{id}` | ✅ | Update |
| DELETE | `/news/{id}` | ✅ | Soft delete |

### 3.6 Attendance (`/attendance`)
| Method | Endpoint | Auth | Notes |
|---|---|---|---|
| GET | `/attendance/{eventId}/{tanggalId}` | ✅ | List of attendance records with `dataUser` relation |
| POST | `/attendance` | ✅ | Fields: `id_anggota* (string), id_event*, id_tanggal*` |

> **Caveat (backend):** this endpoint queries `data_users.id_anggota`, which does **not exist** on the `data_users` table (the column lives on `users`). It may error at runtime. Frontend should treat this endpoint as **unstable** until fixed; plan UI around it but flag to backend team.

### 3.7 Transactions (`/transactions/{eventId}`)
| Method | Endpoint | Auth | Notes |
|---|---|---|---|
| GET | `/transactions/{eventId}` | ✅ | List of transactions for an event, with `dataUser` relation |

### 3.8 Content management
| Method | Endpoint | Auth | Notes |
|---|---|---|---|
| GET | `/carousel` | ✅ | List of carousel slides (`is_active=1`). Fields: `id, judul?, deskripsi?, foto, is_active` |
| POST | `/carousel/{id}` | ✅ | `multipart`. Optional `foto` (file). Replaces slide image |
| GET | `/info/pesantren` | (public) | Single row: `judul, deskripsi, alamat, telpon, email, foto` |
| POST | `/info/pesantren` | ✅ | `multipart`. Fields: `judul*, deskripsi*, alamat*, telpon*`, optional `email`, `foto` |
| GET | `/info/mzt` | (public) | Single row (same shape as pesantren) |
| POST | `/info/mzt` | ✅ | `multipart`. Same fields |

### 3.9 Activity Log
| Method | Endpoint | Auth | Notes |
|---|---|---|---|
| GET | `/activity-log` | ✅ | Latest 100 logs, newest first, with `dataUser` relation |
| GET | `/activity-log/{userId}` | ✅ | Logs for one user |

### 3.10 Profile
| Method | Endpoint | Auth | Notes |
|---|---|---|---|
| POST | `/profile` | ✅ | `multipart`. Fields: `nama*, alamat*, niqobah*, pekerjaan*, tanggal_lahir*, tahun_masuk*, tahun_keluar*, no_hp*`, optional `email, password, foto` |

> **Caveat (backend):** `profileUpdate` writes `nama`/`email` into the `data_users` table, which has no such columns → likely SQL error. Frontend should be built to call this endpoint, but flag it to the backend team; the **members** update endpoint (`POST /members/{id}`) is the reliable alternative.

### 3.11 Public data (used by public pages)
- `GET /api/info/pesantren` — pesantren profile.
- `GET /api/info/mzt` — MZT profile.
- For public news/events, the legacy Blade routes serve HTML. The new frontend should expose its own public pages and consume:
  - `GET /api/news` (requires auth) — **note:** news list currently requires auth. If public news pages are required, the backend should expose a public endpoint. Until then, public news can be fetched with a shared/service token or the route should be relaxed. **Flag to backend.**

### 3.12 KTA & ID Card (print)
- `GET /api/kta/{id}` exists but returns **legacy Blade HTML** — do not use it. The new frontend should **render KTA and ID cards from API data** with its own print layouts.
- KTA data source: `GET /members/{id_users}` (fields: `nama, id_anggota, alamat, niqobah, tahun_masuk, tahun_keluar, foto, barcode`).
- Barcode: the API returns a barcode **string** (e.g. `MZT00001`) in the `data_users` row (via `/user` → `data.data.barcode`). The frontend must generate a Code39 barcode image client-side (e.g. a barcode library) from that string.

---

## 4. Design Direction

**Direction: "Modern Professional Enterprise"** — a clean, premium, data-dense admin experience (reference quality: Linear, Stripe, Notion), extended to a warm, trustworthy public site. Keeps the existing brand identity (emerald + gold) but refined.

### 4.1 Design principles
1. **Consistency** — one design-token system everywhere; no ad-hoc styling.
2. **Clarity** — tables and forms are the core of the product; they must be dense, legible, and calm.
3. **Trust** — Islamic organization context: clean, modest, premium. Emerald conveys growth/trust; gold conveys quality/heritage.
4. **Accessibility** — WCAG AA contrast, focus states, keyboard navigation, semantic HTML.
5. **Responsive-first** — usable on phone, tablet, desktop.

### 4.2 Color palette
Refined brand palette (emerald + gold):

| Token | Light | Dark |
|---|---|---|
| `primary` | `#166534` (deep green) | `#34d399` (emerald 400) |
| `primary-hover` | `#15803d` | `#2ec08a` |
| `primary-soft` | `rgba(22,101,52,0.08)` | `rgba(52,211,153,0.14)` |
| `accent` (gold) | `#b8860b` | `#e6b53c` |
| `accent-soft` | `rgba(184,134,11,0.10)` | `rgba(230,181,60,0.14)` |
| `bg` | `#f6f8f7` | `#0d1317` |
| `bg-card` | `#ffffff` | `#141c22` |
| `bg-muted` | `#eef1f0` | `#1c262d` |
| `fg` | `#17202a` | `#e6edf3` |
| `fg-muted` | `#64748b` | `#8aa0b4` |
| `border` | `#e2e8f0` | `rgba(255,255,255,0.08)` |
| `success` | `#16a34a` | `#4ade80` |
| `warning` | `#d97706` | `#fbbf24` |
| `danger` | `#dc2626` | `#f87171` |
| `info` | `#2563eb` | `#60a5fa` |

### 4.3 Typography
- **Display / headings:** Plus Jakarta Sans (weights 600–800).
- **Body / UI:** Inter (weights 400–600).
- Numeric data (IDs, prices, dates): tabular-nums for alignment.

### 4.4 Spacing & radius
- Spacing scale: 4, 8, 12, 16, 24, 32, 48, 64.
- Radius: sm 6px, md 10px, lg 14px, xl 20px. Buttons/inputs md, cards lg, modals xl.
- Shadows: soft (cards), elevated (dropdown/modal), none on hover-glows (avoid trendy glow).

### 4.5 Dark mode
- Full dark theme, persisted (localStorage), default **light** for admin, respect `prefers-color-scheme` on public site default.

---

## 5. Screen-by-Screen Specification

Legend: **Data** = API source, **States** = loading / empty / error / success.

### 5.1 Login
- Centered card on brand background (subtle pattern/gradient, emerald + gold).
- Fields: `ID Anggota` (number), `Password`.
- Show friendly error ("ID Anggota atau password salah.", "Akun tidak aktif.").
- On success: store token, fetch `/user`, route to `/dashboard` (or first allowed menu).
- **Mobile:** same layout, full-bleed card.

### 5.2 App Shell (authenticated layout)
- **Sidebar:** logo block (MZ monogram), grouped nav by role, collapsible to icons, active state, submenu for "Tentang".
- **Topbar:** search (visual; Ctrl+K), theme toggle, notifications (visual placeholder), user menu (avatar from `foto` or initials) with Profil + Logout.
- **Content:** max-width container, consistent page header pattern (title + description + primary action).
- **Footer:** copyright.
- **Mobile:** sidebar becomes off-canvas drawer with overlay.

### 5.3 Dashboard
- **Data:** `GET /dashboard/stats`, `GET /dashboard/calendar`, `GET /dashboard/events`.
- **Content:**
  - Stat cards: Total Events, Ongoing, Upcoming, Completed, Total Members.
  - Calendar (event-date view) with color-coded status (Ongoing=blue, Completed=green, Upcoming=amber).
  - Upcoming/Ongoing event list.
- **States:** skeleton loaders; empty state when no events.

### 5.4 Members (Anggota)
- **List** (`GET /members`): searchable, sortable, paginated table — columns: No, Nama, ID Anggota, Alamat, Niqobah, Tahun Masuk, Aksi.
- **Row actions:** Edit (opens modal), Delete (confirm dialog), KTA/ID (opens print view).
- **Create/Edit modal:** fields per §3.3, photo preview before upload, role checkboxes/toggles, barcode shown after save.
- **Validation:** required-field marking; inline errors.
- **States:** skeleton, empty, error toast on failure.

### 5.5 Events
- **List** (`GET /events`): table with Judul, Slug, Lokasi, Harga, Tanggal, Status, Aksi.
- **Create/Edit modal:** `tanggal` as a **date-range picker** formatted `d/m/Y - d/m/Y`; banner upload preview.
- **Detail:** event info + banner + attendance + transactions sections.
- **Status badge:** Ongoing / Upcoming / Completed (from `event_status`).

### 5.6 Attendance (Presensi)
- Pick an event + a date → table of records.
- **Scan flow:** input/scan of member `id_anggota` posts to `/attendance`; show member name + success; prevent duplicate (backend returns 400 "already present today").
- **Data:** `GET /attendance/{eventId}/{tanggalId}`.
- **Caveat:** endpoint may be unstable (see §3.6) — build UI, flag to backend.

### 5.7 Transactions
- **List** (`GET /transactions/{eventId}`): member, amount, status, date.
- **Actions:** mark verified (depends on backend support), view detail.
- **Note:** full verification/settlement flows live in legacy admin; match available API only.

### 5.8 News (Berita)
- **List** (`GET /news`): thumbnail, Judul, Slug, Pembuat, Tanggal, status.
- **Create/Edit modal:** judul, slug (auto-suggest), rich text editor (deskripsi), photo upload.

### 5.9 Content management (Tampilan)
- **Carousel:** list of slides (`GET /carousel`), replace image per slide (`POST /carousel/{id}`), live preview.
- **Info Pesantren / Info MZT:** single-record edit forms (`GET/POST /info/pesantren`, `GET/POST /info/mzt`): judul, deskripsi, alamat, telpon, email, foto preview.

### 5.10 Activity Log
- **List** (`GET /activity-log`): user, action, timestamp; filter by user (`/activity-log/{userId}`).

### 5.11 Profile
- Read-only view of own data + editable form (uses `/profile` or `POST /members/{id_users}` as fallback — see §3.10 caveat).

### 5.12 ID Card
- Manage templates + component positioning (foto/nama/niqobah/barcode) → print layout rendered from member data.

### 5.13 Public Website
- **Home:** hero + carousel slides, info pesantren/MZT summary, latest news, events.
- **News list / detail.**
- **Announcements (Pengumuman) / events list / event detail.**
- **Tentang MZT** page.
- **Pembayaran online** (Midtrans Snap) — matches legacy flow; embed Snap.js in an event payment flow.
- **Data:** `GET /info/pesantren`, `GET /info/mzt` public; news/events require backend public endpoints (see §3.11 — flag to backend).

### 5.14 Print layouts
- **KTA (member card):** portrait credit-card-ish sheet (approx 94mm × 122mm) — photo, Code39 barcode (generated client-side from `barcode` string), ID anggota, nama, alamat, niqobah, tahun masuk/keluar.
- **ID Card:** template-driven; print via browser print CSS (`@media print`, no margins).
- Use `@page { margin: 0 }` and hide app chrome during print.

---

## 6. Shared Components

- **PageHeader** — title, description, actions.
- **StatCard** — icon, label, value, optional delta.
- **DataTable** — sorting, searching, pagination, column defs, row actions, empty state.
- **Modal / Drawer** — form modals; close on overlay/Esc; focus trap.
- **FormField** — label, required mark, error text; **Input, Select, DateRangePicker, Textarea, FileDrop (image preview), Toggle.**
- **Badge / StatusPill.**
- **Button** — primary, secondary, ghost, danger, loading state.
- **Toast** — top-end, success/error.
- **Skeleton** — loading placeholders for tables/cards.
- **EmptyState** — icon + message + optional action.
- **Avatar** — image or initials.
- **ConfirmDialog** — destructive confirmations.
- **RichTextEditor** — for news/event descriptions.

---

## 7. Mobile Responsiveness

- Breakpoints: `sm 640`, `md 768`, `lg 1024`, `xl 1280`.
- Sidebar → off-canvas drawer with overlay (< 768px).
- Topbar condenses; search becomes icon-only.
- Tables → horizontal scroll within a container (keep column headers pinned) or card-list on very small screens.
- Modals → full-screen sheet on mobile.
- Touch targets ≥ 44px; forms stack single-column.

---

## 8. Backend Caveats (must flag to backend team)

These findings are from reading the actual API source. The new frontend should not be blocked by them, but the backend should be fixed in parallel:

1. **`POST /api/profile`** — writes `nama`/`email` to `data_users` (columns don't exist) → likely SQL error. Fix or use `POST /members/{id}`.
2. **`POST /api/attendance`** — queries `data_users.id_anggota` (column doesn't exist on `data_users`; it's on `users`). May error at runtime.
3. **`GET /api/kta/{id}`** — returns legacy Blade HTML, not JSON. Render KTA in the frontend from `/members/{id}` instead.
4. **Public news/events** — `GET /api/news`, `/api/events` require auth. Public website pages need public variants.
5. **`event_status` VIEW** — dashboard stats/events depend on a MySQL VIEW that must exist in the DB (created manually, not by migration).
6. **Members ID** — `data_users.id` ≠ `users.id`. Frontend must consistently use `id_users` for members detail/update/delete/KTA.
7. **Barcode** — API stores a barcode *string*, not an image; frontend must render Code39 images client-side.

---

## 9. Non-Goals & Acceptance Criteria

### Non-goals (v1)
- No changes to the Laravel backend API shapes.
- Legacy Blade admin remains as fallback; not redesigned.
- No offline/PWA, no realtime push.

### Acceptance criteria
- [✅] All auth flows work (login, restore session, logout, expired-token handling).
- [✅] Every page in §5 renders real data from the API with loading/empty/error states.
- [✅] Full CRUD for members, events, news via modals.
- [✅] Attendance recording works end-to-end.
- [✅] KTA & ID card print correctly (correct paper size, barcode scannable).
- [✅] Role-based menus correct for all roles.
- [✅] Responsive: usable on 360px phone and desktop.
- [✅] Dark mode toggles and persists; WCAG AA contrast.
- [✅] No console errors; network failures show friendly states.

---

*End of specification. Build against §2/§3 API contract; follow §4/§6 design system; use §5 as the page checklist.*
