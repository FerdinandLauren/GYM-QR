# AGENTS.md — GymPass QR

Panduan operasi untuk agent coding di repo ini. Wajib dibaca sebelum menyentuh kode.

## 0. Status proyek & sumber kebenaran

- Produk: GymPass QR — absensi gym + manajemen loker, satu lokasi, Phase 1.
- Stack: PHP 8.3 / Laravel 11 / MySQL 8.4.3 / Redis / Laravel Reverb.
- Status PRD: **Draft for Review** (belum Development Ready). Decision Log §0.2 adalah keputusan yang mengikat; bagian lain bisa berubah setelah sign-off owner.
- Remote: `https://github.com/FerdinandLauren/GYM-QR.git` (branch `main`).
- Status: M0 selesai (scaffold Laravel 11, 9 migrasi PRD §6.1, Tailwind + token desain, 2 test). Milestone berikutnya: M1 auth.
- Dokumen di repo: `PRD-GymPass-QR-v1.2.md`, `docs/GymPass-QR-Design-Spec.md`, `AGENTS.md`.

Urutan otoritas saat ada konflik:

```
instruksi owner > PRD v1.2 (§0.2 Decision Log) > docs/GymPass-QR-Design-Spec.md > AGENTS.md > skill > default framework
```

Jangan mengubah PRD atau design spec tanpa diminta. Jika implementasi tidak mungkin mengikuti PRD, berhenti dan tanyakan.

## 1. Mulai sesi: skill dulu, baru kode

Cline/harness tidak punya tool skill otomatis. Baca file SKILL.md secara eksplisit.

| Kondisi | Skill yang wajib dibaca |
|---|---|
| Setiap sesi | `C:\Users\ASUS\.agents\skills\using-superpowers\SKILL.md` |
| Fitur/ide baru | `brainstorming\SKILL.md`, lalu `writing-plans\SKILL.md` |
| Tugas multi-langkah | `optimal-coding-workflow\SKILL.md` |
| Implementasi | `test-driven-development\SKILL.md` |
| Bug / test gagal | `systematic-debugging\SKILL.md` |
| Semua perubahan kode | `ponytail\SKILL.md` (mode `full`) |
| Gaya jawaban | `caveman\SKILL.md` |
| Review | `requesting-code-review\SKILL.md` atau `ponytail-review\SKILL.md` |
| Desain modul/refactor | `codebase-design\SKILL.md` |
| Sebelum klaim selesai | `verification-before-completion\SKILL.md` |
| Selesai branch | `finishing-a-development-branch\SKILL.md` |
| Isolasi kerja | `using-git-worktrees\SKILL.md` |
| UI web | `web-design-guidelines\SKILL.md`, `frontend-design\SKILL.md` |
| Mobile screen | `design-mobile-apps\SKILL.md` |
| Tes browser | `webapp-testing\SKILL.md` atau `agent-browser\SKILL.md` |
| Bikin skill | `writing-skills\SKILL.md` |

Aturan:

- Umumkan satu baris: `Using [skill] to [purpose]`.
- Proses skill dulu, skill implementasi kemudian.
- Skill tidak tersedia? Sebutkan satu baris, lanjut dengan prinsip yang sama.
- Output chat: ringkas (caveman). File, kode, komentar, commit, dan dokumen: prosa normal dan lengkap.

RTK (opsional, prompt-level di Cline):

- Jika `rtk gain` merespons, awali command: `rtk git status`, `rtk php artisan test`, `rtk read <file>`.
- Butuh output mentah penuh: `rtk bypass <cmd>`.
- Jangan install RTK tanpa izin. Jangan pakai RTK untuk menyembunyikan kegagalan test.

## 2. Environment

| Item | Nilai |
|---|---|
| Workspace | `C:\laragon\www\GYM QR` |
| PHP | `C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe` |
| Composer | `C:\laragon\bin\composer\composer.bat` |
| MySQL | `C:\laragon\bin\mysql\mysql-8.4.3-winx64` |
| Node | `C:\laragon\bin\nodejs\node-v22` |
| OS | Windows 10/11, shell PowerShell 5.1 |

`php` dan `composer` tidak ada di PATH shell ini. Pakai path absolut di atas atau terminal Laragon.

