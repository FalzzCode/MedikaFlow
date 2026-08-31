<?php
require_once __DIR__ . '/../includes/functions.php';

$visit_id = (int) query_value('visit_id');
$visit = null;
if ($visit_id > 0) {
    $visit = db_select_one("SELECT v.*, p.nama AS nama_pasien, p.no_rm, pc.nama_poli, d.nama_dokter FROM visits v INNER JOIN patients p ON p.id = v.patient_id INNER JOIN polyclinics pc ON pc.id = v.polyclinic_id INNER JOIN doctors d ON d.id = v.doctor_id WHERE v.id = ?", array($visit_id));
} else {
    $visit = db_select_one("SELECT v.*, p.nama AS nama_pasien, p.no_rm, pc.nama_poli, d.nama_dokter FROM visits v INNER JOIN patients p ON p.id = v.patient_id INNER JOIN polyclinics pc ON pc.id = v.polyclinic_id INNER JOIN doctors d ON d.id = v.doctor_id WHERE v.status <> 'Batal' ORDER BY v.tanggal_kunjungan DESC, v.nomor_antrian DESC LIMIT 1");
    $visit_id = (int) ($visit['id'] ?? 0);
}
if (!$visit || $visit['status'] === 'Batal') {
    flash('danger', 'Pilih kunjungan yang aktif untuk membuat resep.');
    redirect_to('kunjungan/index.php');
}

$page_title = 'Buat resep';
$page_description = 'Tambahkan satu atau beberapa obat untuk ' . $visit['nama_pasien'] . '.';
$active_menu = 'resep';
$medicines = db_select_all("SELECT m.id, m.kode_obat, m.nama_obat, m.satuan, m.harga, m.stok FROM medicines m WHERE m.archived_at IS NULL AND m.status = 'Aktif' AND m.tanggal_expired >= CURDATE() AND m.stok > 0 ORDER BY m.nama_obat");
$data = array('catatan' => '', 'medicine_id' => array(''), 'jumlah' => array('1'), 'dosis' => array('1 unit'), 'aturan_penggunaan' => array('3 kali sehari setelah makan'));
$errors = array();
$transaction_started = false;
$prescription_lock = '';

