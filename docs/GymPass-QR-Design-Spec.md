# GymPass QR: Design Specification

**Versi dokumen:** 1.0 (draf untuk review)
**Basis:** PRD GymPass QR v1.1.0 beserta temuan review sebelumnya
**Target perangkat:** Mobile (member), Tablet/PC (kasir), PC/Tablet/Mobile (admin)
**Bahasa antarmuka:** Indonesia

> **Catatan kejujuran.** Semua nilai warna, ukuran, dan rasio kontras di bawah ini adalah *usulan desain*, bukan hasil uji pengguna. Rasio kontras sudah saya hitung dengan rumus WCAG, tetapi tetap perlu dicek ulang di perangkat nyata (terutama layar HP dengan kecerahan rendah di ruang gym). Angka seperti ukuran target sentuh mengikuti pedoman umum (sepengetahuan saya sekitar 44 pt di Apple HIG dan 48 dp di Material); cek dokumen resmi masing-masing sebelum dijadikan acuan final. Keputusan yang masih menunggu jawaban dari PRD ditandai **[OPEN]**.

---

## 1. Arah Desain

### 1.1 Konsep: "Dinding Loker"

Produk ini hidup di dua tempat: layar HP member yang sempit dan meja kasir yang sibuk. Dunia visual yang dimiliki keduanya adalah **ruang ganti gym**: loker baja bercat, label nomor, lantai karet, lakban penanda warna kuning, papan angka di dinding.

Dari situ saya ambil satu elemen yang dibuat paling berkesan, dan sisanya sengaja dibuat tenang:

- **Elemen utama: dinding loker.** Pemilih loker di dasbor kasir tidak berupa daftar atau dropdown, melainkan *grid pintu loker* dengan label nomor. Pintu kosong tampak terang, pintu terisi tampak terisi, dan pintu rusak diberi tanda silang. Di sisi member, hasil check-in menampilkan **label nomor loker besar** seperti tag kunci.
- **Semua hal lain** (form, tabel, laporan) tetap datar, rapi, dan hemat dekorasi.

### 1.2 Prinsip

1. **Kasir tidak boleh berpikir.** Satu kedatangan, satu keputusan, maksimal dua ketukan.
2. **Status dibaca dari jauh.** Kasir melirik layar dari jarak lengan sambil bicara dengan member. Warna, ikon, dan teks status harus terbaca dalam satu detik.
3. **Warna tidak pernah jadi satu-satunya penanda.** Setiap status punya warna, ikon, dan kata.
4. **Ibu jari dulu.** Member memakai satu tangan, kadang sambil membawa tas. Semua aksi utama ada di bawah layar.
5. **Kegagalan memberi arah.** Pesan error menyebut apa yang salah dan apa langkah berikutnya.
6. **Satu momen gerak.** Animasi dipakai ketika ia menjelaskan perubahan (member masuk, loker terisi), bukan sebagai hiasan.

### 1.3 Yang sengaja dihindari

- Latar krem dengan aksen terakota, atau latar hitam dengan aksen hijau neon.
- Semua konten dipotong menjadi kartu seragam dengan bayangan abu yang sama.
- Label ALL-CAPS di atas setiap judul, penomoran dekoratif "01 / 02 / 03", dan panah "→" di setiap tombol.
- Gradien sebagai hiasan.

---

## 2. Design Tokens

### 2.1 Warna (mode terang)

| Token | Hex | Peran |
|---|---|---|
| `--concrete` | `#E9ECEE` | Latar halaman |
| `--surface` | `#FFFFFF` | Permukaan kartu, panel |
| `--ink` | `#172433` | Teks utama, judul |
| `--ink-muted` | `#4B5A68` | Teks sekunder |
| `--steel` | `#2F5F70` | Warna merek, tombol utama, tautan |
| `--plate` | `#F4C20D` | Aksen "label kuning": nomor loker, sorotan aksi penting |
| `--go` | `#14794A` | Aktif, tersedia, berhasil |
| `--stop` | `#C23A2B` | Expired, ditolak, rusak, kesalahan |
| `--hold` | `#B7791F` | Menunggu keputusan, peringatan *(lihat catatan bawah)* |
| `--line` | `#C9D0D6` | Garis pemisah, border |

Aturan pemakaian `--plate`: hanya untuk **nomor loker** dan **satu** aksi paling penting di layar. Jika kuning muncul di banyak tempat, ia kehilangan arti.

**Kontras (hasil hitung saya):**

