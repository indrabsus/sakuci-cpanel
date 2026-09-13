# Sakuci cPanel

Panel pengembangan untuk Sakuci Framework. Tiga hal saja: tambah project
dari GitHub, clone/pull isinya, dan kelola database MySQL-nya.

Ini **bukan** panel hosting komersial -- tidak ada registrasi, paket, tagihan,
atau tiket. Akun dibuat manual lewat terminal.

## Kebutuhan

- PHP 8.1+ dengan ekstensi `mysqli`
- MySQL 8.0 atau MariaDB
- `git` tersedia di PATH

## Pasang

```bash
git clone https://github.com/indrabsus/sakuci-cpanel.git
cd sakuci-cpanel
cp config/env.example.php config/env.php
```

Sunting `config/env.php` sesuai database Anda, lalu:

```bash
mysql -u <user> -p <nama_database> < database/cpanel-schema.sql
php tools/set-password.php admin
```

User `admin` dibuat tanpa password yang bisa dipakai -- login baru mungkin
setelah perintah terakhir di atas dijalankan.

Menjalankan secara lokal:

```bash
php -S 127.0.0.1:8000
```

## Struktur

```
index.php                 halaman login
app/
  partials/layout.php     kerangka halaman: sidebar, bilah judul
  assets/panel.css        sistem tampilan bersama
app/
  dashboard.php           daftar project + tombol clone/pull
  add-project.php         tambah project dari GitHub
  databases.php           buat database per project
  api/clone.php           titipkan clone ke antrean (JSON)
  api/pull.php            titipkan pull ke antrean  (JSON)
  api/job-status.php      pantau status pekerjaan   (JSON)
  api/delete-project.php  hapus project dari daftar (JSON)
  files.php               file manager per project
  users.php               manajemen akun (admin)
config/
  config.php              konstanta + koneksi + sesi
  jobs.php                antrean pekerjaan git
  ip-guard.php            pembatasan akses per IP
  auth.php                verifikasi user ke database
  env.php                 kredensial -- diabaikan git
database/cpanel-schema.sql
tools/
  set-password.php        ganti password lewat terminal
  worker.php              pengeksekusi git, dipanggil cron
```

## Bagaimana clone dan pull dijalankan

Panel **tidak** memanggil shell. Menekan Clone atau Pull hanya menambah baris
di tabel `job_queue`; `tools/worker.php` yang dipanggil cron tiap menit yang
menjalankan git, lalu menulis hasilnya kembali ke baris itu. Halaman menanyakan
status secara berkala sampai selesai.

Rancangan ini lahir karena panel hosting seperti aaPanel mematikan `exec()`
pada PHP web lewat `disable_functions` -- dan itu memang seharusnya begitu.
PHP CLI memakai berkas konfigurasi terpisah yang masih mengizinkannya, jadi
worker tetap bisa bekerja tanpa melonggarkan keamanan PHP web.

Pasang cron-nya:

```
* * * * * /usr/bin/php /path/ke/cpanel/tools/worker.php
```

Tanpa cron, pekerjaan akan menumpuk berstatus `pending` dan tidak pernah jalan.

## Catatan keamanan

Siapa pun yang bisa login dapat menyuruh server menjalankan git. Karena itu:

- Jangan biarkan terbuka ke internet tanpa pembatasan. Pakai IP whitelist
  atau Basic Auth di depannya.
- Selalu lewat HTTPS -- login mengirim password.
- Pakai password panjang; `tools/set-password.php` mensyaratkan 12 karakter.

Panduan pemasangan di server ada di [DEPLOY.md](DEPLOY.md).

## Cara kerja `pull`

`api/pull.php` menjalankan `git fetch` lalu `git reset --hard origin/<branch>`.
Perubahan lokal yang belum di-commit di folder project **akan hilang**. Ini
disengaja: server harus mencerminkan isi repo, bukan menyimpan suntingan
langsung.

## Membatasi akses per IP

Panel ini menjalankan git di server, jadi sebaiknya tidak terbuka bebas.
Cara terbaik adalah menyaringnya di web server. Bila itu tidak tersedia,
isi `allowed_ips` di `config/env.php`:

```php
'allowed_ips' => ['103.158.96.27', '2404:c0:ab00::/48'],
```

Menerima alamat persis maupun CIDR, IPv4 dan IPv6. Larik kosong mematikan
pembatasan. Permintaan dari IP lain dijawab `403` sebelum halaman login
sempat tampil.

Yang dipakai hanya `REMOTE_ADDR`. Header `X-Forwarded-For` sengaja diabaikan
karena dikirim oleh klien dan bisa dipalsukan siapa pun -- mempercayainya
justru meniadakan pembatasan ini. Bila kelak dipasang CDN atau proxy di depan
panel, bagian ini harus ditinjau ulang.

Perintah terminal (`worker.php`, `set-password.php`) tidak ikut disaring.

**Salah isi akan mengunci Anda sendiri.** Pemulihannya lewat SSH: kosongkan
kembali `allowed_ips`, tanpa perlu restart apa pun.

## Menayangkan project yang sudah di-clone

Folder `projects/` adalah ruang kerja, **bukan** direktori publik, dan sengaja
ditutup dari domain panel lewat `projects/.htaccess`. Menayangkan project dari
sana akan membuka `.git` beserta seluruh berkas di luar `public/`.

Cara yang benar: buatkan **situs tersendiri** untuk tiap project.

Di aaPanel:

1. **Website → Add site**, isi domainnya (mis. `app.contoh.id`)
2. Buka pengaturan situs itu → **Site Directory**
   - Site directory: `/www/wwwroot/cpanel.sakuci.id/projects/<nama>`
   - Running directory: `/public`
3. **SSL → Let's Encrypt**, lalu **Force HTTPS**

Pengaturan **Running directory** itu kuncinya: Sakuci menaruh satu-satunya
berkas yang boleh diakses browser di `public/`, dan sisanya (`core/`,
`config/`, `.env`) harus berada di luar jangkauan web.

Setelah itu panel tetap mengurus kodenya -- tombol Pull memperbarui isi folder,
dan situs langsung menyajikan versi terbaru tanpa pengaturan tambahan.
