# Sprint 5 — Implementation Plan

---

# 1. Metadata

| Item | Nilai |
|------|-------|
| **Sprint** | Sprint 5 |
| **Milestone** | v2.1.0-rc1 (Business Layer) |
| **Version Dokumen** | v1.0 |
| **Status** | Planned |
| **Author** | Architecture Review — MZT Apps |
| **Last Updated** | 07 Agustus 2026 |
| **Fase** | Business / Reporting Layer (di atas Phase 2B) |

---

# 2. Background

Phase 2B telah selesai dan ditutup (tag baseline `v2.1.0-beta1`). Seluruh fondasi transaksional platform tersedia:

- **Order** sebagai Aggregate Root (ADR-001)
- **Payment** Engine (status, verifikasi, sisa tagihan)
- **Ticket** Engine (penerbitan idempoten, reissue/revoke, PDF/QR)
- **Communication** Engine (Notification Center + Communication Log, in-app)

Namun belum ada lapisan analitik/operasional untuk membaca kondisi platform secara agregat: total pendapatan, jumlah tiket, status pembayaran, posisi registrasi, dan kesehatan operasional event.

Sprint 5 menutup celah itu dengan membangun **Business Layer** di atas fondasi yang stabil — **read-only** — berupa Finance Dashboard, Reporting, Analytics, dan Operational Monitoring.

---

# 3. Business Goals

1. **Visibilitas keuangan:** panitia/pengurus dapat membaca total pendapatan, sisa tagihan, dan rincian metode pembayaran secara cepat.
2. **Efisiensi monitoring:** pemantauan registrasi, pembayaran, dan tiket terpusat tanpa ekspor manual.
3. **Pengambilan keputusan:** analitik dasar (tren, distribusi, konversi) membantu keputusan event berikutnya.
4. **Efisiensi operasional:** panitia memantau kondisi real-time kesehatan operasional (pesanan, tagihan, tiket terbit).
5. **Fondasi pelaporan:** data tersusun untuk laporan pertanggungjawaban.

Sprint 5 bersifat **baca** — tidak mengubah alur transaksi.

---

# 4. Objectives

1. Menyediakan **Finance Dashboard** berbasis data Order/Payment.
2. Menyediakan **Revenue Summary** (pendapatan per periode & per event).
3. Menyediakan **Registration Summary**.
4. Menyediakan **Payment Monitoring** (status & antrean verifikasi).
5. Menyediakan **Ticket Monitoring** (terbit / dipakai / dibatalkan).
6. Menyediakan **Operational Dashboard** lintas entitas (Orders / Payments / Tickets / Communication).
7. Menyediakan **Export Report** terstruktur.
8. Menyediakan **Analytics dasar** (tren, distribusi, konversi).

Seluruh objectives **read-only**, dipisahkan dari operasi mutation domain.

---

# 5. Scope

Fitur yang akan dibangun:

- Finance Dashboard
- Revenue Summary
- Registration Summary
- Payment Monitoring
- Ticket Monitoring
- Operational Dashboard
- Export Report
- Analytics dasar

---

# 6. Out of Scope

Fitur berikut **tidak** dikerjakan pada Sprint 5 (dijadwalkan pada sprint berikutnya):

- Email Provider
- WhatsApp Provider
- Telegram Provider
- Push Notification
- Broadcast
- Reminder
- Campaign
- Attendance Check-In
- QR Gate
- Mobile App
- AI Feature

---

# 7. Architecture

Sprint 5 menambah **Business API Layer** yang **baca-saja**; dashboard **hanya membaca data** dan **tidak memuat business logic**. Seluruh agregasi berada di Service layer (ADR-004); controller hanya validasi + otorisasi.

```
        +------------------------------+
        |  DOMAIN LAYER (Phase 2B)      |
        |  Order (aggregate root)       |
        |    +-- Payment                |
        |    +-- Ticket                 |
        |    +-- Communication          |
        +-------------+----------------+
                      |  baca-data (model / query)
                      v
        +------------------------------+
        |  BUSINESS LAYER (Phase 5)     |
        |  DashboardService            |
        |  FinanceReportService        |
        |  AnalyticsService            |
        |  ExportService             |
        +-------------+----------------+
                      |  agregasi read-only
                      v
        +------------------------------+
        |  Dashboard API (tipis)        |
        |  otorisasi + serialisasi      |
        +-------------+----------------+
                      |
                      v
        +------------------------------+
        |  Frontend (overview/finance)  |
        |    report / analytics/export  |
        +------------------------------+
```