| Pasangan | Rasio | Catatan |
|---|---|---|
| `ink` di atas `concrete` | 13,24 | Lolos AA dan AAA |
| `ink` di atas `surface` | 15,71 | Lolos AA dan AAA |
| `ink` di atas `plate` | 9,40 | Teks pada label kuning aman |
| putih di atas `steel` | 7,02 | Tombol utama aman |
| `steel` di atas `concrete` | 5,92 | Tautan aman |
| putih di atas `go` | 5,44 | Lencana aktif aman |
| `go` di atas `concrete` | 4,58 | Lolos AA untuk teks, nyaris di batas |
| putih di atas `stop` | 5,34 | Lencana expired aman |
| `stop` di atas `concrete` | 4,50 | **Tepat di batas AA.** Hindari untuk teks kecil; pakai di atas `surface` atau tebalkan |
| `ink-muted` di atas `concrete` | 5,98 | Aman |

Nilai `--hold` **belum saya hitung kontrasnya**. Hitung dan sesuaikan sebelum dipakai untuk teks.

### 2.2 Warna (mode gelap)

Mode gelap relevan karena banyak gym memakai pencahayaan redup dan kasir bisa bekerja malam hari (jam tutup di PRD diberi contoh pukul 23:00).

| Token | Hex | Peran |
|---|---|---|
| `--concrete` | `#0F1A24` | Latar halaman |
| `--surface` | `#16242F` | Permukaan panel |
| `--ink` | `#E6EBEF` | Teks utama |
| `--steel` | `#7FB3C4` | Aksen merek, tautan |
| `--plate` | `#F4C20D` | Label nomor loker (tetap sama) |
| `--go` | `#4CC38A` | Aktif, tersedia |
| `--stop` | `#FF7A6B` | Expired, ditolak |

Kontras hasil hitung: teks di atas latar 14,66; `plate` di atas latar 10,53; `go` di atas surface 7,14; `stop` di atas surface 6,21; `steel` di atas surface 6,89. Ikuti `prefers-color-scheme`, dan sediakan sakelar manual di pengaturan.

### 2.3 Tipografi

| Peran | Font | Alasan |
|---|---|---|
| **Tampilan (judul, angka besar, nomor loker)** | **Big Shoulders Display** (Google Fonts) | Huruf sempit dan tegas seperti papan penanda atletik. Angka loker terbaca besar tanpa memakan lebar. |
| **Antarmuka dan isi** | **Figtree** (Google Fonts) | Bersih, ramah, terbaca baik di ukuran kecil. Kontras jelas dengan font tampilan. |

*Fallback:* `"Big Shoulders Display", "Arial Narrow", sans-serif` dan `"Figtree", system-ui, sans-serif`. Muat dengan `font-display: swap` dan subset Latin agar cepat di jaringan seluler.

**Skala tipografi** (rasio sekitar 1,25, basis 16 px):

| Token | Ukuran / tinggi baris | Pemakaian |
|---|---|---|
| `display-xl` | 72 / 72 | Nomor loker di layar sukses member |
| `display-lg` | 40 / 44 | Judul layar, jumlah di laporan |
| `display-md` | 28 / 32 | Judul panel |
| `body-lg` | 18 / 28 | Nama member di kartu kedatangan |
| `body` | 16 / 24 | Teks isi, input |
| `body-sm` | 14 / 20 | Keterangan, tabel padat |
| `caption` | 12 / 16 | Waktu, metadata |

Aturan: huruf besar-kecil biasa (*sentence case*) di seluruh antarmuka, panjang baris di bawah 80 karakter, input minimal 16 px agar iOS tidak memperbesar halaman saat fokus.

### 2.4 Jarak, sudut, dan bayangan

- **Jarak:** kelipatan 4 px (`4, 8, 12, 16, 24, 32, 48, 64`).
- **Radius bertingkat sesuai fungsi, bukan satu nilai untuk semua:**
  - kontrol (tombol, input): 8 px
  - panel besar: 12 px
  - **pintu loker: 4 px** dengan engsel dan celah ventilasi digambar sebagai garis, agar terasa seperti logam
  - lencana status: pil penuh
- **Bayangan:** hampir tidak dipakai. Hierarki dibuat lewat garis (`--line`) dan perbedaan warna permukaan. Bayangan hanya untuk elemen melayang (modal, toast, sheet).

### 2.5 Gerak

| Token | Durasi | Kurva | Pemakaian |
|---|---|---|---|
| `motion-fast` | 120 ms | ease-out | Hover, tekan tombol |
| `motion-base` | 220 ms | ease-out | Buka panel, ganti tab |
| `motion-emphasis` | 450 ms | ease-out | **Satu momen:** kartu kedatangan masuk, pintu loker menutup |

