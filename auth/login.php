<?php
require_once __DIR__ . '/../includes/functions.php';

$identifier = '';
$errors = array();
$locked_until = (int) ($_SESSION['auth_locked_until'] ?? 0);

if (is_post()) {
    try {
        verify_csrf();
        $identifier = strtolower(post_value('identifier'));
        $password = (string) ($_POST['password'] ?? '');
        $database_lock_seconds = $identifier === '' ? 0 : auth_login_throttle_seconds($identifier);
        $session_lock_seconds = max(0, $locked_until - time());
        $lock_seconds = max($database_lock_seconds, $session_lock_seconds);

        if ($identifier === '' || $password === '') {
            $errors[] = 'Username/email dan password wajib diisi.';
        } elseif (strlen($identifier) > 120 || strlen($password) > 1024) {
            $errors[] = 'Kredensial melebihi batas yang dapat diproses.';
        } elseif ($lock_seconds > 0) {
            $errors[] = 'Terlalu banyak percobaan. Coba lagi dalam ' . $lock_seconds . ' detik.';
        } elseif (attempt_login($identifier, $password)) {
            $user = current_user();
            flash('success', 'Selamat datang, ' . ($user['nama_lengkap'] ?? 'pengguna') . '.');
            redirect_to('dashboard/index.php');
        } else {
            record_auth_login_failure($identifier);
            $attempts = (int) ($_SESSION['auth_failed_attempts'] ?? 0) + 1;
            $_SESSION['auth_failed_attempts'] = $attempts;

            if ($attempts >= 5) {
                $_SESSION['auth_locked_until'] = time() + 60;
                $_SESSION['auth_failed_attempts'] = 0;
                $errors[] = 'Login dikunci selama 60 detik setelah lima percobaan gagal.';
            } else {
                $errors[] = 'Kredensial tidak cocok atau akun sedang nonaktif.';
            }
        }
    } catch (Throwable $exception) {
        error_log('[MedikaFlow] Login request failed: ' . $exception->getMessage());
        $errors[] = 'Login belum dapat diproses. Silakan coba lagi beberapa saat lagi.';
    }
}

$auth_title = 'Masuk ke ruang kerja';
$auth_description = 'Gunakan username atau email dari akun yang sudah didaftarkan Admin.';
$auth_kicker = 'Sesi pengguna';
$auth_mode = 'login';
$auth_focus_field = $identifier !== '' ? 'password' : 'identifier';
require_once __DIR__ . '/../includes/auth_header.php';
?>
<?php if (!empty($errors)): ?>
    <div class="auth-alert auth-alert-danger" role="alert"><?= icon('alert-triangle') ?><span><?= e(implode(' ', $errors)) ?></span></div>
<?php endif; ?>

<form class="auth-form" method="post" action="<?= e(base_url('auth/login.php')) ?>" autocomplete="on">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <div class="auth-field">
        <div class="auth-field-row"><label for="identifier">Username atau email</label><span class="auth-field-hint">Diberikan Admin</span></div>
        <div class="auth-input-shell">
            <?= icon('user-round') ?>
            <input class="auth-input" id="identifier" name="identifier" type="text" value="<?= e($identifier) ?>" maxlength="120" placeholder="Masukkan username atau email" autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" inputmode="email" enterkeyhint="next" <?= $auth_focus_field === 'identifier' ? 'autofocus' : '' ?> required>
        </div>
    </div>

    <div class="auth-field">
        <div class="auth-field-row"><label for="password">Password</label><span class="auth-field-hint">Minimal 8 karakter</span></div>
        <div class="auth-input-shell">
            <?= icon('shield-check') ?>
            <input class="auth-input has-action" id="password" name="password" type="password" placeholder="Masukkan password" autocomplete="current-password" enterkeyhint="go" data-password-input <?= $auth_focus_field === 'password' ? 'autofocus' : '' ?> required>
            <button class="auth-password-toggle" type="button" data-password-toggle aria-label="Tampilkan password" aria-pressed="false"><?= icon('eye') ?></button>
        </div>
    </div>

    <button class="auth-submit" type="submit"><?= icon('arrow-right') ?><span>Masuk ke dashboard</span></button>
</form>

<div class="auth-form-note auth-login-note"><?= icon('shield-check') ?><span><strong>Akun dibuat oleh Admin klinik.</strong> Gunakan username atau email yang diberikan. Tidak ada pendaftaran mandiri atau akun contoh.</span></div>
<?php require_once __DIR__ . '/../includes/auth_footer.php'; ?>