if (is_post()) {
    verify_csrf();
    $data['catatan'] = post_value('catatan');
    $data['medicine_id'] = is_array($_POST['medicine_id'] ?? null) ? array_values($_POST['medicine_id']) : array();
    $data['jumlah'] = is_array($_POST['jumlah'] ?? null) ? array_values($_POST['jumlah']) : array();
    $data['dosis'] = is_array($_POST['dosis'] ?? null) ? array_values($_POST['dosis']) : array();
    $data['aturan_penggunaan'] = is_array($_POST['aturan_penggunaan'] ?? null) ? array_values($_POST['aturan_penggunaan']) : array();
    $items = array();
    $seen_medicines = array();
    $row_count = max(count($data['medicine_id']), count($data['jumlah']), count($data['dosis']), count($data['aturan_penggunaan']));
    if ($row_count > 50) {
        $errors[] = 'Resep tidak boleh memiliki lebih dari 50 baris obat.';
    }
    if (strlen($data['catatan']) > 4000) {
        $errors[] = 'Catatan resep terlalu panjang.';
    }
    for ($index = 0; $index < min($row_count, 50); $index++) {
        $medicine_id = (int) ($data['medicine_id'][$index] ?? 0);
        $quantity = (string) ($data['jumlah'][$index] ?? '');
        $dosage = trim((string) ($data['dosis'][$index] ?? ''));
        $usage_rule = trim((string) ($data['aturan_penggunaan'][$index] ?? ''));
        if ($medicine_id < 1 && $quantity === '' && $dosage === '' && $usage_rule === '') {
            continue;
        }
        if ($medicine_id < 1 || filter_var($quantity, FILTER_VALIDATE_INT) === false || (int) $quantity < 1 || $dosage === '' || $usage_rule === '') {
            $errors[] = 'Setiap baris resep harus memiliki obat, jumlah, dosis, dan aturan penggunaan.';
            continue;
        }
        if (strlen($dosage) > 80 || strlen($usage_rule) > 160) {
            $errors[] = 'Dosis maksimal 80 karakter dan aturan penggunaan maksimal 160 karakter.';
            continue;
        }
        if (isset($seen_medicines[$medicine_id])) {
            $errors[] = 'Obat yang sama tidak boleh dipilih dua kali dalam satu resep.';
            continue;
        }
        $seen_medicines[$medicine_id] = true;
        $medicine = db_select_one("SELECT id, nama_obat, harga, stok FROM medicines WHERE id = ? AND archived_at IS NULL AND status = 'Aktif' AND tanggal_expired >= CURDATE()", array($medicine_id));
        if (!$medicine) {
            $errors[] = 'Salah satu obat tidak aktif, tidak tersedia, atau sudah expired.';
            continue;
        }
        if ((int) $quantity > (int) $medicine['stok']) {
            $errors[] = 'Jumlah ' . $medicine['nama_obat'] . ' melebihi stok yang tersedia (' . $medicine['stok'] . ').';
            continue;
        }
        $items[] = array('medicine_id' => $medicine_id, 'jumlah' => (int) $quantity, 'dosis' => $dosage, 'aturan_penggunaan' => $usage_rule, 'harga_satuan' => (float) $medicine['harga']);
    }
    if (empty($items)) {
        $errors[] = 'Tambahkan minimal satu obat ke resep.';
    }

    if (empty($errors)) {
        try {
            $prescription_lock = acquire_database_lock('prescription:' . $visit['tanggal_kunjungan'], 5);
            begin_transaction();
            $transaction_started = true;
            $locked_visit = db_select_one('SELECT id, status FROM visits WHERE id = ? FOR UPDATE', array($visit_id));
            if (!$locked_visit || $locked_visit['status'] === 'Batal') {
                throw new RuntimeException('Kunjungan sudah tidak aktif untuk pembuatan resep.');
            }
            $prescription_number = generate_prescription_number($visit['tanggal_kunjungan']);
            $insert = db_execute('INSERT INTO prescriptions (no_resep, visit_id, status, catatan) VALUES (?, ?, \'Draft\', ?)', array($prescription_number, $visit_id, $data['catatan']));
            foreach ($items as $item) {
                db_execute('INSERT INTO prescription_details (prescription_id, medicine_id, jumlah, dosis, aturan_penggunaan, harga_satuan) VALUES (?, ?, ?, ?, ?, ?)', array((int) $insert['insert_id'], $item['medicine_id'], $item['jumlah'], $item['dosis'], $item['aturan_penggunaan'], $item['harga_satuan']));
            }
            commit_transaction();
            $transaction_started = false;
            release_database_lock($prescription_lock);
            $prescription_lock = '';
            sync_payment_total($visit_id);
            flash('success', 'Resep ' . $prescription_number . ' berhasil dibuat sebagai draft.');
            redirect_to('resep/detail.php?id=' . $insert['insert_id']);
        } catch (Throwable $exception) {
            if ($transaction_started) {
                rollback_transaction();
            }
            $errors[] = 'Resep belum tersimpan. Pastikan setiap obat hanya muncul satu kali.';
        } finally {
            if ($prescription_lock !== '') {
                release_database_lock($prescription_lock);
                $prescription_lock = '';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="detail-card" style="max-width: 960px; margin-bottom: 18px;"><div class="detail-card-body"><div class="patient-cell"><span class="patient-initial"><?= e(initials($visit['nama_pasien'])) ?></span><div><div class="cell-primary"><?= e($visit['nama_pasien']) ?></div><div class="cell-muted mono"><?= e($visit['no_rm']) ?> · <?= e($visit['no_kunjungan']) ?> · <?= e($visit['nama_poli']) ?></div></div></div></div></div>
<div class="form-card"><div class="form-card-header"><h2>Daftar obat</h2><p>Stok yang terlihat adalah stok saat formulir dibuka. Sistem akan mengecek ulang saat resep diselesaikan.</p></div><div class="form-card-body">
    <?= render_errors($errors) ?>
    <form method="post" action="<?= e(base_url('resep/tambah.php?visit_id=' . $visit_id)) ?>" id="prescription-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div id="prescription-rows">
            <?php foreach ($data['medicine_id'] as $index => $selected_medicine): ?>
                <div class="prescription-row" data-prescription-row>
                    <div class="form-field"><label class="form-label" for="medicine_id_<?= e($index) ?>">Obat <span class="required">*</span></label><select class="form-control medicine-select" id="medicine_id_<?= e($index) ?>" name="medicine_id[]" required><option value="">Pilih obat</option><?php foreach ($medicines as $medicine): ?><option value="<?= e($medicine['id']) ?>" data-stock="<?= e($medicine['stok']) ?>" <?= (string) $selected_medicine === (string) $medicine['id'] ? 'selected' : '' ?>><?= e($medicine['nama_obat']) ?> · sisa <?= e($medicine['stok']) ?></option><?php endforeach; ?></select><p class="form-hint stock-hint">Pilih obat untuk melihat stok.</p></div>
                    <div class="form-field"><label class="form-label" for="jumlah_<?= e($index) ?>">Jumlah <span class="required">*</span></label><input class="form-control" type="number" id="jumlah_<?= e($index) ?>" name="jumlah[]" min="1" value="<?= e($data['jumlah'][$index] ?? '1') ?>" required></div>
                    <div class="form-field"><label class="form-label" for="dosis_<?= e($index) ?>">Dosis <span class="required">*</span></label><input class="form-control" type="text" id="dosis_<?= e($index) ?>" name="dosis[]" value="<?= e($data['dosis'][$index] ?? '') ?>" placeholder="1 tablet" required></div>
                    <div class="form-field"><label class="form-label" for="aturan_<?= e($index) ?>">Aturan penggunaan <span class="required">*</span></label><input class="form-control" type="text" id="aturan_<?= e($index) ?>" name="aturan_penggunaan[]" value="<?= e($data['aturan_penggunaan'][$index] ?? '') ?>" placeholder="3 kali sehari setelah makan" required></div>
                    <button class="row-remove" type="button" data-remove-row aria-label="Hapus obat dari resep"><?= icon('trash-2') ?></button>
                </div>
            <?php endforeach; ?>
        </div>
        <button class="button button-secondary button-small" type="button" id="add-prescription-row" style="margin-top: 12px;"><?= icon('plus') ?><span>Tambah obat lain</span></button>
        <div class="form-section"><div class="form-section-title">Catatan resep</div><div class="form-field"><label class="form-label" for="catatan">Catatan untuk pasien</label><textarea class="form-control" id="catatan" name="catatan" placeholder="Contoh: simpan di tempat kering dan jauhkan dari jangkauan anak"><?= e($data['catatan']) ?></textarea></div></div>
        <div class="form-note"><?= icon('shield-check') ?><span>Resep masih berstatus <strong>Draft</strong> setelah disimpan. Stok obat baru berkurang setelah petugas menekan tombol “Selesaikan resep”.</span></div>
        <div class="form-actions"><a class="button button-secondary" href="<?= e(base_url('kunjungan/detail.php?id=' . $visit_id)) ?>">Batal</a><button class="button button-primary" type="submit"><?= icon('pill') ?><span>Simpan resep</span></button></div>
    </form>
</div></div>
<template id="prescription-row-template"><div class="prescription-row" data-prescription-row><div class="form-field"><label class="form-label">Obat <span class="required">*</span></label><select class="form-control medicine-select" name="medicine_id[]" required><option value="">Pilih obat</option><?php foreach ($medicines as $medicine): ?><option value="<?= e($medicine['id']) ?>" data-stock="<?= e($medicine['stok']) ?>"><?= e($medicine['nama_obat']) ?> · sisa <?= e($medicine['stok']) ?></option><?php endforeach; ?></select><p class="form-hint stock-hint">Pilih obat untuk melihat stok.</p></div><div class="form-field"><label class="form-label">Jumlah <span class="required">*</span></label><input class="form-control" type="number" name="jumlah[]" min="1" value="1" required></div><div class="form-field"><label class="form-label">Dosis <span class="required">*</span></label><input class="form-control" type="text" name="dosis[]" value="1 unit" placeholder="1 tablet" required></div><div class="form-field"><label class="form-label">Aturan penggunaan <span class="required">*</span></label><input class="form-control" type="text" name="aturan_penggunaan[]" value="3 kali sehari setelah makan" placeholder="Aturan minum" required></div><button class="row-remove" type="button" data-remove-row aria-label="Hapus obat dari resep"><?= icon('trash-2') ?></button></div></template>
<script>
(function () {
    var rows = document.getElementById('prescription-rows');
    var addButton = document.getElementById('add-prescription-row');
    var template = document.getElementById('prescription-row-template');
    function updateStockHint(row) {
        var select = row.querySelector('.medicine-select');
        var hint = row.querySelector('.stock-hint');
        var option = select && select.options[select.selectedIndex];
        if (option && option.dataset.stock) {
            hint.textContent = 'Stok tersedia: ' + option.dataset.stock + ' unit.';
        } else {
            hint.textContent = 'Pilih obat untuk melihat stok.';
        }
    }
    function wireRow(row) {
        var select = row.querySelector('.medicine-select');
        var remove = row.querySelector('[data-remove-row]');
        if (select) { select.addEventListener('change', function () { updateStockHint(row); }); updateStockHint(row); }
        if (remove) { remove.addEventListener('click', function () { if (rows.querySelectorAll('[data-prescription-row]').length > 1) { row.remove(); } else { window.alert('Minimal satu obat harus ada di resep.'); } }); }
    }
    rows.querySelectorAll('[data-prescription-row]').forEach(wireRow);
    addButton.addEventListener('click', function () { var clone = template.content.cloneNode(true); rows.appendChild(clone); wireRow(rows.lastElementChild); });
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