- Gerak hanya menjawab aksi atau perubahan data. Tidak ada animasi masuk pada tiap bagian halaman.
- Hormati `prefers-reduced-motion`: ganti gerak dengan perubahan warna atau opasitas instan.
- Gerakan yang memberi tahu perubahan data (kartu baru muncul) boleh tetap ada dalam bentuk sederhana, misalnya sorotan kuning yang memudar.

---

## 3. Layout Responsif

### 3.1 Breakpoint

| Nama | Lebar | Perangkat utama |
|---|---|---|
| `sm` | < 640 px | HP (member) |
| `md` | 640–1023 px | Tablet portrait, HP landscape |
| `lg` | 1024–1439 px | Tablet landscape, laptop (kasir) |
| `xl` | ≥ 1440 px | Monitor meja resepsionis, admin |

Pendekatan **mobile-first** untuk aplikasi member. Dasbor kasir dirancang **tablet landscape dulu** (1024 px) lalu diskalakan ke atas, karena itu perangkat paling mungkin dipakai di meja resepsionis.

### 3.2 Pola navigasi per perangkat

| Peran | Mobile | Tablet | PC |
|---|---|---|---|
| **Member** | Tab bar bawah (3 item) | Tab bar bawah, konten diberi lebar maksimal 560 px dan dipusatkan | Sama seperti tablet |
| **Kasir** | Tidak ditargetkan (lihat 8.1) | Satu layar kerja tanpa navigasi dalam, ganti tampilan lewat tab atas | Layar kerja tiga kolom |
| **Admin** | Menu *drawer*, tabel menjadi daftar kartu | Rel samping ikon | Sidebar penuh |

---

## 4. Aplikasi Member (Mobile-first)

### 4.1 Peta layar

```
Masuk (nomor HP) ─► Verifikasi OTP ─► Beranda ──┬─► Pindai QR ─► Hasil
                                                ├─► Kartu member
                                                └─► Riwayat
```

Tab bar bawah: **Beranda**, **Pindai** (tombol tengah, lebih besar), **Riwayat**. Kartu member dibuka dari Beranda.

### 4.2 Masuk dan OTP

```
┌───────────────────────────┐
│                           │
│   GymPass                 │
│   Masuk dengan nomor HP   │
│                           │
│  ┌─────────────────────┐  │
│  │ +62  812 3456 7890  │  │
│  └─────────────────────┘  │
│                           │
│  [  Kirim kode OTP  ]     │
│                           │
└───────────────────────────┘
```

- Keyboard numerik (`inputmode="numeric"`), awalan +62 otomatis, nol di depan dihapus.
- OTP 6 digit: enam kotak yang berpindah otomatis, mendukung tempel dan isi otomatis dari SMS/WhatsApp (`autocomplete="one-time-code"`).
- Tombol "Kirim ulang" aktif setelah hitung mundur. Lamanya jeda menunggu keputusan backend (lihat rate limiting OTP di review PRD) **[OPEN]**.
- Salah OTP: kotak bergetar singkat dan teks "Kode tidak cocok. Periksa lagi atau kirim ulang."

### 4.3 Beranda

```
┌───────────────────────────┐
│ Halo, John                │
│ ┌───────────────────────┐ │
│ │ Monthly Unlimited     │ │
│ │ Aktif · 18 hari lagi  │ │  ← lencana status
│ │ ▓▓▓▓▓▓▓▓░░░░░░        │ │  ← sisa masa aktif
│ └───────────────────────┘ │
│                           │
│ Hari ini                  │
│ Belum check-in            │
│                           │
│  ┌─────────────────────┐  │
│  │   Pindai QR gym     │  │  ← tombol utama, kuning pelat
│  └─────────────────────┘  │
│                           │
│ [Beranda] [Pindai] [Riwayat]
└───────────────────────────┘
```

- Setelah check-in, area "Hari ini" berubah menampilkan **jam masuk** dan **status loker** (menunggu kasir, atau nomor loker).
- Paket Day Pass menampilkan sisa kuota ("1 kunjungan"), bukan bilah progres.
- Paket expired: kartu berwarna `stop` dengan kata "Kedaluwarsa" dan satu tindakan "Hubungi kasir untuk perpanjang". Perilaku sebenarnya (ditolak atau tetap masuk feed) menunggu keputusan PRD **[OPEN]**.

### 4.4 Pindai QR

