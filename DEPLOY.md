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
git clone https://github.com/indrabsus/sakuci-cpanel.git sakuci-cpanel
chown -R www:www /www/wwwroot/cpanel.sakuci.id
```

## 3. Isi konfigurasi

```bash
cd /www/wwwroot/cpanel.sakuci.id/sakuci-cpanel
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
    'projects_path' => '/www/wwwroot/cpanel.sakuci.id/sakuci-cpanel/projects',
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
mysql -u sakuci_cpanel -p sakuci_cpanel < database/schema.sql
```

Skema membawa user `admin` dengan password bawaan. **Ganti sekarang juga:**

```bash
php tools/set-password.php admin
```

Password diketik langsung di terminal, minimal 12 karakter, dan hanya hash
bcrypt-nya yang tersimpan.

## 5. Amankan situs (jangan dilewati)

**aaPanel → Website → cpanel.sakuci.id:**

1. **Site Directory**
   - Site directory: `/www/wwwroot/cpanel.sakuci.id/sakuci-cpanel`
   - Running directory: `/` (index.php ada di akar repo)
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
cd /www/wwwroot/cpanel.sakuci.id/sakuci-cpanel && git pull
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
