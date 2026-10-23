# Product Requirement Document (PRD)
## Aplikasi Absensi Gym & Manajemen Loker — GymPass QR

---

## Document Control

| Item | Nilai |
|---|---|
| Project Name | Gym Attendance & Locker Management System (GymPass QR) |
| Version | 1.2.0 |
| Date | October 2026 |
| Status | **Draft for Review** (belum Development Ready; berubah setelah Decision Log di §0.2 disetujui owner) |
| Target Release | Q1 2027 |
| Menggantikan | v1.1.0 |

### Riwayat versi

| Versi | Perubahan |
|---|---|
| 1.1.0 | Draf awal: persona, alur, FR, DDL, API |
| 1.2.0 | Menutup kontradiksi QR statis/dinamis, perilaku member expired, kolom GPS nullable, FR pengelolaan membership, alur antrean, model sesi, concurrency loker, tabel konfigurasi/paket/OTP/audit, format error API, NFR terukur, privasi, traceability review |

---

## 0. Ringkasan Perubahan dari v1.1.0

Perubahan berikut wajib dibaca sebelum bagian lain, karena mengubah perilaku produk:

1. **QR statis tetap statis.** Klaim "HMAC-SHA256 token dinamis" dihapus. HMAC hanya dipakai sekali saat *provisioning* untuk membentuk payload statis (`gym_settings.qr_payload`), bukan per request. Konsekuensi keamanan diterima dan ditulis eksplisit di §5.3.
2. **Member expired ditolak server** dengan `403 MEMBERSHIP_EXPIRED`. Kasir tetap menerima *renewal alert* (bukan check-in valid, tidak membuat baris absensi).
3. **`latitude`/`longitude` menjadi NULL-able**, ditambah `gps_accuracy_m` dan `locker_state`.
4. **Modul pengelolaan membership ditambahkan** (§4.3): pembuatan akun visitor, penjualan paket oleh kasir, perpanjangan, suspend, pembatalan, aturan pemakaian kuota Day Pass.
5. **Alur antrean diperbaiki**: member menyatakan "butuh loker / tidak" di aplikasi, sehingga member tanpa loker tidak perlu berhenti di meja kasir (§3.2).
6. **Cooldown 60 menit diganti model sesi**: satu sesi aktif per member, ditutup saat kunci kembali / checkout / jam tutup. Guard 5 menit hanya untuk mencegah double-tap.
7. **Geofence memakai `accuracy`** dari perangkat, radius jadi konfigurasi database, ditambah jalur fallback manual.
8. **Concurrency loker** ditangani transaksi + row lock + update bersyarat.
9. **Skema dilengkapi**: `gym_settings`, `packages`, `otp_requests`, `refresh_tokens`, `audit_logs`, soft delete, kolom audit sesi.
10. **API dilengkapi**: format error standar + kode error, endpoint feed real-time, tanpa-loker, batch reset, daftar member aktif, admin, refresh, logout, payload manual check-in.
11. **NFR dilengkapi**: kapasitas, uptime, backup/RPO/RTO, browser, aksesibilitas, masa berlaku token, retensi data, privasi (UU PDP).

---

## 0.1 Ruang Lingkup

**In scope (Phase 1)**
- Aplikasi member berbasis web (PWA responsif) untuk login OTP, scan QR statis, validasi GPS, kartu digital, status loker.
- Dasbor kasir (web, tablet/desktop) dengan feed real-time, assign/return loker, check-in manual, penjualan & perpanjangan paket.
- Panel admin: paket, aset loker, laporan kunjungan & peak hours, pengaturan gym.
- Satu lokasi gym.

**Out of scope (Phase 1)**
- Payment gateway (QRIS/e-wallet), smart locker RFID/BLE, turnstile gate.
- Multi-cabang (lihat D9).
- Aplikasi native Android/iOS (lihat D12).
- Akuntansi/rekonsiliasi pembayaran; pembayaran paket dicatat manual di luar sistem, sistem hanya mencatat `sold_by_staff_id`.

---

## 0.2 Decision Log (perlu sign-off owner sebelum Development Ready)

| ID | Keputusan | Alasan | Alternatif yang ditolak |
|---|---|---|---|
| D1 | QR tetap **statis**; klaim token dinamis dihapus | Nama produk & biaya operasional; QR dicetak sekali | QR berputar di tablet kasir (Phase 2) |
| D2 | Member expired **ditolak server**, kasir dapat renewal alert | Menjaga KPI "0% kebocoran" | Izinkan masuk feed dengan peringatan merah |
| D3 | Member memilih kebutuhan loker **di aplikasi sebelum scan** | Menghapus antrean wajib di meja kasir untuk member tanpa loker | Kasir selalu bertanya manual |
| D4 | **Satu sesi aktif per member**; guard re-scan 5 menit | Cooldown 60 menit tidak mencegah duplikasi dan menolak member sah | Cooldown tetap 60 menit |
| D5 | Assign loker **atomik** (transaksi + row lock) | Dua kasir bisa memilih nomor sama | Cek-then-update tanpa lock |
| D6 | Geofence **radius konfigurabel + toleransi accuracy**; fallback manual | Akurasi GPS indoor tidak dapat dijamin 30 m | Radius hardcode 30 m |
| D7 | Stack: **PHP 8.3 / Laravel 11 / MySQL 8.0+ / Redis / WebSocket (Laravel Reverb)** | Sesuai lingkungan pengembangan yang ada (Laragon) | Belum ditentukan di v1.1 |
| D8 | Simpan waktu **UTC** di database, tampilkan **Asia/Jakarta** | Menghindari ambiguitas WIB/UTC | Simpan waktu lokal |
| D9 | **Satu gym** di Phase 1; `gym_settings` satu baris | Menghindari `gym_id` di semua tabel sebelum dibutuhkan | Multi-cabang sejak awal |
| D10 | Staf login **password + TOTP opsional** (wajib untuk admin), member login **OTP WhatsApp** | Akun berprivilege tidak memakai kanal OTP publik | Semua role lewat OTP WhatsApp |
| D11 | Foto profil **wajib** diisi saat aktivasi member oleh kasir | Validasi fisik adalah kontrol anti-fraud utama | Foto opsional (`NULL`) |
| D12 | Member app = **PWA web** memakai kamera live (`getUserMedia`), tanpa akses galeri. Deteksi mock GPS & Play Integrity tidak tersedia di web | Biaya & waktu rilis | Aplikasi native dengan Play Integrity |

Catatan D12: pemblokiran galeri hanya menutup satu jalur. Foto QR yang dicetak atau ditampilkan di layar lain tetap dapat dipindai. Kontrol yang menanggung risiko ini adalah geofence, model sesi, dan validasi kasir — bukan QR itu sendiri.

## 1. Executive Summary & Background

### 1.1 Background

Fasilitas fitness skala menengah masih mencatat absensi harian di buku tamu tulis tangan. Kelemahannya:

- **Inefisiensi operasional**: antrean di jam sibuk.
- **Risiko fraud & akurasi rendah**: status keanggotaan sulit diverifikasi.
- **Pengelolaan loker tercecer**: tidak ada pencatatan kunci yang dipinjamkan.

### 1.2 Product Goals

Mengganti buku absensi manual dengan pemindaian **QR Code statis** yang terintegrasi dengan dasbor kasir real-time dan modul peminjaman loker.

Sistem ini **tidak** menghilangkan kebutuhan serah-terima kunci fisik. Untuk member yang membutuhkan loker, interaksi dengan kasir tetap ada. Yang dihilangkan adalah penulisan manual dan antrean bagi member tanpa loker (lihat §3.2).

### 1.3 Key Success Metrics

Semua metrik diukur dari data sistem dan pengamatan langsung, bukan estimasi.

| ID | Metrik | Target | Cara ukur |
|---|---|---|---|
| K1 | Waktu respons `POST /attendance/scan-qr` | p95 ≤ 1.5 detik | Access log server, agregasi harian |
| K2 | Waktu check-in dari sisi member (tap Scan → pesan sukses tampil) | p95 ≤ 5 detik | Event instrumentasi klien, sampel harian |
| K3 | Check-in valid pada membership tidak aktif | 0 per hari | Query harian: `attendance_logs` join `memberships` dengan `effective_status <> 'active'` |
| K4 | Sesi loker tanpa jejak kasir (`assigned_by_staff_id` NULL) | 0 per hari | Query rekonsiliasi harian |
| K5 | Latency event feed ke dasbor kasir | p95 ≤ 500 ms | Timestamp server saat publish vs timestamp penerimaan klien |
| K6 | Baseline manual 30–45 detik/orang | **Belum terverifikasi** | Wajib diukur 3 hari di gym target sebelum go-live; angka v1.1 tanpa sumber |
| K7 | Antrean jam sibuk (17:00–20:00) | turun ≥ 50% dari baseline | Hitung orang menunggu >2 pada 5 sampel acak/hari, 7 hari sebelum vs sesudah |

K3 dan K4 menggantikan klaim "0% kebocoran" dan "100% akurasi pencatatan" di v1.1, yang tidak dapat dibuktikan tanpa definisi operasional.

---

## 2. Target Users, Asumsi & Dependensi

### 2.1 Personas

