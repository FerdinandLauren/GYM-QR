# Deploy & Operasional — GymPass QR

## 1. Prasyarat server

- PHP 8.3 dengan ekstensi: pdo_mysql, mbstring, openssl, sodium, gd, fileinfo, zip, intl.
- Composer 2.
- MySQL 8.0+.
- Redis 5+.
- Node 22 untuk build aset.
- HTTPS wajib (kamera dan geolocation).

## 2. Environment produksi

Isi `.env` produksi. Jangan commit.

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://gym.example.id
APP_TIMEZONE=UTC
DB_CONNECTION=mysql
DB_DATABASE=gympass_qr
SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=true
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=predis
BROADCAST_CONNECTION=reverb
MAIL_MAILER=smtp
```

Generate kunci:

```
php artisan key:generate
php artisan storage:link
```

Keypair JWT harus ada di `storage/app/keys/jwt-private.pem` dan `jwt-public.pem`. Jangan commit.

## 3. Deploy

```
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Rollback kode: deploy commit sebelumnya, lalu `php artisan optimize:clear`. Migrasi tidak otomatis dibalik; siapkan migrasi mundur manual bila perlu.

## 4. Proses yang harus hidup

| Proses | Perintah |
|---|---|
| Web | PHP-FPM / web server |
| Reverb | `php artisan reverb:start --host=0.0.0.0 --port=8080` |
| Queue | `php artisan queue:work redis --tries=3 --timeout=90` |
| Scheduler | `php artisan schedule:work` (atau cron `* * * * * php artisan schedule:run`) |

Di Windows, pakai NSSM atau Task Scheduler agar Reverb, queue, dan scheduler hidup setelah reboot.

## 5. Akun pertama

```
php artisan app:create-admin 081222333444 --name="Owner"
```

Simpan password sementara dan secret TOTP. Login admin wajib TOTP.

## 6. Backup

Database harian penuh:

```powershell
$stamp = Get-Date -Format "yyyyMMdd-HHmm"
& "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe" `
  --user=gympass_qr_app --password=$env:DB_PASSWORD `
  --single-transaction --no-tablespaces --routines --events gympass_qr `
  | Out-File -Encoding utf8 "C:\backups\gympass-$stamp.sql"
```

Binlog aktif untuk RPO ≤ 15 menit. Simpan salinan di mesin/lokasi berbeda.

File foto member: backup folder `storage/app/public/members`.

## 7. Restore drill

1. Buat DB kosong `gympass_qr_restore`.
2. `mysql -u root gympass_qr_restore < gympass-YYYYMMDD-HHmm.sql`.
3. Arahkan `.env` sementara ke DB restore.
4. Jalankan `php artisan migrate:status` dan `php artisan test`.
5. Cek login, scan, assign loker.
6. Kembalikan `.env`.

Target PRD: RPO ≤ 15 menit, RTO ≤ 4 jam. Uji minimal sekali sebelum go-live dan tiap kuartal.

## 8. Monitoring minimum

- Alert error rate `scan-qr` > 2% selama 5 menit.
- Alert Reverb putus > 3 menit.
- Alert job `memberships:expire` gagal.
- Pantau jumlah sesi terbuka, loker `occupied`, dan lag queue.
- Log JSON dengan `request_id`; jangan log OTP, token, atau nomor HP penuh.

## 9. Setelah go-live

- Isi Q1–Q5 PRD.
- Kalibrasi `geofence_radius_m` saat pengukuran lokasi tersedia.
- Review audit scan ditolak mingguan.
- Rotasi secret TOTP admin bila ada pergantian staf.
