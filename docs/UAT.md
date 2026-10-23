# UAT GymPass QR — Phase 1

Checklist uji terima. Isi kolom bukti dengan output nyata, bukan asumsi.

## Prasyarat

1. MySQL, Redis, Reverb hidup.
2. `php artisan migrate --force`.
3. `php artisan storage:link`.
4. `php artisan reverb:start` di terminal terpisah.
5. `npm run build`.
6. Buat akun uji: 1 admin, 1 kasir, 1 member aktif, 1 member expired, 1 Day Pass.
7. Catat QR payload dari `gym_settings`.

## Lokasi gym

- Plus Code: J7MG+73, Tj. Senang, Kota Bandar Lampung, Lampung.
- Koordinat tersimpan: -5.36681250, 105.27518750 (pusat sel Plus Code, akurasi ±14 m).
- geofence_radius_m masih 30 m, belum dikalibrasi di lokasi.

## Acceptance Test

| ID | Langkah | Hasil diharapkan | Bukti |
|---|---|---|---|
| AT-01 | Member aktif scan QR di meja kasir | 200, satu baris `attendance_logs`, kartu muncul di dasbor < 500 ms | |
| AT-02 | Scan memakai foto QR dari dalam gym | Diterima (risiko §5.3), tetap satu sesi | |
| AT-03 | Scan dari 500 m | 403 `GEO_OUT_OF_RANGE`, tanpa baris baru | |
| AT-04 | Scan dengan accuracy 300 m | 422 `GPS_ACCURACY_LOW` | |
| AT-05 | Scan QR acak | 400 `INVALID_QR` | |
| AT-06 | Scan saat sesi masih terbuka | 409 `SESSION_ACTIVE` + info sesi | |
| AT-07 | Dua request dengan `Idempotency-Key` sama | Satu baris, respons identik | |
| AT-08 | Dua kasir assign loker #05 bersamaan | Satu 200, satu 409 `LOCKER_TAKEN`, satu pemilik | |
| AT-09 | Kembalikan kunci | Loker `available`, `checkout_time` terisi, member lihat perubahan | |
| AT-10 | Member expired scan | 403, tanpa baris absensi, muncul di scan ditolak + audit | |
| AT-11 | Check-in manual | Koordinat NULL, `manual_reason` terisi, audit ada | |
| AT-12 | Day Pass dipakai dua hari | Hari 1 200, hari 2 403 `MEMBERSHIP_EXPIRED` | |
| AT-13 | Kasir offline 10 menit, member terus scan | Check-in tersimpan, sesi muncul setelah reconnect | |
| AT-14 | Batch reset dengan jumlah salah | 400, data tidak berubah | |
| AT-15 | Regenerasi QR | Payload lama 400 `INVALID_QR`, payload baru 200 | |

## Uji tambahan

- Assign loker tanpa sesi terpilih: tombol loker tidak aktif.
- Tutup gym dengan jumlah benar: sesi `auto_close`, loker `available`, audit `locker.batch_reset`.
- Mode gelap: token berubah, kontras teks tetap terbaca.
- Refresh token member: diamkan 16 menit, lakukan aksi; UI tetap jalan tanpa login ulang.
- Kasir nonaktif tidak bisa login.
- Admin wajib TOTP.

## Uji beban

```
php artisan load:prepare --members=50 --lockers=50
$env:TOKENS = (Get-Content storage/app/load-tokens.txt) -join ','
$env:QR_PAYLOAD = "<qr_payload>"
k6 run tests/load/scan.js
```

Target: p95 `scan-qr` ≤ 1500 ms, error rate < 2%.

## Bukti yang disimpan

- Screenshot dasbor kasir saat ada kedatangan dan loker terisi.
- Output `php artisan test`.
- Output k6 ringkas (p95, error rate).
- Catatan hasil ukur GPS di lokasi gym (untuk kalibrasi radius).

## Setelah UAT

- Kalibrasi `geofence_radius_m` dari hasil ukur.
- Isi Q1–Q5 di PRD.
- Siapkan backup harian + uji restore.
