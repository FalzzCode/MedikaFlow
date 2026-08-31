<?= render_errors($errors) ?>
<form method="post" action="<?= e($form_action) ?>">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div class="form-section">
        <div class="form-section-title">Identitas utama</div>
        <div class="form-grid">
            <div class="form-field">
                <label class="form-label" for="no_rm">No. rekam medis <span class="required">*</span></label>
                <input class="form-control mono" type="text" id="no_rm" name="no_rm" value="<?= e($data['no_rm']) ?>" maxlength="20" required>
                <p class="form-hint">Gunakan format RM-YYMMNNNN agar mudah dilacak.</p>
            </div>
            <div class="form-field">
                <label class="form-label" for="nik">NIK <span class="required">*</span></label>
                <input class="form-control mono" type="text" id="nik" name="nik" value="<?= e($data['nik']) ?>" maxlength="16" inputmode="numeric" required>
            </div>
            <div class="form-field full">
                <label class="form-label" for="nama">Nama lengkap <span class="required">*</span></label>
                <input class="form-control" type="text" id="nama" name="nama" value="<?= e($data['nama']) ?>" maxlength="120" placeholder="Nama lengkap pasien" required>
            </div>
            <div class="form-field">
                <label class="form-label" for="jenis_kelamin">Jenis kelamin <span class="required">*</span></label>
                <select class="form-control" id="jenis_kelamin" name="jenis_kelamin" required>
                    <option value="">Pilih jenis kelamin</option>
                    <option value="L" <?= $data['jenis_kelamin'] === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                    <option value="P" <?= $data['jenis_kelamin'] === 'P' ? 'selected' : '' ?>>Perempuan</option>
                </select>
            </div>
            <div class="form-field">
                <label class="form-label" for="tanggal_lahir">Tanggal lahir <span class="required">*</span></label>
                <input class="form-control" type="date" id="tanggal_lahir" name="tanggal_lahir" value="<?= e($data['tanggal_lahir']) ?>" max="<?= e(date('Y-m-d')) ?>" required>
            </div>
        </div>
    </div>
    <div class="form-section">
        <div class="form-section-title">Kontak dan alamat</div>
        <div class="form-grid">
            <div class="form-field">
                <label class="form-label" for="no_hp">No. HP <span class="required">*</span></label>
                <input class="form-control" type="tel" id="no_hp" name="no_hp" value="<?= e($data['no_hp']) ?>" maxlength="20" placeholder="08xx-xxxx-xxxx" required>
            </div>
            <div class="form-field full">
                <label class="form-label" for="alamat">Alamat lengkap <span class="required">*</span></label>
                <textarea class="form-control" id="alamat" name="alamat" placeholder="Jalan, nomor rumah, kecamatan, kota" required><?= e($data['alamat']) ?></textarea>
            </div>
        </div>
    </div>
    <div class="form-actions">
        <a class="button button-secondary" href="<?= e(base_url('pasien/index.php')) ?>">Batal</a>
        <button class="button button-primary" type="submit"><?= icon('check') ?><span><?= e($submit_label) ?></span></button>
    </div>
</form>
