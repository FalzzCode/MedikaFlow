# MedikaFlow · Sistem Informasi Manajemen Klinik

Aplikasi CRUD klinik berbasis PHP native/procedural, MySQL, HTML, CSS, dan JavaScript dasar. Struktur ini sengaja tidak memakai framework PHP, ORM, atau OOP PHP agar sesuai dengan ketentuan project.

## Cara menjalankan

1. Pastikan Apache dan MySQL aktif di Laragon.
2. Buka `database/klinik.sql` melalui HeidiSQL/phpMyAdmin, lalu jalankan seluruh isinya.
3. Jika kredensial MySQL tidak memakai default Laragon, set environment variable sesuai `.env.example` atau sesuaikan fallback lokal di `config/koneksi.php`.
4. Buka `http://localhost/Tugas%20Manajemen%20Klinik/` di browser.
5. Pada pembukaan pertama, buat akun Admin melalui halaman setup. Project tidak menyertakan username atau password contoh.

`database/klinik.sql` hanya menyiapkan struktur dan lookup spesialisasi/kategori; data operasional dan akun dibuat melalui aplikasi sehingga instalasi baru tidak menampilkan data demo.

Untuk database project lama, aplikasi akan menambahkan tabel `users`, tabel pembatasan percobaan login, kolom jadwal dokter, dan kolom arsip saat pertama dibuka. Skrip manual autentikasi tersedia di `database/migrations/001_auth_multi_role.sql`, perubahan arsip di `database/migrations/002_archiving.sql`, dan hardening login di `database/migrations/003_security_hardening.sql`.

## Login dan hak akses

- Admin: seluruh modul, pengelolaan akun, arsip data, status akses, dan hubungan akun dokter.
- Dokter: dashboard, pasien, kunjungan, pemeriksaan, resep, riwayat, serta profil pribadi.
- Petugas: dashboard, pasien, obat, kunjungan, pembayaran, riwayat, serta profil pribadi.

Setiap pengguna memakai akun database sendiri. Password diproses dengan `password_hash()`/`password_verify()`, sesi kedaluwarsa setelah delapan jam tidak aktif, percobaan login dibatasi per akun/IP, dan menu maupun rute dilindungi berdasarkan peran.

## Alur utama

Pasien → pendaftaran kunjungan → pemeriksaan → resep → resep diselesaikan dan stok berkurang → pembayaran → riwayat pasien.

## Modul

- Dashboard: ringkasan jumlah data, kunjungan tujuh hari terakhir, antrean hari ini, dan peringatan stok/expired.
- Master data: pasien, dokter, poli, dan obat dengan tambah, lihat, arsip, pemulihan, pencarian, dan validasi.
- Arsip data: daftar soft-delete lintas master data, filter jenis/pencarian, pemulihan ke daftar aktif, dan opsi hapus permanen yang tetap mengikuti perlindungan relasi database.
- Kunjungan: nomor kunjungan otomatis, nomor antrean per tanggal, pemilihan pasien/poli/dokter, status layanan, dan detail alur.
- Pemeriksaan: satu pemeriksaan per kunjungan dengan data vital, diagnosa, tindakan, dan catatan dokter.
- Resep: beberapa item obat per resep, pengecekan stok, serta pengurangan stok satu kali saat resep diselesaikan.
- Pembayaran: biaya pemeriksaan, tindakan, obat, total otomatis, dan status pelunasan.
- Riwayat pasien: pencarian lintas nama/no. rekam medis/no. kunjungan serta akses ke detail kunjungan.
- Akun dan profil: setup Admin pertama, login multi-role, pengelolaan pengguna, jadwal dokter dari database, perubahan identitas, dan pergantian password terverifikasi.

## Catatan kode

Query ditulis dengan `mysqli_*` procedural dan prepared statement. Helper bersama ada di `includes/functions.php`; komponen tampilan ada di `includes/header.php`, `includes/footer.php`, dan `assets/css/style.css`. Tidak ada data transaksi yang ditulis langsung di HTML/PHP sebagai pengganti database.