| Role | Deskripsi | Kebutuhan utama |
|---|---|---|
| **Member Bulanan** | Langganan aktif, kunjungan tak terbatas | Check-in cepat, status paket & sisa hari |
| **Visitor (Day Pass)** | Pengunjung harian | Aktivasi tiket harian oleh kasir, pencatatan loker |
| **Staf Kasir / Resepsionis** | Operasional meja depan | Feed real-time, validasi identitas, alokasi kunci |
| **Owner / Admin** | Pemilik / manajer | Laporan kunjungan & peak hours, kelola paket & loker |

### 2.2 Asumsi

- Satu lokasi gym, satu zona waktu operasional (Asia/Jakarta).
- Kasir memakai browser modern dengan internet; ada prosedur manual cadangan saat offline (§8.3).
- Member memiliki smartphone dengan kamera, GPS, dan browser modern.
- Pembayaran paket dilakukan di luar sistem. Sistem hanya mencatat siapa yang menjual.
- Nomor WhatsApp member adalah identitas utama dan unik.

### 2.3 Dependensi eksternal

| Dependensi | Dipakai untuk | Risiko bila gagal |
|---|---|---|
| Penyedia WhatsApp OTP | Login member | Member tidak bisa login; fallback check-in manual kasir |
| Hosting + domain HTTPS | Kamera & geolocation wajib HTTPS | Fitur scan mati |
| Redis | Cache, rate limit OTP, lock assign loker | Throughput turun; kebenaran assign tetap dijaga lock DB |
| Layanan WebSocket (Reverb/Soketi) | Feed dasbor kasir | Dasbor memakai fallback polling (§8.3) |

Biaya dan kuota penyedia WhatsApp OTP harus dikonfirmasi ke penyedia resmi sebelum implementasi; dokumen ini tidak mengasumsikan angka biaya.

## 3. User Journey & System Workflow

### 3.1 Journey member bulanan

```
[Member datang] → [Buka PWA, pilih: Butuh Loker / Tanpa Loker]
                                      │
                                      ▼
                     [Scan QR statis di meja kasir (kamera live)]
                                      │
                                      ▼
              [Server: validasi payload QR, geofence, sesi, membership]
                    ├── gagal 403/409/429 → pesan jelas di HP
                    └── sukses → buat attendance_log (sesi open)
                                      │
                    ┌─────────────────┴──────────────────┐
                    ▼                                    ▼
        [Pilih "Tanpa Loker"]                 [Pilih "Butuh Loker"]
        locker_state = none                   locker_state = pending
        Member langsung masuk                 Feed kasir: [Assign Loker]
        (tanpa interaksi kasir)                        │
                                                       ▼
                                        [Kasir pilih nomor → serahkan kunci]
                                                       │
                                                       ▼
                                     [HP member: "Loker #05" (real-time)]
```

Pulang: member menyerahkan kunci → kasir klik **Kembalikan Kunci** → loker `available`, `locker_returned_time` terisi, sesi ditutup.

### 3.2 Penyelesaian masalah antrean

Di v1.1 semua member wajib berhenti di kasir untuk ditanya soal loker. Di v1.2 kebutuhan loker dinyatakan di aplikasi **sebelum** scan:

- **Tanpa loker** → `locker_state = 'none'` sejak awal; kasir tidak perlu tindakan; member langsung masuk.
- **Butuh loker** → `locker_state = 'pending'`; kasir hanya memilih nomor dan menyerahkan kunci (satu klik).
- Kasir tetap dapat mengubah: memberi loker ke sesi `none`, atau menandai `none` untuk sesi `pending` yang ternyata tidak jadi memakai loker.

Batas yang diakui: member yang butuh loker tetap mengantre untuk kunci fisik. Itu keterbatasan operasional, bukan cacat sistem.

### 3.3 State machine sesi absensi

```
(scan sukses) → OPEN ──(kunci dikembalikan)────→ CLOSED (locker_returned)
                 │
                 ├──(kasir checkout)───────────→ CLOSED (staff_checkout)
                 └──(job auto-close jam tutup)→ CLOSED (auto_close)

Saat sesi OPEN: scan ulang ditolak 409 SESSION_ACTIVE
                (dalam 5 menit terakhir → 429 RESCAN_GUARD)
```

Sesi juga dibatasi satu per member: `checkout_time IS NULL` untuk lebih dari satu baris per `user_id` dilarang (dijaga di §4.1 FR-MEM-05 dan §7.1).

### 3.4 State machine loker

```
available ──(assign)──→ occupied ──(return)──→ available
    │                       │
    │                       └──(kunci hilang)──→ out_of_service (audit wajib)
    └──(admin: rusak)──→ out_of_service ──(admin: selesai)──→ available
```

Loker `occupied` hanya boleh diubah ke `out_of_service` oleh admin, dan menghasilkan entri `audit_logs` + peringatan di dasbor.

### 3.5 Journey visitor (Day Pass)

```
[Visitor datang] → [Kasir cari nomor HP]
      ├── sudah ada akun, paket habis → [Jual Day Pass]
      └── belum ada akun → [Daftar: nama, HP, foto] → [Jual Day Pass]
                                      │
                                      ▼
                     [Kasir check-in manual (FR-CAS-05)]
                     atau visitor scan QR sendiri setelah login OTP
                                      │
                                      ▼
                     [Assignment loker seperti member biasa]
```

Visitor tanpa smartphone tidak perlu login: kasir melakukan check-in manual dan menetapkan loker.

## 4. Functional Requirements

Prioritas memakai MoSCoW. Setiap FR punya acceptance criteria (AC) yang dapat diuji.

### 4.1 Modul Aplikasi Member (PWA)

#### FR-MEM-01 Authentication OTP — MUST
Login dengan nomor WhatsApp, verifikasi OTP 6 digit.

**AC**
- OTP kedaluwarsa 5 menit, maksimum 5 percobaan verifikasi, kode disimpan sebagai hash (bukan plaintext).
- Kirim ulang dibatasi 60 detik; maksimum 5 permintaan per nomor per jam dan 20 per IP per jam.
- Respons `request-otp` selalu sama untuk nomor terdaftar maupun tidak (mencegah enumerasi akun).
- Login sukses mengembalikan access token JWT (15 menit) dan refresh token (30 hari, disimpan sebagai hash, dapat dicabut).
- Nomor yang belum terdaftar → `404 MEMBER_NOT_FOUND` dengan pesan "Nomor belum terdaftar, silakan daftar di meja kasir". Pengecualian aturan respons seragam ini disengaja karena pendaftaran hanya lewat kasir; risiko enumerasi diterima dan dicatat sebagai R4 (§10.2).

#### FR-MEM-02 Pemindai QR internal — MUST
- Kamera diakses via `getUserMedia` (live), tanpa elemen `<input type="file">` dan tanpa akses galeri.
- Tombol scan hanya aktif setelah izin kamera diberikan; jika ditolak, tampilkan instruksi izin.
- Payload QR divalidasi ke `gym_settings.qr_payload` dengan perbandingan constant-time; QR lain ditolak `400 INVALID_QR`.
- Hasil scan ganda dalam satu tampilan kamera diproses sekali.

**AC**: tidak ada jalur UI untuk memilih berkas gambar; scan QR asing menghasilkan `INVALID_QR`, bukan error 500.

#### FR-MEM-03 Validasi Geofence — MUST
Koordinat dikirim bersama `accuracy` dari perangkat. Aturan server:

| Kondisi | Hasil |
|---|---|
| `accuracy > 150 m` | `422 GPS_ACCURACY_LOW`, pesan: "Sinyal GPS lemah. Minta check-in manual ke kasir." |
| `distance - accuracy > radius` | `403 GEO_OUT_OF_RANGE`, pesan: "Lokasi Anda terlalu jauh dari lokasi Gym (maksimal {radius} meter)." |
| `distance ≤ radius + accuracy` | lolos geofence |

- `radius` (`geofence_radius_m`) dan `geofence_accuracy_max_m` dibaca dari `gym_settings`, bukan konstanta kode. Nilai default 30 m **wajib diverifikasi pengukuran di lokasi gym sebelum go-live** (§10.2 R1).
- `latitude`, `longitude`, `gps_accuracy_m` disimpan di `attendance_logs` untuk audit.

#### FR-MEM-04 Anti-fraud & model sesi — MUST
Menggantikan cooldown 60 menit v1.1 (D4).

- Maksimum **satu sesi terbuka** per member (`checkout_time IS NULL`). Scan saat sesi terbuka → `409 SESSION_ACTIVE`, respons menyertakan `checkin_time` dan nomor loker aktif.
- Guard re-scan 5 menit untuk mencegah double-tap jaringan lambat → `429 RESCAN_GUARD` (khusus kasus tidak ada sesi terbuka, misalnya sesi baru saja ditutup).
- Waktu keputusan memakai jam server (UTC), bukan jam perangkat.
- `Idempotency-Key` wajib pada `POST /attendance/scan-qr`; kunci sama dalam 10 menit mengembalikan respons sukses yang sama tanpa membuat baris baru.
- Pemeriksaan "sesi terbuka" dijalankan dalam transaksi dengan `SELECT ... FOR UPDATE` pada baris `attendance_logs` milik user, ditambah lock Redis `checkin:{user_id}` TTL 10 detik.

#### FR-MEM-05 Kartu digital & status paket — MUST
- Menampilkan nama, foto profil, tipe paket, sisa masa aktif (bulanan) atau sisa kuota (Day Pass), dan `effective_status`.
- `effective_status` dihitung: `cancelled`/`suspended` menang; lalu `expired` jika `end_date < hari ini` atau (`type = single_visit` dan `remaining_visits <= 0`); selain itu `active`.
- Sisa hari dihitung dari tanggal server dalam zona Asia/Jakarta.

