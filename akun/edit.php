<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_role('Admin');

$id = (int) query_value('id');
$account = db_select_one('SELECT * FROM users WHERE id = ?', array($id));
if (!$account) {
    flash('danger', 'Akun tidak ditemukan.');
    redirect_to('akun/index.php');
}

$page_title = 'Edit akun';
$page_description = 'Perbarui identitas, peran, hubungan dokter, atau status akses pengguna.';
$active_menu = 'akun';
$data = $account;
$errors = array();
$transaction_started = false;
$account_lock = '';
$new_profile_photo = null;

if (is_post()) {
    verify_csrf();
    foreach (array('nama_lengkap', 'username', 'email', 'role', 'doctor_id', 'status') as $field) {
        $data[$field] = post_value($field);
    }
    $data['username'] = strtolower($data['username']);
    $data['email'] = strtolower($data['email']);
    $data['doctor_id'] = $data['role'] === 'Dokter' ? (int) $data['doctor_id'] : null;
    $password = (string) ($_POST['password'] ?? '');
    $password_confirmation = (string) ($_POST['password_confirmation'] ?? '');
    $errors = validate_account_form($data, $password, $password_confirmation, $id, false);
    $remove_profile_photo = !empty($_POST['remove_profile_photo']);
    $has_profile_photo_upload = isset($_FILES['profile_photo']['error'])
        && (int) $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE;
    if ($remove_profile_photo && $has_profile_photo_upload) {
        $errors[] = 'Pilih foto baru atau hapus foto saat ini, bukan keduanya sekaligus.';
    }

    $current = current_user();
    if ($id === (int) $current['id'] && $data['status'] !== 'Aktif') {
        $errors[] = 'Akun yang sedang dipakai tidak dapat dinonaktifkan.';
    }
    if ($id === (int) $current['id'] && $data['role'] !== $account['role']) {
        $errors[] = 'Peran akun yang sedang dipakai tidak dapat diubah dari halaman ini.';
    }
    if ($account['role'] === 'Admin' && $account['status'] === 'Aktif' && ($data['role'] !== 'Admin' || $data['status'] !== 'Aktif')) {
        $active_admins = (int) db_value("SELECT COUNT(*) FROM users WHERE role = 'Admin' AND status = 'Aktif'", array(), 0);
        if ($active_admins <= 1) {
            $errors[] = 'Sistem wajib memiliki minimal satu Admin aktif.';
        }
    }

    if (empty($errors)) {
        try {
            if ($has_profile_photo_upload) {
                $new_profile_photo = profile_photo_upload();
            }
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    if (empty($errors)) {
        try {
            // Serialisasi perubahan akses agar dua Admin tidak dapat
            // menonaktifkan Admin terakhir secara bersamaan.
            $account_lock = acquire_database_lock('admin-account-guard', 5);
            begin_transaction();
            $transaction_started = true;
            $locked_account = db_select_one('SELECT role, status FROM users WHERE id = ? FOR UPDATE', array($id));
            if (!$locked_account) {
                throw new RuntimeException('Akun tidak ditemukan.');
            }
            if ($locked_account['role'] === 'Admin' && $locked_account['status'] === 'Aktif' && ($data['role'] !== 'Admin' || $data['status'] !== 'Aktif')) {
                $active_admins = (int) db_value("SELECT COUNT(*) FROM users WHERE role = 'Admin' AND status = 'Aktif'", array(), 0);
                if ($active_admins <= 1) {
                    throw new RuntimeException('Sistem wajib memiliki minimal satu Admin aktif.');
                }
            }
            $params = array($data['nama_lengkap'], $data['username'], $data['email'], $data['role'], $data['doctor_id'], $data['status']);
            $sql = 'UPDATE users SET nama_lengkap = ?, username = ?, email = ?, role = ?, doctor_id = ?, status = ?';
            if ($new_profile_photo !== null) {
                $sql .= ', profile_photo = ?';
                $params[] = $new_profile_photo;
            } elseif ($remove_profile_photo) {
                $sql .= ', profile_photo = NULL';
            }
            if ($password !== '') {
                $sql .= ', password_hash = ?';
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }
            $sql .= ' WHERE id = ?';
            $params[] = $id;
            db_execute($sql, $params);
            commit_transaction();
            $transaction_started = false;
            release_database_lock($account_lock);
            $account_lock = '';
            current_user(true);
            if (($new_profile_photo !== null || $remove_profile_photo) && !empty($account['profile_photo'])) {
                profile_photo_delete($account['profile_photo']);
            }
            flash('success', 'Akun berhasil diperbarui.');
            redirect_to('akun/index.php');
        } catch (Throwable $exception) {
            if ($transaction_started) {
                rollback_transaction();
            }
            if ($new_profile_photo !== null) {
                profile_photo_delete($new_profile_photo);
                $new_profile_photo = null;
            }
            $errors[] = 'Perubahan akun belum tersimpan. Periksa data unik dan hubungan dokter.';
        } finally {
            if ($account_lock !== '') {
                release_database_lock($account_lock);
                $account_lock = '';
            }
        }
    }
}

$doctors = account_form_doctors($id);
$form_action = base_url('akun/edit.php?id=' . $id);
$submit_label = 'Simpan perubahan';
$password_required = false;
require_once __DIR__ . '/../includes/header.php';
?>
<div class="form-card"><div class="form-card-header"><h2>Edit akses pengguna</h2><p>Kosongkan password jika tidak ingin menggantinya.</p></div><div class="form-card-body"><?php require __DIR__ . '/_form.php'; ?></div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