```
┌───────────────────────────┐
│ ✕                  Senter │
│  ┌─────────────────────┐  │
│  │                     │  │
│  │    ┌ ─ ─ ─ ─ ┐      │  │
│  │      kamera         │  │
│  │    └ ─ ─ ─ ─ ┘      │  │
│  │                     │  │
│  └─────────────────────┘  │
│  Arahkan ke QR di meja    │
│  kasir                    │
│  GPS: Lokasi terdeteksi ● │
└───────────────────────────┘
```

- Izin kamera dan lokasi diminta **di konteks** (saat pertama menekan Pindai) dengan penjelasan satu kalimat. Jika ditolak, tampilkan langkah mengaktifkannya di pengaturan perangkat.
- Indikator GPS terlihat sebelum memindai agar member tahu lebih awal jika lokasi belum siap.
- Tidak ada tombol "pilih dari galeri" (sesuai FR-MEM-02).
- Getaran singkat dan bingkai berubah hijau ketika QR terbaca.

### 4.5 Layar hasil

**Berhasil, belum ada loker:**

```
┌───────────────────────────┐
│          ✓                │
│   Check-in berhasil       │
│   18:30                   │
│                           │
│   Butuh loker?            │
│   Sampaikan ke kasir.     │
│                           │
│   [Selesai]               │
└───────────────────────────┘
```

**Loker ditetapkan (layar ini diperbarui otomatis):**

```
┌───────────────────────────┐
│   Loker kamu              │
│  ┌─────────────────────┐  │
│  │ ┌─────────────────┐ │  │
│  │ │                 │ │  │
│  │ │       05        │ │  │  ← display-xl, label kuning
│  │ │                 │ │  │
│  │ └─────────────────┘ │  │
│  └─────────────────────┘  │
│   Kembalikan kunci ke     │
│   kasir saat pulang.      │
└───────────────────────────┘
```

Pembaruan otomatis ini memakai WebSocket/SSE atau polling ringan dengan jeda wajar; mekanisme persisnya belum ditentukan di PRD **[OPEN]**. Saat nomor loker muncul, tag kuning "mendarat" dengan sedikit gerak (momen emphasis) dan perangkat bergetar singkat.

### 4.6 Pesan error (selaras dengan edge case PRD)

| Kondisi | Judul | Penjelasan dan tindakan |
|---|---|---|
| Di luar radius | "Kamu terlalu jauh dari gym" | "Dekati meja kasir lalu coba lagi. Jika sudah di dalam gym, minta kasir check-in manual." |
| Akurasi GPS buruk | "Lokasi belum akurat" | "Tunggu beberapa detik atau pindah dekat jendela. Masih gagal? Minta kasir check-in manual." |
| Cooldown aktif | "Kamu sudah check-in" | "Check-in terakhir pukul 18:30. Kamu bisa memindai lagi setelah 19:30." |
| Paket expired | "Paket kedaluwarsa" | "Perpanjang paket di kasir." |
| QR tidak dikenali | "QR ini bukan dari gym" | "Pindai QR yang terpasang di meja kasir." |
| Tanpa internet | "Tidak ada koneksi" | "Sambungkan internet lalu coba lagi. Atau minta kasir check-in manual." |

Aturan penulisan: tidak ada permintaan maaf kosong, selalu ada langkah berikutnya, dan setiap error di HP punya jalur keluar ke check-in manual oleh kasir.

---

## 5. Dasbor Kasir (Tablet/PC)

### 5.1 Layout tiga zona (≥ 1024 px)

```
┌──────────────────────────────────────────────────────────────────────────┐
│ GymPass · Meja depan          ● Terhubung      Cari member…   [Manual]   │
├───────────────────────┬───────────────────────────────┬──────────────────┤
│  KEDATANGAN BARU      │   DINDING LOKER               │  DI DALAM GYM    │
│                       │                               │  (member aktif)  │
│ ┌───────────────────┐ │  ┌──┬──┬──┬──┬──┬──┐          │  Anna   #03 [↩]  │
│ │ [foto]  John Doe  │ │  │01│02│03│04│05│06│          │  Budi   #12 [↩]  │
│ │ Monthly · Aktif   │ │  ├──┼──┼──┼──┼──┼──┤          │  Citra  —   [✓]  │
│ │ 18:30             │ │  │07│08│09│10│11│12│          │  Dodi   #07 [↩]  │
│ │ [Pilih loker]     │ │  ├──┼──┼──┼──┼──┼──┤          │  …               │
│ │ [Tanpa loker]     │ │  │13│14│15│16│17│18│          │                  │
│ └───────────────────┘ │  └──┴──┴──┴──┴──┴──┘          │  Terisi 7 / 18   │
│ ┌───────────────────┐ │                               │                  │
│ │ Sari …            │ │                               │                  │
│ └───────────────────┘ │                               │                  │
└───────────────────────┴───────────────────────────────┴──────────────────┘
```

