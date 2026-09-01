<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_role('Admin');

$page_title = 'Tambah akun';
$page_description = 'Daftarkan pengguna nyata dan tentukan ruang kerja sesuai tanggung jawabnya.';
$active_menu = 'akun';
$data = array('nama_lengkap' => '', 'username' => '', 'email' => '', 'role' => 'Petugas', 'doctor_id' => '', 'status' => 'Aktif');
$errors = array();
$new_profile_photo = null;

if (is_post()) {
    verify_csrf();
    foreach ($data as $field => $default) {
        $data[$field] = post_value($field, $default);
    }
    $data['username'] = strtolower($data['username']);
    $data['email'] = strtolower($data['email']);
    $data['doctor_id'] = $data['role'] === 'Dokter' ? (int) $data['doctor_id'] : null;
    $password = (string) ($_POST['password'] ?? '');
    $password_confirmation = (string) ($_POST['password_confirmation'] ?? '');
    $errors = validate_account_form($data, $password, $password_confirmation, 0, true);
    $has_profile_photo_upload = isset($_FILES['profile_photo']['error'])
        && (int) $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE;

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
            db_execute('INSERT INTO users (nama_lengkap, username, email, profile_photo, password_hash, role, doctor_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', array(
                $data['nama_lengkap'], $data['username'], $data['email'], $new_profile_photo, password_hash($password, PASSWORD_DEFAULT),
                $data['role'], $data['doctor_id'], $data['status'],
            ));
            flash('success', 'Akun ' . $data['nama_lengkap'] . ' berhasil dibuat.');
            redirect_to('akun/index.php');
        } catch (Throwable $exception) {
            if ($new_profile_photo !== null) {
                profile_photo_delete($new_profile_photo);
                $new_profile_photo = null;
            }
            $errors[] = 'Akun belum tersimpan. Periksa kembali username, email, dan hubungan dokter.';
        }
    }
}

$doctors = account_form_doctors();
$form_action = base_url('akun/tambah.php');
$submit_label = 'Simpan akun';
$password_required = true;
require_once __DIR__ . '/../includes/header.php';
?>
<div class="form-card"><div class="form-card-header"><h2>Akun baru</h2><p>Pengguna dapat langsung login setelah akun berstatus aktif.</p></div><div class="form-card-body"><?php require __DIR__ . '/_form.php'; ?></div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
