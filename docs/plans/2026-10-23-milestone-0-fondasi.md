# Milestone 0 — Fondasi

Tanggal: 2026-10-23
Status: selesai

## Tujuan

Menyiapkan kerangka proyek tanpa fitur: Laravel 11, koneksi MySQL 8.4.3 + Redis,
9 tabel PRD §6.1, Tailwind dengan token desain, dan dua test fondasi.

## Non-goals

- Tidak ada autentikasi, scan QR, geofence, loker, atau endpoint API.
- Tidak ada model Eloquent bisnis, service, atau job.
- Tidak ada perubahan pada `PRD-GymPass-QR-v1.2.md` dan `docs/GymPass-QR-Design-Spec.md`.

## Acceptance criteria

1. `php artisan migrate` membuat 9 tabel PRD §6.1 pada database `gympass_qr`.
2. `.env` memakai MySQL `gympass_qr`, Redis `127.0.0.1:6379`, `APP_TIMEZONE=UTC`.
3. `resources/css/app.css` memuat token design spec §2 sebagai CSS variables.
4. `php artisan test` lulus, termasuk test yang membuktikan `chk_gym_single_row`
   menolak baris kedua.

## Peta file

- `database/migrations/2026_10_23_000001..000009_*.php` — DDL PRD §6.1.
- `resources/css/app.css` — Tailwind + token §2.
- `vite.config.js` — plugin `@tailwindcss/vite`.
- `tests/Feature/HealthCheckTest.php` — health check `/up`.
- `tests/Feature/GymSettingsSingleRowTest.php` — constraint `chk_gym_single_row`.
- `phpunit.xml` — test memakai database `gympass_qr_test`.

## Risiko

- `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`
  masih default Laravel, sementara PRD hanya mendefinisikan 9 tabel. Perlu keputusan
  owner: pakai Redis atau tambah tabel infrastruktur.
- Ekstensi `redis` belum aktif di PHP CLI Laragon, sehingga `REDIS_CLIENT=phpredis`
  belum bisa dipakai sampai ekstensi diaktifkan.
- `App\Models\User` dan `database/factories/UserFactory.php` masih versi default
  Laravel (email/password), belum dipetakan ke skema `users` PRD.