| Zona | Lebar (≥ 1440) | Isi |
|---|---|---|
| Kedatangan baru | ~30% | Antrean kartu kedatangan, yang terbaru di atas |
| Dinding loker | ~40% | Grid loker interaktif |
| Di dalam gym | ~30% | Daftar member yang sedang berlatih dan tombol pengembalian kunci |

**Tablet (640–1023 px):** tiga zona menjadi **tiga tab** di bagian atas (Kedatangan, Loker, Di dalam). Saat ada kedatangan baru, tab "Kedatangan" menampilkan lencana angka.

### 5.2 Kartu kedatangan

Isi kartu: foto, nama, jenis paket, status paket, jam masuk, dan dua aksi.

Status paket (warna + ikon + kata):

| Status | Tampilan |
|---|---|
| Aktif | Lencana hijau, ikon centang, "Aktif" |
| Kedaluwarsa | Kartu berbingkai merah, ikon peringatan, "Kedaluwarsa", dan aksi "Lanjutkan" ditahan sampai kasir memilih (perilaku tepatnya **[OPEN]**) |
| Ditangguhkan | Lencana abu, "Ditangguhkan" |

**Foto adalah alat verifikasi utama kasir**, jadi ukurannya cukup besar (minimal 72 px di tablet). Jika `photo_url` kosong (boleh `NULL` di skema), tampilkan inisial dengan label "Belum ada foto" sebagai pengingat bagi kasir untuk memeriksa identitas lewat cara lain.

Aksi:
- **Pilih loker** (tombol utama, warna `plate`): membuka dinding loker dalam mode pemilihan untuk kartu ini.
- **Tanpa loker**: tombol sekunder. Setelah diketuk, kartu pindah ke daftar "Di dalam gym".

### 5.3 Dinding loker (elemen utama)

Setiap pintu loker:

| Keadaan | Tampilan | Interaksi |
|---|---|---|
| Tersedia | Warna `surface`, garis baja, nomor ink | Dapat dipilih |
| Terpilih | Bingkai kuning tebal, tag kuning terpasang | Konfirmasi dengan tombol "Serahkan kunci #05" |
| Terisi | Isi `steel` gelap, nama member singkat | Ketuk untuk melihat siapa, dan tombol "Kembalikan kunci" |
| Rusak | Diarsir, ikon silang, abu | Tidak dapat dipilih; ada keterangan "Rusak" saat ditekan |

Alur pemilihan (2 ketukan setelah kartu dipilih): **Pilih loker** pada kartu, **ketuk pintu**, lalu **Serahkan kunci**. Jika ingin lebih cepat, ketuk dua kali pintu kosong untuk langsung menetapkan.

Hal yang ditangani di tingkat UI:
- **Dua kasir memilih loker yang sama:** jika server menolak (loker sudah terisi), pintu itu berubah menjadi terisi dengan toast "Loker 05 baru saja dipakai. Pilih loker lain." Ini mengasumsikan backend menangani konflik secara atomik, sesuai saran di review PRD.
- **Keadaan "belum diputuskan":** kartu yang belum diberi keputusan loker tetap di antrean "Kedatangan baru" dan **tidak** berpindah ke "Di dalam gym", supaya `locker_id = NULL` tidak ambigu **[OPEN, tergantung penambahan status loker di skema]**.
- **Saran loker:** boleh ditambahkan fitur menyorot loker kosong terdekat pintu, tetapi ini opsional dan belum ada di PRD.

### 5.4 Daftar "Di dalam gym"

- Baris: nama, nomor loker, jam masuk, tombol **Kembalikan kunci** (ikon + kata).
- Menekan kembalikan kunci menampilkan konfirmasi ringan yang bisa dibatalkan (toast dengan "Urungkan" selama beberapa detik) daripada dialog modal, agar alur tetap cepat.
- Jam tutup: banner kuning di atas daftar, "Gym tutup 23:00. 4 loker masih terisi" dengan tombol **Atur ulang semua loker**. Tombol ini membuka dialog konfirmasi yang mencantumkan nomor loker yang akan dikosongkan (aksi massal yang berisiko, jadi sengaja dibuat berlapis).

### 5.5 Status koneksi

Indikator di bilah atas selalu terlihat:

| Status | Tampilan |
|---|---|
| Terhubung | Titik hijau + "Terhubung" |
| Menyambung ulang | Titik kuning berkedip pelan + "Menyambung ulang…" |
| Terputus | **Banner merah selebar layar:** "Terputus dari server. Data kedatangan baru belum tampil." |