**Prinsip:**
- **Read-only** — tidak ada insert/update di Order Layer dari Dashboard.
- **Service**: seluruh query agregat berada di Service (ADR-004).
- **Tanpa business logic di Controller.**
- Dapat memanfaatkan endpoint yang sudah ada (`/dashboard/stats`, `/public/stats`).

---

# 8. Modul

- **Overview** — ringkasan global lintas entitas + tren singkat.
- **Finance** — struktur pendapatan, sisa tagihan, metode pembayaran.
- **Payment** — jumlah pembayaran, antrean verifikasi.
- **Registration** — jumlah pendaftaran per status & per event.
- **Ticket** — jumlah tiket terbit/dipakai/dibatalkan.
- **Report** — halaman & hasil export.
- **Analytics** — tren, distribusi, konversi.
- **Settings** — preferensi periode/peran (opsional, bukan data bisnis).

---

# 9. Backend

Daftar Service baru (Service Layer):

| Service | Tanggung Jawab |
|---------|----------------|
| `DashboardService` | Agregasi ringkasan global / operasional. |
| `FinanceReportService` | Pendapatan, outstanding, breakdown metode pembayaran. |
| `AnalyticsService` | Tren, distribusi, konversi registrasi → payment → ticket. |
| `ExportService` | Serialisasi hasil (CSV/Excel). |

Catatan:
- Tidak ada business logic di Controller.
- Tidak mengubah struktur/migrasi Order/Payment/Ticket.

---

# 10. Frontend

Halaman baru:

- **Dashboard Overview**
- **Finance Dashboard**
- **Payment Report**
- **Revenue Report**
- **Analytics**
- **Export**

Setiap halaman menampilkan kartu/grafik ringkas dari API Dashboard (Bahasa teks UI dibuat Bahasa Indonesia).

---

# 11. API

Endpoint diperkirakan (**read-only**, **belum diimplementasikan**), dikelompokkan per modul:

### Overview
| Method | Path | Fungsi |
|--------|------|--------|
| GET | `/dashboard/summary` | Ringkasan global (orders/payments/tickets) |

### Finance
| Method | Path | Fungsi |
|--------|------|--------|
| GET | `/dashboard/finance/summary` | Revenue + outstanding |
| GET | `/dashboard/finance/revenue` | Pendapatan per periode/event |

### Registration
| Method | Path | Fungsi |
|--------|------|--------|
| GET | `/dashboard/registration` | Statistik pendaftaran/status |

### Payment
| Method | Path | Fungsi |
|--------|------|--------|
| GET | `/dashboard/payments` | Monitoring status pembayaran |

### Ticket
| Method | Path | Fungsi |
|--------|------|--------|
| GET | `/dashboard/tickets` | Statistik status tiket |

### Report / Export
| Method | Path | Fungsi |
|--------|------|--------|
| GET | `/dashboard/report/export` | Export hasil (CSV/Excel) |

### Analytics
| Method | Path | Fungsi |
|--------|------|--------|
| GET | `/dashboard/analytics/trend` | Tren terhadap metrik |
| GET | `/dashboard/analytics/distribution` | Distribusi (metode, status) |

Seluruh endpoint read-only, di-balut `auth:sanctum` + Policy + RoleGuard.

---

# 12. Database

**Keputusan resmi:**

> **Sprint 5 tidak merencanakan migration baru.**

Analisis:
- Struktur `orders`, `payments`, `tickets`, `communication_logs` + index (`id_order`, `uuid`, `status`, `created_at`) sudah mendukung agregasi dasar secara read-only.

Aturan optimasi:
- **Hindari optimasi prematur.**
- Apabila target performa Dashboard (Overview < 2 detik) **tidak tercapai** dengan struktur Phase 2B, maka optimasi database **diputuskan melalui ADR baru** (misal agregat/snapshot, dapat dimigrasi) — bukan keputusan inline.
- Tidak ada migration baru dalam rencana Sprint 5.

---

# 13. Security

Akses internal (back-office) mengikuti `RoleGuard` yang sudah ada.

**Role Matrix:**

| Modul / Data | Event | Staff | Finance | Ketua | Admin |
|--------------|-------|-------|---------|-------|-------|
| Overview | ✅ | ✅ | ✅ | ✅ | ✅ |
| Finance / Revenue | ❌ | ❌ | ✅ | ✅ | ✅ |
| Payment Monitoring | ❌ | ❌ | ✅ | ✅ | ✅ |
| Registration | ✅ | ✅ | ✅ | ✅ | ✅ |
| Ticket | ✅ | ✅ | ✅ | ✅ | ✅ |
| Export | ❌ | ✅ | ✅ | ✅ | ✅ |
| Analytics | ❌ | ❌ | ✅ | ✅ | ✅ |

