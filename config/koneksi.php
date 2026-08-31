<?php
/*
 * Koneksi database utama.
 * Nilai default tetap kompatibel dengan instalasi Laragon lokal, tetapi
 * kredensial deployment dapat diberikan melalui environment variable.
 */
$host = getenv('MEDIKAFLOW_DB_HOST') ?: '127.0.0.1';
$username = getenv('MEDIKAFLOW_DB_USER') ?: 'root';
$password = getenv('MEDIKAFLOW_DB_PASSWORD');
$password = $password === false ? '' : $password;
$database = getenv('MEDIKAFLOW_DB_NAME') ?: 'klinik';

// Semua error database ditangani oleh wrapper di functions.php agar detail
// driver tidak pernah langsung tampil ke pengguna.
mysqli_report(MYSQLI_REPORT_OFF);

$koneksi = mysqli_connect($host, $username, $password, $database);

if (!$koneksi) {
    die('Koneksi database gagal. Pastikan MySQL aktif dan database klinik sudah dibuat.');
}

mysqli_set_charset($koneksi, 'utf8mb4');
