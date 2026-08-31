<?php
require_once __DIR__ . '/../includes/functions.php';

$id = (int) query_value('id');
$patient = db_select_one('SELECT * FROM patients WHERE id = ?', array($id));
if (!$patient) {
    flash('danger', 'Data pasien tidak ditemukan.');
    redirect_to('pasien/index.php');
}

$is_archived = !empty($patient['archived_at']);
$page_title = 'Detail pasien';
$page_description = 'Profil ringkas dan jejak kunjungan pasien.';
$active_menu = 'pasien';
$page_action_html = $is_archived
    ? page_action('Kembali ke arsip', 'arsip/index.php?jenis=pasien', 'archive', 'button button-secondary')
    : page_action('Edit pasien', 'pasien/edit.php?id=' . $id, 'edit-3', 'button button-secondary') . page_action('Kunjungan baru', 'kunjungan/tambah.php?patient_id=' . $id, 'calendar-days');

$visits = db_select_all("SELECT v.id, v.no_kunjungan, v.tanggal_kunjungan, v.nomor_antrian, v.status, pc.nama_poli, d.nama_dokter, e.diagnosa, pay.total AS total_pembayaran
    FROM visits v
    INNER JOIN polyclinics pc ON pc.id = v.polyclinic_id
    INNER JOIN doctors d ON d.id = v.doctor_id
    LEFT JOIN examinations e ON e.visit_id = v.id
    LEFT JOIN payments pay ON pay.visit_id = v.id
    WHERE v.patient_id = ? ORDER BY v.tanggal_kunjungan DESC, v.nomor_antrian DESC", array($id));

require_once __DIR__ . '/../includes/header.php';
?>
<div class="detail-layout">
    <div class="detail-main">
        <section class="detail-card identity-card">
            <div class="identity-avatar"><?= e(initials($patient['nama'])) ?></div>
            <div class="identity-copy"><h2><?= e($patient['nama']) ?></h2><p class="mono"><?= e($patient['no_rm']) ?> · <?= e($patient['nik']) ?></p><?php if ($is_archived): ?><span class="archive-type-pill archive-type-cyan"><?= icon('archive') ?> Diarsipkan</span><?php else: ?><span class="status-pill status-aktif"><?= icon('badge-check') ?> Data aktif</span><?php endif; ?></div>
        </section>
        <section class="detail-card">
            <div class="detail-card-header"><h2>Informasi pribadi</h2><span class="cell-muted">Dibuat <?= e(format_date_id($patient['created_at'])) ?></span></div>
            <div class="detail-card-body">
                <dl class="detail-list">
                    <div><dt>Nama lengkap</dt><dd><?= e($patient['nama']) ?></dd></div>
                    <div><dt>Jenis kelamin</dt><dd><?= e(format_gender($patient['jenis_kelamin'])) ?></dd></div>
                    <div><dt>Tanggal lahir</dt><dd><?= e(format_date_id($patient['tanggal_lahir'])) ?> · <?= e(calculate_age($patient['tanggal_lahir'])) ?></dd></div>
                    <div><dt>No. HP</dt><dd><?= e($patient['no_hp']) ?></dd></div>
                    <div class="full"><dt>Alamat</dt><dd><?= nl2br(e($patient['alamat'])) ?></dd></div>
                </dl>
            </div>
        </section>
    </div>
    <div class="detail-side">
        <section class="detail-card">
            <div class="detail-card-header"><h2>Ringkasan</h2></div>
            <div class="detail-card-body">
                <div class="metric-list">
                    <div class="metric-row"><span>Total kunjungan</span><strong><?= e(count($visits)) ?></strong></div>
                    <div class="metric-row"><span>Kunjungan terakhir</span><strong><?= e(!empty($visits) ? format_date_id($visits[0]['tanggal_kunjungan']) : '-') ?></strong></div>
                    <div class="metric-row"><span>Usia</span><strong><?= e(calculate_age($patient['tanggal_lahir'])) ?></strong></div>
                </div>
            </div>
        </section>
        <section class="detail-card">
            <div class="detail-card-header"><h2>Kontak</h2><?= icon('phone') ?></div>
            <div class="detail-card-body"><div class="metric-list"><div class="metric-row"><span>No. HP</span><strong><?= e($patient['no_hp']) ?></strong></div><div class="metric-row"><span>No. rekam medis</span><strong class="mono"><?= e($patient['no_rm']) ?></strong></div></div></div>
        </section>
    </div>
</div>

<?php if ($is_archived): ?><div class="archive-guidance archive-context-note"><span class="archive-guidance-icon"><?= icon('archive') ?></span><div><strong>Data pasien ini sedang berada di Arsip.</strong><span>Riwayat kunjungan tetap dapat dibaca, tetapi data tidak dapat dipakai untuk kunjungan baru sebelum dipulihkan oleh Admin.</span></div></div><?php endif; ?>

<section class="panel" style="margin-top: 18px;">
    <div class="panel-header"><div><h2>Riwayat kunjungan</h2><p>Semua layanan yang pernah diterima pasien ini.</p></div><span class="toolbar-meta"><?= e(count($visits)) ?> kunjungan</span></div>
    <?php if (empty($visits)): ?>
        <?= render_empty_state('file-clock', 'Belum ada riwayat', 'Kunjungan pasien akan tercatat di sini setelah didaftarkan.', $is_archived ? '' : page_action('Daftarkan kunjungan', 'kunjungan/tambah.php?patient_id=' . $id, 'calendar-days', 'button button-ghost')) ?>
    <?php else: ?>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Tanggal</th><th>No. kunjungan</th><th>Poli</th><th>Dokter</th><th>Diagnosa</th><th>Total pembayaran</th><th>Status</th><th scope="col" class="table-action-heading"><span class="sr-only">Aksi</span></th></tr></thead><tbody>
        <?php foreach ($visits as $visit): ?>
            <tr><td><?= e(format_date_id($visit['tanggal_kunjungan'])) ?></td><td class="mono"><?= e($visit['no_kunjungan']) ?></td><td><?= e($visit['nama_poli']) ?></td><td><?= e($visit['nama_dokter']) ?></td><td><?= e($visit['diagnosa'] ?: 'Belum diperiksa') ?></td><td><?= e(format_currency($visit['total_pembayaran'] ?: 0)) ?></td><td><span class="<?= e(status_class($visit['status'])) ?>"><?= status_icon($visit['status']) ?><?= e($visit['status']) ?></span></td><td class="action-cell"><a class="table-action" href="<?= e(base_url('kunjungan/detail.php?id=' . $visit['id'])) ?>" aria-label="Detail kunjungan"><?= icon('arrow-right') ?></a></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
