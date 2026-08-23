# Pemasangan cPanel Sakuci untuk UKK

Panduan memasang panel hosting siswa di Ubuntu + aaPanel (Apache).
Target: `cpanel.sakuci.id`, web siswa di `*.uk.sakuci.id`.

Perkiraan waktu: 30-45 menit, sebagian besar menunggu DNS.

> Di server ini ada situs lain yang sudah live. Semua perintah di bawah hanya
> menyentuh `/www/wwwroot/cpanel.sakuci.id` dan konfigurasi Apache yang baru.

---

## Ringkasan

| # | Langkah | Perlu apa |
|---|---|---|
| 1 | Record DNS wildcard | Akses pengaturan DNS domain |
| 2 | Ambil kode terbaru | SSH |
| 3 | Isi konfigurasi | SSH + password database |
| 4 | Pasang vhost subdomain | SSH + root |
| 5 | Daftarkan akun siswa | SSH |
| 6 | Uji | Browser |

---

## 1. Record DNS wildcard

Di pengelola DNS domain `sakuci.id`, tambahkan **satu** record:

| Type | Name | Value | TTL |
|---|---|---|---|
| A | `*.uk` | `103.158.96.27` | default |

Hasilnya `*.uk.sakuci.id`. Cukup sekali; semua project berikutnya otomatis
tercakup tanpa menambah record lagi.

Propagasi biasanya 5-30 menit. Cek dari komputer mana pun:

```bash
nslookup apasaja.uk.sakuci.id
```

Bila menjawab `103.158.96.27`, DNS siap. Kalau masih `Non-existent domain`,
tunggu dan ulangi. **Lanjutkan ke langkah 2 sambil menunggu** -- keduanya tidak
saling bergantung.

---

## 2. Ambil kode terbaru

```bash
sudo bash -c 'cd /www/wwwroot/cpanel.sakuci.id && git pull -q && chown -R www:www . 2>/dev/null; git log --oneline -1'
```

Pastikan yang tercetak adalah commit terbaru. `chown` mungkin mengeluh soal
`.user.ini` -- itu wajar, berkas tersebut sengaja dikunci aaPanel (`chattr +i`)
dan memang tidak boleh diubah.

---

## 3. Isi konfigurasi

```bash
sudo nano /www/wwwroot/cpanel.sakuci.id/config/env.php
```

Isinya harus menjadi seperti ini:

```php
<?php
return [
    'db_host' => 'localhost',
    'db_name' => 'sakuci_cpanel',
    'db_user' => 'sakuci_cpanel',
    'db_pass' => 'PASSWORD_DATABASE_PANEL',

    // Untuk membuat database siswa. Butuh wewenang CREATE DATABASE dan GRANT.
    'db_admin_user' => 'root',
    'db_admin_pass' => 'PASSWORD_ROOT_MYSQL',

    'projects_path' => '/www/wwwroot/cpanel.sakuci.id/projects',
    'debug' => false,

    'site_domain' => 'uk.sakuci.id',
    'phpmyadmin_url' => 'http://103.158.96.27:888/phpmyadmin_529d01f771bb07df/',
];
```

Simpan dengan **Ctrl+O**, **Enter**, **Ctrl+X**.

Yang perlu diperhatikan:

- **`db_admin_pass`** adalah password root MySQL. Tanpa ini tombol "Buat
  Database" tidak berfungsi -- karena membuat database butuh wewenang yang
  sengaja tidak dimiliki user panel sehari-hari.
- **`site_domain`** tanpa `http://` dan tanpa tanda bintang. Kosongkan bila
  DNS belum siap; tombol "Buka Web" hanya akan disembunyikan.
- **`debug` wajib `false`** di server. Bila `true`, galat PHP beserta path dan
  nama user database akan tampil ke pengunjung.

Kunci berkasnya agar hanya terbaca web server:

```bash
sudo chown www:www /www/wwwroot/cpanel.sakuci.id/config/env.php
sudo chmod 640 /www/wwwroot/cpanel.sakuci.id/config/env.php
```

---

## 4. Pasang vhost subdomain

Inilah yang membuat `budi.uk.sakuci.id` langsung menampilkan project `budi`
tanpa konfigurasi per siswa.

**a. Pastikan modul Apache aktif**

```bash
grep -E "vhost_alias|rewrite_module" /www/server/apache/conf/httpd.conf | grep -v "^#"
```