#### FR-MEM-06 Status loker real-time — MUST
- Nomor loker tampil dalam ≤ 2 detik setelah kasir menekan Assign (kanal WebSocket `private-member.{user_id}`).
- Fallback: polling `GET /member/profile` setiap 30 detik bila WebSocket terputus; indikator status koneksi ditampilkan.

#### FR-MEM-07 Pilihan kebutuhan loker — MUST
- Sebelum scan, member memilih `Butuh Loker` / `Tanpa Loker` (dikirim sebagai `locker_intent`), default `Tanpa Loker`.
- `butuh` → `locker_state = 'pending'`; `tidak` → `locker_state = 'none'`.
- Pilihan dapat diubah setelah check-in melalui kasir, bukan dari aplikasi member, agar tidak ada dua sumber kebenaran.

#### FR-MEM-08 Pesan kegagalan yang dapat ditindaklanjuti — MUST
Setiap kode error API dipetakan ke pesan bahasa Indonesia + satu tindakan (mis. "Perpanjang paket di kasir", "Coba lagi 5 menit lagi"). Tabel pemetaan ada di §7.4.

#### FR-MEM-09 Riwayat kunjungan — SHOULD
Daftar 30 kunjungan terakhir: tanggal, jam masuk, jam keluar, nomor loker.

#### FR-MEM-10 Sesi aplikasi — MUST
Refresh token, logout (mencabut refresh token aktif), dan penanganan 401 otomatis ke halaman login.

### 4.2 Modul Dasbor Kasir (Web, Tablet/Desktop)

#### FR-CAS-01 Feed kedatangan real-time — MUST
- Event `attendance.created` dipublikasikan ke kanal `private-reception` ≤ 500 ms setelah commit (K5).
- Kartu berisi: foto, nama, tipe paket, `effective_status`, waktu masuk, `locker_state`, dan badge "Identitas belum dikonfirmasi" sampai FR-CAS-06 dilakukan.
- Reconnect: klien mengirim `last_event_id`; server mengirim ulang event setelah ID tersebut dari buffer Redis (retensi 6 jam). Bila ID sudah kedaluwarsa, klien memuat ulang `GET /receptionist/sessions?date=today` dan menampilkan banner "Data dimuat ulang".
- Fallback tanpa WebSocket: polling `GET /receptionist/sessions?updated_since=<ISO>` tiap 10 detik.

#### FR-CAS-02 Assign loker (atomik) — MUST
- Tombol `[ Assign Loker ]` muncul untuk sesi `locker_state` = `pending` (dan opsional untuk `none`).
- Daftar loker hanya menampilkan `status = 'available'`.
- Server menjalankan dalam satu transaksi: kunci baris sesi `FOR UPDATE`, lalu `UPDATE lockers SET status='occupied' WHERE locker_id=? AND status='available'`; bila `affected_rows = 0` → `409 LOCKER_TAKEN` dan daftar loker di klien di-refresh.
- Sesi dengan `locker_state = 'assigned'` tidak dapat di-assign ulang → `409 LOCKER_ALREADY_ASSIGNED`.
- Setelah commit: `locker_state='assigned'`, `assigned_by_staff_id`, `assigned_at` terisi; event `locker.assigned` dikirim ke kasir dan member.

**AC**: dua permintaan assign loker yang sama secara paralel menghasilkan tepat satu sukses dan satu `409`.

#### FR-CAS-03 Kembalikan kunci — MUST
- Tombol `[ Kembalikan Kunci ]` pada sesi bersesi loker.
- Efek: `locker_returned_time`, `checkout_time`, `checkout_type='locker_returned'` terisi; `locker_state='returned'`; `lockers.status='available'`.
- Endpoint idempoten: klik ganda tidak menimpa timestamp pertama. Permintaan kedua mengembalikan 200 dengan `already_returned: true` dan tanpa perubahan data.

#### FR-CAS-04 Tandai tanpa loker — MUST
- Tombol `[ Tanpa Loker ]` mengubah `locker_state` `pending` → `none`, mencatat `assigned_by_staff_id` + audit.
- Untuk sesi `none` tanpa interaksi kasir, kartu feed tetap tampil sebagai informasi dan tidak memblokir.

#### FR-CAS-05 Check-in manual — MUST
- Pencarian member berdasarkan nama atau nomor HP (minimal 3 karakter).
- Payload wajib menyertakan `user_id`, `membership_id`, `locker_intent`, `reason` (enum: `no_phone`, `battery_dead`, `camera_broken`, `gps_issue`, `other`), dan `idempotency_key`.
- `latitude`/`longitude` disimpan `NULL`; `gps_accuracy_m` `NULL`; `checkin_method='manual_receptionist'`.
- Validasi membership tetap berlaku: membership tidak aktif → `403 MEMBERSHIP_EXPIRED/INACTIVE`. Kasir harus memperpanjang/menjual paket dulu (FR-CAS-08). Override tanpa membership **tidak** disediakan di Phase 1.
- Setiap check-in manual tercatat di `audit_logs` (`attendance.manual_checkin`).

#### FR-CAS-06 Konfirmasi identitas fisik — MUST
- Tombol `[ Identitas Cocok ]` menyimpan `identity_verified_by` dan `identity_verified_at`, dan menulis audit.
- Kartu sesi yang belum dikonfirmasi tetap terlihat dengan badge peringatan; laporan harian menampilkan jumlah sesi tanpa konfirmasi.

#### FR-CAS-07 Daftar sesi aktif hari ini — MUST
- Filter: semua / butuh loker / loker terpasang / tanpa loker / belum konfirmasi identitas.
- Kolom: nama, waktu masuk, nomor loker, status, tombol aksi.
- Sesi `auto_close` tidak lagi muncul sebagai aktif tetapi tetap terlihat di riwayat hari itu.

#### FR-CAS-08 Penjualan & perpanjangan paket — MUST
Form tunggal untuk visitor baru dan member lama:
- Cari nomor HP → jika tidak ada, isi nama + nomor HP + **foto wajib** (FR-D11) → akun dibuat dengan `role='member'`.
- Pilih paket dari `packages` (aktif saja), sistem membuat/memperpanjang `memberships` sesuai aturan §4.3.
- `sold_by_staff_id` dan `created_by_staff_id` tercatat; audit `membership.created` / `membership.renewed`.
- Pembayaran tidak dicatat sistem (out of scope).

#### FR-CAS-09 Peringatan tutup & batch reset loker — MUST
- Pukul `closing_time` (dari `gym_settings`), dasbor menampilkan daftar loker `occupied` dan sesi `open` yang belum ditutup.
- Aksi `[ Reset Loker Massal ]` meminta konfirmasi dengan mengetik jumlah loker yang dikembalikan.
- Efek: sesi `open` → `checkout_time`, `checkout_type='auto_close'`; loker `occupied` → `available`; `locker_state` menjadi `returned` bila belum.
- Audit `locker.batch_reset` menyimpan jumlah dan daftar nomor.

#### FR-CAS-10 Indikator offline & kontinuitas operasional — MUST
- Indikator merah "Offline" saat WebSocket dan polling sama-sama gagal.
- Saat offline, aksi yang butuh server (assign, return, check-in manual, jual paket) **dinonaktifkan** dengan instruksi: catat manual di form kertas cadangan, input setelah koneksi pulih. Sistem tidak menyediakan antrean tulis offline untuk Phase 1 karena risiko konflik data (D5).
- Setelah reconnect, dasbor memuat ulang sesi hari ini dan menampilkan jumlah sesi yang terlewat.

#### FR-CAS-11 Renewal alert untuk member kedaluwarsa — MUST
- Scan dari member tidak aktif **tidak** membuat `attendance_logs`, tetapi menulis `audit_logs` (`attendance.scan_rejected`) dengan alasan.
- Dasbor menerima event `attendance.rejected` dan menampilkan kartu merah berisi nama + alasan + tombol `[ Jual/Perpanjang Paket ]`.
- Panel "Aktivitas ditolak hari ini" membaca `GET /receptionist/rejected-attempts?date=today` agar kejadian tidak hilang saat kasir sedang sibuk.

#### FR-CAS-12 Autentikasi staf — MUST
- Login password (hash bcrypt); admin wajib TOTP. Sesi staf 8 jam, dapat dicabut admin.
- Semua endpoint kasir memeriksa `role IN ('receptionist','admin')`; endpoint admin memeriksa `role='admin'`.
- Percobaan login dibatasi 5 kali per 15 menit per akun dan per IP.

### 4.3 Pengelolaan Membership (modul baru)

Modul ini menutup celah v1.1: tidak ada yang membuat/mengubah data `memberships`, sehingga tidak ada data untuk dipindai.

