<?php
require_once __DIR__ . '/../includes/functions.php';

$id = (int) query_value('id');
$prescription = db_select_one("SELECT pr.*, v.id AS visit_id, v.no_kunjungan, v.tanggal_kunjungan, p.nama AS nama_pasien, p.no_rm, pc.nama_poli, d.nama_dokter FROM prescriptions pr INNER JOIN visits v ON v.id = pr.visit_id INNER JOIN patients p ON p.id = v.patient_id INNER JOIN polyclinics pc ON pc.id = v.polyclinic_id INNER JOIN doctors d ON d.id = v.doctor_id WHERE pr.id = ?", array($id));
if (!$prescription) { flash('danger', 'Data resep tidak ditemukan.'); redirect_to('resep/index.php'); }

$page_title = 'Detail resep';
$page_description = 'Periksa isi resep dan selesaikan pengeluaran obat dengan aman.';
$active_menu = 'resep';
$page_action_html = page_action('Kembali ke kunjungan', 'kunjungan/detail.php?id=' . $prescription['visit_id'], 'arrow-left', 'button button-secondary');
$transaction_started = false;

if (is_post()) {
    try {
        verify_csrf();
        if (post_value('action') !== 'selesaikan') { throw new RuntimeException('Tindakan resep tidak dikenali.'); }
        begin_transaction();
        $transaction_started = true;
        // Kunci baris resep dan baca ulang status di dalam transaksi. Ini
        // mencegah dua tab mengurangi stok dua kali pada submit bersamaan.
        $locked_prescription = db_select_one('SELECT id, visit_id, status FROM prescriptions WHERE id = ? FOR UPDATE', array($id));
        if (!$locked_prescription || $locked_prescription['status'] !== 'Draft') { throw new RuntimeException('Resep ini sudah tidak berstatus draft.'); }
        $details_for_update = db_select_all('SELECT medicine_id, jumlah FROM prescription_details WHERE prescription_id = ? FOR UPDATE', array($id));
        if (empty($details_for_update)) { throw new RuntimeException('Resep tidak memiliki obat yang dapat dikeluarkan.'); }
        foreach ($details_for_update as $detail) {
            $medicine = db_select_one('SELECT id, nama_obat, stok, tanggal_expired FROM medicines WHERE id = ? FOR UPDATE', array((int) $detail['medicine_id']));
            if (!$medicine || !is_valid_calendar_date($medicine['tanggal_expired']) || $medicine['tanggal_expired'] < date('Y-m-d')) {
                throw new RuntimeException('Ada obat expired di resep. Resep tidak dapat diselesaikan.');
            }
            if ((int) $detail['jumlah'] > (int) $medicine['stok']) {
                throw new RuntimeException('Stok ' . $medicine['nama_obat'] . ' tidak cukup. Tersedia ' . $medicine['stok'] . ', resep membutuhkan ' . $detail['jumlah'] . '.');
            }
            $updated = db_execute('UPDATE medicines SET stok = stok - ? WHERE id = ? AND stok >= ?', array((int) $detail['jumlah'], (int) $detail['medicine_id'], (int) $detail['jumlah']));
            if ((int) $updated['affected_rows'] !== 1) {
                throw new RuntimeException('Stok berubah saat proses berjalan. Silakan periksa ulang resep.');
            }
        }
        $status_update = db_execute("UPDATE prescriptions SET status = 'Diselesaikan', selesai_pada = NOW() WHERE id = ? AND status = 'Draft'", array($id));
        if ((int) $status_update['affected_rows'] !== 1) {
            throw new RuntimeException('Resep sudah diproses oleh pengguna lain.');
        }
        sync_payment_total((int) $locked_prescription['visit_id']);
        commit_transaction();
        $transaction_started = false;
        flash('success', 'Resep diselesaikan. Stok obat sudah otomatis diperbarui.');
    } catch (Throwable $exception) {
        if ($transaction_started) {
            rollback_transaction();
        }
        flash('danger', $exception->getMessage());
    }
    redirect_to('resep/detail.php?id=' . $id);
}

