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
  dashboard.php           daftar project + tombol clone/pull
  add-project.php         tambah project dari GitHub
  databases.php           buat database per project
  phpmyadmin.php          pintasan ke PhpMyAdmin
  api/clone.php           git clone   (JSON)
  api/pull.php            git pull    (JSON)
config/
  config.php              konstanta + koneksi + sesi
  auth.php                verifikasi user ke database
  env.php                 kredensial -- diabaikan git
database/cpanel-schema.sql
tools/set-password.php    ganti password lewat terminal
```

## Catatan keamanan

Panel ini menjalankan `git` lewat `exec()`. Siapa pun yang bisa login dapat
menjalankan clone dan pull di server. Karena itu:

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
