# GYM-QR

GymPass QR — absensi gym dan manajemen loker (Laravel 11 + MySQL 8 + Redis).

## Dokumen

- `PRD-GymPass-QR-v1.2.md` — product requirements, sumber kebenaran.
- `docs/GymPass-QR-Design-Spec.md` — design spec (token, layout, komponen).
- `AGENTS.md` — aturan kerja agent, invariant domain, alur milestone.

## Status

M0 selesai: scaffold Laravel 11, 9 migrasi PRD §6.1, Tailwind + token desain, 2 test.
Berikutnya: M1 auth staf + OTP member.

## Perintah

```
php artisan migrate
php artisan test
vendor\bin\pint --test
npm run build
```

PHP 8.3: `C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe`
