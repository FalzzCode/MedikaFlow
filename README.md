# MedikaFlow

## Sistem Informasi Manajemen Klinik

MedikaFlow adalah aplikasi manajemen klinik berbasis PHP native/procedural untuk menghubungkan data pasien, layanan klinis, persediaan obat, resep, pembayaran, riwayat, arsip, dan akses pengguna dalam satu alur kerja.

Project ini sengaja menggunakan PHP procedural tanpa framework PHP, ORM, atau OOP agar tetap sesuai dengan struktur tugas dan mudah dijalankan pada lingkungan Laragon.

## Daftar isi

- [Ringkasan fitur](#ringkasan-fitur)
- [Teknologi](#teknologi)
- [Persyaratan](#persyaratan)
- [Menjalankan dengan Laragon](#menjalankan-dengan-laragon)
- [Menjalankan manual dengan PHP built-in server](#menjalankan-manual-dengan-php-built-in-server)
- [Konfigurasi database](#konfigurasi-database)
- [Database dan migrasi](#database-dan-migrasi)
- [Role dan hak akses](#role-dan-hak-akses)
- [Alur kerja klinik](#alur-kerja-klinik)
- [Foto profil akun](#foto-profil-akun)
- [Struktur project](#struktur-project)
- [Keamanan](#keamanan)
- [Checklist pengembangan](#checklist-pengembangan)
- [Catatan deployment](#catatan-deployment)

## Ringkasan fitur

- Dashboard operasional dengan ringkasan pasien, dokter, obat, kunjungan, antrean, dan tren tujuh hari.
- Master data pasien, dokter, poli, dan obat dengan pencarian, filter, validasi, dan status aktif.
- Soft-delete lintas master data melalui menu Arsip, termasuk pemulihan dan perlindungan relasi.
- Pendaftaran kunjungan dengan nomor kunjungan serta nomor antrean yang dibuat dari database.
- Pemeriksaan klinis dengan data vital, diagnosa, tindakan, dan catatan dokter.
- Resep dengan beberapa item obat, pengecekan stok, dan pengurangan stok satu kali saat diselesaikan.
- Pembayaran dengan rincian pemeriksaan, tindakan, obat, total otomatis, dan status pelunasan.
- Riwayat kunjungan yang menghubungkan pasien, dokter, poli, pemeriksaan, resep, dan pembayaran.
- Login multi-role, setup Admin pertama, pembatasan percobaan login, serta manajemen akun.
- Foto profil akun yang dapat dibuat, diganti, atau dihapus oleh Admin.
- Skeleton loading per halaman, animasi transisi ringan, navigasi yang mempertahankan posisi sidebar, dan dukungan reduced motion.

## Teknologi

- PHP native/procedural
- MySQL atau MariaDB
- `mysqli_*` dengan prepared statement
- HTML5, CSS3, dan JavaScript vanilla
- Apache melalui Laragon atau PHP built-in server untuk pengembangan lokal

## Persyaratan

- PHP 8.x dengan ekstensi `mysqli`, `fileinfo`, dan dukungan upload file.
- MySQL/MariaDB aktif.
- Apache Laragon untuk URL berbasis folder, atau PHP CLI untuk built-in server.
- Browser modern dengan JavaScript aktif.

Ekstensi `fileinfo` diperlukan untuk memvalidasi tipe MIME foto profil dari isi file, bukan dari nama file saja.

## Menjalankan dengan Laragon

1. Letakkan folder project di document root Laragon, misalnya `C:\laragon\www\Tugas Manajemen Klinik`.
2. Nyalakan Apache dan MySQL dari Laragon.
3. Buka HeidiSQL atau phpMyAdmin, lalu jalankan seluruh isi [`database/klinik.sql`](database/klinik.sql) pada database `klinik`.
4. Buka:

   `http://localhost/Tugas%20Manajemen%20Klinik/`

5. Pada instalasi pertama, buka halaman setup dan buat Admin pertama. Project tidak menyertakan username atau password contoh.

`database/klinik.sql` menyiapkan struktur dan data lookup spesialisasi/kategori. Data akun dan data operasional dibuat dari aplikasi sehingga instalasi baru tidak otomatis memiliki data demo.

## Menjalankan manual dengan PHP built-in server

PHP built-in server cocok untuk pengembangan lokal, bukan untuk deployment produksi. MySQL tetap harus berjalan.

Dari PowerShell:

~~~powershell
Set-Location 'C:\laragon\www\Tugas Manajemen Klinik'
php -S 127.0.0.1:8088 -t .
~~~

Lalu buka:

`http://127.0.0.1:8088/dashboard/index.php`

Untuk menghentikan server, fokuskan terminal yang menjalankannya lalu tekan `Ctrl+C`.

## Konfigurasi database

Default project kompatibel dengan instalasi Laragon:

| Environment variable | Default |
| --- | --- |
| `MEDIKAFLOW_DB_HOST` | `127.0.0.1` |
| `MEDIKAFLOW_DB_USER` | `root` |
| `MEDIKAFLOW_DB_PASSWORD` | kosong |
| `MEDIKAFLOW_DB_NAME` | `klinik` |

Contoh tersedia di [`.env.example`](.env.example). `config/koneksi.php` membaca environment variable sistem; pastikan nilai tersebut benar-benar dimuat oleh Apache/PHP atau terminal yang menjalankan server.

Contoh PowerShell:

~~~powershell
$env:MEDIKAFLOW_DB_HOST = '127.0.0.1'
$env:MEDIKAFLOW_DB_USER = 'root'
$env:MEDIKAFLOW_DB_PASSWORD = ''
$env:MEDIKAFLOW_DB_NAME = 'klinik'
php -S 127.0.0.1:8088 -t .
~~~

Jangan commit password database atau file `.env`. File tersebut sudah masuk `.gitignore`.

## Database dan migrasi

Untuk instalasi baru, jalankan `database/klinik.sql`.

Untuk project lama, `include/functions.php` melakukan pemeriksaan schema saat aplikasi dibuka dan dapat menambahkan komponen yang belum ada: tabel akun, indeks keamanan login, kolom jadwal dokter, kolom arsip, dan kolom foto profil.

Migrasi manual yang tersedia:

| File | Isi |
| --- | --- |
| `001_auth_multi_role.sql` | Tabel akun multi-role |
| `002_archiving.sql` | Kolom soft-delete dan pelacakan arsip |
| `003_security_hardening.sql` | Pencatatan percobaan login |
| `004_profile_photos.sql` | Kolom foto profil akun |

Jalankan setiap migrasi satu kali pada database target. Sebelum migrasi manual, lakukan backup database.

## Role dan hak akses

| Role | Modul utama | Hak khusus |
| --- | --- | --- |
| Admin | Semua modul | Kelola akun, role, status akses, arsip, relasi akun dokter, dan foto profil |
| Dokter | Dashboard, pasien, kunjungan, pemeriksaan, resep, riwayat, profil | Mengelola alur klinis sesuai akses dokter |
| Petugas | Dashboard, pasien, obat, kunjungan, pembayaran, riwayat, profil | Mengelola administrasi layanan sesuai akses petugas |

Setiap akun memakai kredensial database sendiri. Menu yang tampil dan route yang dapat dibuka sama-sama diperiksa oleh server; menyembunyikan menu saja tidak dianggap sebagai pengamanan.

## Alur kerja klinik

Pasien → pendaftaran kunjungan → pemeriksaan → resep → resep diselesaikan dan stok berkurang → pembayaran → riwayat pasien.

Relasi antar data dipertahankan oleh foreign key dan validasi aplikasi. Data master yang dihapus dari daftar aktif masuk ke Arsip sehingga riwayat transaksi tetap dapat ditelusuri.

## Foto profil akun

Pengaturan foto profil berada di halaman **Manajemen akun** dan hanya dapat diakses Admin. Admin dapat:

- memilih foto saat membuat akun;
- mengganti foto saat mengedit akun;
- menghapus foto saat ini;
- melihat fallback inisial jika akun belum memiliki foto.

Aturan upload:

- format yang diterima: JPG, PNG, atau WebP;
- ukuran maksimal: 2 MB;
- MIME dan isi gambar diverifikasi di server;
- nama file asli tidak dipakai sebagai nama penyimpanan;
- file disimpan di `storage/profile-photos` dengan nama acak;
- akses file dilayani melalui `profil/foto.php` setelah autentikasi, bukan melalui path file mentah;
- pengguna non-Admin hanya dapat melihat foto miliknya sendiri melalui endpoint tersebut.

Foto yang diunggah bersifat runtime dan diabaikan Git. Foldernya tetap disediakan melalui `.gitkeep`; backup production harus mencakup folder ini atau mekanisme penyimpanan pengganti yang digunakan.

## Struktur project

~~~text
.
├── assets/
│   ├── css/                 # style dasar dan tema klinik
│   ├── images/              # logo dan aset visual
│   └── js/                  # interaksi UI, loader, filter, dan preview
├── auth/                    # login, setup Admin pertama, logout
├── akun/                    # manajemen akun Admin
├── config/                  # koneksi database
├── database/
│   ├── klinik.sql           # schema instalasi baru
│   └── migrations/          # perubahan schema bertahap
├── includes/
│   ├── functions.php        # helper database, auth, CSRF, role, dan avatar
│   ├── header.php           # shell, sidebar, topbar, skeleton
│   └── footer.php           # penutup layout dan script
├── profil/
│   ├── index.php            # profil pengguna yang sedang login
│   └── foto.php             # endpoint foto profil terautentikasi
├── storage/
│   ├── profile-photos/      # foto runtime, tidak di-commit
│   └── sessions/            # session file lokal
└── README.md
~~~

Folder modul lain seperti `dashboard`, `pasien`, `dokter`, `poli`, `obat`, `kunjungan`, `pemeriksaan`, `resep`, `pembayaran`, `riwayat`, dan `arsip` berisi halaman alur kliniknya masing-masing.

## Keamanan

Baseline keamanan yang sudah diterapkan:

- query database memakai prepared statement;
- password disimpan dengan `password_hash()` dan diverifikasi dengan `password_verify()`;
- form perubahan data memakai token CSRF;
- session memakai cookie `HttpOnly`, `SameSite=Lax`, strict mode, dan timeout inaktif;
- login memiliki throttle berbasis identifier dan IP;
- validasi role dilakukan di server pada setiap modul;
- header `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, dan `Permissions-Policy` dipasang;
- detail exception database tidak ditampilkan ke pengguna;
- file foto divalidasi berdasarkan MIME/isi, diberi nama acak, dan folder storage membatasi akses langsung Apache.

Untuk deployment nyata, gunakan HTTPS, kredensial database dari secret manager/environment, backup terjadwal, permission filesystem minimum, konfigurasi upload PHP yang sesuai, dan web server yang sudah di-hardening.

## Checklist pengembangan

Sebelum commit:

~~~powershell
git diff --check
php -l includes/functions.php
php -l includes/header.php
php -l akun/tambah.php
php -l akun/edit.php
php -l akun/_form.php
php -l profil/foto.php
~~~

Setelah menjalankan aplikasi, verifikasi setidaknya:

1. Admin dapat membuat akun tanpa foto.
2. Admin dapat membuat akun dengan foto JPG/PNG/WebP yang valid.
3. Admin dapat mengganti dan menghapus foto.
4. Avatar fallback inisial tetap tampil jika foto kosong.
5. Dokter/Petugas tidak melihat kontrol upload dan tidak dapat membuka route manajemen akun.
6. File lebih besar dari 2 MB, MIME palsu, dan file non-gambar ditolak.
7. Data akun, login, dan modul klinik yang sudah ada tetap berjalan.

## Catatan deployment

Project ini disiapkan untuk pengembangan lokal dan kebutuhan tugas. Belum ada pipeline CI/CD atau mekanisme penyimpanan object storage bawaan. Jika dipasang di server publik, review ulang konfigurasi Apache/PHP, HTTPS, backup, audit log, kebijakan retensi data medis, serta aturan perlindungan data yang berlaku.

Tidak ada lisensi open-source yang didefinisikan di repository ini. Tambahkan file lisensi terpisah jika project akan didistribusikan di luar kebutuhan internal atau akademik.
