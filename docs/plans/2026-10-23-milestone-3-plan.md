# Rencana Milestone 3 — Loker, check-in manual, feed

Dipecah jadi 4 sub-task. Satu sub-task = satu Cline task. Jangan gabung.

Status awal: M2 selesai (73 test, commit `76e0244`, tag `m2`).

## 3A — Perbaikan M2 (kecil, kerjakan dulu)

1. Pindahkan cek idempotensi ke **sebelum** validasi QR dan geofence. Key sama dalam 10 menit → kembalikan respons sukses tersimpan tanpa validasi ulang.
2. Test konkurensi nyata: dua proses paralel dengan `Idempotency-Key` sama → tepat satu baris `attendance_logs`. Gunakan dua koneksi DB terpisah atau `Process::start`.
3. Test replay: request kedua membawa koordinat/QR berbeda, tetap 200 dengan respons identik.

Exit: `php artisan test` hijau, termasuk test konkurensi.

## 3B — Assign, return, no-locker, verify, daftar sesi

Endpoint (guard `staff`, role `receptionist|admin`):

| Method | Path |
|---|---|
| PATCH | `/api/v1/receptionist/attendance/{log_id}/assign-locker` |
| PATCH | `/api/v1/receptionist/attendance/{log_id}/release-locker` |
| PATCH | `/api/v1/receptionist/attendance/{log_id}/no-locker` |
| PATCH | `/api/v1/receptionist/attendance/{log_id}/verify-identity` |
| GET | `/api/v1/receptionist/lockers` |
| GET | `/api/v1/receptionist/sessions` |

Aturan:

- Assign: transaksi → `SELECT sesi FOR UPDATE` → validasi `locker_state IN ('pending','none')` + sesi terbuka → `UPDATE lockers SET status='occupied' WHERE locker_id=? AND status='available'` → `affected_rows = 0` berarti rollback + `409 LOCKER_TAKEN`. Sudah punya loker → `409 LOCKER_ALREADY_ASSIGNED`. Sesi tertutup → `409 SESSION_CLOSED`. Set `locker_state='assigned'`, `assigned_by_staff_id`, `assigned_at`. Audit `locker.assigned`.
- Return: idempoten. Klik kedua → 200 `already_returned: true`, timestamp pertama tidak ditimpa. Set `checkout_time`, `checkout_type='locker_returned'`, `locker_state='returned'`, `lockers.status='available'`. Audit `locker.returned`.
- No-locker: `pending` → `none`, catat `assigned_by_staff_id`, audit.
- Verify identity: simpan `identity_verified_by` + `identity_verified_at`, audit.
- Sessions: filter `date`, `updated_since`, `filter` = `all|need_locker|assigned|no_locker|unverified`.

Test wajib: AT-08 (dua assign paralel → satu 200, satu 409), AT-09, sesi tertutup tidak bisa assign, member ditolak 403.

## 3C — Check-in manual + pencarian member + rejected attempts

| Method | Path |
|---|---|
| POST | `/api/v1/receptionist/manual-checkin` |
| GET | `/api/v1/receptionist/members?q=` |
| GET | `/api/v1/receptionist/rejected-attempts?date=` |

Aturan:

- Manual check-in: `Idempotency-Key` wajib. Payload `user_id`, `membership_id`, `locker_intent`, `reason` enum (`no_phone|battery_dead|camera_broken|gps_issue|other`), `note`. Koordinat NULL, `checkin_method='manual_receptionist'`. Validasi membership tetap berlaku; tanpa membership aktif → 403. Audit `attendance.manual_checkin`.
- Members: minimal 3 karakter, cari nama atau nomor HP, tanpa user soft-deleted.
- Rejected attempts: baca `audit_logs` `attendance.scan_rejected` per tanggal.

Test wajib: AT-11, pencarian < 3 karakter ditolak, user soft-deleted tidak muncul.

## 3D — Feed real-time (TERTUNDA: butuh keputusan infra)

Blocker: PHP 8.3.16 di mesin ini tidak punya `pcntl`, `sockets`, atau `posix`. Laravel Reverb belum terpasang. `npm run build` tidak terpengaruh.

Pilihan:

| Opsi | Isi | Konsekuensi |
|---|---|---|
| A | Jalankan Reverb di WSL/Docker | Paling dekat dengan PRD §7.8 |
| B | Soketi via Node (sudah ada Node 22) | Alternatif, protokol Pusher |
| C | Tunda WebSocket, pakai polling 10 detik | Menyimpang dari K5 (p95 ≤ 500 ms), catat sebagai deviasi |

Jangan mulai 3D sebelum owner memilih.

## Urutan

```
3A → test hijau → commit → 3B → test hijau → commit → 3C → test hijau → commit → [keputusan infra] → 3D
```
