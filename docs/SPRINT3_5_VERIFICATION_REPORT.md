# SPRINT 3.5 — Verifikasi Architecture Gates

**Tanggal:** 2026-08-07
**Fase:** Phase 2B (setelah Mid-Phase Architecture Review, sebelum Sprint 4)
**Status:** **PASS** ✅

---

## 1. Ruang lingkup

Sprint 3.5 hanya menutup **gate blocker hasil Mid-Phase Architecture Review**.
**Tidak ada** implementasi Communication Engine, Queue Listener, Email/WhatsApp/
Broadcast/Reminder, maupun Dashboard pada sprint ini.

| Gate | Lingkup | Status |
|------|---------|--------|
| **Gate 1** | Instant Payment → Ticket untuk seluruh jalur PAID | ✅ |
| **Gate 2** | Domain Event dispatch `after_commit` (3 event) | ✅ |
| **Gate 3** | Fondasi Queue (`database` + worker + jobs table + docs) | ✅ |

---

## 2. Perubahan yang dilakukan

### Gate 1 — Instant Payment → Ticket
- `app/Services/PaymentService.php`:
  - Injeksi `TicketService`.
  - Di dalam `create()` (transaksi), setiap kali order mencapai **benar-benar PAID**
    (cash/sponsor/complimentary dengan nominal menutup sisa tagihan), dipanggil
    `TicketService::generate()` — **idempoten**, kembali tiket yang sudah ada bila
    order sudah punya tiket (tidak pernah duplikat).
  - Jalur transfer tetap lewat `PaymentVerificationService::verify()` → PAID → tiket.
- Semua jalur PAID memakai **aturan bisnis yang sama** (`TicketService::canIssue`
  + `generate`). Tak ada duplikasi tiket (diverifikasi: `COUNT(ticket)==1` setelah
  pengulangan permintaan).

### Gate 2 — Domain Event After Commit
Seluruh dispatch domain event diganti menjadi **`DB::afterCommit(...)`** sehingga
event hanya dikirim **jika transaksi database benar-benar commit**; bila rollback,
callback dibuang dan event tidak pernah terkirim. Event:
- `PaymentStatusChanged` (di `PaymentService::create` & `uploadProof`, serta
  `PaymentVerificationService::verify`)
- `TicketIssued` (di `TicketService::generate`)
- `TicketStatusChanged` (di `TicketLifecycleService::reissue` & `revoke`)

### Gate 3 — Fondasi Queue (infra only, belum ada Job/Listener)
- Migrasi baru: `2026_08_13_000001_create_jobs_table.php` (tabel `jobs` untuk
  driver `database`; `failed_jobs` sudah ada dari Laravel).
- `config`/env: `QUEUE_CONNECTION=database` (di `.env`, `.env.example`, dan
  `docker-compose.yml`).
- `deploy/docker-compose.yml`: service **`worker`** baru, memakai image yang sama
  (`mzt-backend:local`), menjalankan `php artisan queue:work --sleep=2 --tries=3
  --timeout=90 --max-time=3600`, diawasi `restart: unless-stopped` (tidak perlu
  unit systemd/supervisor tambahan). Belum dibuat Job/Listener.
- `deploy/README.md`: dokumentasi topologi & cara verifikasi/aktifkan queue.

---

## 3. Hasil Pipeline

- Build Jenkins `mzt-deploy`: **#37 (SUCCESS)**
- Commit diterapkan di deploy dir:
  - Backend  : `5ad49c5`
  - Frontend : `db8af6f`
- Stages: Validate → Sync → Build (`mzt-backend:local`) → Backup DB → Deploy →
  Migrate DB (`jobs` table Ran) → Verify schema → Health check → Prune.

### Verifikasi deployment (langsung di host)
- Kontainer: `mzt-backend`, `mzt-frontend`, `mzt-caddy`, `mzt-db` (healthy),
  dan **`mzt-worker`** (Up, `php artisan queue:work`).
- `QUEUE_CONNECTION=database` terbaca di container backend.
- Tabel `jobs` + `failed_jobs` ada; migrasi `2026_08_13_000001_create_jobs_table`
  status **[16] Ran**.
- Health: `GET /api/public/stats` → `200` `{"success":true,...,"total_anggota":950}`.
- Verify schema Jenkins: PASS (users audit + orders/events Phase 2A).

---

## 4. Hasil Regression Harness (server-side, di kontainer)

Harness dijalankan **dalam DB transaction lalu rollback** → **0 artefak** data uji.

`RESULT: 36 passed, 0 failed`

| Kelompok | Kasus | Hasil |
|----------|-------|-------|
| **Gate 2** | Callback `after_commit` tidak eksekusi saat rollback | ✅ |
| **Gate 2** | Callback berjalan hanya setelah commit | ✅ |
| **Gate 1** | Cash → PAID → tepat 1 tiket | ✅ |
| **Gate 1** | Sponsor → PAID → tepat 1 tiket | ✅ |
| **Gate 1** | Complimentary → PAID → tepat 1 tiket | ✅ |
| **Gate 1** | Tidak ada event sebelum commit (in-transaction guard) | ✅ |
| **Gate 1** | Idempoten: pengulangan tidak menciptakan tiket duplikat | ✅ |
| **Gate 1b** | Transfer → Verify(PAID) → tepat 1 tiket; re-verify idempoten | ✅ |
| **Gate 1** | Rollback → 0 baris tiket/pembayaran tersisa (zero artefak) | ✅ |

### Check no-artifacts (post-run query)
`orders.id_event=999999` → **0** · `payments` untuk order test → **0** ·
`tickets` → **0** · `orders.nomor_order LIKE 'H%'` → **0**.

---

## 5. Hasil Architecture Review

_Resolved blockers from Sprint 3 review_
- **Resolved 1 (Instant Payment → Ticket):** cash/sponsor/complimentary (dan
  transfer lewat verify) sekarang selalu menghasilkan tiket lewat aturan yang
  sama; tidak ada order PAID tanpa tiket.
- **Resolved 2 (after_commit):** `PaymentStatusChanged`, `TicketIssued`,
  `TicketStatusChanged` hanya dispatch setelah commit — menerapkan
  **Transactional Consistency**, siap dipakai Communication Engine Sprint 4.
- **Resolved 3 (Queue):** `QUEUE_CONNECTION=database`, tabel `jobs`/`failed_jobs`,
  worker container (supervised), dokumentasi deployment queue. Communication
  Engine tinggal dispatch Job tanpa perubahan fondasi.

_Sengaja di luar scope sprint ini (dijadwalkan di sprint berikutnya):_
- Belum ada Job/Listener/Email/WhatsApp/Broadcast/Reminder/Dashboard — hanya
  fondasi queue dan event `after_commit` (masa persiapan Sprint 4).
- Sinkronisasi dokumen ADR-011 (state machine + `revoked`) dan ADR-017 ke
  `docs/ADR.md` — bukan blocker runtime; didasarkan pada roadmap sprint berikutnya.
- Sisa gap non-blocker dari Architecture Review (mis. Cancel Order) tetap di
  backlog Sprint-4-relevant, tidak menghambat PASS gate ini.

## 6. Keputusan

**PASS** ✅ — Sprint 3.5 menyelesaikan seluruh blocker Gate dari Architecture
Review. Sprint 4 (Communication Engine) **boleh dimulai**; fondasi queue sudah
aktif dan event sudah `after_commit`.