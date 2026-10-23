# Milestone 2 — Check-in QR, Profil Member, Riwayat Kunjungan

Status: selesai (M2), diverifikasi 2026-10-23. Sumber: PRD v1.2 §4.1 (FR-MEM-02..09), §4.3, §6.2, §7.5, §8.1, §8.5, §9.2 (AT-01..AT-12).

## Tujuan

Member dengan paket aktif dapat check-in lewat QR statis di meja kasir. Server memvalidasi QR,
geofence, sesi, dan membership; menulis satu baris `attendance_logs` secara atomik; serta
menolak scan yang tidak sah dengan kode error PRD §7.3. Member juga dapat membaca profil,
sesi aktif, dan 30 kunjungan terakhir miliknya sendiri.

## Non-goals (ditunda)

- WebSocket `attendance.created` / `attendance.rejected` (M2 hanya data; event M3).
- Assign / return loker oleh kasir, check-in manual, batch reset, laporan admin.
- Penjualan paket, job penandaan kedaluwarsa, UI/PWA.
- Endpoint member lain di luar profil, riwayat, dan scan.

## Slice

1. **Scan QR** (`POST /api/v1/attendance/scan-qr`)
   - Validasi input + header `Idempotency-Key`.
   - QR statis dibandingkan `hash_equals` terhadap `gym_settings.qr_payload`.
   - Geofence dibaca dari DB: `accuracy > geofence_accuracy_max_m` → 422; `distance > radius + accuracy` → 403.
   - Sesi terbuka → 409 `SESSION_ACTIVE` + `checkin_time`, `locker_number`; scan ulang < `rescan_guard_seconds` setelah sesi ditutup → 429 `RESCAN_GUARD`.
   - Transaksi + `SELECT ... FOR UPDATE` pada baris absensi user + lock Redis `checkin:{user_id}` TTL 10 detik. Redis mati → lanjut dengan lock DB (PRD §8.7).
   - Membership dipilih saat request: `single_visit` aktif hari ini, lalu `monthly_unlimited` dengan `end_date` terdekat. `effective_status` dihitung, bukan kolom `status`.
   - Day Pass: kuota dikurangi di transaksi yang sama (`WHERE remaining_visits > 0`); 0 baris terpengaruh → rollback + 403. Kuota menjadi 0 → `status='expired'`.
   - Penolakan membership / QR / geofence menulis `audit_logs` `attendance.scan_rejected` tanpa baris absensi.
2. **Profil member** (`GET /api/v1/member/profile`)
   - `user` (termasuk `phone_masked`), `membership` (tipe, nama paket, `effective_status`, `remaining_days`/`remaining_visits`, `end_date`), `active_session` (+ nomor loker).
   - `remaining_days` dihitung dari tanggal Asia/Jakarta, bukan UTC.
3. **Riwayat kunjungan** (`GET /api/v1/member/attendance/history`)
   - 30 baris terakhir milik user yang login: `log_id`, `checkin_time`, `checkout_time`, `locker_number`.

## Peta file

| File | Perubahan |
|---|---|
| `app/Exceptions/ApiException.php` | factory kode PRD §7.3 untuk scan |
| `app/Http/Requests/ScanQrRequest.php` | validasi payload + header idempotensi |
| `app/Services/MembershipResolver.php` | seleksi membership + `effective_status` |
| `app/Services/CheckInService.php` | alur scan: QR, geofence, sesi, idempotensi, transaksi, audit |
| `app/Http/Controllers/Api/V1/AttendanceController.php` | endpoint scan |
| `app/Http/Controllers/Api/V1/MemberProfileController.php` | endpoint profil |
| `app/Http/Controllers/Api/V1/MemberAttendanceHistoryController.php` | endpoint riwayat |
| `app/Support/PhoneNumber.php` | `mask()` untuk `phone_masked` |
| `routes/api.php` | 3 route + `throttle:scan-qr` |
| `tests/Support/InteractsWithGym.php` | fixture gym, paket, membership, absensi |
| `tests/Feature/CheckInScanTest.php` | AT-01, 03–07, 10, 12 + kuota + waktu |
| `tests/Feature/MemberProfileTest.php` | profil, riwayat, isolasi data, zona waktu |

Tidak ada migrasi baru: `attendance_logs` sudah memuat `gps_accuracy_m`, `locker_state`,
`idempotency_key`, dan `uq_logs_idempotency`; `gym_settings` sudah memuat radius, batas akurasi,
guard, dan `qr_payload`.

## Acceptance criteria

- [x] AT-01 scan aktif → 200, tepat 1 baris, koordinat + akurasi tersimpan.
- [x] AT-03 jarak 500 m → 403 `GEO_OUT_OF_RANGE`, 0 baris.
- [x] AT-04 `accuracy` 300 m → 422 `GPS_ACCURACY_LOW`.
- [x] AT-05 QR acak → 400 `INVALID_QR`; QR benar lolos perbandingan constant-time.
- [x] AT-06 sesi terbuka → 409 + jam masuk + nomor loker.
- [x] AT-07 `Idempotency-Key` sama → 1 baris, respons identik; kuota tidak berkurang dua kali.
- [x] AT-10 member expired → 403, 0 baris, entri `audit_logs`.
- [x] AT-12 Day Pass hari pertama 200, hari kedua 403; kuota 1 habis sekali; check-in gagal tidak mengurangi kuota.
- [x] Batas `distance == radius + accuracy` lolos; 0,01 m di atasnya ditolak.
- [x] Waktu tersimpan UTC dan respons ISO 8601 `Z`; `remaining_days` memakai tanggal Jakarta (kasus 00:30 WIB setelah tengah malam).
- [x] Member hanya bisa membaca profil dan riwayat miliknya.

## Verifikasi

| Perintah | Hasil |
|---|---|
| `php artisan test` | 73 passed (563 assertions), 4,70 s |
| `vendor\bin\pint --test` | PASS 71 files |
| `vendor\bin\phpstan analyse --memory-limit=512M --no-progress` | `[OK] No errors` |
| `npm run build` | built in 827 ms |
| `php artisan route:list --path=api` | 9 routes |

## Risiko & keputusan

- Idempotensi menyimpan hasil pada baris absensi; replay setelah 10 menit tetap mengembalikan baris yang sama karena indeks unik `(user_id, idempotency_key)` tidak mengenal kedaluwarsa. Batas 10 menit hanya memengaruhi kapan kunci boleh dipakai ulang; reuse menghasilkan respons lama, bukan baris baru. Ini disengaja agar tidak ada 500 akibat unique constraint. Replay tetap melewati validasi QR dan geofence; retry dengan payload sah mengembalikan baris lama.
- Jarak geofence dibulatkan 2 desimal (sentimeter) agar batas `radius + accuracy` stabil; toleransi 0,01 m sesuai PRD §8.1.
- Lock Redis bersifat tambahan; jika Redis tidak tersedia check-in tetap berjalan dengan lock baris DB (PRD §8.7).
- AT-11 (check-in manual) belum dapat diuji: endpoint baru ada di M3.
- Data `locker_number` pada sesi aktif berasal dari join `lockers`; pada M2 belum ada penulisan `locker_id`.
