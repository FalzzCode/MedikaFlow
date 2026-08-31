<?php
require_once __DIR__ . '/../includes/functions.php';

$visit_id = (int) query_value('visit_id');
$visit = db_select_one("SELECT v.*, p.nama AS nama_pasien, p.no_rm, p.nik, pc.nama_poli, d.nama_dokter, e.diagnosa FROM visits v INNER JOIN patients p ON p.id = v.patient_id INNER JOIN polyclinics pc ON pc.id = v.polyclinic_id INNER JOIN doctors d ON d.id = v.doctor_id LEFT JOIN examinations e ON e.visit_id = v.id WHERE v.id = ?", array($visit_id));
if (!$visit) { flash('danger', 'Kunjungan tidak ditemukan.'); redirect_to('pembayaran/index.php'); }
$payment = sync_payment_total($visit_id);
$page_title = 'Detail pembayaran';
$page_description = 'Rincikan biaya layanan untuk ' . $visit['nama_pasien'] . ' dan catat pelunasannya.';
$active_menu = 'pembayaran';
$page_action_html = page_action('Kembali ke daftar', 'pembayaran/index.php', 'arrow-left', 'button button-secondary') . '<button class="button button-secondary no-print" type="button" data-print-page>' . icon('printer') . '<span>Cetak invoice</span></button>';
$errors = array();
$transaction_started = false;

if (is_post()) {
    try {
        verify_csrf();
        $examination_fee = post_value('biaya_pemeriksaan');
        $action_fee = post_value('biaya_tindakan');
        $payment_status = post_value('status');
        if (!is_valid_nonnegative_decimal($examination_fee, 12, 2)) { $errors[] = 'Biaya pemeriksaan harus berupa angka maksimal 2 desimal dan tidak boleh negatif.'; }
        if (!is_valid_nonnegative_decimal($action_fee, 12, 2)) { $errors[] = 'Biaya tindakan harus berupa angka maksimal 2 desimal dan tidak boleh negatif.'; }
        if (!in_array($payment_status, array('Belum Dibayar', 'Sudah Dibayar'), true)) { $errors[] = 'Status pembayaran tidak valid.'; }
        if (empty($errors)) {
            begin_transaction();
            $transaction_started = true;
            // Ambil total obat terbaru di transaksi yang sama agar pembayaran
            // tidak menyimpan angka lama saat resep selesai bersamaan.
            $payment = sync_payment_total($visit_id);
            $total = (float) $examination_fee + (float) $action_fee + (float) $payment['total_obat'];
            if ($total > 9999999999999.99) { throw new RuntimeException('Total pembayaran melebihi batas sistem.'); }
            $paid_at = $payment_status === 'Sudah Dibayar' ? date('Y-m-d H:i:s') : null;
            db_execute('UPDATE payments SET biaya_pemeriksaan = ?, biaya_tindakan = ?, total_obat = ?, total = ?, status = ?, dibayar_pada = ? WHERE visit_id = ?', array((float) $examination_fee, (float) $action_fee, (float) $payment['total_obat'], $total, $payment_status, $paid_at, $visit_id));
            commit_transaction();
            $transaction_started = false;
            flash('success', $payment_status === 'Sudah Dibayar' ? 'Pembayaran ditandai sudah dibayar.' : 'Rincian pembayaran berhasil disimpan.');
            redirect_to('pembayaran/detail.php?visit_id=' . $visit_id);
        }
    } catch (Throwable $exception) {
        if ($transaction_started) { rollback_transaction(); }
        $errors[] = 'Perubahan pembayaran belum tersimpan.';
    }
}