Frontend (provisional, menunggu keputusan owner): **Blade + Tailwind CSS + Alpine.js + Laravel Echo**. Design spec §10 mengusulkan React/shadcn, tetapi itu usulan, bukan keputusan. Jangan scaffold Inertia/React sebelum owner memilih.

## 3. Perintah

Setelah scaffold Laravel:

```
php artisan test
php artisan test --filter=<TestName>
vendor\bin\pint
vendor\bin\phpstan analyse
php artisan migrate
php artisan reverb:start
npm run dev
npm run build
```

- Gunakan test runner yang dipasang scaffold. Jangan menambah Pest/PHPUnit kedua.
- Jangan mengarang command. Baca `composer.json`, `package.json`, README.
- Semua klaim "selesai" harus disertai output command.

## 4. Arsitektur & konvensi

- Ikuti struktur Laravel 11 default: Controller tipis, FormRequest untuk validasi, Service/Action untuk logika domain, Policy untuk otorisasi, Resource untuk respons API.
- Satu konsep domain = satu tempat. Jangan menduplikasi aturan membership atau loker di controller dan job.
- Jangan menambah package, layer, repository pattern, atau event bus tanpa kebutuhan nyata dan persetujuan.
- Semua waktu di database **UTC** (`config('app.timezone') = 'UTC'`); konversi ke `Asia/Jakarta` hanya di presentasi/laporan.
- Uang: `DECIMAL(12,2)`, jangan float.
- Nomor HP disimpan E.164 tanpa `+` (contoh `628123456789`); normalisasi input `08...`.
- Respons API selalu memakai envelope PRD §7.2 dan kode error §7.3. Jangan membuat format baru.
- Migrasi harus kompatibel mundur (PRD §5.2): tidak ada `DROP` destruktif tanpa rencana.
- Kode dan identifier: Inggris. Teks UI, pesan error, dan dokumen: Indonesia.

## 5. Invariant domain — jangan dilanggar

1. **QR statis.** `gym_settings.qr_payload` dibandingkan constant-time. Tidak ada token dinamis per request. Regenerasi QR membuat payload lama langsung invalid.
2. **Geofence.** `accuracy > geofence_accuracy_max_m` → `422 GPS_ACCURACY_LOW`. `distance - accuracy > geofence_radius_m` → `403 GEO_OUT_OF_RANGE`. Lolos jika `distance <= radius + accuracy`. Radius dibaca dari DB, bukan konstanta.
3. **Satu sesi terbuka per member.** Scan saat ada sesi `checkout_time IS NULL` → `409 SESSION_ACTIVE`. Guard re-scan 5 menit → `429 RESCAN_GUARD`. Keputusan memakai jam server.
4. **Idempotensi.** `Idempotency-Key` wajib untuk `scan-qr` dan `manual-checkin`. Key sama dalam 10 menit mengembalikan respons sukses yang sama tanpa baris baru.
5. **Check-in atomic.** Transaksi + `SELECT ... FOR UPDATE` pada baris absensi user + lock Redis `checkin:{user_id}` TTL 10 detik.
6. **Assign loker atomic.** Kunci baris sesi, lalu `UPDATE lockers SET status='occupied' WHERE locker_id=? AND status='available'`. `affected_rows = 0` → rollback + `409 LOCKER_TAKEN`. Sesi yang sudah punya loker → `409 LOCKER_ALREADY_ASSIGNED`.
7. **Kembalikan kunci idempoten.** Klik kedua → 200 `already_returned: true`, timestamp pertama tidak ditimpa.
8. **Membership.** `effective_status` dihitung saat request (cancelled/suspended menang; lalu expired; selain itu active). Kolom `status` hanya hasil job, bukan sumber keputusan check-in.
9. **Prioritas membership.** `single_visit` aktif hari ini → `monthly_unlimited` dengan `end_date` terdekat → `403 MEMBERSHIP_EXPIRED`.
10. **Kuota Day Pass.** Kurangi di transaksi check-in yang sama: `UPDATE ... SET remaining_visits = remaining_visits - 1 WHERE remaining_visits > 0`. `affected_rows = 0` → batalkan check-in. Kuota 0 → `expired`.
11. **Member expired ditolak.** Tidak membuat `attendance_logs`; tulis `audit_logs` + event `attendance.rejected` ke kasir.
12. **Loker tidak pernah dihapus.** Hanya `available` / `occupied` / `out_of_service`.
13. **User soft delete.** Nomor HP tetap unik termasuk user terhapus. Riwayat absensi dipertahankan.
14. **Audit wajib** untuk: assign/return loker, batch reset, check-in manual, scan ditolak, konfirmasi identitas, perubahan membership, perubahan gym settings, perubahan loker.
15. **Batch reset** hanya manual oleh kasir, dengan konfirmasi jumlah. Tidak ada auto-close tanpa aksi kasir.