| Aksi | Pelaku | Aturan |
|---|---|---|
| Daftar member baru | Kasir (FR-CAS-08), Admin | Nomor HP unik, nama wajib, foto wajib. Akun `role='member'`, `is_active=1`. Audit `user.created`. |
| Jual `monthly_unlimited` | Kasir | `start_date` = tanggal hari ini, `end_date` = `start_date + duration_days - 1`, `remaining_visits` NULL |
| Jual `single_visit` (Day Pass) | Kasir | `start_date` = `end_date` = hari ini, `remaining_visits = 1`. Hanya dapat dipakai pada hari itu |
| Perpanjang bulanan | Kasir | Basis = `MAX(hari ini, end_date berjalan)`, `end_date` baru = basis + `duration_days - 1`. Audit `membership.renewed` |
| Suspend | Admin | `status='suspended'`. Check-in baru ditolak `403 MEMBERSHIP_INACTIVE`. Sesi yang sedang terbuka tetap bisa ditutup |
| Aktifkan kembali | Admin | Kembali `active` hanya bila `end_date >= hari ini` dan (jika Day Pass) `remaining_visits > 0`; jika tidak, arahkan ke perpanjangan |
| Batalkan | Admin | `status='cancelled'`. Refund di luar sistem. Audit `membership.cancelled` |
| Konsumsi kuota | Sistem | `single_visit`: `remaining_visits` dikurangi 1 di transaksi check-in yang sama (`UPDATE ... SET remaining_visits = remaining_visits - 1 WHERE remaining_visits > 0`); bila hasilnya 0 → `status='expired'` |
| Penandaan kedaluwarsa | Job harian 00:05 WIB | Set `status='expired'` untuk `end_date < hari ini` atau (`single_visit` dan `remaining_visits = 0`) |

**Sumber kebenaran status.** Kolom `memberships.status` adalah hasil job, bisa tertinggal. Keputusan check-in **selalu** memakai `effective_status` yang dihitung saat request (§4.1 FR-MEM-05). Job hanya untuk pelaporan dan tampilan.

**Pemilihan membership saat check-in.** Satu user boleh memiliki beberapa membership. Urutan prioritas otomatis:
1. `single_visit` aktif yang berlaku hari ini (paling cepat hangus),
2. `monthly_unlimited` aktif dengan `end_date` paling dekat,
3. jika tidak ada → `403 MEMBERSHIP_EXPIRED`.

Pada check-in manual, kasir dapat memilih membership lain secara eksplisit; pilihan dicatat di audit.

**Penghapusan user** memakai soft delete (`users.deleted_at`). Riwayat `attendance_logs` dipertahankan; user terhapus tidak dapat login dan tidak muncul di pencarian.

### 4.4 Modul Admin & Manajemen Data

#### FR-ADM-01 Manajemen paket — MUST
- CRUD `packages`: `code`, `name`, `type`, `duration_days`, `visit_quota`, `price`, `is_active`.
- Paket yang sudah dipakai membership tidak dapat dihapus, hanya dinonaktifkan (`is_active=0`); paket nonaktif tidak muncul di form kasir.
- Perubahan harga tidak mengubah membership yang sudah ada (harga disalin ke `memberships.price_snapshot`).

#### FR-ADM-02 Manajemen aset loker — MUST
- Tambah, ubah nomor, nonaktifkan (`out_of_service`), aktifkan kembali.
- Mengubah loker `occupied` → `out_of_service` wajib konfirmasi dan alasan; audit `locker.out_of_service`.
- Nomor loker unik; penghapusan permanen tidak disediakan (hanya `out_of_service`) agar riwayat absensi tetap valid.

#### FR-ADM-03 Laporan & analitik — MUST
- Kunjungan harian, mingguan, bulanan (jumlah sesi, jumlah member unik, rata-rata durasi kunjungan).
- Histogram *peak hours* per jam (0–23) untuk rentang tanggal.
- Daftar sesi tanpa konfirmasi identitas, daftar loker yang dikembalikan setelah jam tutup, daftar check-in manual per kasir.
- Ekspor CSV. Zona waktu laporan Asia/Jakarta, data tersimpan UTC.

#### FR-ADM-04 Pengaturan gym — MUST
- Koordinat gym, `geofence_radius_m`, `geofence_accuracy_max_m`, `closing_time`, `timezone`.
- Regenerasi `qr_payload`: aksi eksplisit dengan konfirmasi, audit `gym.qr_regenerated`, dan instruksi mencetak ulang QR fisik. QR lama otomatis tidak valid.
- Perubahan radius berlaku pada request berikutnya (tanpa deploy).

#### FR-ADM-05 Manajemen staf — MUST
- Buat/nonaktifkan akun kasir & admin, reset password, cabut sesi aktif.
- Perubahan role tercatat di audit.

#### FR-ADM-06 Audit log — MUST
- Penampil read-only dengan filter pelaku, aksi, dan rentang tanggal.
- Aksi yang wajib tercatat: assign loker, return loker, batch reset, check-in manual, scan ditolak, konfirmasi identitas, pembuatan/perubahan membership, perubahan pengaturan gym, perubahan loker.
- Retensi audit minimum 12 bulan.

#### FR-ADM-07 Tindakan membership oleh admin — MUST
- Suspend, aktifkan kembali, batalkan, dan perpanjang manual (dengan alasan wajib) dari panel admin, memakai aturan §4.3.

## 5. Non-Functional Requirements

### 5.1 Performance & Kapasitas

| Kategori | Target |
|---|---|
| Respons `POST /attendance/scan-qr` | p95 ≤ 1.5 detik, p99 ≤ 3 detik (tidak termasuk jaringan klien) |
| Latency event WebSocket dari commit ke klien | p95 ≤ 500 ms |
| Waktu render dasbor kasir (first meaningful paint) | ≤ 2 detik pada LAN gym |
| Beban puncak asumsi | 40 check-in/menit, 3 kasir aktif bersamaan, 300 sesi terbuka, 10.000 baris absensi/hari |
| Query laporan | ≤ 3 detik untuk rentang 31 hari |
| Indeks wajib | `attendance_logs(user_id, checkin_time)`, `attendance_logs(checkin_time)`, `attendance_logs(checkout_time)`, `memberships(user_id, status)` |

Kapasitas di atas adalah asumsi perencanaan; validasi dengan uji beban sebelum go-live.

### 5.2 Availability, Real-time & Pemulihan

- Uptime target 99.5% per bulan untuk jam operasional.
- WebSocket memakai *exponential backoff* (1s → 2s → 4s → maks 30s) dan indikator status koneksi.
- Bila WebSocket gagal 3 kali, dasbor beralih ke polling 10 detik dan menampilkan banner.
- Event feed disimpan di Redis Stream (retensi 6 jam) agar `last_event_id` dapat diputar ulang.
- Backup database harian penuh + binlog; **RPO ≤ 15 menit**, **RTO ≤ 4 jam**.
- Uji restore dilakukan minimal sekali sebelum go-live dan setiap kuartal.
- Migrasi skema harus kompatibel mundur (deploy tanpa downtime).

### 5.3 Keamanan

| Area | Ketentuan |
|---|---|
| Transport | HTTPS/TLS 1.2+ wajib. HTTP dialihkan permanen |
| Token member | JWT access 15 menit (RS256), refresh token opaque 30 hari dengan rotasi dan pencabutan; refresh disimpan sebagai hash |
| Token staf | Sesi server-side 8 jam, cookie `HttpOnly; Secure; SameSite=Strict` |
| Password staf | bcrypt cost 12; admin wajib TOTP |
| OTP | 6 digit, hash tersimpan, kedaluwarsa 5 menit, maks 5 percobaan, rate limit §FR-MEM-01 |
| Rate limit API | `scan-qr`: 10/menit/token; `assign-locker`: 30/menit/kasir; `manual-checkin`: 60/jam/kasir |
| Otorisasi | Setiap endpoint memeriksa role + kepemilikan resource (member hanya boleh membaca datanya sendiri) |
| Secret | Di environment variable / secret manager, tidak masuk repo |
| Log | OTP, token, dan nomor HP dimasking (`08****1234`); payload QR tidak dicatat penuh |
| Database | User aplikasi dengan hak minimum, tanpa `DROP`; akses langsung dibatasi |

**Kontrol keamanan QR statis.** Karena payload tetap (D1), siapa pun yang memegang salinan QR dapat mengirimkannya dari lokasi lain. Mitigasi yang berlaku:

1. Geofence wajib pada setiap scan (`distance ≤ radius + accuracy`).
2. Satu sesi aktif per member; tidak ada check-in berulang yang menghasilkan baris baru.
3. Validasi identitas fisik oleh kasir pada kunjungan pertama dan pada sesi tanpa konfirmasi (FR-CAS-06).
4. `qr_payload` dapat diregenerasi admin (FR-ADM-04) bila QR bocor.
5. Audit seluruh scan ditolak untuk analisis pola penyalahgunaan.

Risiko yang **tidak** dimitigasi di Phase 1 dan diterima tertulis: pemalsuan lokasi (mock GPS) pada PWA tidak dapat dideteksi. Bila risiko ini terbukti terjadi di lapangan, jalur perbaikan adalah aplikasi native dengan Play Integrity/DeviceCheck (Phase 2).

### 5.4 Usability & Aksesibilitas

- Dasbor kasir: prinsip *minimal click* — assign loker maksimal 2 klik dari kartu feed.
- Target sentuh minimum 44×44 px; font dasar ≥ 16 px untuk tablet 10 inci.
- Kontras minimum 4.5:1 (WCAG 2.1 AA); status tidak boleh dibedakan hanya dengan warna (selalu ada label teks/ikon).
- Navigasi keyboard penuh untuk panel admin; fokus terlihat.
- Semua aksi destruktif (batch reset, batalkan membership, regenerasi QR) memakai dialog konfirmasi eksplisit.

### 5.5 Kompatibilitas

