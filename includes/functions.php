<?php
require_once __DIR__ . '/../config/koneksi.php';

// Hardening session harus dipasang sebelum session_start(). Strict mode
// menolak session id yang belum pernah diterbitkan server dan membantu
// mencegah session fixation.
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('display_errors', '0');

$session_path = __DIR__ . '/../storage/sessions';
if (!is_dir($session_path)) {
    @mkdir($session_path, 0775, true);
}
if (is_dir($session_path) && is_writable($session_path)) {
    session_save_path($session_path);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('medikaflow_session');
    session_set_cookie_params(array(
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ));
    session_start();
}

if (!headers_sent()) {
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), geolocation=(), microphone=()');
    header('Cache-Control: no-store, private');
}

function app_exception_handler($exception)
{
    error_log('[MedikaFlow] Unhandled exception: ' . get_class($exception) . ' - ' . $exception->getMessage());

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }

    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Gangguan sistem</title></head><body style="font-family:Arial,sans-serif;padding:32px;color:#14233f"><h1>Gangguan sistem</h1><p>Permintaan belum dapat diproses. Silakan coba lagi beberapa saat lagi.</p></body></html>';
    exit;
}

set_exception_handler('app_exception_handler');

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function app_brand_name()
{
    return 'MedikaFlow';
}

function app_base_path()
{
    static $base_path = null;

    if ($base_path !== null) {
        return $base_path;
    }

    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $modules = array(
        '/dashboard/', '/pasien/', '/dokter/', '/poli/', '/obat/',
        '/kunjungan/', '/pemeriksaan/', '/resep/', '/pembayaran/', '/riwayat/', '/profil/',
        '/auth/', '/akun/', '/arsip/'
    );

    foreach ($modules as $module) {
        $position = strpos($script, $module);
        if ($position !== false) {
            $base_path = rtrim(substr($script, 0, $position), '/');
            return $base_path;
        }
    }

    $base_path = rtrim(str_replace('\\', '/', dirname($script)), '/');
    return $base_path === '.' ? '' : $base_path;
}

function base_url($path = '')
{
    $base_path = app_base_path();
    $path = ltrim($path, '/');

    if ($path === '') {
        return $base_path === '' ? '/' : $base_path . '/';
    }

    return ($base_path === '' ? '' : $base_path) . '/' . $path;
}

function redirect_to($path)
{
    header('Location: ' . base_url($path));
    exit;
}

function post_value($key, $default = '')
{
    return trim((string) ($_POST[$key] ?? $default));
}

function query_value($key, $default = '')
{
    return trim((string) ($_GET[$key] ?? $default));
}

function is_post()
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf()
{
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token)) {
        throw new RuntimeException('Sesi formulir sudah kedaluwarsa. Silakan coba lagi.');
    }
}

function request_client_ip()
{
    // REMOTE_ADDR berasal dari koneksi web server. Header forwarded tidak
    // dipercaya karena dapat dipalsukan oleh client.
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
}

function auth_identifier_hash($identifier)
{
    return hash('sha256', strtolower(trim((string) $identifier)));
}

function flash($type, $message)
{
    $_SESSION['flash'][] = array(
        'type' => $type,
        'message' => $message,
    );
}

function pull_flash_messages()
{
    $messages = $_SESSION['flash'] ?? array();
    unset($_SESSION['flash']);
    return $messages;
}

function infer_param_type($value)
{
    if (is_int($value)) {
        return 'i';
    }

    if (is_float($value)) {
        return 'd';
    }

    return 's';
}

function prepare_statement($sql, $params = array())
{
    global $koneksi;

    $statement = mysqli_prepare($koneksi, $sql);
    if (!$statement) {
        throw new RuntimeException('Query tidak dapat disiapkan: ' . mysqli_error($koneksi));
    }

    if (!empty($params)) {
        $types = '';
        $references = array($statement);

        foreach ($params as $index => $param) {
            $types .= infer_param_type($param);
            $references[] = &$params[$index];
        }

        array_splice($references, 1, 0, $types);
        call_user_func_array('mysqli_stmt_bind_param', $references);
    }

    return $statement;
}

function db_select_all($sql, $params = array())
{
    $statement = prepare_statement($sql, $params);

    if (!mysqli_stmt_execute($statement)) {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Query gagal dijalankan: ' . $error);
    }

    $result = mysqli_stmt_get_result($statement);
    if ($result === false) {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Hasil query tidak dapat dibaca: ' . $error);
    }

    $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
    mysqli_stmt_close($statement);

    return $rows;
}

function db_select_one($sql, $params = array())
{
    $rows = db_select_all($sql, $params);
    return $rows[0] ?? null;
}

function db_value($sql, $params = array(), $default = 0)
{
    $row = db_select_one($sql, $params);
    if (!$row) {
        return $default;
    }

    return array_values($row)[0];
}

function db_execute($sql, $params = array())
{
    global $koneksi;

    $statement = prepare_statement($sql, $params);
    if (!mysqli_stmt_execute($statement)) {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Perubahan database gagal: ' . $error);
    }

    $result = array(
        'affected_rows' => mysqli_stmt_affected_rows($statement),
        'insert_id' => mysqli_insert_id($koneksi),
    );

    mysqli_stmt_close($statement);
    return $result;
}

function begin_transaction()
{
    global $koneksi;
    mysqli_begin_transaction($koneksi);
}

function commit_transaction()
{
    global $koneksi;
    mysqli_commit($koneksi);
}

function rollback_transaction()
{
    global $koneksi;
    mysqli_rollback($koneksi);
}

function database_column_exists($table, $column)
{
    global $koneksi;

    $table = mysqli_real_escape_string($koneksi, (string) $table);
    $column = mysqli_real_escape_string($koneksi, (string) $column);
    $result = mysqli_query($koneksi, "SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");

    if ($result === false) {
        throw new RuntimeException('Struktur database tidak dapat diperiksa: ' . mysqli_error($koneksi));
    }

    $exists = mysqli_num_rows($result) > 0;
    mysqli_free_result($result);
    return $exists;
}

function database_index_exists($table, $index)
{
    global $koneksi;

    $table = mysqli_real_escape_string($koneksi, (string) $table);
    $index = mysqli_real_escape_string($koneksi, (string) $index);
    $result = mysqli_query($koneksi, "SHOW INDEX FROM `{$table}` WHERE Key_name = '{$index}'");

    if ($result === false) {
        throw new RuntimeException('Indeks database tidak dapat diperiksa: ' . mysqli_error($koneksi));
    }

    $exists = mysqli_num_rows($result) > 0;
    mysqli_free_result($result);
    return $exists;
}

function ensure_application_schema()
{
    static $schema_ready = false;
    global $koneksi;

    if ($schema_ready) {
        return;
    }

    $doctor_columns = array(
        'jadwal_hari' => "ALTER TABLE doctors ADD COLUMN jadwal_hari VARCHAR(120) NOT NULL DEFAULT 'Senin - Jumat' AFTER alamat",
        'jam_mulai' => "ALTER TABLE doctors ADD COLUMN jam_mulai TIME NOT NULL DEFAULT '08:00:00' AFTER jadwal_hari",
        'jam_selesai' => "ALTER TABLE doctors ADD COLUMN jam_selesai TIME NOT NULL DEFAULT '16:00:00' AFTER jam_mulai",
    );

    foreach ($doctor_columns as $column => $sql) {
        if (!database_column_exists('doctors', $column) && !mysqli_query($koneksi, $sql)) {
            throw new RuntimeException('Kolom jadwal dokter belum dapat dibuat: ' . mysqli_error($koneksi));
        }
    }

    $users_sql = "CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nama_lengkap VARCHAR(120) NOT NULL,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(120) NOT NULL UNIQUE,
        profile_photo VARCHAR(255) DEFAULT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('Admin', 'Dokter', 'Petugas') NOT NULL,
        doctor_id INT UNSIGNED DEFAULT NULL,
        status ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Aktif',
        last_login_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_users_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON UPDATE CASCADE ON DELETE SET NULL,
        UNIQUE KEY uq_users_doctor (doctor_id),
        INDEX idx_users_role_status (role, status),
        INDEX idx_users_name (nama_lengkap)
    ) ENGINE=InnoDB";

    if (!mysqli_query($koneksi, $users_sql)) {
        throw new RuntimeException('Tabel akun belum dapat dibuat: ' . mysqli_error($koneksi));
    }

    $user_columns = array(
        'profile_photo' => 'ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) DEFAULT NULL AFTER email',
    );

    foreach ($user_columns as $column => $sql) {
        if (!database_column_exists('users', $column) && !mysqli_query($koneksi, $sql)) {
            throw new RuntimeException('Kolom profil akun belum dapat dibuat: ' . mysqli_error($koneksi));
        }
    }

    $login_attempts_sql = "CREATE TABLE IF NOT EXISTS auth_login_attempts (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        identifier_hash CHAR(64) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_login_attempts_lookup (identifier_hash, ip_address, attempted_at),
        INDEX idx_login_attempts_ip_time (ip_address, attempted_at)
    ) ENGINE=InnoDB";

    if (!mysqli_query($koneksi, $login_attempts_sql)) {
        throw new RuntimeException('Tabel keamanan login belum dapat dibuat: ' . mysqli_error($koneksi));
    }

    if (!database_index_exists('auth_login_attempts', 'idx_login_attempts_time')
        && !mysqli_query($koneksi, 'ALTER TABLE auth_login_attempts ADD INDEX idx_login_attempts_time (attempted_at)')) {
        throw new RuntimeException('Indeks keamanan login belum dapat dibuat: ' . mysqli_error($koneksi));
    }

    $archive_columns = array(
        'patients' => array(
            'archived_at' => 'ALTER TABLE patients ADD COLUMN archived_at DATETIME NULL DEFAULT NULL',
            'archived_by' => 'ALTER TABLE patients ADD COLUMN archived_by INT UNSIGNED NULL DEFAULT NULL',
        ),
        'doctors' => array(
            'archived_at' => 'ALTER TABLE doctors ADD COLUMN archived_at DATETIME NULL DEFAULT NULL',
            'archived_by' => 'ALTER TABLE doctors ADD COLUMN archived_by INT UNSIGNED NULL DEFAULT NULL',
        ),
        'polyclinics' => array(
            'archived_at' => 'ALTER TABLE polyclinics ADD COLUMN archived_at DATETIME NULL DEFAULT NULL',
            'archived_by' => 'ALTER TABLE polyclinics ADD COLUMN archived_by INT UNSIGNED NULL DEFAULT NULL',
        ),
        'medicines' => array(
            'archived_at' => 'ALTER TABLE medicines ADD COLUMN archived_at DATETIME NULL DEFAULT NULL',
            'archived_by' => 'ALTER TABLE medicines ADD COLUMN archived_by INT UNSIGNED NULL DEFAULT NULL',
        ),
    );

    foreach ($archive_columns as $table => $columns) {
        foreach ($columns as $column => $sql) {
            if (!database_column_exists($table, $column) && !mysqli_query($koneksi, $sql)) {
                throw new RuntimeException('Kolom arsip belum dapat dibuat pada ' . $table . ': ' . mysqli_error($koneksi));
            }
        }
    }

    $schema_ready = true;
}