## 6. Keamanan & privasi

- HTTPS wajib. Token member: JWT access 15 menit (RS256) + refresh opaque 30 hari (hash, rotasi, bisa dicabut). Staf: sesi server-side 8 jam, cookie `HttpOnly; Secure; SameSite=Strict`.
- Password staf bcrypt cost 12; admin wajib TOTP. Member login OTP WhatsApp.
- OTP: 6 digit, disimpan sebagai hash, kedaluwarsa 5 menit, maks 5 percobaan, kirim ulang 60 detik, maks 5/nomor/jam dan 20/IP/jam.
- Rate limit: `scan-qr` 10/menit/token; `assign-locker` 30/menit/kasir; `manual-checkin` 60/jam/kasir.
- Setiap endpoint memeriksa role + kepemilikan resource. Member hanya membaca datanya sendiri.
- Jangan pernah log OTP, token, atau nomor HP penuh. Mask `08****1234`. Jangan log payload QR.
- Secret hanya di `.env`/secret manager. `.env` tidak masuk repo.
- Retensi: GPS 90 hari, absensi 24 bulan, audit 12 bulan, OTP dihapus 24 jam setelah kedaluwarsa, log aplikasi 30 hari.
- Consent dicatat di `users.consent_at` + `consent_version` saat pendaftaran.

## 7. UI/UX

Token desain dari design spec (pakai persis, jangan mengarang warna baru):

```
--concrete #E9ECEE   --surface #FFFFFF   --ink #172433   --ink-muted #4B5A68
--steel #2F5F70      --plate #F4C20D     --go #14794A    --stop #C23A2B
--hold #B7791F       --line #C9D0D6
```

- Dark mode: `--concrete #0F1A24`, `--surface #16242F`, `--ink #E6EBEF`, `--steel #7FB3C4`, `--go #4CC38A`, `--stop #FF7A6B`.
- Font: `Big Shoulders Display` (judul, angka, nomor loker) + `Figtree` (UI). `font-display: swap`, subset Latin.
- Radius: kontrol 8px, panel 12px, pintu loker 4px, lencana pil penuh. Jarak kelipatan 4px.
- Gerak: 120ms / 220ms / 450ms, ease-out. Hormati `prefers-reduced-motion`.
- Status selalu warna + ikon + teks. Jangan hanya warna.
- Target sentuh minimal 44px (mobile) / 48px (tablet). Font input minimal 16px.
- Kontras minimal 4.5:1 (WCAG 2.1 AA). Fokus keyboard terlihat (3px `steel`).
- Mobile-first untuk member; kasir dirancang tablet landscape 1024px dulu.
- Pesan error: sebut masalah + langkah berikutnya, tanpa permintaan maaf kosong.
- Hindari: latar krem/terakota, hitam-neon, kartu seragam berbayang, label ALL-CAPS dekoratif, gradien hiasan.
- `--plate` hanya untuk nomor loker dan satu aksi utama per layar.

## 8. Testing

- TDD: test gagal dulu, lalu implementasi minimum, lalu refactor.
- Wajib automated test untuk semua yang menyentuh uang, kuota, sesi, dan status loker (PRD §9.2).
- Map ke acceptance test PRD: AT-01..AT-15. Minimal satu test per AT yang relevan dengan slice.
- Test konkurensi: dua assign loker paralel → tepat satu 200, satu 409.
- Test idempotensi: dua request dengan `Idempotency-Key` sama → satu baris.
- Test waktu: simpan UTC, tampilkan Asia/Jakarta; kasus tengah malam WIB.
- Test `effective_status`: cancelled/suspended/expired/active, Day Pass kuota 0.
- Test geofence: lolos, di luar radius, accuracy terlalu besar, koordinat kosong.
- Jangan menghapus atau melemahkan test untuk membuat CI hijau.

## 9. Alur kerja

