<?php
require_once __DIR__ . '/../includes/functions.php';

$user = current_user();
$page_title = 'Profil & keamanan';
$page_description = 'Kelola identitas akun pribadi dan tinjau konteks kerja yang tersimpan di sistem.';
$active_menu = 'profil';
$page_action_html = ($user['role'] ?? '') === 'Admin'
    ? page_action('Kelola semua akun', 'akun/index.php', 'shield-check', 'button button-secondary')
    : '';
$data = array(
    'nama_lengkap' => $user['nama_lengkap'] ?? '',
    'username' => $user['username'] ?? '',
    'email' => $user['email'] ?? '',
);
$errors = array();

if (is_post()) {
    try {
        verify_csrf();
        foreach ($data as $field => $default) {
            $data[$field] = post_value($field, $default);
        }
        $data['username'] = strtolower($data['username']);
        $data['email'] = strtolower($data['email']);
        $current_password = (string) ($_POST['current_password'] ?? '');
        $new_password = (string) ($_POST['new_password'] ?? '');
        $new_password_confirmation = (string) ($_POST['new_password_confirmation'] ?? '');

        if ($data['nama_lengkap'] === '' || $data['username'] === '' || $data['email'] === '') {
            $errors[] = 'Nama, username, dan email wajib diisi.';
        }
        if (strlen($data['nama_lengkap']) > 120 || strlen($data['username']) > 50 || strlen($data['email']) > 120) {
            $errors[] = 'Nama, username, atau email melebihi batas karakter.';
        }
        if (!preg_match('/^[a-z0-9._-]{3,50}$/', $data['username'])) {
            $errors[] = 'Username memakai 3-50 karakter: huruf kecil, angka, titik, garis bawah, atau strip.';
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email belum valid.';
        }
        if ((int) db_value('SELECT COUNT(*) FROM users WHERE username = ? AND id <> ?', array($data['username'], (int) $user['id']), 0) > 0) {
            $errors[] = 'Username sudah digunakan akun lain.';
        }
        if ((int) db_value('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', array($data['email'], (int) $user['id']), 0) > 0) {
            $errors[] = 'Email sudah digunakan akun lain.';
        }

        if ($new_password !== '' || $current_password !== '' || $new_password_confirmation !== '') {
            $stored_hash = (string) db_value('SELECT password_hash FROM users WHERE id = ?', array((int) $user['id']), '');
            if ($current_password === '' || !password_verify($current_password, $stored_hash)) {
                $errors[] = 'Password saat ini belum benar.';
            }
            $password_error = validate_password_strength($new_password);
            if ($password_error !== '') {
                $errors[] = $password_error;
            }
            if ($new_password !== $new_password_confirmation) {
                $errors[] = 'Konfirmasi password baru belum sama.';
            }
        }

        if (empty($errors)) {
            $params = array($data['nama_lengkap'], $data['username'], $data['email']);
            $sql = 'UPDATE users SET nama_lengkap = ?, username = ?, email = ?';
            if ($new_password !== '') {
                $sql .= ', password_hash = ?';
                $params[] = password_hash($new_password, PASSWORD_DEFAULT);
            }
            $sql .= ' WHERE id = ?';
            $params[] = (int) $user['id'];
            db_execute($sql, $params);
            current_user(true);
            flash('success', $new_password !== '' ? 'Profil dan password berhasil diperbarui.' : 'Profil berhasil diperbarui.');
            redirect_to('profil/index.php');
        }
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
    }
}

$user = current_user();
$schedule = user_schedule($user);
$is_doctor = ($user['role'] ?? '') === 'Dokter' && !empty($user['doctor_id']);
$is_profile_active = ($user['status'] ?? '') === 'Aktif';
$role_modules = available_modules_for_role($user['role'] ?? '');