function auth_login_throttle_seconds($identifier)
{
    $identifier_hash = auth_identifier_hash($identifier);
    $ip_address = request_client_ip();
    $pair_state = db_select_one("SELECT COUNT(*) AS attempts, MAX(attempted_at) AS last_attempt
        FROM auth_login_attempts
        WHERE identifier_hash = ? AND ip_address = ?
          AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)", array($identifier_hash, $ip_address));
    $ip_state = db_select_one("SELECT COUNT(*) AS attempts, MAX(attempted_at) AS last_attempt
        FROM auth_login_attempts
        WHERE ip_address = ?
          AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)", array($ip_address));

    $pair_attempts = (int) ($pair_state['attempts'] ?? 0);
    $ip_attempts = (int) ($ip_state['attempts'] ?? 0);
    $last_attempt = max(
        !empty($pair_state['last_attempt']) ? strtotime($pair_state['last_attempt']) : 0,
        !empty($ip_state['last_attempt']) ? strtotime($ip_state['last_attempt']) : 0
    );

    // Batasi kredensial yang sama secara ketat, dan tetap punya pagar untuk
    // serangan yang menggilir banyak username dari satu alamat IP.
    if (($pair_attempts >= 5 || $ip_attempts >= 20) && $last_attempt > 0) {
        return max(0, ($last_attempt + 60) - time());
    }

    return 0;
}

function record_auth_login_failure($identifier)
{
    db_execute('INSERT INTO auth_login_attempts (identifier_hash, ip_address) VALUES (?, ?)', array(
        auth_identifier_hash($identifier),
        request_client_ip(),
    ));
    // Retensi singkat cukup untuk throttling dan mencegah tabel tumbuh tanpa
    // batas pada instalasi klinik yang berjalan lama.
    db_execute('DELETE FROM auth_login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)', array());
}

function clear_auth_login_failures($identifier)
{
    db_execute('DELETE FROM auth_login_attempts WHERE identifier_hash = ? AND ip_address = ?', array(
        auth_identifier_hash($identifier),
        request_client_ip(),
    ));
}

function acquire_database_lock($name, $timeout = 5)
{
    $name = 'medikaflow:' . preg_replace('/[^a-zA-Z0-9:_-]/', '-', (string) $name);
    $timeout = max(0, min(30, (int) $timeout));
    $result = db_value('SELECT GET_LOCK(?, ?)', array($name, $timeout), null);
    if ((int) $result !== 1) {
        throw new RuntimeException('Sistem sedang memproses permintaan lain. Silakan coba lagi.');
    }

    return $name;
}

function release_database_lock($name)
{
    if ((string) $name === '') {
        return;
    }

    try {
        db_value('SELECT RELEASE_LOCK(?)', array((string) $name), null);
    } catch (Throwable $exception) {
        error_log('[MedikaFlow] Database lock release failed: ' . $exception->getMessage());
    }
}

function is_valid_calendar_date($date)
{
    $date = (string) $date;
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    return $parsed instanceof DateTime && $parsed->format('Y-m-d') === $date;
}

function is_valid_clock_time($time)
{
    $time = (string) $time;
    $parsed = DateTime::createFromFormat('!H:i', $time);
    return $parsed instanceof DateTime && $parsed->format('H:i') === $time;
}

function is_valid_nonnegative_decimal($value, $max_integer_digits = 12, $max_fraction_digits = 2)
{
    $value = trim((string) $value);
    if ($value === '' || !is_finite((float) $value)) {
        return false;
    }

    $pattern = '/^\d{1,' . (int) $max_integer_digits . '}(?:\.\d{1,' . (int) $max_fraction_digits . '})?$/D';
    return preg_match($pattern, $value) === 1;
}

function archive_entity_definitions()
{
    static $definitions = null;

    if ($definitions === null) {
        $definitions = array(
            'pasien' => array(
                'label' => 'Pasien',
                'table' => 'patients',
                'alias' => 'p',
                'name_column' => 'nama',
                'code_column' => 'no_rm',
                'meta_sql' => "CONCAT('NIK ', p.nik)",
                'search_columns' => array('p.nama', 'p.no_rm', 'p.nik', 'p.no_hp'),
                'join_sql' => '',
                'icon' => 'users-round',
                'theme' => 'cyan',
            ),
            'dokter' => array(
                'label' => 'Dokter',
                'table' => 'doctors',
                'alias' => 'd',
                'name_column' => 'nama_dokter',
                'code_column' => 'kode_dokter',
                'meta_sql' => "CONCAT('Spesialisasi ', s.nama)",
                'search_columns' => array('d.nama_dokter', 'd.kode_dokter', 's.nama', 'd.no_hp'),
                'join_sql' => 'INNER JOIN specializations s ON s.id = d.specialization_id',
                'icon' => 'stethoscope',
                'theme' => 'purple',
            ),
            'poli' => array(
                'label' => 'Poli',
                'table' => 'polyclinics',
                'alias' => 'p',
                'name_column' => 'nama_poli',
                'code_column' => 'kode_poli',
                'meta_sql' => "CONCAT(p.lokasi, IF(p.keterangan IS NULL OR p.keterangan = '', '', CONCAT(' · ', p.keterangan)))",
                'search_columns' => array('p.nama_poli', 'p.kode_poli', 'p.lokasi', 'p.keterangan'),
                'join_sql' => '',
                'icon' => 'building-2',
                'theme' => 'rose',
            ),
            'obat' => array(
                'label' => 'Obat',
                'table' => 'medicines',
                'alias' => 'm',
                'name_column' => 'nama_obat',
                'code_column' => 'kode_obat',
                'meta_sql' => "CONCAT(mc.nama_kategori, ' · ', m.satuan)",
                'search_columns' => array('m.nama_obat', 'm.kode_obat', 'mc.nama_kategori'),
                'join_sql' => 'INNER JOIN medicine_categories mc ON mc.id = m.category_id',
                'icon' => 'pill',
                'theme' => 'amber',
            ),
        );
    }

    return $definitions;
}

function archive_entity_definition($entity)
{
    $definitions = archive_entity_definitions();
    return $definitions[$entity] ?? null;
}

function archive_entity_record($entity, $id, $user_id)
{
    $definition = archive_entity_definition($entity);
    if (!$definition) {
        throw new RuntimeException('Jenis data arsip tidak valid.');
    }

    $id = (int) $id;
    if ($id < 1) {
        throw new RuntimeException('Data yang dipilih tidak valid.');
    }

    $record = db_select_one('SELECT id, archived_at FROM ' . $definition['table'] . ' WHERE id = ?', array($id));
    if (!$record) {
        throw new RuntimeException('Data ' . strtolower($definition['label']) . ' tidak ditemukan.');
    }
    if (!empty($record['archived_at'])) {
        throw new RuntimeException('Data tersebut sudah berada di Arsip.');
    }

    $archived_by = (int) $user_id > 0 ? (int) $user_id : null;
    $result = db_execute('UPDATE ' . $definition['table'] . ' SET archived_at = NOW(), archived_by = ? WHERE id = ? AND archived_at IS NULL', array($archived_by, $id));
    if ((int) $result['affected_rows'] !== 1) {
        throw new RuntimeException('Data belum dapat dipindahkan ke Arsip. Silakan muat ulang halaman.');
    }

    return $definition;
}

function restore_archive_entity($entity, $id)
{
    $definition = archive_entity_definition($entity);
    if (!$definition) {
        throw new RuntimeException('Jenis data arsip tidak valid.');
    }

    $id = (int) $id;
    $record = db_select_one('SELECT id, archived_at FROM ' . $definition['table'] . ' WHERE id = ?', array($id));
    if (!$record || empty($record['archived_at'])) {
        throw new RuntimeException('Data tersebut tidak ditemukan di Arsip.');
    }

    $result = db_execute('UPDATE ' . $definition['table'] . ' SET archived_at = NULL, archived_by = NULL WHERE id = ? AND archived_at IS NOT NULL', array($id));
    if ((int) $result['affected_rows'] !== 1) {
        throw new RuntimeException('Data belum dapat dipulihkan. Silakan muat ulang halaman.');
    }

    return $definition;
}

