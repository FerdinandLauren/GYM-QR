# M0.5 — Hardening Fondasi (tanpa fitur bisnis)

Tanggal: 2026-10-23

## Tujuan

Menyiapkan fondasi teknis sebelum M1: Redis via predis, model `User` sesuai PRD §6.1,
routing `/api/v1`, envelope error PRD §7.2/§7.3, seam OTP, audit logger, rate limiter,
seeder lokal, dan pipeline CI.

## Non-goals

- Tidak ada endpoint bisnis, QR, geofence, atau UI.
- Tidak install Sanctum; autentikasi staf/member dikerjakan di M1.
- Tidak mengubah PRD atau design spec.

## Keputusan owner

- Opsi A: `REDIS_CLIENT=predis` (ekstensi phpredis tidak tersedia di PHP CLI Laragon).

## Acceptance criteria

1. `composer.json`: predis, firebase/php-jwt, pragmarx/google2fa di `require`; larastan di `require-dev`.
2. `.env.example` memakai predis, session/cache/queue Redis, disk `public`, DB `gympass_qr`.
3. Database `gympass_qr_test` ada dan `gympass_qr_app` punya akses penuh.
4. `App\Models\User` hanya berisi kolom PRD (tanpa email/password/remember_token), soft delete aktif.
5. Semua error pada `/api/*` memakai envelope §7.2 dengan kode §7.3.
6. `OtpSender` ter-bind ke `LogOtpSender`; kode OTP tidak pernah ditulis ke log.
7. `AuditLogger::log(actor, action, entity, before, after)` menulis `audit_logs`.
8. Enam named rate limiter terdaftar dan aktif di route.
9. Seeder idempoten: 1 `gym_settings`, 2 `packages`, 5 `lockers`.
10. `pint.json`, `phpstan.neon`, script composer `test`/`lint`/`analyse`, CI GitHub Actions.

## Peta file

| File | Perubahan |
|---|---|
| `app/Models/User.php` | kolom PRD, soft delete, `password_hash` sebagai auth password |
| `database/factories/UserFactory.php` | data sesuai kolom baru |
| `database/seeders/DatabaseSeeder.php` | gym settings, paket, loker |
| `bootstrap/app.php` | routing API + renderer error |
| `app/Exceptions/ApiErrorRenderer.php` | envelope gagal §7.2 |
| `routes/api.php` | grup prefix `v1` |
| `app/Support/*` | `OtpSender`, `LogOtpSender`, `AuditLogger` |
| `app/Providers/AppServiceProvider.php` | binding + named limiter |
| `tests/Feature/*` | envelope, limiter, factory, audit |
| `phpstan.neon`, `pint.json`, `composer.json`, `.github/workflows/ci.yml` | tooling |

## Risiko

- Koordinat, jam tutup, dan jumlah loker pada seeder masih placeholder sampai Q4/Q5 dijawab owner.
- `composer audit` melaporkan advisory pada `laravel/framework` 11.x; upgrade framework di luar scope M0.5.
- Rate limit `otp-verify` (10/menit per nomor+IP) adalah turunan dari batas 5 percobaan per kode; angka final menunggu Q2.
- `FILESYSTEM_DISK=public` dipilih sesuai instruksi; Q3 (akses foto) belum diputuskan.