require_once __DIR__ . '/../includes/header.php';
?>
<section class="profile-layout">
    <article class="profile-current-card">
        <div class="profile-card-kicker"><span class="profile-live-dot <?= $is_profile_active ? 'is-active' : 'is-inactive' ?>" aria-hidden="true"></span> <?= $is_profile_active ? 'Akun aktif' : 'Akun nonaktif' ?></div>
        <div class="profile-current-top">
            <div class="profile-avatar-large"><?= e(initials($user['nama_lengkap'])) ?></div>
            <div class="profile-current-copy">
                <span><?= e($user['username']) ?></span>
                <h2><?= e($user['nama_lengkap']) ?></h2>
                <p><?= e(user_role_label($user)) ?> · <?= e($user['email']) ?></p>
            </div>
        </div>
        <div class="profile-schedule-grid">
            <?php if ($is_doctor): ?>
                <div class="profile-schedule-item"><span><?= icon('calendar-days') ?> Hari praktik</span><strong><?= e($schedule['days']) ?></strong></div>
                <div class="profile-schedule-item"><span><?= icon('clock-3') ?> Jam praktik</span><strong><?= e($schedule['hours']) ?></strong></div>
                <div class="profile-schedule-item"><span><?= icon('building-2') ?> Penempatan</span><strong><?= e($schedule['room']) ?></strong></div>
            <?php else: ?>
                <div class="profile-schedule-item"><span><?= icon('shield-check') ?> Peran sistem</span><strong><?= e($user['role']) ?></strong></div>
                <div class="profile-schedule-item"><span><?= icon('clock-3') ?> Login terakhir</span><strong><?= e($user['last_login_at'] ? format_date_id($user['last_login_at'], true) : 'Sesi pertama') ?></strong></div>
                <div class="profile-schedule-item"><span><?= icon('calendar-days') ?> Akun dibuat</span><strong><?= e(format_date_id($user['created_at'])) ?></strong></div>
            <?php endif; ?>
        </div>
        <div class="profile-current-note"><?= icon('badge-check') ?><span>Identitas ini berasal dari akun login dan digunakan konsisten di seluruh halaman.</span></div>
    </article>

    <article class="profile-guidance-card">
        <div class="profile-guidance-icon"><?= icon('shield-check') ?></div>
        <span class="profile-card-kicker">Ruang kerja <?= e($user['role']) ?></span>
        <h2>Akses sesuai tanggung jawab</h2>
        <p>Sistem hanya menampilkan modul yang relevan untuk peran akun ini. Perubahan hak akses dikelola oleh Admin.</p>
        <div class="profile-access-list" aria-label="Modul yang dapat diakses">
            <?php foreach ($role_modules as $module): ?><span><?= icon('check') ?><?= e(ucfirst($module)) ?></span><?php endforeach; ?>
        </div>
    </article>
</section>

<div class="form-card profile-security-form">
    <div class="form-card-header"><h2>Identitas & password</h2><p>Perubahan tersimpan langsung ke akun yang sedang digunakan.</p></div>
    <div class="form-card-body">
        <?= render_errors($errors) ?>
        <form method="post" action="<?= e(base_url('profil/index.php')) ?>" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="form-section">
                <div class="form-section-title">Identitas akun</div>
                <div class="form-grid">
                    <div class="form-field"><label class="form-label" for="nama_lengkap">Nama lengkap <span class="required">*</span></label><input class="form-control" id="nama_lengkap" name="nama_lengkap" value="<?= e($data['nama_lengkap']) ?>" maxlength="120" autocomplete="name" required></div>
                    <div class="form-field"><label class="form-label" for="username">Username <span class="required">*</span></label><input class="form-control mono" id="username" name="username" value="<?= e($data['username']) ?>" maxlength="50" autocomplete="username" required></div>
                    <div class="form-field full"><label class="form-label" for="email">Email <span class="required">*</span></label><input class="form-control" type="email" id="email" name="email" value="<?= e($data['email']) ?>" maxlength="120" autocomplete="email" required></div>
                </div>
            </div>
            <div class="form-section">
                <div class="form-section-title">Ganti password <span class="form-optional">opsional</span></div>
                <div class="form-grid three">
                    <div class="form-field"><label class="form-label" for="current_password">Password saat ini</label><div class="password-field-shell"><input class="form-control" type="password" id="current_password" name="current_password" autocomplete="current-password" data-password-input><button class="form-password-toggle" type="button" data-password-toggle aria-label="Tampilkan password" aria-pressed="false"><?= icon('eye') ?></button></div></div>
                    <div class="form-field"><label class="form-label" for="new_password">Password baru</label><div class="password-field-shell"><input class="form-control" type="password" id="new_password" name="new_password" autocomplete="new-password" data-password-input><button class="form-password-toggle" type="button" data-password-toggle aria-label="Tampilkan password" aria-pressed="false"><?= icon('eye') ?></button></div><span class="form-help">Minimal 8 karakter, huruf dan angka.</span></div>
                    <div class="form-field"><label class="form-label" for="new_password_confirmation">Ulangi password baru</label><div class="password-field-shell"><input class="form-control" type="password" id="new_password_confirmation" name="new_password_confirmation" autocomplete="new-password" data-password-input><button class="form-password-toggle" type="button" data-password-toggle aria-label="Tampilkan password" aria-pressed="false"><?= icon('eye') ?></button></div></div>
                </div>
            </div>
            <div class="form-note"><?= icon('shield-check') ?><span>Password baru disimpan menggunakan hash dan tidak dapat dilihat kembali, termasuk oleh Admin.</span></div>
            <div class="form-actions"><a class="button button-secondary" href="<?= e(base_url('dashboard/index.php')) ?>">Kembali</a><button class="button button-primary" type="submit"><?= icon('check') ?><span>Simpan profil</span></button></div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