Setelah tersambung kembali, dasbor mengambil ulang daftar hari ini lalu menyorot kartu yang baru muncul. Mekanisme pengambilan data yang terlewat perlu ditetapkan backend (lihat poin 9 di review PRD) **[OPEN]**.

### 5.6 Pintasan keyboard (PC)

| Tombol | Aksi |
|---|---|
| `/` | Fokus ke pencarian member |
| `M` | Buka check-in manual |
| `↑` / `↓` | Pindah antar kartu kedatangan |
| `Enter` | Buka dinding loker untuk kartu terpilih |
| `N` | "Tanpa loker" untuk kartu terpilih |
| `Esc` | Tutup panel atau batalkan pemilihan |

Tampilkan daftar pintasan lewat tombol `?`. Pintasan hanya membantu pengguna PC; seluruh fungsi tetap bisa dicapai dengan sentuhan.

### 5.7 Check-in manual

Panel samping yang meluncur dari kanan: kolom pencarian (nama atau nomor HP), hasil muncul saat mengetik, satu ketukan pada hasil menampilkan ringkasan paket, lalu tombol "Check-in manual". Sistem mencatat bahwa aksi ini dilakukan manual oleh kasir (selaras dengan `checkin_method`).

---

## 6. Panel Admin

### 6.1 Navigasi

Sidebar: **Ringkasan**, **Member**, **Paket**, **Loker**, **Laporan**, **Staf**. Pada mobile menjadi *drawer*. Menu **Member** (pembuatan dan perpanjangan paket) hanya berlaku jika FR pengelolaan membership ditambahkan ke PRD **[OPEN]**.

### 6.2 Ringkasan

- Angka utama hari ini: kunjungan, member di dalam gym, loker terisi. Angka ditampilkan sederhana dengan label kecil, tanpa gradien.
- Grafik batang kunjungan per jam hari ini dibandingkan hari yang sama minggu lalu.
- Tampilan data di mobile menjadi kartu bertumpuk satu kolom.

### 6.3 Laporan dan analisis jam sibuk

- Filter periode di atas: Hari ini, 7 hari, 30 hari, rentang kustom.
- **Peta panas jam sibuk** (hari dalam seminggu di sumbu Y, jam di sumbu X). Ini cocok untuk kebutuhan FR-ADM-03 karena pola mingguan lebih mudah dibaca dibanding daftar angka.
- Setiap grafik wajib punya **tabel pendamping** (alternatif teks) dan nilai tertulis saat disorot atau diketuk, supaya tidak bergantung pada warna.
- Tombol unduh CSV *(belum ada di PRD; usulan)*.

### 6.4 Tabel data

- Desktop: tabel dengan header lengket, pencarian, filter, dan urutan kolom.
- Mobile: setiap baris menjadi kartu ringkas dengan 3 data terpenting dan tombol "Detail".
- Aksi berisiko (nonaktifkan loker saat terisi, tangguhkan paket) memakai dialog yang menjelaskan akibatnya.

### 6.5 Manajemen loker

Gunakan **dinding loker yang sama** dengan dasbor kasir, dalam mode edit: ketuk pintu untuk menandai "Rusak" atau mengaktifkannya kembali. Konsistensi ini mengurangi beban belajar bagi admin.

---

## 7. Komponen Inti

| Komponen | Ketentuan |
|---|---|
| **Tombol utama** | Latar `steel`, teks putih, tinggi 48 px (mobile) / 44 px (desktop), radius 8 px. Tombol aksi paling penting di layar memakai `plate` dengan teks ink. |
| **Tombol sekunder** | Garis `steel`, latar transparan. |
| **Tombol berisiko** | Latar `stop`, hanya muncul di dialog konfirmasi. |
| **Lencana status** | Pil, ikon + teks + warna. Tidak pernah hanya warna. |
| **Pintu loker** | Komponen khusus dengan empat keadaan (lihat 5.3). Tinggi minimal 56 px di tablet. |
| **Input** | Label selalu terlihat di atas (bukan hanya *placeholder*), pesan error tepat di bawah input. |
| **Toast** | Muncul di bawah (mobile) atau kanan bawah (desktop); aksi "Urungkan" untuk tindakan yang dapat dibalik. Menggunakan `aria-live`. |
| **Sheet / Modal** | Mobile: *bottom sheet*. Desktop: dialog tengah. Fokus terkunci di dalamnya dan kembali ke pemicu saat ditutup. |
| **Kondisi kosong** | Selalu ada tindakan. Contoh daftar kedatangan kosong: "Belum ada yang datang. Kedatangan baru akan muncul di sini." |
| **Skeleton** | Dipakai saat memuat data pertama, bentuknya sama dengan komponen asli. |