function permanently_delete_archive_entity($entity, $id)
{
    $definition = archive_entity_definition($entity);
    if (!$definition) {
        throw new RuntimeException('Jenis data arsip tidak valid.');
    }

    $id = (int) $id;
    $record = db_select_one('SELECT id, archived_at FROM ' . $definition['table'] . ' WHERE id = ?', array($id));
    if (!$record || empty($record['archived_at'])) {
        throw new RuntimeException('Data tersebut tidak ditemukan di Arsip.');
    }

    $result = db_execute('DELETE FROM ' . $definition['table'] . ' WHERE id = ? AND archived_at IS NOT NULL', array($id));
    if ((int) $result['affected_rows'] !== 1) {
        throw new RuntimeException('Data belum dapat dihapus permanen. Silakan muat ulang halaman.');
    }

    return $definition;
}

function current_route_key()
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $base_path = app_base_path();

    if ($base_path !== '' && strpos($script, $base_path . '/') === 0) {
        return ltrim(substr($script, strlen($base_path)), '/');
    }

    return ltrim($script, '/');
}

function auth_users_exist()
{
    ensure_application_schema();
    return (int) db_value('SELECT COUNT(*) FROM users', array(), 0) > 0;
}

function current_user($refresh = false)
{
    static $loaded = false;
    static $user = null;

    if ($refresh) {
        $loaded = false;
        $user = null;
    }

    if ($loaded) {
        return $user;
    }

    $loaded = true;
    $user_id = (int) ($_SESSION['auth_user_id'] ?? 0);
    if ($user_id <= 0) {
        return null;
    }

    ensure_application_schema();
    $user = db_select_one("SELECT u.id, u.nama_lengkap, u.username, u.email, u.profile_photo, u.role, u.doctor_id,
            u.status, u.last_login_at, u.created_at,
            d.kode_dokter, d.nama_dokter, d.jadwal_hari, d.jam_mulai, d.jam_selesai,
            d.status AS status_dokter, d.archived_at AS archived_at_dokter, s.nama AS spesialisasi,
            COALESCE((SELECT pc.nama_poli FROM polyclinics pc
                WHERE pc.status = 'Aktif' AND pc.archived_at IS NULL AND pc.nama_poli LIKE CONCAT('%', s.nama, '%')
                ORDER BY pc.id ASC LIMIT 1), 'Layanan klinik') AS ruang_layanan
        FROM users u
        LEFT JOIN doctors d ON d.id = u.doctor_id
        LEFT JOIN specializations s ON s.id = d.specialization_id
        WHERE u.id = ?
        LIMIT 1", array($user_id));

    return $user ?: null;
}

function user_role_label($user = null)
{
    $user = $user ?: current_user();
    if (!$user) {
        return 'Pengguna';
    }

    if ($user['role'] === 'Dokter' && !empty($user['spesialisasi'])) {
        return 'Dokter ' . $user['spesialisasi'];
    }

    return $user['role'];
}

function user_schedule($user = null)
{
    $user = $user ?: current_user();

    if ($user && $user['role'] === 'Dokter' && !empty($user['doctor_id'])) {
        $start = !empty($user['jam_mulai']) ? date('H.i', strtotime($user['jam_mulai'])) : '08.00';
        $end = !empty($user['jam_selesai']) ? date('H.i', strtotime($user['jam_selesai'])) : '16.00';
        return array(
            'label' => 'Jadwal praktik',
            'days' => $user['jadwal_hari'] ?: 'Belum diatur',
            'hours' => $start . ' - ' . $end,
            'room' => $user['ruang_layanan'] ?: 'Layanan klinik',
        );
    }

    return array(
        'label' => 'Akses sistem',
        'days' => $user ? 'Peran ' . $user['role'] : 'Belum masuk',
        'hours' => 'Sesi aktif',
        'room' => app_brand_name(),
    );
}

function available_modules_for_role($role)
{
    $modules = array(
        'Admin' => array('dashboard', 'pasien', 'dokter', 'poli', 'obat', 'kunjungan', 'pemeriksaan', 'resep', 'pembayaran', 'riwayat', 'akun', 'arsip', 'profil'),
        'Dokter' => array('dashboard', 'pasien', 'kunjungan', 'pemeriksaan', 'resep', 'riwayat', 'profil'),
        'Petugas' => array('dashboard', 'pasien', 'obat', 'kunjungan', 'pembayaran', 'riwayat', 'profil'),
    );

    return $modules[$role] ?? array('dashboard', 'profil');
}

function can_access_module($module, $role = null)
{
    if ($module === '' || $module === 'index.php' || $module === 'auth') {
        return true;
    }

    if ($role === null) {
        $user = current_user();
        $role = $user['role'] ?? '';
    }
    return in_array($module, available_modules_for_role($role), true);
}

function can_access_path($path, $role = null)
{
    $clean_path = (string) parse_url((string) $path, PHP_URL_PATH);
    $clean_path = ltrim(str_replace('\\', '/', $clean_path), '/');
    $base_path = ltrim(app_base_path(), '/');

    if ($base_path !== '' && strpos($clean_path, $base_path . '/') === 0) {
        $clean_path = substr($clean_path, strlen($base_path) + 1);
    }

    $module = explode('/', $clean_path)[0] ?? '';
    return can_access_module($module, $role);
}

function require_role($roles)
{
    $roles = (array) $roles;
    $user = current_user();

    if (!$user || !in_array($user['role'], $roles, true)) {
        flash('danger', 'Menu tersebut tidak tersedia untuk peran akun Anda.');
        redirect_to('dashboard/index.php');
    }
}

function validate_password_strength($password)
{
    if (strlen((string) $password) < 8) {
        return 'Password minimal 8 karakter.';
    }

    if (!preg_match('/[A-Za-z]/', (string) $password) || !preg_match('/[0-9]/', (string) $password)) {
        return 'Password harus memuat huruf dan angka.';
    }

    return '';
}

function attempt_login($identifier, $password)
{
    $identifier = strtolower(trim((string) $identifier));
    $user = db_select_one("SELECT u.*
        FROM users u
        LEFT JOIN doctors d ON d.id = u.doctor_id
        WHERE (u.username = ? OR u.email = ?)
          AND u.status = 'Aktif'
          AND (u.role <> 'Dokter' OR (d.status = 'Aktif' AND d.archived_at IS NULL))
        LIMIT 1", array($identifier, $identifier));

    if (!$user || !password_verify((string) $password, $user['password_hash'])) {
        return false;
    }

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        db_execute('UPDATE users SET password_hash = ? WHERE id = ?', array(password_hash((string) $password, PASSWORD_DEFAULT), (int) $user['id']));
    }

    session_regenerate_id(true);
    $_SESSION['auth_user_id'] = (int) $user['id'];
    $_SESSION['auth_last_activity'] = time();
    // Token lama tidak dipakai lintas sesi setelah privilege berubah.
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    unset($_SESSION['auth_failed_attempts'], $_SESSION['auth_locked_until']);
    db_execute('UPDATE users SET last_login_at = NOW() WHERE id = ?', array((int) $user['id']));
    clear_auth_login_failures($identifier);
    current_user(true);
    return true;
}

function logout_user()
{
    unset(
        $_SESSION['auth_user_id'],
        $_SESSION['auth_last_activity'],
        $_SESSION['auth_failed_attempts'],
        $_SESSION['auth_locked_until'],
        $_SESSION['active_doctor_id'],
        $_SESSION['csrf_token']
    );
    session_regenerate_id(true);
    current_user(true);
}

function auth_bootstrap()
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    ensure_application_schema();
    $route = current_route_key();
    $users_exist = auth_users_exist();
    $is_setup = $route === 'auth/setup.php';
    $is_login = $route === 'auth/login.php';
    $is_logout = $route === 'auth/logout.php';

    if (!$users_exist) {
        if (!$is_setup && !$is_logout) {
            redirect_to('auth/setup.php');
        }
        return;
    }

    if ($is_setup) {
        redirect_to(!empty($_SESSION['auth_user_id']) ? 'dashboard/index.php' : 'auth/login.php');
    }

    if ($is_login) {
        if (!empty($_SESSION['auth_user_id']) && current_user()) {
            redirect_to('dashboard/index.php');
        }
        return;
    }

    if ($is_logout) {
        return;
    }

    if (empty($_SESSION['auth_user_id'])) {
        redirect_to('auth/login.php');
    }

    if (!empty($_SESSION['auth_last_activity']) && time() - (int) $_SESSION['auth_last_activity'] > 28800) {
        logout_user();
        flash('danger', 'Sesi berakhir setelah tidak aktif selama 8 jam. Silakan masuk kembali.');
        redirect_to('auth/login.php');
    }

    $user = current_user(true);
    $doctor_profile_inactive = $user && $user['role'] === 'Dokter' && ($user['status_dokter'] !== 'Aktif' || !empty($user['archived_at_dokter']));
    if (!$user || $user['status'] !== 'Aktif' || $doctor_profile_inactive) {
        logout_user();
        flash('danger', $doctor_profile_inactive ? 'Profil dokter yang terhubung sedang nonaktif atau berada di Arsip.' : 'Akun tidak aktif atau sudah tidak tersedia.');
        redirect_to('auth/login.php');
    }

    $_SESSION['auth_last_activity'] = time();
    $module = explode('/', $route)[0] ?? '';
    if (!can_access_module($module, $user['role'])) {
        flash('danger', 'Menu tersebut tidak tersedia untuk peran ' . $user['role'] . '.');
        redirect_to('dashboard/index.php');
    }
}