if (!empty($errors)) {
    $payment['biaya_pemeriksaan'] = post_value('biaya_pemeriksaan', $payment['biaya_pemeriksaan']);
    $payment['biaya_tindakan'] = post_value('biaya_tindakan', $payment['biaya_tindakan']);
    $payment['status'] = post_value('status', $payment['status']);
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="detail-layout">
    <div class="detail-main">
        <section class="detail-card identity-card"><div class="identity-avatar"><?= e(initials($visit['nama_pasien'])) ?></div><div class="identity-copy"><h2><?= e($visit['nama_pasien']) ?></h2><p class="mono"><?= e($visit['no_rm']) ?> · <?= e($visit['no_kunjungan']) ?></p><span class="<?= e(status_class($visit['status'])) ?>"><?= status_icon($visit['status']) ?><?= e($visit['status']) ?></span></div></section>
        <section class="form-card"><div class="form-card-header"><h2>Rincian biaya</h2><p>Total obat terhubung otomatis dari resep yang tercatat pada kunjungan ini.</p></div><div class="form-card-body">
            <?= render_errors($errors) ?>
            <form method="post" action="<?= e(base_url('pembayaran/detail.php?visit_id=' . $visit_id)) ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="form-section"><div class="form-section-title">Komponen tagihan</div><div class="form-grid three"><div class="form-field"><label class="form-label" for="biaya_pemeriksaan">Biaya pemeriksaan <span class="required">*</span></label><input class="form-control" type="number" id="biaya_pemeriksaan" name="biaya_pemeriksaan" value="<?= e($payment['biaya_pemeriksaan']) ?>" min="0" step="0.01" required></div><div class="form-field"><label class="form-label" for="biaya_tindakan">Biaya tindakan <span class="required">*</span></label><input class="form-control" type="number" id="biaya_tindakan" name="biaya_tindakan" value="<?= e($payment['biaya_tindakan']) ?>" min="0" step="0.01" required></div><div class="form-field"><label class="form-label">Total obat</label><div class="form-control" style="display:flex; align-items:center; background: #f8fafd;"><strong><?= e(format_currency($payment['total_obat'])) ?></strong></div><p class="form-hint">Dari resep kunjungan</p></div></div></div>
                <div class="form-section"><div class="form-section-title">Status pelunasan</div><div class="form-grid"><div class="form-field"><label class="form-label" for="status">Status pembayaran <span class="required">*</span></label><select class="form-control" id="status" name="status" required><option value="Belum Dibayar" <?= $payment['status'] === 'Belum Dibayar' ? 'selected' : '' ?>>Belum Dibayar</option><option value="Sudah Dibayar" <?= $payment['status'] === 'Sudah Dibayar' ? 'selected' : '' ?>>Sudah Dibayar</option></select></div></div></div>
                <div class="form-actions"><a class="button button-secondary" href="<?= e(base_url('kunjungan/detail.php?id=' . $visit_id)) ?>">Batal</a><button class="button button-primary" type="submit"><?= icon('wallet') ?><span>Simpan pembayaran</span></button></div>
            </form>
        </div></section>
        <section class="detail-card print-only"><div class="detail-card-header"><h2><?= e(app_brand_name()) ?> · Invoice pembayaran</h2></div><div class="detail-card-body"><dl class="detail-list"><div><dt>Pasien</dt><dd><?= e($visit['nama_pasien']) ?></dd></div><div><dt>No. kunjungan</dt><dd class="mono"><?= e($visit['no_kunjungan']) ?></dd></div><div><dt>Tanggal</dt><dd><?= e(format_date_id($visit['tanggal_kunjungan'])) ?></dd></div><div><dt>Poli / dokter</dt><dd><?= e($visit['nama_poli']) ?> · <?= e($visit['nama_dokter']) ?></dd></div></dl></div></section>
    </div>
    <div class="detail-side"><section class="detail-card"><div class="detail-card-header"><h2>Ringkasan tagihan</h2><span class="<?= e(status_class($payment['status'])) ?>"><?= status_icon($payment['status']) ?><?= e($payment['status']) ?></span></div><div class="detail-card-body"><div class="metric-list"><div class="metric-row"><span>Biaya pemeriksaan</span><strong><?= e(format_currency($payment['biaya_pemeriksaan'])) ?></strong></div><div class="metric-row"><span>Biaya tindakan</span><strong><?= e(format_currency($payment['biaya_tindakan'])) ?></strong></div><div class="metric-row"><span>Total obat</span><strong><?= e(format_currency($payment['total_obat'])) ?></strong></div></div><div class="total-row"><span>Total yang perlu dibayar</span><strong><?= e(format_currency((float) $payment['biaya_pemeriksaan'] + (float) $payment['biaya_tindakan'] + (float) $payment['total_obat'])) ?></strong></div></div></section><section class="detail-card"><div class="detail-card-header"><h2>Konteks kunjungan</h2></div><div class="detail-card-body"><div class="metric-list"><div class="metric-row"><span>Poli</span><strong><?= e($visit['nama_poli']) ?></strong></div><div class="metric-row"><span>Dokter</span><strong><?= e($visit['nama_dokter']) ?></strong></div><div class="metric-row"><span>Diagnosa</span><strong><?= e($visit['diagnosa'] ?: 'Belum diperiksa') ?></strong></div></div></div></section></div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