1. Triage: fitur / bug / refactor / review. Baca PRD bagian terkait.
2. Pahami: acceptance criteria + non-goals ditulis dulu.
3. Rencana: vertical slice kecil, peta file, risiko. Simpan di `docs/plans/` bila > 30 menit.
4. Isolasi: branch/worktree; baseline test harus hijau sebelum perubahan.
5. Implementasi: satu slice, satu test, satu diff kecil.
6. Verifikasi: lint → typecheck/static analysis → test → build → smoke test manual.
7. Lapor: hasil, bukti, yang dilewati, risiko.

Jangan commit kecuali diminta. Jangan force-push. Jangan ubah config git.

## 9a. Git & milestone

Remote: `https://github.com/FerdinandLauren/GYM-QR.git`, branch `main`.

Sekali di awal repo (sudah dilakukan):

```
git init -b main
git remote add origin https://github.com/FerdinandLauren/GYM-QR.git
```

Aturan commit:

- **Satu commit per milestone.** Jangan campur dua milestone dalam satu commit.
- Sebelum commit: `git status` dan `git diff` untuk memastikan hanya file yang dimaksud yang masuk.
- Pesan commit: `feat(M1): auth staf + OTP member`, `fix(M2): geofence salah hitung`, `chore: baseline M0`.
- Tag titik stabil setelah milestone lolos verifikasi: `git tag m0`, `git tag m1`, lalu `git push origin main --tags`.
- Push ke `origin main` setelah user meminta. Jangan push tanpa diminta.
- Jangan pernah commit: `.env`, `.env.*` selain `.env.example`, `storage/*.key`, `vendor/`, `node_modules/`, `public/build`, dump DB, log, atau kredensial.
- `.gitignore` Laravel standar wajib memuat: `vendor/`, `node_modules/`, `.env`, `storage/*.key`, `public/build`, `.phpunit.result.cache`.
- Jangan menulis ulang history, jangan `--amend` commit yang sudah di-push, jangan `reset --hard` pada commit yang sudah di-push.

Alur per milestone:

1. Selesaikan implementasi + verifikasi (test, lint, build).
2. `git status` → `git diff` → review.
3. Commit dengan pesan berpola.
4. Push hanya bila user minta.
5. Tag bila milestone dinyatakan stabil.

Jika repo belum punya git, sebutkan dan minta izin sebelum `git init`.

## 10. Larangan

- Jangan implementasi payment gateway, smart locker, turnstile, multi-cabang, atau aplikasi native (Phase 1 out of scope).
- Jangan membuat antrean tulis offline.
- Jangan memberi check-in tanpa membership aktif (tidak ada override).
- Jangan membuat QR dinamis.
- Jangan hard delete loker atau user.
- Jangan menambah dependency tanpa persetujuan.
- Jangan menyimpan token di `localStorage` bila ada alternatif aman; ikuti PRD.
- Jangan menulis data pribadi ke log.
- Jangan mengklaim selesai tanpa output test.
- Jangan mengubah PRD/design spec diam-diam.
- Jangan mengisi bagian **[OPEN]** atau Q1–Q5 dengan asumsi.

## 11. Pertanyaan terbuka — tanyakan, jangan tebak

- Q1 baseline check-in manual; Q2 penyedia & biaya OTP; Q3 storage foto; Q4 jam tutup; Q5 jumlah/jenis loker.
- Design spec §12: perilaku member expired, QR statis/dinamis, status "belum memilih loker", FR membership, PWA vs native, mekanisme real-time member, jumlah cabang, login staf via OTP.
- Frontend: Blade/Alpine vs Inertia/React — belum diputuskan.

Jika blocked oleh pertanyaan ini: tulis asumsi di laporan, jangan diam-diam memilih.

## 12. Definisi selesai

- [ ] Acceptance criteria terpenuhi dan dapat diuji
- [ ] Test baru lulus, test lama tetap lulus
- [ ] Lint/static analysis bersih
- [ ] Build sukses
- [ ] Smoke test alur utama (member scan, kasir assign/return, admin laporan)
- [ ] Tidak ada invariant §5 yang dilanggar
- [ ] Tidak ada secret/PII di kode atau log
- [ ] Perubahan docs bila perilaku berubah
- [ ] Laporan: hasil, bukti command, yang dilewati, risiko