function format_currency($value)
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

function format_date_id($date, $include_time = false)
{
    if (!$date) {
        return '-';
    }

    $months = array(
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    );
    $timestamp = strtotime($date);
    $formatted = date('d', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . date('Y', $timestamp);

    if ($include_time) {
        $formatted .= ' · ' . date('H:i', $timestamp);
    }

    return $formatted;
}

function format_date_long_id($date)
{
    if (!$date) {
        return '-';
    }

    $days = array('Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu');
    $months = array(
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    );
    $timestamp = strtotime($date);

    return $days[(int) date('w', $timestamp)] . ', ' . date('d', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . date('Y', $timestamp);
}

function format_gender($gender)
{
    return $gender === 'L' ? 'Laki-laki' : 'Perempuan';
}

function calculate_age($birth_date)
{
    if (!$birth_date) {
        return '-';
    }

    $birth_timestamp = strtotime($birth_date);
    $age = (int) date('Y') - (int) date('Y', $birth_timestamp);
    if (date('md') < date('md', $birth_timestamp)) {
        $age--;
    }

    return $age . ' tahun';
}

function initials($name)
{
    $words = preg_split('/\s+/', trim((string) $name));
    $words = array_values(array_filter($words));

    if (empty($words)) {
        return '--';
    }

    $result = strtoupper(substr($words[0], 0, 1));
    if (count($words) > 1) {
        $result .= strtoupper(substr($words[count($words) - 1], 0, 1));
    }

    return $result;
}

function profile_photo_storage_directory()
{
    $directory = __DIR__ . '/../storage/profile-photos';

    if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder penyimpanan foto profil belum dapat dibuat.');
    }

    return $directory;
}

function profile_photo_filename($filename)
{
    $filename = strtolower(trim((string) $filename));

    if ($filename === '' || !preg_match('/\A[a-f0-9]{48}\.(jpg|png|webp)\z/', $filename)) {
        return null;
    }

    return $filename;
}

function profile_photo_file_path($filename)
{
    $filename = profile_photo_filename($filename);
    if ($filename === null) {
        return null;
    }

    return profile_photo_storage_directory() . DIRECTORY_SEPARATOR . $filename;
}

function profile_photo_mime_type($path)
{
    if (!is_string($path) || !is_file($path) || !function_exists('finfo_open')) {
        return null;
    }

    $file_info = @finfo_open(FILEINFO_MIME_TYPE);
    if (!$file_info) {
        return null;
    }

    $mime = @finfo_file($file_info, $path);
    finfo_close($file_info);

    return in_array($mime, array('image/jpeg', 'image/png', 'image/webp'), true) ? $mime : null;
}

function profile_photo_upload($field = 'profile_photo')
{
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
        return null;
    }

    $file = $_FILES[$field];
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($error !== UPLOAD_ERR_OK) {
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new RuntimeException('Foto profil terlalu besar. Ukuran maksimalnya 2 MB.');
        }

        throw new RuntimeException('Foto profil belum dapat dibaca. Pilih file gambar lain.');
    }

    $temporary_path = (string) ($file['tmp_name'] ?? '');
    $file_size = (int) ($file['size'] ?? 0);
    if ($temporary_path === '' || !is_uploaded_file($temporary_path)) {
        throw new RuntimeException('Upload foto profil tidak valid.');
    }

    if ($file_size <= 0 || $file_size > 2 * 1024 * 1024) {
        throw new RuntimeException('Foto profil terlalu besar. Ukuran maksimalnya 2 MB.');
    }

    $mime = profile_photo_mime_type($temporary_path);
    $extensions = array(
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    );
    if (!isset($extensions[$mime])) {
        throw new RuntimeException('Format foto profil harus JPG, PNG, atau WebP.');
    }

    $image_size = @getimagesize($temporary_path);
    if ($image_size === false || (int) ($image_size[0] ?? 0) <= 0 || (int) ($image_size[1] ?? 0) <= 0) {
        throw new RuntimeException('File yang dipilih bukan gambar yang valid.');
    }

    if ((int) $image_size[0] > 4000 || (int) $image_size[1] > 4000) {
        throw new RuntimeException('Resolusi foto profil terlalu besar. Gunakan gambar maksimal 4000 × 4000 piksel.');
    }

    $filename = bin2hex(random_bytes(24)) . '.' . $extensions[$mime];
    $destination = profile_photo_file_path($filename);
    if ($destination === null || !@move_uploaded_file($temporary_path, $destination)) {
        throw new RuntimeException('Foto profil belum dapat disimpan. Periksa izin folder storage.');
    }

    return $filename;
}

function profile_photo_delete($filename)
{
    $path = profile_photo_file_path($filename);
    if ($path !== null && is_file($path) && !@unlink($path)) {
        error_log('[MedikaFlow] Foto profil lama tidak dapat dihapus: ' . $filename);
    }
}

function profile_photo_url($user)
{
    if (!is_array($user)) {
        return '';
    }

    $user_id = (int) ($user['id'] ?? 0);
    $filename = profile_photo_filename($user['profile_photo'] ?? '');
    if ($user_id <= 0 || $filename === null) {
        return '';
    }

    $cache_key = substr(hash('sha256', $filename), 0, 12);
    return base_url('profil/foto.php?id=' . $user_id . '&v=' . rawurlencode($cache_key));
}

function user_avatar_content($user)
{
    $user = is_array($user) ? $user : array();
    $photo_url = profile_photo_url($user);

    if ($photo_url !== '') {
        return '<img class="avatar-photo" src="' . e($photo_url) . '" alt="" decoding="async">';
    }

    return e(initials($user['nama_lengkap'] ?? ''));
}

