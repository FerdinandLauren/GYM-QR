# M1 — Auth staf & member (FR-CAS-12, FR-MEM-01, FR-MEM-10)

Tanggal: 2026-10-23
Status: disetujui owner lewat instruksi langsung (D10, PRD §5.3, §7.5–§7.6).

## Hasil yang terlihat user

- Kasir/admin login dengan nomor HP + password (admin juga TOTP) dan mendapat sesi 8 jam
  di Redis; cookie `HttpOnly; Secure; SameSite=Strict`.
- Member login dengan nomor WhatsApp + OTP 6 digit dan mendapat access JWT RS256 15 menit
  plus refresh token opaque 30 hari yang bisa dirotasi dan dicabut.
- Endpoint terlindungi menolak role yang salah dengan `403 FORBIDDEN_ROLE`.

## Non-goals

Tidak ada UI, QR/geofence, check-in, Sanctum, atau perubahan PRD/design spec.

## Keputusan teknis

| Topik | Keputusan |
|---|---|
| OTP sender | `LogOtpSender` sampai Q2 dijawab (tidak menulis kode ke log) |
| JWT | RS256, keypair di `storage/app/keys/`, path dari env |
| Access TTL | 900 detik; refresh TTL 2 592 000 detik (30 hari) |
| Refresh | opaque 32 byte, disimpan `sha256` hex di `refresh_tokens.token_hash`, rotasi saat dipakai |
| Guard | `staff` (session, provider `users`), `member` (driver `jwt` kustom) |
| Rate limit | `staff-login` 5/15 menit per akun + per IP; `otp-request` 5/jam per nomor + 20/jam per IP (sudah ada di `AppServiceProvider`) |
| Session | `SESSION_DRIVER=redis`, `SESSION_LIFETIME=480`, cookie ketat via env |
| Error code | `MEMBER_NOT_FOUND`, `OTP_INVALID`, `OTP_EXPIRED`, `TOKEN_EXPIRED`, `FORBIDDEN_ROLE` (PRD §7.3) |

## Slice

1. **Config & kunci** — generate keypair, `.env`/`.env.example`, `.gitignore`, `config/auth.php`.
2. **Member OTP** — `POST /api/v1/auth/request-otp` + `verify-otp`; test hash, expiry, 5 percobaan,
   rate limit, respons seragam.
3. **Token member** — `POST /api/v1/auth/refresh` dan `/auth/logout`; test rotasi, pencabutan,
   signature invalid, alg bukan RS256, access expired.
4. **Staf** — `POST /api/v1/receptionist/auth/login` dan `/logout`; test password, rate limit,
   admin tanpa TOTP, cookie, sesi Redis.
5. **Middleware role** — `role:receptionist,admin` dan `role:admin`; test penolakan role.

## Peta file

Dibuat: `app/Auth/JwtGuard.php`, `app/Support/JwtService.php`, `app/Support/PhoneNumber.php`,
`app/Support/ApiResponse.php`, `app/Exceptions/ApiException.php`,
`app/Services/MemberAuthService.php`, `app/Http/Middleware/EnsureRole.php`,
`app/Http/Controllers/Api/V1/{MemberAuthController,StaffAuthController}.php`,
`app/Http/Requests/{RequestOtpRequest,VerifyOtpRequest,RefreshTokenRequest,StaffLoginRequest}.php`,
test baru di `tests/Feature/`, `tests/Unit/`.

Diubah: `config/auth.php`, `bootstrap/app.php`, `app/Exceptions/ApiErrorRenderer.php`,
`app/Providers/AppServiceProvider.php`, `routes/api.php`, `.env`, `.env.example`, `.gitignore`,
`phpunit.xml`, `.github/workflows/ci.yml`.

## Acceptance criteria

- OTP disimpan sebagai hash; kedaluwarsa 5 menit; 5 percobaan; 5/jam nomor dan 20/jam IP;
  respons `request-otp` identik untuk nomor terdaftar dan tidak.
- `verify-otp` nomor tidak terdaftar → 404 `MEMBER_NOT_FOUND`.
- Access token kedaluwarsa → 401 `TOKEN_EXPIRED`; signature salah atau alg bukan RS256 → 401.
- Refresh rotasi: token lama mati, token baru hidup; logout mencabut token.
- Staff login 200 hanya dengan password benar; admin tanpa TOTP ditolak; 6 percobaan/15 menit → 429.
- Cookie sesi staf `HttpOnly`, `Secure`, `SameSite=Strict`; sesi tersimpan di Redis, bukan tabel.

## Risiko

- Kunci JWT lokal harus ada di setiap environment; CI membuatnya saat build (tidak di-commit).
- `SESSION_SECURE_COOKIE=false` hanya untuk dev lokal via HTTP; `.env.example` tetap `true`.
- Deviasi skema: `users.totp_secret` dilebarkan ke `VARCHAR(512)` (migrasi 000010) karena
  cast `encrypted` menghasilkan ciphertext 256 karakter, melebihi `VARCHAR(255)` PRD §6.1.
  Perubahan hanya menambah lebar kolom, tanpa mengubah tipe atau menghapus data.
- Migrasi 000010 belum dijalankan di database lokal; jalankan `php artisan migrate` sebelum
  mencoba login admin dengan TOTP.
