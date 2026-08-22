# Deploy cPanel Sakuci ke aaPanel

Target: `cpanel.sakuci.id` di server Ubuntu 22.04 + aaPanel (Apache).

> **Baca dulu:** cPanel ini menjalankan perintah shell (`git clone`, `git pull`)
> lewat `exec()`. Di server yang sama ada situs yang sudah live. Jangan
> lewatkan Langkah 5 -- itu yang mencegah tool ini jadi pintu masuk.

---

## 1. Buat database (aaPanel)

**Database → Add database**

| Kolom | Isi |
|---|---|
| Database name | `sakuci_cpanel` |
| Username | `sakuci_cpanel` |
| Password | klik *Generate*, lalu **salin** |

Simpan passwordnya di password manager. Jangan kirim lewat chat.

## 2. Ambil kode dari GitHub

**aaPanel → Terminal** (berjalan sebagai root):

```bash
cd /www/wwwroot/cpanel.sakuci.id
rm -f index.html 404.html 502.html
git init -q
git remote add origin https://github.com/indrabsus/sakuci-cpanel.git
git fetch -q origin main
git checkout -f -b main origin/main
chown -R www:www /www/wwwroot/cpanel.sakuci.id
```

Dipakai `git init` + `fetch`, bukan `git clone`, karena folder situs sudah
berisi `.well-known` (dipakai aaPanel untuk perpanjangan SSL) dan `.user.ini`.
Cara ini menaruh kode di akar situs tanpa menyentuh keduanya, sehingga
Document Root tidak perlu diubah dan sertifikat tetap bisa diperbarui.

## 3. Isi konfigurasi

```bash
cd /www/wwwroot/cpanel.sakuci.id
cp config/env.example.php config/env.php
mkdir -p projects && chown www:www projects
nano config/env.php
```

Isi sesuai database dari Langkah 1:

```php
return [
    'db_host' => 'localhost',
    'db_name' => 'sakuci_cpanel',
    'db_user' => 'sakuci_cpanel',
    'db_pass' => 'PASSWORD_DARI_LANGKAH_1',
    'projects_path' => '/www/wwwroot/cpanel.sakuci.id/projects',
];
```

`projects_path` **harus** berada di dalam `open_basedir` situs
(`/www/wwwroot/cpanel.sakuci.id/`), kalau tidak PHP menolak membacanya.

Kunci file berisi password ini agar hanya bisa dibaca web server:

```bash
chown www:www config/env.php && chmod 640 config/env.php
```

## 4. Impor skema dan buat user

```bash
mysql -u sakuci_cpanel -p sakuci_cpanel < database/cpanel-schema.sql
```

> Gunakan `cpanel-schema.sql`, **bukan** `schema.sql`. Berkas `schema.sql`,
> `schema_clean.sql`, dan `schema_fixed.sql` adalah sisa aplikasi hosting
> berbayar yang sudah dibatalkan dan akan membuat database yang salah.

Skema membuat user `admin` yang **belum punya password sama sekali** --
login mustahil sampai Anda menetapkannya:

```bash
php tools/set-password.php admin
```

Password diketik langsung di terminal, minimal 12 karakter, dan hanya hash
bcrypt-nya yang tersimpan.

## 5. Amankan situs (jangan dilewati)

**aaPanel → Website → cpanel.sakuci.id:**

1. **Site Directory:** biarkan apa adanya
   (`/www/wwwroot/cpanel.sakuci.id`, running directory `/`) -- kode sudah
   berada di akar situs, jadi tidak ada yang perlu diubah di sini.
2. **PHP version:** 8.2 atau 8.3
3. **SSL → Force HTTPS: ON** -- tanpa ini password login terkirim polos
4. **Security → IP whitelist:** isi IP Anda saja

Langkah 4 adalah pengaman utamanya. Kalau IP Anda berubah-ubah, pakai
Basic Auth (**Website → Password access**) sebagai gantinya.

## 6. Uji

Buka `https://cpanel.sakuci.id`, login, lalu pastikan:

- [ ] Halaman login tampil lewat HTTPS
- [ ] Login berhasil dengan password baru
- [ ] Dashboard memuat tanpa galat
- [ ] Tambah project → tombol Clone berfungsi
- [ ] Akses dari IP lain (mis. data seluler) **ditolak**

Poin terakhir yang membuktikan pembatasan akses benar-benar aktif.

---

## Update berikutnya

```bash
cd /www/wwwroot/cpanel.sakuci.id && git pull
```

`config/env.php` dan `projects/` diabaikan git, jadi tidak akan tertimpa.

## Kalau bermasalah

```bash
tail -50 /www/wwwlogs/cpanel.sakuci.id-error_log
```

| Gejala | Penyebab umum |
|---|---|
| Halaman putih | Cek error log; biasanya `env.php` salah isi |
| "Database tidak dapat dihubungi" | Kredensial di `env.php` keliru |
| Clone gagal, output kosong | `git` tidak ada, atau `projects/` tidak writable oleh `www` |
| Project tidak terdeteksi ter-clone | `projects_path` di luar `open_basedir` |
