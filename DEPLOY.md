# Deploy cPanel Sakuci ke aaPanel

Target: `cpanel.sakuci.id` di server Ubuntu 22.04 + aaPanel (Apache).

> **Baca dulu:** di server yang sama ada situs lain yang sudah live. Panel ini
> sendiri tidak pernah memanggil shell -- permintaan git dititipkan ke tabel
> `job_queue` dan dikerjakan worker cron (Langkah 5), sehingga `exec()` tetap
> boleh mati di PHP web. Meski begitu, siapa pun yang bisa login tetap dapat
> menyuruh server menjalankan git, jadi Langkah 6 jangan dilewatkan.

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
mysql -h 127.0.0.1 -u sakuci_cpanel -p sakuci_cpanel < database/cpanel-schema.sql
```

Dipakai `-h 127.0.0.1` agar lewat TCP. Tanpa itu klien mencari socket di
`/run/mysqld/mysqld.sock` sesuai `my.cnf`, padahal MySQL aaPanel membuatnya
di `/tmp/mysql.sock`.

Kalau memperbarui instalasi lama yang belum punya `job_queue`:

```bash
mysql -h 127.0.0.1 -u sakuci_cpanel -p sakuci_cpanel < database/migrations/001-job-queue.sql
```

Skema membuat user `admin` yang **belum punya password sama sekali** --
login mustahil sampai Anda menetapkannya:

```bash
sudo php tools/set-password.php admin
```

Password diketik langsung di terminal, minimal 12 karakter, dan hanya hash
bcrypt-nya yang tersimpan. `sudo` diperlukan karena `config/env.php` sengaja
dikunci `640 www:www` sehingga user biasa tidak bisa membacanya.

## 5. Pasang worker (wajib -- tanpa ini Clone/Pull tidak jalan)

aaPanel mematikan `exec()` pada PHP web lewat `disable_functions`, jadi panel
tidak menjalankan git sendiri. Panel hanya menitipkan permintaan di tabel
`job_queue`; worker inilah yang mengerjakannya. PHP **CLI** memakai
`php-cli.ini` terpisah yang masih mengizinkan `exec()`.

Pasang cron sebagai **root** (agar berkas hasil git bisa di-`chown` ke `www`):

```bash
sudo crontab -e
```

Tambahkan satu baris:

```
* * * * * /usr/bin/php /www/wwwroot/cpanel.sakuci.id/tools/worker.php >> /var/log/sakuci-worker.log 2>&1
```

Uji manual lebih dulu:

```bash
sudo /usr/bin/php /www/wwwroot/cpanel.sakuci.id/tools/worker.php && echo "worker OK"
```

Worker memakai file lock, jadi cron tiap menit aman meski satu clone berjalan
lebih lama dari satu menit.

## 6. Amankan situs (jangan dilewati)

**aaPanel → Website → cpanel.sakuci.id:**

1. **Site Directory:** biarkan apa adanya
   (`/www/wwwroot/cpanel.sakuci.id`, running directory `/`) -- kode sudah
   berada di akar situs, jadi tidak ada yang perlu diubah di sini.
2. **PHP version:** 8.2 atau 8.3
3. **SSL → Force HTTPS: ON** -- tanpa ini password login terkirim polos
4. **Security → IP whitelist:** isi IP Anda saja

Butir 4 adalah pengaman utamanya. Kalau IP Anda berubah-ubah, pakai
Basic Auth (**Website → Password access**) sebagai gantinya.

## 7. Uji

Buka `https://cpanel.sakuci.id`, login, lalu pastikan:

- [ ] Halaman login tampil lewat HTTPS
- [ ] Login berhasil dengan password baru
- [ ] Dashboard memuat tanpa galat
- [ ] Tambah project → klik Clone → status berubah jadi "menunggu giliran",
      lalu "selesai" dalam waktu paling lama satu menit
- [ ] `.git` **tidak** bisa diakses: `curl -I https://cpanel.sakuci.id/.git/config`
      harus menjawab 403
- [ ] Akses dari IP lain (mis. data seluler) **ditolak**

Butir Clone menguji worker sekaligus: kalau status mentok di "menunggu
giliran", berarti cron belum jalan. Dua butir terakhir membuktikan
pembatasan akses benar-benar aktif.

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

Log worker:

```bash
tail -50 /var/log/sakuci-worker.log
```

| Gejala | Penyebab umum |
|---|---|
| Halaman putih | Cek error log; biasanya `env.php` salah isi |
| "Database tidak dapat dihubungi" | Kredensial di `env.php` keliru |
| Status mentok "menunggu giliran" | Cron worker belum terpasang atau gagal jalan |
| Job `failed`, keluhan kredensial | Repo privat; git sengaja tidak menunggu prompt |
| Project ter-clone tapi tak terbaca panel | `projects_path` di luar `open_basedir` |
| Berkas hasil clone tak bisa ditulis web | Worker tidak jalan sebagai root, `chown` dilewati |
