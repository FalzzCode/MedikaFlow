<?php
require_once __DIR__ . '/../includes/functions.php';

$data = array('nama_lengkap' => '', 'username' => '', 'email' => '');
$errors = array();
$transaction_started = false;
$setup_lock = '';

if (is_post()) {
    try {
        verify_csrf();
        foreach ($data as $field => $default) {
            $data[$field] = post_value($field, $default);
        }
        $data['username'] = strtolower($data['username']);
        $data['email'] = strtolower($data['email']);
        $password = (string) ($_POST['password'] ?? '');
        $password_confirmation = (string) ($_POST['password_confirmation'] ?? '');

        if ($data['nama_lengkap'] === '' || $data['username'] === '' || $data['email'] === '' || $password === '') {
            $errors[] = 'Semua field wajib diisi.';
        }
        if (strlen($data['nama_lengkap']) > 120 || strlen($data['email']) > 120) {
            $errors[] = 'Nama dan email tidak boleh melebihi batas karakter.';
        }
        if (!preg_match('/^[a-z0-9._-]{3,50}$/', $data['username'])) {
            $errors[] = 'Username memakai 3-50 karakter: huruf kecil, angka, titik, garis bawah, atau strip.';
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email belum valid.';
        }
        $password_error = validate_password_strength($password);
        if ($password_error !== '') {
            $errors[] = $password_error;
        }
        if ($password !== $password_confirmation) {
            $errors[] = 'Konfirmasi password belum sama.';
        }

        if (empty($errors)) {
            // Cek jumlah akun di dalam lock yang sama dengan INSERT agar dua
            // request setup pertama tidak dapat membuat dua Admin sekaligus.
            $setup_lock = acquire_database_lock('initial-setup', 5);
            begin_transaction();
            $transaction_started = true;
            if ((int) db_value('SELECT COUNT(*) FROM users', array(), 0) > 0) {
                throw new RuntimeException('Setup sudah selesai. Silakan masuk menggunakan akun terdaftar.');
            }

            db_execute('INSERT INTO users (nama_lengkap, username, email, password_hash, role, status) VALUES (?, ?, ?, ?, \'Admin\', \'Aktif\')', array(
                $data['nama_lengkap'],
                $data['username'],
                $data['email'],
                password_hash($password, PASSWORD_DEFAULT),
            ));
            commit_transaction();
            $transaction_started = false;
            release_database_lock($setup_lock);
            $setup_lock = '';

            attempt_login($data['username'], $password);
            flash('success', 'Setup selesai. Akun Admin pertama sudah aktif.');
            redirect_to('dashboard/index.php');
        }
    } catch (Throwable $exception) {
        if ($transaction_started) {
            rollback_transaction();
        }
        error_log('[MedikaFlow] Initial setup failed: ' . $exception->getMessage());
        $errors[] = 'Admin pertama belum dapat dibuat. Silakan periksa data dan coba lagi.';
    } finally {
        if ($setup_lock !== '') {
            release_database_lock($setup_lock);
            $setup_lock = '';
        }
    }
}

$auth_title = 'Siapkan Admin pertama';
$auth_description = 'Langkah ini hanya muncul saat tabel akun masih kosong. Kredensial disimpan langsung ke MySQL.';
$auth_kicker = 'Setup pertama';
$auth_mode = 'setup';
require_once __DIR__ . '/../includes/auth_header.php';
?>
<div class="auth-setup-progress"><span>1 · Identitas</span><span>2 · Keamanan</span><span>3 · Siap dipakai</span></div>

<?php if (!empty($errors)): ?>
    <div class="auth-alert auth-alert-danger" role="alert"><?= icon('alert-triangle') ?><span><?= e(implode(' ', array_unique($errors))) ?></span></div>
<?php endif; ?>

<form class="auth-form" method="post" action="<?= e(base_url('auth/setup.php')) ?>" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <div class="auth-field">
        <label for="nama_lengkap">Nama lengkap Admin</label>
        <div class="auth-input-shell"><?= icon('user-round') ?><input class="auth-input" id="nama_lengkap" name="nama_lengkap" value="<?= e($data['nama_lengkap']) ?>" maxlength="120" placeholder="Nama pengguna utama" autocomplete="name" required></div>
    </div>

    <div class="auth-field">
        <label for="username">Username</label>
        <div class="auth-input-shell"><?= icon('badge-check') ?><input class="auth-input" id="username" name="username" value="<?= e($data['username']) ?>" maxlength="50" placeholder="contoh: admin.klinik" autocomplete="username" required></div>
    </div>

    <div class="auth-field auth-field-wide">
        <label for="email">Email</label>
        <div class="auth-input-shell"><?= icon('file-text') ?><input class="auth-input" id="email" name="email" type="email" value="<?= e($data['email']) ?>" maxlength="120" placeholder="admin@klinik.id" autocomplete="email" required></div>
    </div>

    <div class="auth-field">
        <div class="auth-field-row"><label for="password">Password</label><span class="auth-field-hint">Huruf + angka, minimal 8 karakter</span></div>
        <div class="auth-input-shell"><?= icon('shield-check') ?><input class="auth-input has-action" id="password" name="password" type="password" placeholder="Buat password utama" autocomplete="new-password" data-password-input required><button class="auth-password-toggle" type="button" data-password-toggle aria-label="Tampilkan password" aria-pressed="false"><?= icon('eye') ?></button></div>
    </div>

    <div class="auth-field">
        <label for="password_confirmation">Ulangi password</label>
        <div class="auth-input-shell"><?= icon('check-circle-2') ?><input class="auth-input has-action" id="password_confirmation" name="password_confirmation" type="password" placeholder="Ketik ulang password" autocomplete="new-password" data-password-input required><button class="auth-password-toggle" type="button" data-password-toggle aria-label="Tampilkan password" aria-pressed="false"><?= icon('eye') ?></button></div>
    </div>

    <button class="auth-submit auth-submit-wide" type="submit"><?= icon('shield-check') ?><span>Buat Admin dan mulai</span></button>
</form>

<div class="auth-form-note"><?= icon('activity') ?><span>Setelah masuk, buka menu Akun untuk menambahkan pengguna Dokter dan Petugas. Tidak ada password bawaan yang perlu diganti.</span></div>
<?php require_once __DIR__ . '/../includes/auth_footer.php'; ?>
