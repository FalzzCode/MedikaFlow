# Dokumentasi Project MedikaFlow

## 1. Deskripsi

MedikaFlow membantu petugas klinik mengelola data master dan menjalankan alur kunjungan secara utuh. Fokus antarmukanya adalah memperpendek langkah kerja petugas: status mudah dipindai, aksi utama selalu dekat dengan konteks, dan data transaksi saling terhubung.

## 2. Tujuan

- Menyediakan CRUD data pasien, dokter, poli, dan obat.
- Menyambungkan pendaftaran kunjungan dengan pemeriksaan, resep, pembayaran, dan riwayat.
- Menjaga validasi dasar, relasi foreign key, dan keamanan query.
- Menyajikan ringkasan operasional yang dapat dipahami dalam sekali lihat.

## 3. Struktur relasi

```text
specializations 1 ────< doctors
doctors 1 ──── 0..1 users
medicine_categories 1 ────< medicines
patients 1 ────< visits >──── 1 polyclinics
                         >──── 1 doctors
visits 1 ──── 0..1 examinations
visits 1 ────< prescriptions 1 ────< prescription_details >──── 1 medicines
visits 1 ──── 0..1 payments
```

## 4. Aturan bisnis penting

1. NIK, nomor rekam medis, kode dokter, kode poli, dan kode obat unik.
2. Harga dan stok tidak boleh negatif.
3. Satu kunjungan hanya memiliki satu pemeriksaan (`UNIQUE visit_id`).
4. Nomor antrean unik dalam satu tanggal.
5. Resep disimpan sebagai draft terlebih dahulu. Stok baru dipotong ketika resep diselesaikan, dan proses itu dikunci dalam transaksi database.
6. Data yang sudah dipakai transaksi dilindungi foreign key `RESTRICT`.
7. Pengarsipan memakai POST, token CSRF, dan konfirmasi modal custom. Data master tidak langsung dihapus agar relasi transaksi tetap utuh.
8. Sistem tidak menyediakan akun contoh. Admin pertama dibuat melalui setup satu kali saat tabel `users` kosong.
9. Password disimpan sebagai hash, login menggunakan session server-side dengan strict session cookie, pembatasan percobaan login per akun/IP, dan rute dibatasi untuk peran Admin, Dokter, atau Petugas.
10. Satu data dokter hanya dapat terhubung ke satu akun Dokter. Hari dan jam praktik tersimpan pada tabel `doctors`.
11. Arsip hanya dapat dibuka Admin. Data yang diarsipkan keluar dari daftar aktif dan dropdown transaksi, tetapi masih bisa dipulihkan. Hapus permanen tetap ditahan oleh foreign key bila data masih dipakai riwayat atau transaksi.
12. Pembuatan setup Admin pertama, nomor antrean, nomor kunjungan, nomor resep, dan perubahan akses Admin memakai lock database agar request bersamaan tetap konsisten.
13. Penyelesaian resep mengunci baris resep dan stok di dalam satu transaksi sehingga double-submit tidak memotong stok dua kali.

## 5. Alur autentikasi

1. Sistem memeriksa struktur tabel autentikasi saat request pertama.
2. Jika belum ada pengguna, seluruh halaman diarahkan ke `auth/setup.php` untuk membuat Admin pertama.
3. Setelah setup, pengguna masuk melalui `auth/login.php` menggunakan username atau email.
4. Admin menambahkan akun operasional nyata dari menu Manajemen Akun; tidak ada kredensial bawaan.
5. Session dan status akun diperiksa di setiap request. Akun nonaktif atau role yang tidak sesuai tidak dapat membuka rute terkait.
6. Logout memerlukan token sesi agar situs lain tidak dapat memaksa browser keluar melalui request lintas situs.

## 6. Rute utama

| Modul | Halaman utama |
| --- | --- |
| Dashboard | `dashboard/index.php` |
| Pasien | `pasien/index.php` |
| Dokter | `dokter/index.php` |
| Poli | `poli/index.php` |
| Obat | `obat/index.php` |
| Kunjungan | `kunjungan/index.php` |
| Pemeriksaan | `pemeriksaan/index.php` |
| Resep | `resep/index.php` |
| Pembayaran | `pembayaran/index.php` |
| Riwayat | `riwayat/index.php` |
| Login | `auth/login.php` |
| Setup pertama | `auth/setup.php` |
| Manajemen akun | `akun/index.php` |
| Profil pribadi | `profil/index.php` |
| Arsip data | `arsip/index.php` |