$details = db_select_all('SELECT pd.*, m.kode_obat, m.nama_obat, m.satuan FROM prescription_details pd INNER JOIN medicines m ON m.id = pd.medicine_id WHERE pd.prescription_id = ? ORDER BY pd.id', array($id));
$total_obat = 0;
foreach ($details as $detail) { $total_obat += (float) $detail['jumlah'] * (float) $detail['harga_satuan']; }

require_once __DIR__ . '/../includes/header.php';
?>
<div class="detail-layout">
    <div class="detail-main">
        <section class="detail-card identity-card"><div class="identity-avatar"><?= e(initials($prescription['nama_pasien'])) ?></div><div class="identity-copy"><h2><?= e($prescription['nama_pasien']) ?></h2><p class="mono"><?= e($prescription['no_rm']) ?> · <?= e($prescription['no_kunjungan']) ?></p><span class="<?= e(status_class($prescription['status'])) ?>"><?= status_icon($prescription['status']) ?><?= e($prescription['status']) ?></span></div></section>
        <section class="detail-card"><div class="detail-card-header"><div><h2>Isi resep</h2><span class="cell-muted mono"><?= e($prescription['no_resep']) ?> · <?= e(format_date_id($prescription['created_at'], true)) ?></span></div><?php if ($prescription['status'] === 'Draft'): ?><form method="post" class="inline-form" action="<?= e(base_url('resep/detail.php?id=' . $id)) ?>" data-confirm="Selesaikan resep ini dan kurangi stok semua obat sesuai jumlah? Tindakan ini tidak dapat dibatalkan."><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="selesaikan"><button class="button button-primary button-small" type="submit"><?= icon('check') ?><span>Selesaikan resep</span></button></form><?php endif; ?></div><?php if (empty($details)): ?><?= render_empty_state('pill', 'Resep belum memiliki item', 'Tambahkan obat dari halaman pembuatan resep.') ?><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Obat</th><th>Jumlah</th><th>Dosis</th><th>Aturan penggunaan</th><th>Harga</th></tr></thead><tbody><?php foreach ($details as $detail): ?><tr><td><div class="cell-primary"><?= e($detail['nama_obat']) ?></div><div class="cell-muted mono"><?= e($detail['kode_obat']) ?> · <?= e($detail['satuan']) ?></div></td><td><?= e($detail['jumlah']) ?></td><td><?= e($detail['dosis']) ?></td><td><?= e($detail['aturan_penggunaan']) ?></td><td><?= e(format_currency((float) $detail['jumlah'] * (float) $detail['harga_satuan'])) ?></td></tr><?php endforeach; ?></tbody></table></div><div class="detail-card-body"><div class="total-row" style="margin-top: 0;"><span>Total obat</span><strong><?= e(format_currency($total_obat)) ?></strong></div></div><?php endif; ?></section>
        <section class="detail-card"><div class="detail-card-header"><h2>Catatan resep</h2></div><div class="detail-card-body"><p style="margin: 0; color: var(--muted); font-size: 12px;"><?= nl2br(e($prescription['catatan'] ?: 'Tidak ada catatan tambahan.')) ?></p></div></section>
    </div>
    <div class="detail-side"><section class="detail-card"><div class="detail-card-header"><h2>Informasi kunjungan</h2></div><div class="detail-card-body"><div class="metric-list"><div class="metric-row"><span>Tanggal</span><strong><?= e(format_date_id($prescription['tanggal_kunjungan'])) ?></strong></div><div class="metric-row"><span>Poli</span><strong><?= e($prescription['nama_poli']) ?></strong></div><div class="metric-row"><span>Dokter</span><strong><?= e($prescription['nama_dokter']) ?></strong></div><?php if ($prescription['selesai_pada']): ?><div class="metric-row"><span>Diselesaikan</span><strong><?= e(format_date_id($prescription['selesai_pada'], true)) ?></strong></div><?php endif; ?></div></div></section><section class="detail-card"><div class="detail-card-header"><h2>Catatan stok</h2></div><div class="detail-card-body"><div class="form-note" style="margin-top: 0;"><?= icon('shield-check') ?><span>Pengurangan stok dilakukan satu kali di dalam transaksi database setelah petugas menyelesaikan resep.</span></div></div></section></div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