- Browser: Chrome/Edge versi stabil 2 terakhir, Safari iOS 15+, Chrome Android 10+.
- Fitur yang bergantung pada HTTPS dan izin pengguna: kamera, geolocation. Bila izin ditolak, aplikasi menampilkan instruksi, bukan error.
- Tidak mendukung IE dan browser tanpa `getUserMedia`.

### 5.6 Retensi Data & Privasi

Sistem memproses data pribadi: nama, nomor HP, foto wajah, dan koordinat lokasi. Rujukan hukum: UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi (teks resmi perlu dikonfirmasi ke sumber pemerintah sebelum go-live).

| Data | Retensi | Dasar |
|---|---|---|
| Nomor HP, nama | Selama akun aktif + 30 hari setelah soft delete | Identitas layanan |
| Foto profil | Selama akun aktif + 30 hari setelah soft delete | Verifikasi kehadiran |
| Koordinat GPS absensi | 90 hari, lalu dihapus; agregat (ada/tidak ada koordinat) dipertahankan | Audit geofence |
| Riwayat absensi | 24 bulan | Operasional & sengketa |
| Audit log | 12 bulan | Investigasi kecurangan |
| OTP | Dihapus 24 jam setelah kedaluwarsa | Minimalisasi |
| Log aplikasi | 30 hari, PII dimasking | Troubleshooting |

- Persetujuan (consent) diambil saat pendaftaran: kasir mencentang persetujuan member, tercatat `users.consent_at` dan versi teks persetujuan.
- Tujuan pemrosesan dibatasi: absensi, keamanan, dan laporan operasional. Data tidak dijual atau dibagikan ke pihak ketiga selain penyedia layanan OTP.
- Hak subjek data (akses, koreksi, penghapusan): diajukan ke admin, diproses maksimal 14 hari kerja; penghapusan memakai soft delete + anonimisasi pada retensi berakhir.
- Akses data dibatasi per role; ekspor CSV hanya untuk admin dan tercatat di audit.
- Insiden kebocoran data: eskalasi ke owner dalam 1×24 jam.

### 5.7 Observability

- Structured log JSON dengan `request_id`, `user_id`, `route`, `latency`.
- Metrik wajib: error rate per endpoint, p95 `scan-qr`, jumlah sesi terbuka, jumlah loker `occupied`, disconnect WebSocket, sisa kuota OTP.
- Alert: error rate `scan-qr` > 2% selama 5 menit; WebSocket putus > 3 menit; job auto-close gagal.

## 6. Database Architecture & Schema

Stack: **MySQL 8.0+** (InnoDB, `utf8mb4_unicode_ci`), sesuai D7. Semua waktu disimpan **UTC** dalam kolom `DATETIME`/`TIMESTAMP`; konversi ke Asia/Jakarta dilakukan di lapisan aplikasi dan laporan.

### 6.1 DDL

DDL di bawah dieksekusi penuh pada MySQL 8.4.3 saat penyusunan dokumen: 9 tabel terbentuk tanpa error. Dua constraint diuji dengan data: `chk_gym_single_row` menolak baris kedua, dan `chk_logs_geo` mengizinkan check-in manual tanpa koordinat tetapi menolak check-in QR tanpa koordinat. Pola `UPDATE ... WHERE status='available'` juga diuji menghasilkan 1 baris terpengaruh pada assign pertama dan 0 pada assign kedua.

```sql
-- 0. Konfigurasi gym (satu baris, D9)
CREATE TABLE gym_settings (
    gym_id                  TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
    gym_name                VARCHAR(100) NOT NULL,
    timezone                VARCHAR(64)  NOT NULL DEFAULT 'Asia/Jakarta',
    latitude                DECIMAL(10,8) NOT NULL,
    longitude               DECIMAL(11,8) NOT NULL,
    geofence_radius_m       SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    geofence_accuracy_max_m SMALLINT UNSIGNED NOT NULL DEFAULT 150,
    rescan_guard_seconds    SMALLINT UNSIGNED NOT NULL DEFAULT 300,
    closing_time            TIME NOT NULL DEFAULT '23:00:00',
    qr_payload              VARCHAR(128) NOT NULL,
    updated_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                            ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_gym_single_row CHECK (gym_id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1. Users (member, kasir, admin). Soft delete, tanpa CASCADE ke riwayat.
CREATE TABLE users (
    user_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(100) NOT NULL,
    phone_number    VARCHAR(20)  NOT NULL,
    photo_url       VARCHAR(255) NULL,
    role            ENUM('member','receptionist','admin') NOT NULL DEFAULT 'member',
    password_hash   VARCHAR(255) NULL,          -- wajib untuk staf, NULL untuk member
    totp_secret     VARCHAR(255) NULL,          -- wajib admin (D10)
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    consent_at      DATETIME     NULL,
    consent_version VARCHAR(20)  NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                                 ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP    NULL DEFAULT NULL,
    UNIQUE KEY uq_users_phone (phone_number),
    KEY idx_users_role (role, is_active),
    KEY idx_users_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Nomor HP unik berlaku juga untuk user soft-deleted: mencegah daur ulang identitas.
```

