<?php
require_once __DIR__ . '/../includes/functions.php';

$id = (int) query_value('id');
$visit = db_select_one("SELECT v.*, p.nama AS nama_pasien, p.no_rm, p.nik, p.jenis_kelamin, p.no_hp, pc.nama_poli, pc.lokasi, d.nama_dokter, d.kode_dokter, s.nama AS spesialisasi, e.id AS examination_id, e.keluhan, e.hasil_pemeriksaan, e.tekanan_darah, e.suhu, e.berat_badan, e.diagnosa, e.tindakan, e.catatan_dokter, e.diperiksa_pada
    FROM visits v
    INNER JOIN patients p ON p.id = v.patient_id
    INNER JOIN polyclinics pc ON pc.id = v.polyclinic_id
    INNER JOIN doctors d ON d.id = v.doctor_id
    INNER JOIN specializations s ON s.id = d.specialization_id
    LEFT JOIN examinations e ON e.visit_id = v.id
    WHERE v.id = ?", array($id));
if (!$visit) {
    flash('danger', 'Detail kunjungan tidak ditemukan.');
    redirect_to('kunjungan/index.php');
}

$page_title = 'Detail kunjungan';
$page_description = 'Satu halaman untuk mengikuti perjalanan layanan pasien sampai selesai.';
$active_menu = 'kunjungan';
$page_action_html = page_action('Kunjungan lain', 'kunjungan/index.php', 'arrow-left', 'button button-secondary');

if (is_post()) {
    try {
        verify_csrf();
        $action = post_value('action');
        if ($action === 'mulai') {
            if ($visit['status'] !== 'Menunggu') {
                throw new RuntimeException('Kunjungan hanya dapat dimulai dari status Menunggu.');
            }
            $updated = db_execute("UPDATE visits SET status = 'Diperiksa' WHERE id = ? AND status = 'Menunggu'", array($id));
            if ((int) $updated['affected_rows'] !== 1) {
                throw new RuntimeException('Status kunjungan berubah. Silakan muat ulang halaman.');
            }
            flash('success', 'Kunjungan dimulai. Pasien siap masuk ke pemeriksaan.');
        } elseif ($action === 'selesai') {
            if ($visit['status'] !== 'Diperiksa') {
                throw new RuntimeException('Kunjungan hanya dapat diselesaikan dari status Diperiksa.');
            }
            if (!$visit['examination_id']) {
                throw new RuntimeException('Simpan hasil pemeriksaan sebelum menandai kunjungan selesai.');
            }
            $updated = db_execute("UPDATE visits SET status = 'Selesai' WHERE id = ? AND status = 'Diperiksa'", array($id));
            if ((int) $updated['affected_rows'] !== 1) {
                throw new RuntimeException('Status kunjungan berubah. Silakan muat ulang halaman.');
            }
            flash('success', 'Kunjungan ditandai selesai.');
        } elseif ($action === 'batal') {
            if (!in_array($visit['status'], array('Menunggu', 'Diperiksa'), true)) {
                throw new RuntimeException('Kunjungan yang sudah selesai atau dibatalkan tidak dapat dibatalkan lagi.');
            }
            $updated = db_execute("UPDATE visits SET status = 'Batal' WHERE id = ? AND status IN ('Menunggu', 'Diperiksa')", array($id));
            if ((int) $updated['affected_rows'] !== 1) {
                throw new RuntimeException('Status kunjungan berubah. Silakan muat ulang halaman.');
            }
            flash('success', 'Kunjungan dibatalkan.');
        } else {
            throw new RuntimeException('Tindakan kunjungan tidak dikenali.');
        }
    } catch (Throwable $exception) {
        flash('danger', $exception->getMessage());
    }
    redirect_to('kunjungan/detail.php?id=' . $id);
}

$prescriptions = db_select_all("SELECT pr.id, pr.no_resep, pr.status, pr.catatan, pr.selesai_pada, pr.created_at, COUNT(pd.id) AS item_count, COALESCE(SUM(pd.jumlah * pd.harga_satuan), 0) AS total_obat
    FROM prescriptions pr LEFT JOIN prescription_details pd ON pd.prescription_id = pr.id WHERE pr.visit_id = ? GROUP BY pr.id ORDER BY pr.created_at DESC", array($id));
$payment = sync_payment_total($id);
$completed_prescription_count = 0;
foreach ($prescriptions as $prescription) {
    if ($prescription['status'] === 'Diselesaikan') {
        $completed_prescription_count++;
    }
}
$has_prescription = !empty($prescriptions);

require_once __DIR__ . '/../includes/header.php';
?>
<div class="detail-layout">
    <div class="detail-main">
        <section class="detail-card identity-card"><div class="identity-avatar"><?= e(initials($visit['nama_pasien'])) ?></div><div class="identity-copy"><h2><?= e($visit['nama_pasien']) ?></h2><p class="mono"><?= e($visit['no_rm']) ?> · <?= e($visit['no_kunjungan']) ?></p><span class="<?= e(status_class($visit['status'])) ?>"><?= status_icon($visit['status']) ?><?= e($visit['status']) ?></span></div></section>
        <section class="detail-card"><div class="detail-card-header"><h2>Informasi kunjungan</h2><span class="cell-muted">Dibuat <?= e(format_date_id($visit['created_at'], true)) ?></span></div><div class="detail-card-body"><dl class="detail-list"><div><dt>Tanggal layanan</dt><dd><?= e(format_date_id($visit['tanggal_kunjungan'])) ?></dd></div><div><dt>Nomor antrean</dt><dd>#<?= e($visit['nomor_antrian']) ?></dd></div><div><dt>Poli</dt><dd><?= e($visit['nama_poli']) ?><br><span class="cell-muted"><?= e($visit['lokasi']) ?></span></dd></div><div><dt>Dokter</dt><dd><?= e($visit['nama_dokter']) ?><br><span class="cell-muted"><?= e($visit['spesialisasi']) ?></span></dd></div><div class="full"><dt>Keluhan awal</dt><dd><?= nl2br(e($visit['keluhan_awal'])) ?></dd></div></dl></div></section>
        <?php if ($visit['examination_id']): ?><section class="detail-card"><div class="detail-card-header"><h2>Hasil pemeriksaan</h2><a class="panel-link" href="<?= e(base_url('pemeriksaan/edit.php?id=' . $visit['examination_id'])) ?>">Edit pemeriksaan <?= icon('arrow-right') ?></a></div><div class="detail-card-body"><div class="visit-summary"><div class="summary-tile"><span>Diagnosa</span><strong><?= e($visit['diagnosa']) ?></strong></div><div class="summary-tile"><span>Tekanan darah</span><strong><?= e($visit['tekanan_darah'] ?: '-') ?></strong></div><div class="summary-tile"><span>Suhu</span><strong><?= e($visit['suhu'] ? $visit['suhu'] . ' °C' : '-') ?></strong></div></div><dl class="detail-list"><div class="full"><dt>Hasil pemeriksaan</dt><dd><?= nl2br(e($visit['hasil_pemeriksaan'])) ?></dd></div><div><dt>Tindakan</dt><dd><?= nl2br(e($visit['tindakan'] ?: '-')) ?></dd></div><div><dt>Catatan dokter</dt><dd><?= nl2br(e($visit['catatan_dokter'] ?: '-')) ?></dd></div></dl></div></section><?php endif; ?>
        <section class="detail-card"><div class="detail-card-header"><div><h2>Resep obat</h2><span class="cell-muted"><?= e(count($prescriptions)) ?> resep tercatat</span></div><?php if ($visit['status'] !== 'Batal'): ?><a class="panel-link" href="<?= e(base_url('resep/tambah.php?visit_id=' . $id)) ?>">Buat resep <?= icon('arrow-right') ?></a><?php endif; ?></div><?php if (empty($prescriptions)): ?><?= render_empty_state('pill', 'Belum ada resep', 'Resep dapat dibuat setelah dokter menilai kebutuhan pasien.', $visit['status'] !== 'Batal' ? page_action('Buat resep', 'resep/tambah.php?visit_id=' . $id, 'plus', 'button button-ghost') : '') ?><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>No. resep</th><th>Item</th><th>Total obat</th><th>Status</th><th scope="col" class="table-action-heading"><span class="sr-only">Aksi</span></th></tr></thead><tbody><?php foreach ($prescriptions as $prescription): ?><tr><td class="mono"><?= e($prescription['no_resep']) ?><div class="cell-muted"><?= e(format_date_id($prescription['created_at'])) ?></div></td><td><?= e($prescription['item_count']) ?> obat</td><td><?= e(format_currency($prescription['total_obat'])) ?></td><td><span class="<?= e(status_class($prescription['status'])) ?>"><?= status_icon($prescription['status']) ?><?= e($prescription['status']) ?></span></td><td class="action-cell"><a class="table-action" href="<?= e(base_url('resep/detail.php?id=' . $prescription['id'])) ?>" aria-label="Detail resep"><?= icon('arrow-right') ?></a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
    </div>
    <div class="detail-side">
        <section class="detail-card"><div class="detail-card-header"><h2>Perjalanan layanan</h2></div><div class="detail-card-body"><div class="timeline"><div class="timeline-item"><span class="timeline-dot"></span><div class="timeline-copy"><strong>Pendaftaran dibuat</strong><span><?= e(format_date_id($visit['created_at'], true)) ?> · Antrean #<?= e($visit['nomor_antrian']) ?></span></div></div><div class="timeline-item <?= $visit['examination_id'] ? '' : 'is-muted' ?>"><span class="timeline-dot"></span><div class="timeline-copy"><strong><?= $visit['examination_id'] ? 'Pemeriksaan selesai' : 'Menunggu pemeriksaan' ?></strong><span><?= $visit['examination_id'] ? e(format_date_id($visit['diperiksa_pada'], true)) : 'Hasil pemeriksaan belum diisi' ?></span></div></div><div class="timeline-item <?= $completed_prescription_count > 0 ? '' : 'is-muted' ?>"><span class="timeline-dot"></span><div class="timeline-copy"><strong><?= $completed_prescription_count > 0 ? 'Resep diselesaikan' : 'Resep belum diselesaikan' ?></strong><span><?= $completed_prescription_count > 0 ? 'Stok obat sudah diperbarui' : ($has_prescription ? 'Menunggu konfirmasi resep' : 'Tidak ada resep tercatat') ?></span></div></div><div class="timeline-item <?= $payment['status'] === 'Sudah Dibayar' ? '' : 'is-muted' ?>"><span class="timeline-dot"></span><div class="timeline-copy"><strong><?= e($payment['status']) ?></strong><span><?= $payment['status'] === 'Sudah Dibayar' ? e(format_date_id($payment['dibayar_pada'], true)) : 'Total ' . format_currency($payment['total']) ?></span></div></div></div></div></section>
        <section class="detail-card"><div class="detail-card-header"><h2>Tindakan berikutnya</h2></div><div class="detail-card-body"><div class="quick-actions"><?php if ($visit['status'] === 'Menunggu'): ?><form method="post" class="quick-action" action="<?= e(base_url('kunjungan/detail.php?id=' . $id)) ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="mulai"><button type="submit" style="display: contents; text-align: left; cursor: pointer;"><span class="quick-action-icon"><?= icon('stethoscope') ?></span><span class="quick-action-copy"><strong>Mulai pemeriksaan</strong><span>Panggil pasien berikutnya</span></span></button></form><?php elseif (!$visit['examination_id'] && $visit['status'] !== 'Batal'): ?><a class="quick-action" href="<?= e(base_url('pemeriksaan/tambah.php?visit_id=' . $id)) ?>"><span class="quick-action-icon"><?= icon('stethoscope') ?></span><span class="quick-action-copy"><strong>Isi pemeriksaan</strong><span>Catat hasil dokter</span></span></a><?php endif; ?><?php if ($visit['examination_id'] && $visit['status'] !== 'Batal'): ?><a class="quick-action" href="<?= e(base_url('resep/tambah.php?visit_id=' . $id)) ?>"><span class="quick-action-icon"><?= icon('pill') ?></span><span class="quick-action-copy"><strong>Buat resep</strong><span>Tambahkan obat pasien</span></span></a><?php endif; ?><a class="quick-action" href="<?= e(base_url('pembayaran/detail.php?visit_id=' . $id)) ?>"><span class="quick-action-icon"><?= icon('wallet') ?></span><span class="quick-action-copy"><strong>Kelola pembayaran</strong><span><?= e($payment['status']) ?></span></span></a></div><?php if ($visit['status'] === 'Diperiksa' && $visit['examination_id']): ?><form method="post" action="<?= e(base_url('kunjungan/detail.php?id=' . $id)) ?>" style="margin-top: 12px;"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="selesai"><button class="button button-primary" type="submit" style="width: 100%;"><?= icon('check') ?><span>Tandai kunjungan selesai</span></button></form><?php endif; ?><?php if ($visit['status'] !== 'Selesai' && $visit['status'] !== 'Batal'): ?><form method="post" action="<?= e(base_url('kunjungan/detail.php?id=' . $id)) ?>" data-confirm="Batalkan kunjungan ini? Tindakan ini tidak dapat dibatalkan."><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="batal"><button class="button button-danger button-small" type="submit" style="width: 100%; margin-top: 8px;">Batalkan kunjungan</button></form><?php endif; ?></div></section>
        <section class="detail-card"><div class="detail-card-header"><h2>Ringkasan biaya</h2><a class="panel-link" href="<?= e(base_url('pembayaran/detail.php?visit_id=' . $id)) ?>">Buka <?= icon('arrow-right') ?></a></div><div class="detail-card-body"><div class="metric-list"><div class="metric-row"><span>Biaya pemeriksaan</span><strong><?= e(format_currency($payment['biaya_pemeriksaan'])) ?></strong></div><div class="metric-row"><span>Biaya tindakan</span><strong><?= e(format_currency($payment['biaya_tindakan'])) ?></strong></div><div class="metric-row"><span>Total obat</span><strong><?= e(format_currency($payment['total_obat'])) ?></strong></div></div><div class="total-row"><span>Total pembayaran</span><strong><?= e(format_currency($payment['total'])) ?></strong></div><div style="margin-top: 12px;"><span class="<?= e(status_class($payment['status'])) ?>"><?= status_icon($payment['status']) ?><?= e($payment['status']) ?></span></div></div></section>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