**Target sentuh:** minimal sekitar 44–48 px dengan jarak antar-target yang cukup. Pintu loker dan tombol kasir sengaja lebih besar karena dipakai sambil berdiri dan sambil berbicara.

---

## 8. Interaksi Lintas Perangkat

### 8.1 Perangkat apa untuk siapa

| Peran | Perangkat yang dirancang | Perangkat yang tetap harus berfungsi |
|---|---|---|
| Member | HP (iOS Safari, Android Chrome) | Tablet |
| Kasir | Tablet landscape, monitor PC | Laptop. HP tidak ditargetkan, tetapi tata letak tidak boleh rusak |
| Admin | PC | Tablet dan HP untuk pengecekan cepat |

### 8.2 Masukan

- **Sentuh:** semua aksi dapat dilakukan dengan satu ketukan, tanpa bergantung pada *hover*. Informasi yang muncul saat hover di desktop punya padanan ketukan di perangkat sentuh.
- **Mouse dan keyboard:** pintasan di 5.6, urutan tab logis, dan indikator fokus yang jelas (garis 3 px berwarna `steel` dengan jarak 2 px, kontras terhadap latar).
- **Gestur:** geser untuk menutup *bottom sheet*, tarik untuk menyegarkan di Riwayat. Gestur bersifat tambahan, selalu ada tombol setara.

### 8.3 Kinerja yang dirasakan

PRD menargetkan respons validasi QR di bawah 1,5 detik dan latensi WebSocket di bawah 500 ms. Dari sisi desain:

- Tampilkan status "Memeriksa…" langsung saat QR terbaca, jangan menunggu respons.
- Pembaruan kasir bersifat *optimistic* untuk aksi yang kemungkinan besar berhasil, lalu dikoreksi jika server menolak.
- Hindari font besar dan gambar tanpa kompresi; foto profil disajikan dalam ukuran kecil (misalnya 160 px) untuk daftar.

### 8.4 PWA

Karena PRD menyebut "Mobile / Web App" tanpa memutuskan aplikasi native atau web, saya sarankan **PWA** sebagai titik awal: dapat dipasang di layar utama dan terasa seperti aplikasi. Ketahuilah bahwa PWA **tidak bisa mendeteksi lokasi palsu** (mock location) secara andal; ini berkaitan dengan temuan keamanan GPS di review PRD, dan perlu keputusan tim apakah risikonya diterima atau memerlukan aplikasi native **[OPEN]**.

---

## 9. Aksesibilitas

Target: WCAG 2.1 AA (rujukan resmi: dokumen W3C; cek versi terbaru yang berlaku untuk proyek).

- Kontras teks minimal 4,5:1 (3:1 untuk teks besar dan komponen antarmuka). Hasil hitung ada di 2.1 dan 2.2.
- Semua status memakai **warna + ikon + teks**.
- Semua fungsi dapat dijangkau dengan keyboard; urutan fokus mengikuti urutan visual.
- Label dan nama aksesibel untuk setiap kontrol; tombol ikon diberi `aria-label`.
- Pembaruan langsung (kartu kedatangan baru, status koneksi) diumumkan lewat `aria-live="polite"`. Jangan umumkan setiap perubahan loker agar pembaca layar tidak kebanjiran.
- Hormati `prefers-reduced-motion` dan `prefers-color-scheme`.
- Dukung pembesaran teks sampai 200% tanpa kehilangan fungsi. Gunakan satuan relatif (`rem`).
- Pemindai QR perlu alternatif untuk pengguna yang kesulitan membidik kamera: sediakan jalur "Minta kasir check-in manual" pada setiap kegagalan.

---

## 10. Saran Implementasi

Ini usulan, bukan keharusan; sesuaikan dengan stack yang dipilih tim (belum ditulis di PRD).

| Area | Usulan |
|---|---|
| Kerangka | React (atau kerangka lain yang dikuasai tim) |
| Gaya | Tailwind CSS dengan token di atas sebagai variabel CSS |
| Komponen dasar | shadcn/ui sebagai titik awal komponen yang aksesibel, lalu sesuaikan tampilannya dengan token GymPass (agar tidak tampak seperti tema bawaan) |
| Grafik | Pustaka grafik ringan dengan tabel pendamping |
| Real-time | WebSocket atau SSE sesuai PRD, dengan penyambungan ulang otomatis |
| Pemindai QR | Pustaka pemindai berbasis kamera di browser; uji di Safari iOS dan Chrome Android karena perilaku izin kamera berbeda |