```sql
-- 2. Paket (FR-ADM-01); tipe tidak lagi hardcode di memberships
CREATE TABLE packages (
    package_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code          VARCHAR(30)  NOT NULL,
    name          VARCHAR(100) NOT NULL,
    type          ENUM('monthly_unlimited','single_visit') NOT NULL,
    duration_days SMALLINT UNSIGNED NOT NULL,
    visit_quota   SMALLINT UNSIGNED NULL,
    price         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    is_active     TINYINT(1)    NOT NULL DEFAULT 1,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_packages_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Memberships (riwayat dipertahankan: tanpa ON DELETE CASCADE)
CREATE TABLE memberships (
    membership_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id           INT UNSIGNED NOT NULL,
    package_id        INT UNSIGNED NOT NULL,
    type              ENUM('monthly_unlimited','single_visit') NOT NULL,
    remaining_visits  SMALLINT UNSIGNED NULL,
    price_snapshot    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    start_date        DATE NOT NULL,
    end_date          DATE NULL,
    status            ENUM('active','expired','suspended','cancelled')
                      NOT NULL DEFAULT 'active',
    created_by_staff_id INT UNSIGNED NULL,
    sold_by_staff_id    INT UNSIGNED NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_mem_user (user_id, status, end_date),
    CONSTRAINT fk_mem_user       FOREIGN KEY (user_id)        REFERENCES users(user_id),
    CONSTRAINT fk_mem_package    FOREIGN KEY (package_id)     REFERENCES packages(package_id),
    CONSTRAINT fk_mem_created_by FOREIGN KEY (created_by_staff_id) REFERENCES users(user_id),
    CONSTRAINT fk_mem_sold_by    FOREIGN KEY (sold_by_staff_id)    REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Lockers (tanpa hard delete; hanya out_of_service)
CREATE TABLE lockers (
    locker_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    locker_number VARCHAR(10) NOT NULL,
    status        ENUM('available','occupied','out_of_service') NOT NULL DEFAULT 'available',
    note          VARCHAR(255) NULL,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                            ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_lockers_number (locker_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

```sql
-- 5. Attendance logs (satu baris = satu sesi kunjungan)
CREATE TABLE attendance_logs (
    log_id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id            INT UNSIGNED NOT NULL,
    membership_id      INT UNSIGNED NOT NULL,
    locker_id          INT UNSIGNED NULL,
    locker_state       ENUM('pending','none','assigned','returned') NOT NULL DEFAULT 'pending',
    assigned_by_staff_id INT UNSIGNED NULL,
    assigned_at        DATETIME NULL,
    identity_verified_by INT UNSIGNED NULL,
    identity_verified_at DATETIME NULL,
    checkin_time       DATETIME NOT NULL,
    locker_returned_time DATETIME NULL,
    checkout_time      DATETIME NULL,
    checkout_type      ENUM('locker_returned','staff_checkout','auto_close') NULL,
    checkin_method     ENUM('qr_scan','manual_receptionist') NOT NULL DEFAULT 'qr_scan',
    manual_reason      ENUM('no_phone','battery_dead','camera_broken','gps_issue','other') NULL,
    latitude           DECIMAL(10,8) NULL,
    longitude          DECIMAL(11,8) NULL,
    gps_accuracy_m     DECIMAL(6,2)  NULL,
    idempotency_key    VARCHAR(64)   NULL,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_logs_idempotency (user_id, idempotency_key),
    KEY idx_logs_user_time (user_id, checkin_time),
    KEY idx_logs_checkin (checkin_time),
    KEY idx_logs_open (checkout_time),
    KEY idx_logs_locker (locker_id),
    CONSTRAINT fk_logs_user      FOREIGN KEY (user_id)      REFERENCES users(user_id),
    CONSTRAINT fk_logs_member    FOREIGN KEY (membership_id) REFERENCES memberships(membership_id),
    CONSTRAINT fk_logs_locker    FOREIGN KEY (locker_id)    REFERENCES lockers(locker_id),
    CONSTRAINT fk_logs_staff     FOREIGN KEY (assigned_by_staff_id) REFERENCES users(user_id),
    CONSTRAINT fk_logs_verifier  FOREIGN KEY (identity_verified_by) REFERENCES users(user_id),
    CONSTRAINT chk_logs_geo CHECK (
        (checkin_method = 'manual_receptionist')
        OR (latitude IS NOT NULL AND longitude IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Satu sesi terbuka per user dijaga transaksi aplikasi (§4.1 FR-MEM-04),
-- bukan unique index, karena MySQL melarang generated column yang mereferensikan AUTO_INCREMENT.
```

```sql
-- 6. OTP (hash, bukan plaintext)
CREATE TABLE otp_requests (
    otp_id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(20) NOT NULL,
    code_hash    VARCHAR(255) NOT NULL,
    attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at   DATETIME NOT NULL,
    consumed_at  DATETIME NULL,
    request_ip   VARBINARY(16) NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_otp_phone (phone_number, created_at),
    KEY idx_otp_cleanup (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Refresh token member (rotasi + pencabutan)
CREATE TABLE refresh_tokens (
    token_id   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_refresh_hash (token_hash),
    KEY idx_refresh_user (user_id, expires_at),
    CONSTRAINT fk_refresh_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Audit log (assign loker, override, perubahan data)
CREATE TABLE audit_logs (
    audit_id      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT UNSIGNED NULL,
    actor_role    VARCHAR(20)  NULL,
    action        VARCHAR(60)  NOT NULL,
    entity_type   VARCHAR(30)  NULL,
    entity_id     BIGINT UNSIGNED NULL,
    before_json   JSON NULL,
    after_json    JSON NULL,
    request_ip    VARBINARY(16) NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_action (action, created_at),
    KEY idx_audit_actor (actor_user_id, created_at),
    CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 6.2 Aturan Implementasi Data

**Assign loker atomik (FR-CAS-02).** Urutan perintah dalam satu transaksi:

1. `SELECT ... FROM attendance_logs WHERE log_id = ? FOR UPDATE`
2. Validasi `locker_state IN ('pending','none')` dan sesi masih terbuka.
3. `UPDATE lockers SET status='occupied' WHERE locker_id = ? AND status='available'` → bila `affected_rows = 0` lakukan `ROLLBACK` dan kembalikan `409 LOCKER_TAKEN`.
4. Update baris absensi (`locker_id`, `locker_state='assigned'`, `assigned_by_staff_id`, `assigned_at`).
5. `COMMIT`, lalu publish event.

**Check-in (FR-MEM-04).** `SELECT log_id FROM attendance_logs WHERE user_id = ? AND checkout_time IS NULL FOR UPDATE` di dalam transaksi, ditambah lock Redis `checkin:{user_id}` (TTL 10 detik) agar dua request paralel tidak lolos bersamaan.

**Pemakaian Day Pass.** Pengurangan kuota memakai `UPDATE memberships SET remaining_visits = remaining_visits - 1 WHERE membership_id = ? AND remaining_visits > 0`; `affected_rows = 0` berarti kuota habis dan check-in dibatalkan.

**Tabel `packages` sebagai sumber tipe.** `memberships.type` adalah snapshot dari `packages.type` pada saat penjualan, agar perubahan konfigurasi paket tidak mengubah arti data historis.

**Migrasi dari v1.1.** Perubahan destruktif minimal: `latitude`/`longitude` menjadi `NULL`, tambah kolom baru. Bila data v1.1 sudah ada, `locker_state` diisi dari `locker_id` (`assigned` bila ada, `none` bila tidak) sebelum kolom dijadikan `NOT NULL`.

## 7. API Specifications

### 7.1 Konvensi

- Base URL: `https://<host>/api/v1`.
- Autentikasi: `Authorization: Bearer <access_token>` untuk member; cookie sesi untuk staf.
- Body & respons: `application/json; charset=utf-8`.
- Waktu: ISO 8601 UTC dengan sufiks `Z` (contoh `2026-10-11T18:30:00Z`). Tanggal kalender memakai `YYYY-MM-DD` berdasarkan zona Asia/Jakarta.
- Idempotensi: `Idempotency-Key` (UUID v4) wajib pada `scan-qr` dan `manual-checkin`; opsional pada assign loker.
- Pagination: `?page=1&per_page=25` (maks 100), respons menyertakan `meta.page`, `meta.per_page`, `meta.total`.
- Nomor HP dikirim dalam format E.164 tanpa `+` (contoh `628123456789`); server menormalkan input lokal `08...`.

### 7.2 Format Respons

Sukses:

```json
{
  "status": "success",
  "message": "Check-in berhasil. Silakan konfirmasi penggunaan loker ke kasir.",
  "data": {},
  "meta": { "request_id": "01J9Z8Y6Q0K3M2" }
}
```

Gagal (semua kode 4xx/5xx memakai bentuk ini):

```json
{
  "status": "error",
  "code": "GEO_OUT_OF_RANGE",
  "message": "Lokasi Anda terlalu jauh dari lokasi Gym (maksimal 30 meter).",
  "data": null,
  "meta": {
    "request_id": "01J9Z8Y6Q0K3M2",
    "details": { "distance_m": 412.7, "radius_m": 30, "accuracy_m": 18.5 }
  }
}
```

### 7.3 Kode Error

| HTTP | `code` | Kondisi | Aksi klien |
|---|---|---|---|
| 400 | `VALIDATION_ERROR` | Field wajib/format salah | Tampilkan pesan per field |
| 400 | `INVALID_QR` | Payload QR tidak cocok dengan `qr_payload` | "QR tidak dikenali, pastikan memindai QR di meja kasir" |
| 401 | `UNAUTHENTICATED` | Token tidak ada/invalid | Arahkan ke login |
| 401 | `TOKEN_EXPIRED` | Access token kedaluwarsa | Refresh token, lalu ulangi |
| 403 | `FORBIDDEN_ROLE` | Role tidak berhak | Tampilkan halaman 403 |
| 403 | `GEO_OUT_OF_RANGE` | Di luar radius | "Anda berada di luar area gym" |
| 403 | `MEMBERSHIP_EXPIRED` | Paket habis/kedaluwarsa | "Perpanjang paket di kasir" |
| 403 | `MEMBERSHIP_INACTIVE` | Paket suspended/cancelled | "Hubungi kasir" |
| 404 | `NOT_FOUND` | Resource tidak ada | Muat ulang daftar |
| 404 | `MEMBER_NOT_FOUND` | Nomor HP belum terdaftar | "Daftar dulu di meja kasir" |
| 409 | `SESSION_ACTIVE` | Masih ada sesi terbuka | Tampilkan jam masuk + nomor loker |
| 409 | `LOCKER_TAKEN` | Loker sudah dipakai kasir lain | Refresh daftar loker |
| 409 | `LOCKER_ALREADY_ASSIGNED` | Sesi sudah punya loker | Refresh kartu |
| 409 | `SESSION_CLOSED` | Sesi sudah ditutup | Refresh daftar |
| 422 | `GPS_ACCURACY_LOW` | `accuracy` melebihi batas | "Minta check-in manual ke kasir" |
| 422 | `OTP_INVALID` | Kode salah | Coba lagi (maks 5) |
| 422 | `OTP_EXPIRED` | Kode kedaluwarsa | Minta kode baru |
| 429 | `RESCAN_GUARD` | Scan ulang < 5 menit setelah sesi ditutup | Tunggu sesuai `retry_after_seconds` |
| 429 | `OTP_RATE_LIMITED` | Rate limit OTP | Tunggu `retry_after_seconds` |
| 429 | `RATE_LIMITED` | Rate limit umum | Backoff |
| 500 | `INTERNAL_ERROR` | Kesalahan tak terduga | Tampilkan "Coba lagi", laporkan `request_id` |
| 503 | `SERVICE_UNAVAILABLE` | DB/Redis/penyedia OTP down | Instruksi check-in manual |

### 7.4 Pemetaan Pesan Member

| `code` | Pesan + tindakan di aplikasi |
|---|---|
| `GEO_OUT_OF_RANGE` | "Lokasi Anda terlalu jauh dari lokasi Gym (maksimal {radius} meter)." Tombol: "Coba lagi" |
| `GPS_ACCURACY_LOW` | "Sinyal GPS lemah. Minta check-in manual ke kasir." Tanpa tombol ulangi |
| `MEMBERSHIP_EXPIRED` | "Paket Anda sudah berakhir. Perpanjang di meja kasir." |
| `SESSION_ACTIVE` | "Anda sudah check-in pukul HH:mm. Loker: #05 / tanpa loker." |
| `RESCAN_GUARD` | "Baru saja check-in. Coba lagi dalam N menit." |

### 7.5 Endpoint Member & Publik

| Method | Path | Keterangan |
|---|---|---|
| POST | `/auth/request-otp` | Kirim OTP WhatsApp |
| POST | `/auth/verify-otp` | Verifikasi OTP, terbitkan token |
| POST | `/auth/refresh` | Rotasi refresh token |
| POST | `/auth/logout` | Cabut refresh token aktif |
| GET | `/member/profile` | Profil, paket aktif, sesi & loker saat ini |
| GET | `/member/attendance/history` | Riwayat kunjungan (30 terakhir) |
| POST | `/attendance/scan-qr` | Check-in via QR |

`POST /auth/request-otp`

```json
{ "phone_number": "628123456789" }
```
Respons 200: `{"status":"success","message":"Kode OTP telah dikirim ke WhatsApp Anda.","data":{"expires_in":300},"meta":{...}}`

`POST /auth/verify-otp`

```json
{ "phone_number": "628123456789", "code": "123456" }
```
Respons 200:

```json
{
  "status": "success",
  "message": "Login berhasil.",
  "data": {
    "access_token": "<jwt>",
    "token_type": "Bearer",
    "expires_in": 900,
    "refresh_token": "<opaque>"
  },
  "meta": { "request_id": "..." }
}
```

`GET /member/profile` → 200

```json
{
  "status": "success",
  "message": "OK",
  "data": {
    "user": { "user_id": 7, "full_name": "John Doe", "photo_url": "https://.../7.jpg", "phone_masked": "6281****6789" },
    "membership": {
      "membership_id": 21,
      "type": "monthly_unlimited",
      "package_name": "Bulanan Unlimited",
      "effective_status": "active",
      "remaining_days": 12,
      "remaining_visits": null,
      "end_date": "2026-11-04"
    },
    "active_session": {
      "log_id": 1045,
      "checkin_time": "2026-10-11T11:30:00Z",
      "locker_state": "assigned",
      "locker_number": "05"
    }
  },
  "meta": { "request_id": "..." }
}
```

`POST /attendance/scan-qr` (header `Idempotency-Key` wajib)

```json
{
  "qr_payload": "GYM_STATIC_TOKEN_XYZ123",
  "latitude": -6.2088,
  "longitude": 106.8456,
  "accuracy": 12.5,
  "locker_intent": "need_locker"
}
```

`locker_intent`: `need_locker` | `no_locker`. Respons 200:

```json
{
  "status": "success",
  "message": "Check-in berhasil. Silakan konfirmasi penggunaan loker ke kasir.",
  "data": {
    "log_id": 1045,
    "checkin_time": "2026-10-11T11:30:00Z",
    "member_name": "John Doe",
    "membership_type": "monthly_unlimited",
    "locker_state": "pending",
    "locker_number": null
  },
  "meta": { "request_id": "..." }
}
```

Respons gagal mengikuti §7.2; contoh 409 `SESSION_ACTIVE` menyertakan `details.checkin_time` dan `details.locker_number`.

### 7.6 Endpoint Kasir

| Method | Path | Keterangan |
|---|---|---|
| POST | `/receptionist/auth/login` | Login staf (password, TOTP untuk admin) |
| POST | `/receptionist/auth/logout` | Akhiri sesi staf |
| GET | `/receptionist/sessions` | Sesi hari ini (`date`, `updated_since`, `filter`) |
| GET | `/receptionist/lockers` | Status seluruh loker |
| PATCH | `/receptionist/attendance/{log_id}/assign-locker` | Assign loker |
| PATCH | `/receptionist/attendance/{log_id}/no-locker` | Tandai tanpa loker |
| PATCH | `/receptionist/attendance/{log_id}/release-locker` | Kembalikan kunci |
| PATCH | `/receptionist/attendance/{log_id}/verify-identity` | Konfirmasi identitas |
| POST | `/receptionist/manual-checkin` | Check-in manual |
| POST | `/receptionist/lockers/batch-reset` | Reset loker massal jam tutup |
| GET | `/receptionist/rejected-attempts` | Daftar scan ditolak hari ini |
| GET | `/receptionist/members` | Cari member (`q` minimal 3 karakter) |
| POST | `/receptionist/memberships` | Daftarkan member baru / jual / perpanjang paket |

`PATCH .../assign-locker`

```json
{ "locker_id": 5 }
```
200: `{"status":"success","message":"Loker #05 berhasil di-assign ke member.","data":{"log_id":1045,"locker_id":5,"locker_number":"05","locker_state":"assigned"},"meta":{...}}`

`POST /receptionist/manual-checkin` (header `Idempotency-Key` wajib)

```json
{
  "user_id": 7,
  "membership_id": 21,
  "locker_intent": "no_locker",
  "reason": "battery_dead",
  "note": "HP mati, identitas dicek dari KTP"
}
```
200 mengembalikan `log_id`, `checkin_method: "manual_receptionist"`, dan `locker_state`.

`POST /receptionist/lockers/batch-reset`

```json
{ "confirm_count": 7, "note": "Tutup operasional" }
```
Server menolak `400 VALIDATION_ERROR` bila `confirm_count` tidak sama dengan jumlah loker `occupied` saat itu.

`POST /receptionist/memberships`

```json
{
  "phone_number": "628123456789",
  "full_name": "John Doe",
  "photo_url": "https://.../new-member.jpg",
  "package_id": 2,
  "action": "new",
  "consent": true
}
```
`action`: `new` | `renew`. 201 untuk member baru, 200 untuk perpanjangan.

### 7.7 Endpoint Admin

| Method | Path | Keterangan |
|---|---|---|
| GET/POST | `/admin/packages` | Daftar / buat paket |
| PATCH | `/admin/packages/{id}` | Ubah atau nonaktifkan paket |
| GET/POST | `/admin/lockers` | Daftar / tambah loker |
| PATCH | `/admin/lockers/{id}` | Ubah nomor/status |
| GET/PATCH | `/admin/gym-settings` | Baca/ubah konfigurasi gym |
| POST | `/admin/gym-settings/regenerate-qr` | Regenerasi `qr_payload` |
| GET/POST/PATCH | `/admin/staff` | Kelola akun staf |
| PATCH | `/admin/memberships/{id}` | Suspend / aktifkan / batalkan / perpanjang |
| GET | `/admin/reports/attendance` | `from`, `to`, `group=day\|week\|month` |
| GET | `/admin/reports/peak-hours` | Histogram per jam |
| GET | `/admin/audit-logs` | Filter `actor_user_id`, `action`, `from`, `to` |

### 7.8 WebSocket

- Kanal: `private-reception` (kasir/admin), `private-member.{user_id}`.
- Otorisasi kanal memakai token/sesi yang sama dengan HTTP.

| Event | Penerima | Payload ringkas |
|---|---|---|
| `attendance.created` | reception | `log_id`, `user_id`, `full_name`, `photo_url`, `membership_type`, `locker_state`, `checkin_time` |
| `attendance.rejected` | reception | `user_id`, `full_name`, `reason_code`, `occurred_at` |
| `attendance.identity_verified` | reception | `log_id`, `verified_by`, `verified_at` |
| `locker.assigned` | reception, member | `log_id`, `locker_number`, `assigned_at` |
| `locker.returned` | reception, member | `log_id`, `locker_number`, `returned_at` |
| `session.closed` | member | `log_id`, `checkout_time`, `checkout_type` |

Envelope event:

```json
{ "event_id": "1728633000123-0", "type": "locker.assigned", "occurred_at": "2026-10-11T11:31:05Z", "payload": {} }
```

`event_id` adalah ID Redis Stream yang dipakai klien sebagai `last_event_id` saat reconnect.

## 8. Exception Handling & Edge Cases

### 8.1 Scan dari luar area / GPS palsu

| Kondisi | Perilaku sistem |
|---|---|
| Jarak melebihi `radius + accuracy` | `403 GEO_OUT_OF_RANGE`, tidak membuat baris absensi, dicatat di audit |
| `accuracy` > `geofence_accuracy_max_m` | `422 GPS_ACCURACY_LOW`, arahkan ke check-in manual |
| Koordinat tidak dikirim / kosong | `400 VALIDATION_ERROR` |
| Selisih waktu perangkat > 10 menit dari server | Diabaikan; keputusan memakai jam server, `client_time` hanya untuk diagnostik |

GPS palsu (mock location) tidak dapat dideteksi pada PWA. Mitigasi: geofence bukan satu-satunya kontrol (§5.3) dan pola scan ditolak ditinjau admin melalui audit.

### 8.2 Kunci loker tidak dikembalikan

- Pukul `closing_time` dasbor menampilkan daftar loker `occupied` + sesi `open`.
- Kasir menekan **Reset Loker Massal** (konfirmasi dengan mengetik jumlah) → sesi ditutup `auto_close`, loker kembali `available`, audit tersimpan.
- Bila kunci hilang: kasir/admin menandai loker `out_of_service` dengan alasan; sesi ditutup `staff_checkout`; audit wajib.
- Batch reset tidak pernah berjalan otomatis tanpa kasir. Job terjadwal hanya **mengingatkan**.

### 8.3 Koneksi terputus

| Skenario | Perilaku |
|---|---|
| Kasir offline, member tetap scan | Server tetap menerima check-in. Dasbor menampilkan indikator merah; saat reconnect memuat ulang sesi hari ini (`updated_since`) dan WS replay via `last_event_id`. Aksi server-side (assign/return/jual paket) dinonaktifkan sementara; kasir memakai form kertas cadangan, lalu input setelah pulih |
| HP member offline / sinyal buruk | Scan gagal di klien; petunjuk: "Minta check-in manual ke kasir". Kasir memakai FR-CAS-05 |
| Keduanya online, WebSocket putus | Fallback polling 10 detik otomatis, banner status ditampilkan |
| Member dan kasir offline bersamaan | Kasir mencatat manual di kertas; seluruh check-in diinput setelah koneksi pulih. Urutan input mengikuti jam kejadian yang dicatat kasir, dengan `manual_reason` dan catatan |

Event yang terlewat lebih dari 6 jam (retensi Redis Stream) dipulihkan dengan memuat ulang daftar sesi, bukan dengan replay.

### 8.4 QR bocor atau QR perlu diganti

1. Admin memakai `POST /admin/gym-settings/regenerate-qr`.
2. `qr_payload` lama langsung tidak valid; scan dengan payload lama → `400 INVALID_QR`.
3. QR fisik baru dicetak dan dipasang; audit `gym.qr_regenerated` mencatat pelaku dan waktu.

### 8.5 Day Pass dan kuota

- Day Pass hanya berlaku pada `start_date`; percobaan besoknya → `403 MEMBERSHIP_EXPIRED`.
- Kuota dikonsumsi di transaksi check-in yang sama; check-in gagal tidak mengurangi kuota.
- Scan ganda dengan `Idempotency-Key` sama tidak mengurangi kuota dua kali.
- Day Pass dengan `remaining_visits = 0` langsung `expired` dan muncul sebagai renewal alert di kasir.

### 8.6 Loker rusak saat sedang dipakai

Hanya admin yang boleh mengubah `occupied` → `out_of_service`; wajib alasan; sesi tetap tercatat memakai loker tersebut sampai ditutup, agar riwayat tidak berubah.

### 8.7 Kegagalan layanan internal

| Kegagalan | Perilaku |
|---|---|
| Database tidak tersedia | `503 SERVICE_UNAVAILABLE`; aplikasi member menampilkan instruksi check-in manual; dasbor menampilkan banner gangguan |
| Redis tidak tersedia | Rate limit & buffer event turun; check-in tetap berjalan karena kebenaran data dijaga database |
| Penyedia OTP gagal | `503`; kasir melakukan check-in manual; member diberi tahu untuk lapor ke kasir |
| Job auto-close gagal | Alert ke admin; kasir dapat menutup sesi manual |

### 8.8 OTP tidak sampai / salah nomor

- Kirim ulang setelah 60 detik; maksimum 5 permintaan per jam per nomor.
- Nomor salah ketik tidak dapat "diperbaiki" lewat OTP; kasir mendaftarkan nomor baru atau memperbaiki nomor lewat panel (audit `user.phone_updated`).
- Perubahan nomor HP oleh admin wajib verifikasi baru sebelum member dapat login kembali.

## 9. Prioritas & Acceptance Test

### 9.1 Ringkasan MoSCoW

| Prioritas | Item |
|---|---|
| **Must** | FR-MEM-01…08, FR-MEM-10; FR-CAS-01…09, FR-CAS-11, FR-CAS-12; §4.3 seluruhnya; FR-ADM-01…06 |
| **Should** | FR-MEM-09 (riwayat), FR-ADM-07, ekspor CSV laporan |
| **Could** | Notifikasi WhatsApp pengingat masa aktif, dashboard TV di lobi |
| **Won't (Phase 1)** | Payment gateway, smart locker, turnstile, multi-cabang, aplikasi native |

### 9.2 Acceptance Test End-to-End

| ID | Skenario | Hasil yang diharapkan |
|---|---|---|
| AT-01 | Member aktif scan QR di meja kasir | 200, 1 baris `attendance_logs`, event muncul di dasbor < 500 ms |
| AT-02 | Scan dengan QR hasil foto dari lokasi gym | Lolos (diterima sebagai risiko, §5.3) — tetapi hanya menghasilkan 1 sesi |
| AT-03 | Scan dari 500 m | 403 `GEO_OUT_OF_RANGE`, tidak ada baris baru |
| AT-04 | Scan dengan `accuracy` 300 m | 422 `GPS_ACCURACY_LOW` |
| AT-05 | Scan dengan QR acak | 400 `INVALID_QR` |
| AT-06 | Scan saat sesi masih terbuka | 409 `SESSION_ACTIVE` + info sesi |
| AT-07 | Scan dua kali dengan `Idempotency-Key` sama | 1 baris, respons identik |
| AT-08 | Dua kasir assign loker #05 bersamaan | Satu 200, satu 409 `LOCKER_TAKEN`, loker tetap satu pemilik |
| AT-09 | Kembalikan kunci | Loker `available`, `checkout_time` terisi, member lihat status berubah ≤ 2 detik |
| AT-10 | Member expired scan | 403, tidak ada baris absensi, muncul renewal alert + entri audit |
| AT-11 | Check-in manual | Baris dengan `latitude/longitude NULL`, `manual_reason` terisi, audit ada |
| AT-12 | Day Pass dipakai dua kali di hari berbeda | Hari pertama 200, hari kedua 403 `MEMBERSHIP_EXPIRED` |
| AT-13 | Kasir offline 10 menit saat member terus scan | Check-in tersimpan; setelah reconnect semua sesi muncul |
| AT-14 | Batch reset saat jam tutup dengan jumlah salah | 400, tidak ada perubahan data |
| AT-15 | Regenerasi QR | Payload lama → 400 `INVALID_QR`, payload baru → 200 |

Setiap AT yang melibatkan uang, kuota, atau status loker wajib punya automated test, bukan hanya uji manual.

---

## 10. Rencana Rilis, Risiko & Pertanyaan Terbuka

### 10.1 Milestone menuju Q1 2027

| Fase | Target | Keluaran |
|---|---|---|
| Sign-off PRD v1.2 | Nov 2026 | Decision Log §0.2 disetujui, pengukuran baseline K6 selesai |
| Fondasi & auth | Des 2026 | Skema DB, auth member (OTP) & staf, settings gym |
| Check-in & feed | Jan 2027 | Scan QR, geofence, sesi, WebSocket feed |
| Loker & membership | Feb 2027 | Assign/return/batch reset, modul penjualan paket, laporan |
| UAT & go-live | Mar 2027 | Uji lapangan geofence, uji beban, uji restore, pelatihan kasir |

### 10.2 Risiko

| ID | Risiko | Dampak | Mitigasi | Pemilik |
|---|---|---|---|---|
| R1 | Akurasi GPS indoor membuat member sah ditolak | Tinggi | Ukur di lokasi sebelum go-live; kalibrasi `geofence_radius_m`; jalur check-in manual | Owner + Tech |
| R2 | Biaya/kuota OTP WhatsApp membengkak atau provider memblokir | Sedang | Rate limit, monitoring sisa kuota, kontrak provider, fallback manual | Owner |
| R3 | Mock GPS tidak terdeteksi di PWA | Sedang | Diterima tertulis (§5.3); tinjau audit; jalur native Phase 2 | Owner |
| R4 | Enumerasi nomor lewat `MEMBER_NOT_FOUND` | Rendah | Rate limit per IP, monitoring pola, pesan tanpa detail tambahan | Tech |
| R5 | Kasir offline bergantung pada prosedur kertas | Sedang | Form cadangan tercetak, pelatihan, input ulang teraudit | Operasional |
| R6 | QR difoto dan dibagikan | Sedang | Regenerasi QR, konfirmasi identitas, sesi tunggal | Owner + Tech |

### 10.3 Pertanyaan Terbuka

| ID | Pertanyaan | Perlu jawaban dari |
|---|---|---|
| Q1 | Berapa baseline waktu check-in manual sebenarnya di gym ini? (K6) | Owner |
| Q2 | Penyedia WhatsApp OTP mana yang dipakai, dan berapa biaya per pesan? | Owner |
| Q3 | Foto member disimpan di storage mana, dan siapa yang boleh mengakses? | Tech |
| Q4 | Apakah jam tutup sama setiap hari (termasuk akhir pekan)? | Owner |
| Q5 | Berapa jumlah loker aktual, dan apakah ada loker khusus (mis. loker besar)? | Owner |

---

## Appendix A. Traceability Review v1.1 → v1.2

| Temuan review | Lokasi perbaikan |
|---|---|
| A.1 Kontradiksi QR statis vs token dinamis | §0.2 D1, §5.3, FR-MEM-02 |
| A.2 Perilaku member expired tidak jelas | §0.2 D2, FR-CAS-11, AT-10 |
| A.3 `latitude`/`longitude` NOT NULL vs manual check-in | §6.1 `attendance_logs`, FR-CAS-05 |
| A.4 Tidak ada FR pengelolaan membership | §4.3, FR-CAS-08, FR-ADM-01/07 |
| A.5 Alur "tanpa antre" masih mengantre | §1.2, §3.2, FR-MEM-07 |
| B.6 Geofence 30 m berisiko menolak member sah | FR-MEM-03, §6.1 `gym_settings`, R1 |
| B.7 Cooldown 60 menit tidak menyelesaikan duplikasi | §0.2 D4, FR-MEM-04, §3.3 |
| B.8 Race condition loker & status ambigu | FR-CAS-02, §3.4, §6.1, §6.2, AT-08 |
| B.9 Edge case offline satu sisi | §8.3, FR-CAS-01/10, §5.2 |
| B.10 Skema tidak mendukung fitur | §6 (`packages`, `gym_settings`, `otp_requests`, `refresh_tokens`, `audit_logs`, soft delete, timezone, D7 stack) |
| B.11 API tidak lengkap | §7.2–§7.8 (format error, kode, endpoint feed, no-locker, batch reset, admin, refresh, logout, payload manual) |
| B.12 Auth staf & onboarding member | §0.2 D10/D11, FR-CAS-06, FR-CAS-08, FR-CAS-12 |
| C.1 Status dokumen ganda | Document Control (satu status: Draft for Review) |
| C.2 KPI tidak terukur | §1.3 (K1–K7 + cara ukur) |
| C.3 NFR kurang | §5 selengkapnya |
| C.4 Privasi/UU PDP | §5.6 |
| C.5 MoSCoW, AC, out-of-scope, asumsi, risiko, dependensi, milestone, open questions | §0.1, §2.2, §2.3, §4, §9, §10 |
| C.6 Mekanisme update loker ke member | FR-MEM-06, §7.8 |
| C.7 Multi-cabang | §0.1, §0.2 D9 |
| D. Contoh perbaikan skema | §6.1 (diterapkan sebagai skema final MySQL 8) |