Harus muncul dua baris `LoadModule`. Bila salah satu tidak ada atau masih
diawali `#`, hapus tanda `#`-nya:

```bash
sudo nano /www/server/apache/conf/httpd.conf
```

**b. Salin berkas konfigurasi**

```bash
sudo cp /www/wwwroot/cpanel.sakuci.id/deploy/apache-wildcard.conf \
        /www/server/panel/vhost/apache/000-wildcard-uk.conf
```

Awalan `000-` membuatnya dimuat lebih dulu daripada vhost situs lain.

**c. Uji konfigurasi sebelum diterapkan**

```bash
sudo /www/server/apache/bin/httpd -t
```

Harus menjawab `Syntax OK`. **Jangan lanjut bila ada galat** -- Apache yang
gagal start akan mematikan semua situs di server ini, termasuk yang sudah live.

**d. Muat ulang**

```bash
sudo systemctl reload httpd
```

`reload` dipakai, bukan `restart`, agar situs lain tidak terputus.

---

## 5. Daftarkan akun siswa

```bash
cd /www/wwwroot/cpanel.sakuci.id
sudo php tools/add-user.php --acak budi siti eka joko
```

Password acak tercetak sekali di layar -- **salin sekarang** untuk dibagikan,
karena hanya hash-nya yang tersimpan.

Untuk satu kelas, cukup deretkan semua nama dalam satu perintah. Nama harus
huruf kecil, angka, atau garis bawah, 3-32 karakter.

Bila ingin menentukan password sendiri:

```bash
sudo php tools/add-user.php budi
```

Mengganti password yang sudah ada:

```bash
sudo php tools/set-password.php budi
```

---

## 6. Uji

Login sebagai salah satu siswa di `https://cpanel.sakuci.id`, lalu:

- [ ] **Add Project** -- isi domain `uji`, git URL repo publik mana saja
- [ ] Klik **📥 Clone** -- status berubah "menunggu giliran" lalu "selesai"
      dalam waktu paling lama satu menit
- [ ] Klik **📁 File** -- daftar berkas muncul, `.env` bisa dibuka dan disimpan
- [ ] Klik **🌐 Buka Web** -- `uji.uk.sakuci.id` menampilkan aplikasinya
- [ ] Menu **Databases** -- buat database, catat kredensialnya
- [ ] Menu **PhpMyAdmin** -- login dengan kredensial tadi, hanya database
      itu yang terlihat

Bila semua lolos, panel siap dipakai UKK.

---

## Bila bermasalah

| Gejala | Penyebab & solusi |
|---|---|
| Status mentok "menunggu giliran" | Cron worker mati. Cek `ls -l /var/log/sakuci-worker.log`, harus diperbarui tiap menit |
| Tombol "Buka Web" tidak muncul | `site_domain` kosong di `env.php`, atau project belum di-clone |
| `budi.uk.sakuci.id` tidak bisa dibuka | DNS belum propagasi, atau vhost langkah 4 belum dimuat |
| `budi.uk.sakuci.id` menampilkan situs lain | Vhost wildcard dimuat setelah vhost lain -- pastikan namanya diawali `000-` |
| "Gagal membuat database" | `db_admin_user`/`db_admin_pass` salah atau kosong |
| Tombol Buat Database bilang belum dikonfigurasi | `db_admin_user` belum diisi di `env.php` |
| Halaman putih | Cek `tail -50 /www/wwwlogs/cpanel.sakuci.id-error_log` |

Log worker:

```bash
sudo tail -50 /var/log/sakuci-worker.log
```

Log Apache untuk web siswa:

```bash
sudo tail -50 /www/wwwlogs/uk-wildcard-error.log
```

---

## Catatan keamanan

Panel ini menyuruh server menjalankan `git` dan memberi siswa akses menyunting
berkas. Yang perlu diketahui pengelola:

- **Panel terbuka bagi siapa pun yang tahu alamatnya**, dijaga hanya oleh
  password. Bila ingin dibatasi, isi `allowed_ips` di `env.php` (menerima CIDR),
  atau pasang Basic Auth lewat aaPanel.
- **Antar-siswa sudah terisolasi**: tiap siswa hanya melihat project dan
  database miliknya sendiri, dan tidak bisa membaca database panel.
- **Siswa tidak bisa keluar dari foldernya** lewat file manager; setiap path
  diverifikasi dengan `realpath()`.
- Web PHP tetap tanpa `exec()`. Perintah git dijalankan worker cron terpisah,
  bukan oleh permintaan dari browser.