Contoh token sebagai variabel CSS:

```css
:root {
  --concrete: #E9ECEE;
  --surface:  #FFFFFF;
  --ink:      #172433;
  --ink-muted:#4B5A68;
  --steel:    #2F5F70;
  --plate:    #F4C20D;
  --go:       #14794A;
  --stop:     #C23A2B;
  --line:     #C9D0D6;

  --font-display: "Big Shoulders Display", "Arial Narrow", sans-serif;
  --font-ui: "Figtree", system-ui, sans-serif;

  --radius-control: 8px;
  --radius-panel: 12px;
  --radius-locker: 4px;
}

@media (prefers-color-scheme: dark) {
  :root {
    --concrete: #0F1A24;
    --surface:  #16242F;
    --ink:      #E6EBEF;
    --steel:    #7FB3C4;
    --go:       #4CC38A;
    --stop:     #FF7A6B;
  }
}

@media (prefers-reduced-motion: reduce) {
  * { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }
}
```

---

## 11. Rencana Pengujian Desain

Sebelum dikunci, uji hal-hal ini bersama pengguna nyata:

1. **Uji kasir (tablet, jam sibuk):** apakah kasir bisa menyelesaikan satu kedatangan (cek foto, pilih loker, serahkan kunci) tanpa bertanya? Ukur jumlah ketukan dan waktu.
2. **Uji GPS di lokasi:** ukur akurasi di meja kasir dan di berbagai sudut gym sebelum menentukan radius dan pesan "lokasi belum akurat".
3. **Uji keterbacaan:** layar HP di bawah cahaya ruangan gym; layar kasir dari jarak lengan.
4. **Uji aksesibilitas:** pembaca layar (TalkBack dan VoiceOver), navigasi keyboard penuh, pembesaran teks 200%.
5. **Uji perangkat:** Safari iOS, Chrome Android, dan layar tablet yang benar-benar dipakai gym.
6. **Uji alur gagal:** koneksi putus, loker bentrok, member expired, kamera ditolak.

Alat uji otomatis (misalnya uji kontras dan uji alur) bisa melengkapi, tetapi tidak menggantikan uji dengan pengguna.

---

## 12. Daftar Keputusan Terbuka

Beberapa bagian desain bergantung pada keputusan produk yang masih terbuka di PRD:

| # | Pertanyaan | Dampak pada desain |
|---|---|---|
| 1 | Member expired ditolak di server, atau tetap masuk feed dengan peringatan? | Isi kartu kedatangan dan pesan error di HP (4.3, 4.6, 5.2) |
| 2 | QR tetap statis atau berputar di layar? | Perlu layar QR tambahan di meja kasir jika berputar |
| 3 | Bagaimana status "belum memilih loker" disimpan? | Perilaku antrean kedatangan (5.3) |
| 4 | Apakah ada FR penjualan dan pengelolaan membership? | Menu Member di admin (6.1) dan alur kasir untuk Day Pass |
| 5 | PWA atau aplikasi native? | Kemampuan izin, pemasangan, dan deteksi lokasi palsu (8.4) |
| 6 | Cara pembaruan real-time di sisi member | Layar loker otomatis (4.5) |
| 7 | Satu cabang atau banyak cabang? | Pemilih cabang di admin, dinding loker per cabang |
| 8 | Apakah kasir juga login lewat OTP WhatsApp? | Layar masuk untuk staf |

---

## 13. Ringkasan

- **Identitas:** "Dinding Loker." Baja bercat, label kuning, huruf sempit atletik. Satu elemen berkesan (pemilih loker berbentuk dinding pintu), sisanya tenang.
- **Member:** mobile-first, satu aksi utama (Pindai), hasil check-in berupa tag nomor loker besar.
- **Kasir:** tiga zona di tablet landscape (kedatangan, dinding loker, di dalam gym), menjadi tiga tab di layar lebih kecil, plus pintasan keyboard untuk PC.
- **Admin:** konsisten dengan komponen kasir, grafik selalu punya tabel pendamping.
- **Kualitas dasar:** responsif, fokus keyboard terlihat, gerak dikurangi saat diminta, mode gelap, kontras terhitung.

Jika dibutuhkan, langkah berikutnya yang bisa saya bantu: membuat **prototipe HTML interaktif** (dasbor kasir dengan dinding loker yang bisa diklik, dan layar member) agar desain ini bisa dicoba langsung di PC, tablet, dan HP.