function doctor_schedule($doctor)
{
    if (!is_array($doctor)) {
        $doctor = db_select_one("SELECT d.*, s.nama AS spesialisasi,
                COALESCE((SELECT pc.nama_poli FROM polyclinics pc
                    WHERE pc.status = 'Aktif' AND pc.archived_at IS NULL AND pc.nama_poli LIKE CONCAT('%', s.nama, '%')
                    ORDER BY pc.id ASC LIMIT 1), 'Layanan klinik') AS ruang_layanan
            FROM doctors d
            INNER JOIN specializations s ON s.id = d.specialization_id
            WHERE d.kode_dokter = ?
            LIMIT 1", array((string) $doctor));
    }

    if (!$doctor) {
        return array('days' => 'Belum diatur', 'hours' => '-', 'room' => 'Layanan klinik');
    }

    $start = !empty($doctor['jam_mulai']) ? date('H.i', strtotime($doctor['jam_mulai'])) : '08.00';
    $end = !empty($doctor['jam_selesai']) ? date('H.i', strtotime($doctor['jam_selesai'])) : '16.00';

    return array(
        'days' => $doctor['jadwal_hari'] ?: 'Belum diatur',
        'hours' => $start . ' - ' . $end,
        'room' => $doctor['ruang_layanan'] ?? 'Layanan klinik',
    );
}

function active_doctor_profile()
{
    $user = current_user();
    if (!$user || empty($user['doctor_id'])) {
        return null;
    }

    $profile = array(
        'id' => $user['doctor_id'],
        'kode_dokter' => $user['kode_dokter'],
        'nama_dokter' => $user['nama_dokter'],
        'status' => $user['status_dokter'],
        'spesialisasi' => $user['spesialisasi'],
        'jadwal_hari' => $user['jadwal_hari'],
        'jam_mulai' => $user['jam_mulai'],
        'jam_selesai' => $user['jam_selesai'],
        'ruang_layanan' => $user['ruang_layanan'],
    );
    $profile['schedule'] = doctor_schedule($profile);
    return $profile;
}

function status_class($status)
{
    $value = strtolower(str_replace(array(' ', '/'), '-', trim((string) $status)));
    return 'status-pill status-' . preg_replace('/[^a-z0-9-]/', '', $value);
}

function status_icon($status)
{
    $status = strtolower((string) $status);
    if (in_array($status, array('selesai', 'sudah dibayar', 'diselesaikan', 'aktif'), true)) {
        return icon('check');
    }

    if (in_array($status, array('batal', 'nonaktif', 'expired'), true)) {
        return icon('x');
    }

    return icon('clock-3');
}

function icon($name, $class = '')
{
    $paths = array(
        'activity' => '<path d="M3 12h4l2-8 4 16 2-8h6"/>',
        'alert-triangle' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.46 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'archive' => '<polyline points="21 8 21 21 3 21 3 8"/><rect width="18" height="5" x="3" y="3" rx="1"/><path d="M10 12h4"/>',
        'arrow-left' => '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
        'arrow-right' => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
        'arrow-up-right' => '<path d="M7 17 17 7"/><path d="M7 7h10v10"/>',
        'badge-check' => '<path d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.77 4 4 0 0 1 0 6.76 4 4 0 0 1-4.78 4.77 4 4 0 0 1-6.74 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.76Z"/><path d="m9 12 2 2 4-4"/>',
        'building-2' => '<path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v8h20v-8a2 2 0 0 0-2-2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/>',
        'calendar-days' => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'check-circle-2' => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'chevron-left' => '<path d="m15 18-6-6 6-6"/>',
        'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
        'circle-dollar-sign' => '<circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18V6"/>',
        'clipboard-check' => '<rect width="14" height="16" x="5" y="4" rx="2"/><path d="M9 4V2h6v2"/><path d="m9 12 2 2 4-4"/>',
        'clipboard-list' => '<rect width="8" height="4" x="8" y="2" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14H4V6a2 2 0 0 1 2-2h2"/><path d="M8 10h8"/><path d="M8 14h8"/><path d="M8 18h5"/>',
        'clipboard-plus' => '<rect width="8" height="4" x="8" y="2" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14H4V6a2 2 0 0 1 2-2h2"/><path d="M12 10v6"/><path d="M9 13h6"/>',
        'clock-3' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'download' => '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/>',
        'edit-3' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'eye' => '<path d="M2.06 12.35a1 1 0 0 1 0-.7C3.42 7.7 7.36 5 12 5c4.64 0 8.58 2.7 9.94 6.65a1 1 0 0 1 0 .7C20.58 16.3 16.64 19 12 19c-4.64 0-8.58-2.7-9.94-6.65Z"/><circle cx="12" cy="12" r="3"/>',
        'file-clock' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v6h6"/><circle cx="12" cy="15" r="3"/><path d="M12 13.5V15l1 1"/>',
        'file-text' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h5"/>',
        'filter' => '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>',
        'grid-2x2' => '<rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/>',
        'layout-dashboard' => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
        'log-out' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>',
        'map-pin' => '<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'menu' => '<line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="18" y2="18"/>',
        'more-horizontal' => '<circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>',
        'notepad-text' => '<rect width="16" height="18" x="4" y="3" rx="2"/><path d="M8 8h8"/><path d="M8 12h8"/><path d="M8 16h5"/>',
        'pill' => '<path d="m10.5 20.5 9-9a5 5 0 0 0-7-7l-9 9a5 5 0 0 0 7 7Z"/><path d="m8.5 8.5 7 7"/>',
        'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.33 1.78.62 2.63a2 2 0 0 1-.45 2.11L8 9.73a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.85.29 1.73.5 2.63.62A2 2 0 0 1 22 16.92Z"/>',
        'plus' => '<path d="M5 12h14"/><path d="M12 5v14"/>',
        'printer' => '<path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/>',
        'receipt' => '<path d="M4 2v20l2-2 2 2 2-2 2 2 2-2 2 2 2-2 2 2V2l-2 2-2-2-2 2-2-2-2 2-2-2-2 2Z"/><path d="M8 10h8"/><path d="M8 14h5"/>',
        'refresh-cw' => '<path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'settings-2' => '<path d="M20 7h-9"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/>',
        'shield-check' => '<path d="M20 13c0 5-3.5 7.5-8 9-4.5-1.5-8-4-8-9V5l8-3 8 3Z"/><path d="m9 12 2 2 4-4"/>',
        'stethoscope' => '<path d="M4.8 3.5a2.5 2.5 0 0 1 5 0v5a5 5 0 0 1-10 0v-5a2.5 2.5 0 0 1 5 0"/><path d="M7.3 13.5V16a4 4 0 0 0 8 0v-3"/><path d="M15.3 6.5h2a3 3 0 1 1-3 3"/><circle cx="17.3" cy="6.5" r="1"/>',
        'trash-2' => '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6v14H5V6"/><path d="M10 11v5"/><path d="M14 11v5"/>',
        'trending-up' => '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>',
        'undo-2' => '<path d="M9 14 4 9l5-5"/><path d="M4 9h10a6 6 0 0 1 0 12h-1"/>',
        'user-round' => '<circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/>',
        'user-plus' => '<circle cx="9" cy="8" r="5"/><path d="M16 19h6"/><path d="M19 16v6"/><path d="M2 21a7 7 0 0 1 14 0"/>',
        'users-round' => '<path d="M18 21a8 8 0 0 0-16 0"/><circle cx="10" cy="8" r="5"/><path d="M22 21a8 8 0 0 0-6-7.75"/><path d="M16 3.13a5 5 0 0 1 0 9.75"/>',
        'wallet' => '<path d="M20 7V5a2 2 0 0 0-2-2H5a3 3 0 0 0 0 6h15v10a2 2 0 0 1-2 2H5a3 3 0 0 1-3-3V6"/><path d="M16 13h2"/>',
        'x' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
    );

    $path = $paths[$name] ?? $paths['grid-2x2'];
    $class_attribute = $class === '' ? '' : ' class="' . e($class) . '"';

    return '<svg' . $class_attribute . ' width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
}

function render_errors($errors)
{
    if (empty($errors)) {
        return '';
    }

    $html = '<div class="alert alert-danger" role="alert">' . icon('alert-triangle') . '<div><strong>Periksa kembali data Anda.</strong><ul class="error-list">';
    foreach ($errors as $error) {
        $html .= '<li>' . e($error) . '</li>';
    }
    $html .= '</ul></div></div>';
    return $html;
}

function render_empty_state($icon_name, $title, $description, $action_html = '')
{
    return '<div class="empty-state"><div class="empty-icon">' . icon($icon_name) . '</div><h3>' . e($title) . '</h3><p>' . e($description) . '</p>' . $action_html . '</div>';
}

/*
 * Page loading skeletons
 *
 * Skeleton markup is rendered beside the real page markup so the browser can
 * reserve the same visual rhythm before the page becomes interactive. Counts
 * intentionally come from the arrays already queried by each route; this
 * keeps the loading state aligned with the current database response after a
 * create, edit, archive, restore, or filter action.
 */
function clinic_skeleton_block($class = '', $style = '')
{
    $class = trim('loading-skeleton ' . $class);
    $style_attribute = $style !== '' ? ' style="' . e($style) . '"' : '';
    return '<span class="' . e($class) . '"' . $style_attribute . '></span>';
}

function clinic_skeleton_context_number($context, $key, $fallback = 0)
{
    if (array_key_exists($key, $context) && is_numeric($context[$key])) {
        return max(0, (int) $context[$key]);
    }

    return max(0, (int) $fallback);
}

function clinic_skeleton_value_class($value, $prefix = 'skeleton-stat-value')
{
    $digits = strlen((string) abs((int) $value));
    $digits = min(max($digits, 1), 4);
    return $prefix . '-digits-' . $digits;
}

function clinic_skeleton_dashboard_chart($context)
{
    $chart = isset($context['chart']) && is_array($context['chart']) ? $context['chart'] : array();
    $width = isset($chart['width']) ? max(1, (int) $chart['width']) : 720;
    $height = isset($chart['height']) ? max(1, (int) $chart['height']) : 250;
    $points = isset($context['chart_points']) && is_array($context['chart_points']) ? $context['chart_points'] : array();
    $safe_points = array();

    foreach ($points as $point) {
        if (!is_array($point) || !isset($point['x'], $point['y']) || !is_numeric($point['x']) || !is_numeric($point['y'])) {
            continue;
        }

        $safe_points[] = array(
            'x' => round((float) $point['x'], 1),
            'y' => round((float) $point['y'], 1),
            'is_today' => !empty($point['is_today']),
        );
    }

    if (count($safe_points) < 2) {
        $left = isset($chart['left']) ? (float) $chart['left'] : 42;
        $right = isset($chart['right']) ? (float) $chart['right'] : 24;
        $bottom = isset($chart['bottom']) ? (float) $chart['bottom'] : 196;
        $step = ($width - $left - $right) / 6;
        $safe_points = array();
        for ($index = 0; $index < 7; $index++) {
            $safe_points[] = array(
                'x' => round($left + ($step * $index), 1),
                'y' => $bottom,
                'is_today' => $index === 6,
            );
        }
    }

    $line_points = array();
    foreach ($safe_points as $point) {
        $line_points[] = e($point['x'] . ',' . $point['y']);
    }

    $first_point = $safe_points[0];
    $last_point = $safe_points[count($safe_points) - 1];
    $bottom = isset($chart['bottom']) ? (float) $chart['bottom'] : 196;
    $area_path = 'M ' . e($first_point['x']) . ' ' . e($bottom) . ' L ' . implode(' ', $line_points) . ' L ' . e($last_point['x']) . ' ' . e($bottom) . ' Z';
    $html = '<div class="skeleton-chart-area">'
        . '<svg class="skeleton-chart-svg" viewBox="0 0 ' . e($width) . ' ' . e($height) . '" preserveAspectRatio="none" aria-hidden="true" focusable="false">'
        . '<path class="skeleton-chart-area-fill" d="' . e($area_path) . '"></path>'
        . '<polyline class="skeleton-chart-line-svg" points="' . implode(' ', $line_points) . '"></polyline>';

    foreach ($safe_points as $point) {
        $html .= '<circle class="skeleton-chart-point-svg' . ($point['is_today'] ? ' skeleton-chart-point-svg-today' : '') . '" cx="' . e($point['x']) . '" cy="' . e($point['y']) . '" r="5"></circle>';
    }

    return $html . '</svg>'
        . clinic_skeleton_block('skeleton-chart-axis skeleton-chart-axis-one')
        . clinic_skeleton_block('skeleton-chart-axis skeleton-chart-axis-two')
        . '</div>';
}

function clinic_skeleton_count($context, $keys, $fallback = 3, $max = 8)
{
    foreach ((array) $keys as $key) {
        if (!array_key_exists($key, $context) || !is_array($context[$key])) {
            continue;
        }

        $count = count($context[$key]);
        if ($count > 0) {
            return min($count, $max);
        }
    }

    return min(max((int) $fallback, 1), $max);
}

function clinic_skeleton_panel_header($action = true)
{
    $html = '<div class="skeleton-panel-header"><div class="skeleton-panel-heading">'
        . clinic_skeleton_block('skeleton-heading-line')
        . clinic_skeleton_block('skeleton-caption-line')
        . '</div>';

    if ($action) {
        $html .= clinic_skeleton_block('skeleton-action-line');
    }

    return $html . '</div>';
}

function clinic_skeleton_column_count($variant)
{
    $columns = array(
        'dashboard' => 5,
        'kunjungan' => 7,
        'pemeriksaan' => 6,
        'resep' => 7,
        'pembayaran' => 6,
        'riwayat' => 7,
        'pasien' => 6,
        'dokter' => 6,
        'poli' => 5,
        'obat' => 7,
        'akun' => 6,
    );

    return $columns[$variant] ?? 5;
}

function clinic_skeleton_has_status($variant)
{
    return in_array($variant, array('dashboard', 'kunjungan', 'pemeriksaan', 'resep', 'pembayaran', 'riwayat', 'dokter', 'poli', 'obat', 'akun'), true);
}

function clinic_skeleton_table_rows($count, $variant = 'default')
{
    $html = '';
    $count = max(1, (int) $count);
    $column_count = clinic_skeleton_column_count($variant);
    $has_status = clinic_skeleton_has_status($variant);

    for ($row = 0; $row < $count; $row++) {
        $html .= '<div class="skeleton-table-row skeleton-table-row-' . e($variant) . '">';

        for ($cell = 0; $cell < $column_count; $cell++) {
            if ($cell === 0) {
                $html .= '<div class="skeleton-table-cell skeleton-table-cell-primary">'
                    . clinic_skeleton_block('skeleton-cell-line skeleton-cell-line-strong')
                    . clinic_skeleton_block('skeleton-cell-line skeleton-cell-line-short')
                    . '</div>';
                continue;
            }

            if ($cell === $column_count - 1) {
                $html .= '<div class="skeleton-table-cell skeleton-table-cell-action">' . clinic_skeleton_block('skeleton-cell-action') . '</div>';
                continue;
            }

            if ($has_status && $cell === $column_count - 2) {
                $html .= '<div class="skeleton-table-cell">' . clinic_skeleton_block('skeleton-cell-pill') . '</div>';
                continue;
            }

            $line_class = $cell % 2 === 0 ? 'skeleton-cell-line-medium' : 'skeleton-cell-line-short';
            $html .= '<div class="skeleton-table-cell">' . clinic_skeleton_block('skeleton-cell-line ' . $line_class) . '</div>';
        }

        $html .= '</div>';
    }

    return $html;
}

function clinic_skeleton_list_summary($page_key)
{
    $summary_count = 0;
    if (in_array($page_key, array('kunjungan', 'riwayat'), true)) {
        $summary_count = 3;
    } elseif ($page_key === 'akun') {
        $summary_count = 3;
    } elseif ($page_key === 'arsip') {
        $summary_count = 4;
    }

    if ($summary_count === 0) {
        return '';
    }

    $html = '<div class="skeleton-summary-grid skeleton-summary-' . e($page_key) . '">';
    for ($index = 0; $index < $summary_count; $index++) {
        $html .= '<div class="skeleton-summary-card">'
            . clinic_skeleton_block('skeleton-summary-label')
            . clinic_skeleton_block('skeleton-summary-value')
            . clinic_skeleton_block('skeleton-summary-note')
            . '</div>';
    }

    return $html . '</div>';
}

function clinic_skeleton_toolbar()
{
    return '<div class="skeleton-toolbar">'
        . clinic_skeleton_block('skeleton-toolbar-search')
        . clinic_skeleton_block('skeleton-toolbar-select')
        . clinic_skeleton_block('skeleton-toolbar-button')
        . '</div>';
}

function clinic_skeleton_list($page_key, $context)
{
    $data_keys = array(
        'kunjungan' => array('visits'),
        'pemeriksaan' => array('visits'),
        'resep' => array('prescriptions'),
        'pembayaran' => array('payments'),
        'riwayat' => array('visits'),
        'pasien' => array('patients'),
        'dokter' => array('doctors'),
        'poli' => array('polyclinics'),
        'obat' => array('medicines'),
        'akun' => array('accounts'),
        'arsip' => array('archive_entries'),
    );
    $row_count = clinic_skeleton_count($context, $data_keys[$page_key] ?? array(), 4, 20);
    $table_variant = $page_key === 'arsip' ? 'default' : $page_key;

    if ($page_key === 'arsip') {
        $archive_entries = isset($context['archive_entries']) && is_array($context['archive_entries']) ? $context['archive_entries'] : array();
        $archive_body = '';
        if (count($archive_entries) > 0) {
            $archive_body = '<div class="skeleton-archive-entries">' . clinic_skeleton_table_rows($row_count, 'arsip') . '</div>';
        } else {
            $archive_body = '<div class="skeleton-empty-state">'
                . clinic_skeleton_block('skeleton-empty-icon')
                . clinic_skeleton_block('skeleton-empty-title')
                . clinic_skeleton_block('skeleton-empty-copy')
                . clinic_skeleton_block('skeleton-empty-copy skeleton-empty-copy-short')
                . '</div>';
        }

        return clinic_skeleton_hero($context)
            . clinic_skeleton_list_summary($page_key)
            . '<div class="skeleton-archive-guidance">'
            . clinic_skeleton_block('skeleton-guidance-icon')
            . '<div>' . clinic_skeleton_block('skeleton-guidance-title') . clinic_skeleton_block('skeleton-guidance-copy') . '</div>'
            . '</div>'
            . clinic_skeleton_toolbar()
            . '<section class="skeleton-panel skeleton-list-panel skeleton-list-arsip">'
            . clinic_skeleton_panel_header(false)
            . $archive_body
            . '</section>';
    }

    return clinic_skeleton_hero($context)
        . clinic_skeleton_list_summary($page_key)
        . clinic_skeleton_toolbar()
        . '<section class="skeleton-panel skeleton-list-panel skeleton-list-' . e($page_key) . '">'
        . clinic_skeleton_panel_header(false)
        . '<div class="skeleton-table skeleton-table-' . e($table_variant) . '" data-skeleton-row-count="' . e($row_count) . '">'
        . clinic_skeleton_table_head($table_variant)
        . clinic_skeleton_table_rows($row_count, $table_variant)
        . '</div></section>';
}

function clinic_skeleton_table_head($variant)
{
    $html = '<div class="skeleton-table-head">';
    $column_count = clinic_skeleton_column_count($variant);
    for ($index = 0; $index < $column_count; $index++) {
        $html .= clinic_skeleton_block('skeleton-head-line');
    }

    return $html . '</div>';
}

function clinic_skeleton_hero($context = array())
{
    $action_markup = trim((string) ($context['page_action_html'] ?? ''));
    $action_count = min(3, substr_count($action_markup, '<a '));
    $has_actions = $action_count > 0;
    $is_dashboard = ($context['active_menu'] ?? '') === 'dashboard';
    $hero_classes = array();
    if (!$has_actions) {
        $hero_classes[] = 'skeleton-page-hero-no-actions';
    }
    if ($is_dashboard) {
        $hero_classes[] = 'skeleton-page-hero-dashboard';
    }
    $hero_class = empty($hero_classes) ? '' : ' ' . implode(' ', $hero_classes);
    $html = '<div class="skeleton-page-hero' . $hero_class . '">'
        . '<div class="skeleton-page-hero-copy">'
        . clinic_skeleton_block('skeleton-eyebrow')
        . clinic_skeleton_block('skeleton-title')
        . clinic_skeleton_block('skeleton-copy skeleton-copy-wide')
        . clinic_skeleton_block('skeleton-copy skeleton-copy-medium')
        . '</div>';

    if ($is_dashboard) {
        $html .= '<div class="skeleton-dashboard-hero-media">'
            . '<img class="skeleton-dashboard-hero-image" src="' . e(base_url('assets/images/medikaflow-dashboard-hero-v4.png?v=20260901-1')) . '" alt="" width="2172" height="724" decoding="async">'
            . '<span class="dashboard-hero-blur dashboard-hero-blur-left"></span>'
            . '<span class="dashboard-hero-blur dashboard-hero-blur-mid"></span>'
            . '<span class="dashboard-hero-blur dashboard-hero-blur-near"></span>'
            . '<span class="dashboard-hero-scrim"></span>'
            . '<span class="skeleton-dashboard-hero-live loading-skeleton"><i></i><span></span></span>'
            . '<span class="skeleton-dashboard-hero-shimmer loading-skeleton"></span>'
            . '</div>';
    } else {
        $html .= '<div class="skeleton-hero-visual">'
            . clinic_skeleton_block('skeleton-hero-card')
            . clinic_skeleton_block('skeleton-hero-badge')
            . '</div>';
    }

    if ($has_actions) {
        $html .= '<div class="skeleton-hero-actions">';
        for ($index = 0; $index < $action_count; $index++) {
            $html .= clinic_skeleton_block($index === 0 ? 'skeleton-action-button skeleton-action-button-primary' : 'skeleton-action-button skeleton-action-button-secondary');
        }
        $html .= '</div>';
    }

    return $html . '</div>';
}

function clinic_skeleton_dashboard($context)
{
    $recent_count = clinic_skeleton_count($context, array('recent_visits'), 3, 5);
    $notice_count = clinic_skeleton_count($context, array('medicine_alerts'), 2, 5);
    $expired_count = (int) ($context['expired_count'] ?? 0);
    $today_visits = clinic_skeleton_context_number($context, 'today_visits');
    $today_waiting = clinic_skeleton_context_number($context, 'today_waiting');
    $today_finished = clinic_skeleton_context_number($context, 'today_finished');
    $progress = min(100, clinic_skeleton_context_number($context, 'progress'));
    $chart_points = isset($context['chart_points']) && is_array($context['chart_points']) ? $context['chart_points'] : array();
    $selected_value = 0;
    if (!empty($chart_points)) {
        $last_chart_point = $chart_points[count($chart_points) - 1];
        if (is_array($last_chart_point) && isset($last_chart_point['value']) && is_numeric($last_chart_point['value'])) {
            $selected_value = max(0, (int) $last_chart_point['value']);
        }
    }

    $stat_cards = array(
        array(
            'tone' => 'teal',
            'value' => clinic_skeleton_context_number($context, 'total_patients'),
            'label_class' => 'skeleton-stat-label-wide',
            'note_strong_class' => 'skeleton-stat-note-strong-medium',
            'note_copy_class' => 'skeleton-stat-note-copy-wide',
            'link_class' => 'skeleton-stat-link-wide',
        ),
        array(
            'tone' => 'blue',
            'value' => clinic_skeleton_context_number($context, 'total_doctors'),
            'label_class' => 'skeleton-stat-label-medium',
            'note_strong_class' => 'skeleton-stat-note-strong-short',
            'note_copy_class' => 'skeleton-stat-note-copy-medium',
            'link_class' => 'skeleton-stat-link-medium',
        ),
        array(
            'tone' => 'amber',
            'value' => clinic_skeleton_context_number($context, 'total_medicines'),
            'label_class' => 'skeleton-stat-label-wide',
            'note_strong_class' => 'skeleton-stat-note-strong-wide',
            'note_copy_class' => 'skeleton-stat-note-copy-medium',
            'link_class' => 'skeleton-stat-link-wide',
        ),
        array(
            'tone' => 'violet',
            'value' => $today_visits,
            'label_class' => 'skeleton-stat-label-widest',
            'note_strong_class' => 'skeleton-stat-note-strong-wide',
            'note_copy_class' => 'skeleton-stat-note-copy-short',
            'link_class' => 'skeleton-stat-link-medium',
        ),
    );

    $html = clinic_skeleton_hero($context);

    $html .= '<section class="skeleton-stat-grid" data-skeleton-stat-count="' . e(count($stat_cards)) . '">';
    foreach ($stat_cards as $stat) {
        $html .= '<div class="skeleton-stat-card skeleton-stat-card-' . e($stat['tone']) . '" data-skeleton-stat-value="' . e($stat['value']) . '">'
            . '<div class="skeleton-stat-top">'
            . clinic_skeleton_block('skeleton-stat-label ' . $stat['label_class'])
            . clinic_skeleton_block('skeleton-stat-icon')
            . '</div>'
            . clinic_skeleton_block('skeleton-stat-value ' . clinic_skeleton_value_class($stat['value']))
            . '<div class="skeleton-stat-note">'
            . clinic_skeleton_block('skeleton-stat-note-strong ' . $stat['note_strong_class'])
            . clinic_skeleton_block('skeleton-stat-note-copy ' . $stat['note_copy_class'])
            . '</div>'
            . '<div class="skeleton-stat-link">'
            . clinic_skeleton_block('skeleton-stat-link-label ' . $stat['link_class'])
            . clinic_skeleton_block('skeleton-stat-link-arrow')
            . '</div>'
            . '</div>';
    }
    $html .= '</section>';

    $html .= '<section class="skeleton-dashboard-grid">'
        . '<article class="skeleton-panel skeleton-chart-panel">'
        . clinic_skeleton_panel_header(true)
        . clinic_skeleton_dashboard_chart($context)
        . '<div class="skeleton-chart-insight">'
        . clinic_skeleton_block('skeleton-insight-label')
        . clinic_skeleton_block('skeleton-insight-value ' . clinic_skeleton_value_class($selected_value, 'skeleton-insight-value'))
        . '</div>'
        . '<div class="skeleton-chart-scrubber">' . clinic_skeleton_block('skeleton-scrubber-track') . '</div>'
        . '</article>'
        . '<article class="skeleton-panel skeleton-queue-panel">'
        . clinic_skeleton_panel_header(true)
        . '<div class="skeleton-queue-summary">'
        . clinic_skeleton_block('skeleton-queue-ring', '--skeleton-ring-progress: ' . $progress . '%;')
        . '<div class="skeleton-queue-copy">'
        . clinic_skeleton_block('skeleton-queue-label')
        . clinic_skeleton_block('skeleton-queue-title')
        . clinic_skeleton_block('skeleton-queue-note')
        . '</div></div>'
        . '<div class="skeleton-progress-line">' . clinic_skeleton_block('skeleton-progress-fill', '--skeleton-progress-width: ' . $progress . '%;') . '</div>'
        . '<div class="skeleton-mini-stats">'
        . '<div>' . clinic_skeleton_block('skeleton-mini-label') . clinic_skeleton_block('skeleton-mini-value ' . clinic_skeleton_value_class($today_waiting, 'skeleton-mini-value')) . '</div>'
        . '<div>' . clinic_skeleton_block('skeleton-mini-label') . clinic_skeleton_block('skeleton-mini-value ' . clinic_skeleton_value_class($today_finished, 'skeleton-mini-value')) . '</div>'
        . '</div></article></section>';

    $html .= '<section class="skeleton-dashboard-grid skeleton-dashboard-bottom">'
        . '<article class="skeleton-panel skeleton-recent-panel">'
        . clinic_skeleton_panel_header(true)
        . '<div class="skeleton-table skeleton-table-dashboard" data-skeleton-row-count="' . e($recent_count) . '">'
        . clinic_skeleton_table_head('dashboard')
        . clinic_skeleton_table_rows($recent_count, 'dashboard')
        . '</div></article>'
        . '<article class="skeleton-panel skeleton-attention-panel">'
        . clinic_skeleton_panel_header(true)
        . '<div class="skeleton-notice-list" data-skeleton-row-count="' . e($notice_count) . '">';
    for ($index = 0; $index < $notice_count; $index++) {
        $html .= '<div class="skeleton-notice-item">'
            . clinic_skeleton_block('skeleton-notice-icon')
            . '<div>' . clinic_skeleton_block('skeleton-notice-title') . clinic_skeleton_block('skeleton-notice-copy') . '</div>'
            . clinic_skeleton_block('skeleton-notice-arrow')
            . '</div>';
    }
    if ($expired_count > 0) {
        $html .= '<div class="skeleton-warning-note">' . clinic_skeleton_block('skeleton-warning-icon') . clinic_skeleton_block('skeleton-warning-copy') . '</div>';
    }
    $html .= '</div></article></section>';

    return $html;
}

function clinic_skeleton_form_field($wide = false)
{
    return '<div class="skeleton-form-field' . ($wide ? ' skeleton-form-field-wide' : '') . '">'
        . clinic_skeleton_block('skeleton-field-label')
        . clinic_skeleton_block('skeleton-field-control')
        . '</div>';
}

function clinic_skeleton_prescription_row()
{
    return '<div class="skeleton-prescription-row">'
        . '<div class="skeleton-form-field">' . clinic_skeleton_block('skeleton-field-label') . clinic_skeleton_block('skeleton-field-control') . clinic_skeleton_block('skeleton-field-hint') . '</div>'
        . '<div class="skeleton-form-field">' . clinic_skeleton_block('skeleton-field-label') . clinic_skeleton_block('skeleton-field-control') . '</div>'
        . '<div class="skeleton-form-field">' . clinic_skeleton_block('skeleton-field-label') . clinic_skeleton_block('skeleton-field-control') . '</div>'
        . '<div class="skeleton-form-field">' . clinic_skeleton_block('skeleton-field-label') . clinic_skeleton_block('skeleton-field-control') . '</div>'
        . clinic_skeleton_block('skeleton-prescription-remove')
        . '</div>';
}

function clinic_skeleton_form($page_key, $context)
{
    $has_visit_context = isset($context['visit']) || isset($context['patient']);
    $html = clinic_skeleton_hero($context);
    if ($has_visit_context && in_array($page_key, array('pemeriksaan', 'resep', 'pembayaran'), true)) {
        $html .= '<div class="skeleton-context-strip">'
            . clinic_skeleton_block('skeleton-context-avatar')
            . '<div>' . clinic_skeleton_block('skeleton-context-title') . clinic_skeleton_block('skeleton-context-copy') . '</div>'
            . '</div>';
    }

    if ($page_key === 'resep') {
        $medicine_data = isset($context['data']['medicine_id']) && is_array($context['data']['medicine_id']) ? $context['data']['medicine_id'] : array('');
        $medicine_count = min(max(count($medicine_data), 1), 8);
        $prescription_rows = '';
        for ($index = 0; $index < $medicine_count; $index++) {
            $prescription_rows .= clinic_skeleton_prescription_row();
        }

        return $html
            . '<section class="skeleton-form-card skeleton-prescription-card">'
            . '<div class="skeleton-form-header">' . clinic_skeleton_block('skeleton-form-title') . clinic_skeleton_block('skeleton-form-description') . '</div>'
            . '<div class="skeleton-form-body">'
            . '<div class="skeleton-prescription-rows" data-skeleton-row-count="' . e($medicine_count) . '">' . $prescription_rows . '</div>'
            . clinic_skeleton_block('skeleton-prescription-add')
            . '<div class="skeleton-prescription-note-section">'
            . '<div class="skeleton-form-section-title">' . clinic_skeleton_block('skeleton-section-dot') . clinic_skeleton_block('skeleton-section-line') . '</div>'
            . clinic_skeleton_block('skeleton-prescription-textarea')
            . '</div>'
            . '<div class="skeleton-form-note">' . clinic_skeleton_block('skeleton-note-icon') . clinic_skeleton_block('skeleton-note-copy') . '</div>'
            . '<div class="skeleton-form-actions">' . clinic_skeleton_block('skeleton-form-button') . clinic_skeleton_block('skeleton-form-button skeleton-form-button-primary') . '</div>'
            . '</div></section>';
    }

    $field_counts = array(
        'kunjungan' => 5,
        'pemeriksaan' => 8,
        'pasien' => 7,
        'dokter' => 9,
        'poli' => 5,
        'obat' => 7,
        'akun' => 8,
    );
    $field_count = $field_counts[$page_key] ?? 6;
    $html .= '<section class="skeleton-form-card">'
        . '<div class="skeleton-form-header">'
        . clinic_skeleton_block('skeleton-form-kicker')
        . clinic_skeleton_block('skeleton-form-title')
        . clinic_skeleton_block('skeleton-form-description')
        . '</div><div class="skeleton-form-body">'
        . '<div class="skeleton-form-section-title">' . clinic_skeleton_block('skeleton-section-dot') . clinic_skeleton_block('skeleton-section-line') . '</div>'
        . '<div class="skeleton-form-grid">';
    for ($index = 0; $index < $field_count; $index++) {
        $wide_field = $index === 2 && in_array($page_key, array('kunjungan', 'pasien', 'akun'), true);
        $html .= clinic_skeleton_form_field($wide_field);
    }
    $html .= '</div>'
        . '<div class="skeleton-form-note">' . clinic_skeleton_block('skeleton-note-icon') . clinic_skeleton_block('skeleton-note-copy') . '</div>'
        . '<div class="skeleton-form-actions">' . clinic_skeleton_block('skeleton-form-button') . clinic_skeleton_block('skeleton-form-button skeleton-form-button-primary') . '</div>'
        . '</div></section>';

    return $html;
}

function clinic_skeleton_detail($page_key, $context)
{
    $related_count = clinic_skeleton_count($context, array('visits', 'prescriptions', 'details'), 3, 6);
    $output = '';

    for ($index = 0; $index < $related_count; $index++) {
        $output .= '<div class="skeleton-side-row">' . clinic_skeleton_block('skeleton-side-dot') . '<div>' . clinic_skeleton_block('skeleton-side-title') . clinic_skeleton_block('skeleton-side-copy') . '</div></div>';
    }

    return clinic_skeleton_hero($context)
        . '<section class="skeleton-detail-layout skeleton-detail-' . e($page_key) . '">'
        . '<div class="skeleton-detail-main">'
        . '<article class="skeleton-panel skeleton-identity-panel">'
        . clinic_skeleton_block('skeleton-detail-avatar')
        . '<div>' . clinic_skeleton_block('skeleton-detail-kicker') . clinic_skeleton_block('skeleton-detail-title') . clinic_skeleton_block('skeleton-detail-copy') . '</div>'
        . '</article>'
        . '<article class="skeleton-panel skeleton-detail-content">'
        . clinic_skeleton_panel_header(false)
        . '<div class="skeleton-detail-lines">'
        . clinic_skeleton_block('skeleton-detail-line skeleton-detail-line-wide')
        . clinic_skeleton_block('skeleton-detail-line')
        . clinic_skeleton_block('skeleton-detail-line skeleton-detail-line-medium')
        . '</div></article>'
        . '</div>'
        . '<aside class="skeleton-detail-side">'
        . '<article class="skeleton-panel skeleton-side-card">'
        . clinic_skeleton_panel_header(false)
        . '<div class="skeleton-side-list" data-skeleton-row-count="' . e($related_count) . '">' . $output . '</div>'
        . '</article></aside></section>';
}

function clinic_skeleton_profile($context)
{
    return clinic_skeleton_hero($context)
        . '<section class="skeleton-profile-layout">'
        . '<article class="skeleton-panel skeleton-profile-current">'
        . clinic_skeleton_block('skeleton-profile-kicker')
        . '<div class="skeleton-profile-identity">' . clinic_skeleton_block('skeleton-profile-avatar') . '<div>' . clinic_skeleton_block('skeleton-profile-name') . clinic_skeleton_block('skeleton-profile-role') . '</div></div>'
        . '<div class="skeleton-profile-metrics"><div>' . clinic_skeleton_block('skeleton-metric-label') . clinic_skeleton_block('skeleton-metric-value') . '</div><div>' . clinic_skeleton_block('skeleton-metric-label') . clinic_skeleton_block('skeleton-metric-value') . '</div><div>' . clinic_skeleton_block('skeleton-metric-label') . clinic_skeleton_block('skeleton-metric-value') . '</div></div>'
        . '</article>'
        . '<article class="skeleton-panel skeleton-profile-guidance">'
        . clinic_skeleton_block('skeleton-profile-icon')
        . clinic_skeleton_block('skeleton-guidance-title')
        . clinic_skeleton_block('skeleton-guidance-copy')
        . clinic_skeleton_block('skeleton-guidance-copy skeleton-guidance-copy-short')
        . '</article>'
        . '<section class="skeleton-form-card skeleton-profile-form">'
        . '<div class="skeleton-form-header">' . clinic_skeleton_block('skeleton-form-title') . clinic_skeleton_block('skeleton-form-description') . '</div>'
        . '<div class="skeleton-form-body"><div class="skeleton-form-grid">'
        . '<div class="skeleton-form-field">' . clinic_skeleton_block('skeleton-field-label') . clinic_skeleton_block('skeleton-field-control') . '</div>'
        . '<div class="skeleton-form-field">' . clinic_skeleton_block('skeleton-field-label') . clinic_skeleton_block('skeleton-field-control') . '</div>'
        . '<div class="skeleton-form-field skeleton-form-field-wide">' . clinic_skeleton_block('skeleton-field-label') . clinic_skeleton_block('skeleton-field-control') . '</div>'
        . '</div><div class="skeleton-form-actions">' . clinic_skeleton_block('skeleton-form-button skeleton-form-button-primary') . '</div></div>'
        . '</section></section>';
}

function render_page_skeleton($page_key, $route, $context = array())
{
    $route = str_replace('\\', '/', (string) $route);
    $route_name = basename($route);
    $page_key = (string) $page_key;
    $attributes = ' data-page-skeleton="' . e($page_key) . '" data-page-route="' . e($route) . '"';

    if ($page_key === 'dashboard') {
        $content = clinic_skeleton_dashboard($context);
    } elseif ($page_key === 'profil') {
        $content = clinic_skeleton_profile($context);
    } elseif ($route_name === 'detail.php') {
        $content = clinic_skeleton_detail($page_key, $context);
    } elseif (in_array($route_name, array('tambah.php', 'edit.php'), true) || isset($context['form_action'])) {
        $content = clinic_skeleton_form($page_key, $context);
    } else {
        $content = clinic_skeleton_list($page_key, $context);
    }

    return '<div class="page-loading-skeleton page-loading-skeleton-' . e($page_key) . '"' . $attributes . ' aria-hidden="true">' . $content . '</div>';
}

function generate_medical_record_number()
{
    $prefix = 'RM-' . date('ym');
    $last = db_value("SELECT MAX(CAST(SUBSTRING(no_rm, 8) AS UNSIGNED)) FROM patients WHERE no_rm LIKE ?", array($prefix . '%'), 0);
    return $prefix . str_pad((int) $last + 1, 4, '0', STR_PAD_LEFT);
}

function generate_visit_number($date)
{
    $prefix = 'KJ-' . date('Ymd', strtotime($date));
    $last = db_value("SELECT MAX(CAST(SUBSTRING(no_kunjungan, 12) AS UNSIGNED)) FROM visits WHERE no_kunjungan LIKE ?", array($prefix . '%'), 0);
    return $prefix . str_pad((int) $last + 1, 3, '0', STR_PAD_LEFT);
}

function generate_prescription_number($date)
{
    $prefix = 'RX-' . date('Ymd', strtotime($date));
    $last = db_value("SELECT MAX(CAST(SUBSTRING(no_resep, 12) AS UNSIGNED)) FROM prescriptions WHERE no_resep LIKE ?", array($prefix . '%'), 0);
    return $prefix . str_pad((int) $last + 1, 3, '0', STR_PAD_LEFT);
}

function next_queue_number($date)
{
    $last = db_value('SELECT MAX(nomor_antrian) FROM visits WHERE tanggal_kunjungan = ?', array($date), 0);
    return (int) $last + 1;
}

function sync_payment_total($visit_id)
{
    $payment = db_select_one('SELECT * FROM payments WHERE visit_id = ?', array((int) $visit_id));
    if (!$payment) {
        // visit_id unik; UPSERT membuat dua request paralel tetap idempotent.
        db_execute('INSERT INTO payments (visit_id) VALUES (?) ON DUPLICATE KEY UPDATE visit_id = VALUES(visit_id)', array((int) $visit_id));
        $payment = db_select_one('SELECT * FROM payments WHERE visit_id = ?', array((int) $visit_id));
    }

    $medicine_total = (float) db_value("SELECT COALESCE(SUM(pd.jumlah * pd.harga_satuan), 0) FROM prescription_details pd INNER JOIN prescriptions pr ON pr.id = pd.prescription_id WHERE pr.visit_id = ? AND pr.status <> 'Dibatalkan'", array((int) $visit_id), 0);
    $total = (float) $payment['biaya_pemeriksaan'] + (float) $payment['biaya_tindakan'] + $medicine_total;
    db_execute('UPDATE payments SET total_obat = ?, total = ? WHERE visit_id = ?', array($medicine_total, $total, (int) $visit_id));

    $payment['total_obat'] = $medicine_total;
    $payment['total'] = $total;
    return $payment;
}

function page_action($label, $url, $icon_name = 'plus', $class = 'button button-primary')
{
    if (!can_access_path($url)) {
        return '';
    }

    return '<a class="' . e($class) . '" href="' . e(base_url($url)) . '">' . icon($icon_name) . '<span>' . e($label) . '</span></a>';
}

auth_bootstrap();
