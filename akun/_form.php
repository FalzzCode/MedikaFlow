<?= render_errors($errors) ?>
<form method="post" action="<?= e($form_action) ?>" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <div class="form-section">
        <div class="form-section-title">Identitas pengguna</div>
        <div class="form-grid">
            <div class="form-field"><label class="form-label" for="nama_lengkap">Nama lengkap <span class="required">*</span></label><input class="form-control" id="nama_lengkap" name="nama_lengkap" value="<?= e($data['nama_lengkap']) ?>" maxlength="120" autocomplete="name" required></div>
            <div class="form-field"><label class="form-label" for="username">Username <span class="required">*</span></label><input class="form-control mono" id="username" name="username" value="<?= e($data['username']) ?>" maxlength="50" placeholder="nama.pengguna" autocomplete="username" required><span class="form-help">Huruf kecil, angka, titik, garis bawah, atau strip.</span></div>
            <div class="form-field full"><label class="form-label" for="email">Email <span class="required">*</span></label><input class="form-control" id="email" name="email" type="email" value="<?= e($data['email']) ?>" maxlength="120" autocomplete="email" required></div>
        </div>
    </div>

    <div class="form-section">
        <div class="form-section-title">Akses sistem</div>
        <div class="form-grid">
            <div class="form-field"><label class="form-label" for="role">Peran <span class="required">*</span></label><select class="form-control" id="role" name="role" data-role-select required><option value="Admin" <?= $data['role'] === 'Admin' ? 'selected' : '' ?>>Admin · seluruh modul</option><option value="Dokter" <?= $data['role'] === 'Dokter' ? 'selected' : '' ?>>Dokter · layanan klinis</option><option value="Petugas" <?= $data['role'] === 'Petugas' ? 'selected' : '' ?>>Petugas · administrasi layanan</option></select></div>
            <div class="form-field"><label class="form-label" for="status">Status akun <span class="required">*</span></label><select class="form-control" id="status" name="status" required><option value="Aktif" <?= $data['status'] === 'Aktif' ? 'selected' : '' ?>>Aktif</option><option value="Nonaktif" <?= $data['status'] === 'Nonaktif' ? 'selected' : '' ?>>Nonaktif</option></select></div>
            <div class="form-field full role-doctor-field" data-role-doctor-field><label class="form-label" for="doctor_id">Hubungkan data dokter <span class="required">*</span></label><select class="form-control" id="doctor_id" name="doctor_id"><option value="">Pilih dokter</option><?php foreach ($doctors as $doctor): ?><option value="<?= e($doctor['id']) ?>" <?= (string) $data['doctor_id'] === (string) $doctor['id'] ? 'selected' : '' ?>><?= e($doctor['nama_dokter']) ?> · <?= e($doctor['spesialisasi']) ?> (<?= e($doctor['kode_dokter']) ?>)</option><?php endforeach; ?></select><span class="form-help">Satu dokter hanya dapat terhubung ke satu akun login.</span></div>
        </div>
    </div>

    <div class="form-section">
        <div class="form-section-title"><?= $password_required ? 'Password awal' : 'Ganti password' ?></div>
        <div class="form-grid">
            <div class="form-field"><label class="form-label" for="password">Password <?= $password_required ? '<span class="required">*</span>' : '<span class="form-optional">opsional</span>' ?></label><div class="password-field-shell"><input class="form-control" id="password" name="password" type="password" autocomplete="new-password" data-password-input <?= $password_required ? 'required' : '' ?>><button class="form-password-toggle" type="button" data-password-toggle aria-label="Tampilkan password" aria-pressed="false"><?= icon('eye') ?></button></div><span class="form-help">Minimal 8 karakter dan memuat huruf serta angka.</span></div>
            <div class="form-field"><label class="form-label" for="password_confirmation">Ulangi password <?= $password_required ? '<span class="required">*</span>' : '' ?></label><div class="password-field-shell"><input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" data-password-input <?= $password_required ? 'required' : '' ?>><button class="form-password-toggle" type="button" data-password-toggle aria-label="Tampilkan password" aria-pressed="false"><?= icon('eye') ?></button></div></div>
        </div>
    </div>

    <div class="form-note"><?= icon('shield-check') ?><span>Password hanya disimpan sebagai hash. Nilai aslinya tidak pernah ditampilkan kembali oleh sistem.</span></div>
    <div class="form-actions"><a class="button button-secondary" href="<?= e(base_url('akun/index.php')) ?>">Batal</a><button class="button button-primary" type="submit"><?= icon('check') ?><span><?= e($submit_label) ?></span></button></div>
</form>