- Data finansial hanya untuk `finance/ketua/admin`.
- Menerapkan `Policy` baru (mis. `DashboardPolicy` / `FinancePolicy`) + `RoleGuard` (staff & verifier set).

---

# 14. Performance

| Komponen | Target |
|----------|--------|
| Dashboard Overview | **< 2 detik** |
| Filter / muat ulang | **< 1 detik** |
| Export report | **< 10 detik** |

Pendekatan:
- Paginasi pada daftar tabel.
- Agregasi terkonsolidasi di satu lapisan Service (menghindari N+1).
- Pemakaian indeks yang sudah ada; tidak ada migration tambahan.

---

# 15. Risks

| Risiko | Level | Mitigasi |
|--------|-------|----------|
| Query agregat lambat (volume besar) | Medium | Agregasi di Service, pagination, ADR baru bila perlu |
| Interpretasi salah angka keuangan | Medium | Uji dengan data hasil Phase 2B |
| `ApiController` (1482 baris) god-controller | High | Service + controller terpisah; jangan menambah method di `ApiController` |
| Format tanggal `event_status`/`created_at` bervariasi | Medium | Normalisasi di Service layer |
| Perubahan frontend besar | Medium | Prioritaskan halaman inti dulu |

---

# 16. Non Functional Requirements

| Kategori | Kebutuhan |
|----------|-----------|
| **Skalabilitas** | Dashboard ringan, caching hasil agregat bila perlu. |
| **Keandalan** | Lapisan baca terpisah; tidak menurunkan throughput domain. |
| **Observabilitas** | Logging / instrumentasi endpoint agregasi. |
| **Maintainability** | Query dikonsolidasikan di Service (ADR-004). |
| **Konsistensi** | Nominal dalam IDR, tanggal konsisten, Bahasa Indonesia pada UI. |
| **Keamanan** | Hanya GET, autentikasi + policy + role.

---

# 17. Sprint Deliverables

| # | Deliverable |
|---|-------------|
| 1 | Dokumen `SPRINT5_PLANNING.md` |
| 2 | Backend: `DashboardService`, `FinanceReportService`, `AnalyticsService`, `ExportService` |
| 3 | Backend: Dashboard API (read-only) + Policy |
| 4 | Frontend: Overview / Finance / Report / Analytics / Export |
| 5 | Regression Dashboard (read-only) + zero regression Phase 2B |
| 6 | Doc upgrade (Implementation Summary / Release Notes) |

---

# 18. Acceptance Criteria

- [ ] `/dashboard/summary` tersedia, read-only, format `{success, data}`.
- [ ] Dashboard Overview memuat < 2 detik.
- [ ] Filter < 1 detik.
- [ ] Export < 10 detik.
- [ ] Finance / Revenue hanya untuk `finance/ketua/admin` (403 utk lain).
- [ ] Data kosong tidak menyebabkan error (nilai kosong/0).
- [ ] Tidak ada regresi pada module Payment/Order/Ticket/Communication (Phase 2B).
- [ ] Tidak ada mutasi terhadap Order/Payment/Ticket dari Dashboard.

---

# 19. Exit Criteria

Sprint 5 dapat ditutup ketika:

- [ ] Seluruh scope inti (Finance, Revenue, Registration, Payment, Ticket, Export, Analytics dasar) selesai & terlihat.
- [ ] Struktur domain (Phase 2B) tidak diubah.
- [ ] Tidak ada migration baru tanpa ADR resmi.
- [ ] Performance (2/1/10) tercapai terukur.
- [ ] Role Matrix diterapkan (uji 403).
- [ ] Architecture Review Sprint 5 PASS.
- [ ] Regresi Phase 2B lulus (0 regresi).
- [ ] Document/Release Notes diperbarui.

---

# 20. Sprint Roadmap

```
Sprint 5  — Business Layer
             Finance · Reporting · Analytics · Operational Monitoring
        |
        v
Sprint 6  — External Communication
             Email / WhatsApp Provider · Broadcast · Reminder
        |
        v
Sprint 7  — Check-In & Attendance
             QR Gate · Mobile App · AI
```

---

# Self Review

- Struktur sesuai template 20 bab.
- Konsisten dengan ADR-001–017 dan hasil Phase 2B Closure.
- Tidak ada asumsi yang tidak didukung arsitektur.
- Sprint 5 hanya perencanaan read-only; tidak ada akses mutasi domain.
- Tidak menghasilkan kode.

---

*Dokumen ini merupakan perencanaan Sprint 5; implementasi dimulai hanya setelah dokumen disetujui. Tidak ada kode aplikasi, PRD, maupun ADR yang diubah dalam proses penulisan.*