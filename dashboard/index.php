<?php
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Dashboard';
$page_description = 'Ringkasan operasional klinik untuk membantu tim bekerja lebih tenang hari ini.';
$active_menu = 'dashboard';
$page_action_html = page_action('Daftarkan kunjungan', 'kunjungan/tambah.php', 'calendar-days') . page_action('Tambah pasien', 'pasien/tambah.php', 'user-plus', 'button button-secondary');

$total_patients = (int) db_value('SELECT COUNT(*) FROM patients WHERE archived_at IS NULL');
$total_doctors = (int) db_value("SELECT COUNT(*) FROM doctors WHERE archived_at IS NULL AND status = 'Aktif'");
$total_polyclinics = (int) db_value("SELECT COUNT(*) FROM polyclinics WHERE archived_at IS NULL AND status = 'Aktif'");
$total_medicines = (int) db_value("SELECT COUNT(*) FROM medicines WHERE archived_at IS NULL AND status = 'Aktif'");
$today_visits = (int) db_value('SELECT COUNT(*) FROM visits WHERE tanggal_kunjungan = CURDATE()');
$today_waiting = (int) db_value("SELECT COUNT(*) FROM visits WHERE tanggal_kunjungan = CURDATE() AND status = 'Menunggu'");
$today_finished = (int) db_value("SELECT COUNT(*) FROM visits WHERE tanggal_kunjungan = CURDATE() AND status = 'Selesai'");
$low_stock_count = (int) db_value('SELECT COUNT(*) FROM medicines WHERE archived_at IS NULL AND status = \'Aktif\' AND stok <= 10 AND tanggal_expired >= CURDATE()');
$expired_count = (int) db_value('SELECT COUNT(*) FROM medicines WHERE archived_at IS NULL AND status = \'Aktif\' AND tanggal_expired < CURDATE()');

$week_rows = db_select_all("SELECT tanggal_kunjungan, COUNT(*) AS total FROM visits WHERE tanggal_kunjungan >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY tanggal_kunjungan ORDER BY tanggal_kunjungan");
$week_map = array();
foreach ($week_rows as $week_row) {
    $week_map[$week_row['tanggal_kunjungan']] = (int) $week_row['total'];
}

$week_days = array();
for ($offset = 6; $offset >= 0; $offset--) {
    $date = date('Y-m-d', strtotime('-' . $offset . ' days'));
    $week_days[] = array(
        'date' => $date,
        'label' => date('D', strtotime($date)),
        'total' => $week_map[$date] ?? 0,
        'is_today' => $offset === 0,
    );
}
$week_max = 1;
foreach ($week_days as $week_day) {
    $week_max = max($week_max, $week_day['total']);
}

$chart = array(
    'width' => 720,
    'height' => 250,
    'left' => 42,
    'right' => 24,
    'top' => 24,
    'bottom' => 196,
);
$chart['plot_width'] = $chart['width'] - $chart['left'] - $chart['right'];
$chart['plot_height'] = $chart['bottom'] - $chart['top'];
$chart_points = array();
$chart_step = $chart['plot_width'] / max(1, count($week_days) - 1);
$chart_divisor = max(1, $week_max);

foreach ($week_days as $index => $week_day) {
    $chart_points[] = array(
        'x' => round($chart['left'] + ($chart_step * $index), 1),
        'y' => round($chart['top'] + (($week_max - $week_day['total']) / $chart_divisor * $chart['plot_height']), 1),
        'value' => $week_day['total'],
        'label' => $week_day['is_today'] ? 'Hari ini' : $week_day['label'],
        'is_today' => $week_day['is_today'],
    );
}

$chart_line_points = '';
foreach ($chart_points as $point) {
    $chart_line_points .= ($chart_line_points === '' ? '' : ' ') . $point['x'] . ',' . $point['y'];
}
$chart_area_path = 'M ' . $chart_points[0]['x'] . ' ' . $chart['bottom'] . ' L ' . $chart_line_points . ' L ' . $chart_points[count($chart_points) - 1]['x'] . ' ' . $chart['bottom'] . ' Z';
$chart_grid_values = array(0, (int) ceil($week_max / 2), $week_max);
$chart_data = array();
foreach ($week_days as $week_day) {
    $chart_data[] = array(
        'label' => $week_day['is_today'] ? 'Hari ini' : $week_day['label'],
        'date' => format_date_id($week_day['date']),
        'total' => $week_day['total'],
    );
}

$recent_visits = db_select_all("SELECT v.id, v.no_kunjungan, v.nomor_antrian, v.tanggal_kunjungan, v.status, p.nama, p.no_rm, pc.nama_poli, d.nama_dokter
    FROM visits v
    INNER JOIN patients p ON p.id = v.patient_id
    INNER JOIN polyclinics pc ON pc.id = v.polyclinic_id
    INNER JOIN doctors d ON d.id = v.doctor_id
    ORDER BY v.tanggal_kunjungan DESC, v.nomor_antrian DESC
    LIMIT 5");

$medicine_alerts = db_select_all("SELECT m.nama_obat, m.stok, m.tanggal_expired, mc.nama_kategori
    FROM medicines m
    INNER JOIN medicine_categories mc ON mc.id = m.category_id
    WHERE m.archived_at IS NULL AND m.status = 'Aktif' AND (m.stok <= 10 OR m.tanggal_expired < CURDATE())
    ORDER BY (m.tanggal_expired < CURDATE()) DESC, m.stok ASC
    LIMIT 4");

$progress = $today_visits > 0 ? min(100, (int) round(($today_finished / $today_visits) * 100)) : 0;

require_once __DIR__ . '/../includes/header.php';
?>
<section class="stat-grid" aria-label="Ringkasan data klinik">
    <a class="stat-card stat-card-link stat-teal" href="<?= e(base_url('pasien/index.php')) ?>" aria-label="Buka data pasien">
        <div class="stat-top">
            <span class="stat-label">Total pasien</span>
            <span class="stat-icon"><?= icon('users-round') ?></span>
        </div>
        <div class="stat-value"><?= e($total_patients) ?></div>
        <div class="stat-note"><strong>Data aktif</strong><span>tercatat di sistem</span></div>
        <span class="stat-link-hint">Buka data pasien <?= icon('arrow-up-right') ?></span>
    </a>
    <a class="stat-card stat-card-link stat-blue" href="<?= e(base_url('dokter/index.php')) ?>" aria-label="Buka data dokter">
        <div class="stat-top">
            <span class="stat-label">Dokter aktif</span>
            <span class="stat-icon"><?= icon('stethoscope') ?></span>
        </div>
        <div class="stat-value"><?= e($total_doctors) ?></div>
        <div class="stat-note"><strong><?= e($total_polyclinics) ?> poli</strong><span>siap melayani</span></div>
        <span class="stat-link-hint">Buka data dokter <?= icon('arrow-up-right') ?></span>
    </a>
    <a class="stat-card stat-card-link stat-amber" href="<?= e(base_url('obat/index.php')) ?>" aria-label="Buka data obat">
        <div class="stat-top">
            <span class="stat-label">Persediaan obat</span>
            <span class="stat-icon"><?= icon('pill') ?></span>
        </div>
        <div class="stat-value"><?= e($total_medicines) ?></div>
        <div class="stat-note"><strong><?= e($low_stock_count) ?> perlu dicek</strong><span>stok menipis</span></div>
        <span class="stat-link-hint">Kelola persediaan <?= icon('arrow-up-right') ?></span>
    </a>
    <a class="stat-card stat-card-link stat-violet" href="<?= e(base_url('kunjungan/index.php')) ?>" aria-label="Buka daftar kunjungan">
        <div class="stat-top">
            <span class="stat-label">Kunjungan hari ini</span>
            <span class="stat-icon"><?= icon('calendar-days') ?></span>
        </div>
        <div class="stat-value"><?= e($today_visits) ?></div>
        <div class="stat-note"><strong><?= e($today_waiting) ?> menunggu</strong><span>di antrean</span></div>
        <span class="stat-link-hint">Lihat antrean <?= icon('arrow-up-right') ?></span>
    </a>
</section>

<section class="dashboard-grid">
    <article class="panel chart-panel">
        <div class="panel-header">
            <div>
                <h2>Ritme kunjungan</h2>
                <p>Jumlah kunjungan tujuh hari terakhir</p>
            </div>
            <span class="status-pill status-aktif"><?= icon('trending-up') ?> Data terbaru</span>
        </div>
        <div class="panel-body">
            <div class="chart-area line-chart-area" data-visit-chart data-chart-values='<?= e(json_encode($chart_data, JSON_UNESCAPED_UNICODE)) ?>' aria-label="Grafik garis kunjungan tujuh hari terakhir">
                <svg class="line-chart" viewBox="0 0 <?= e($chart['width']) ?> <?= e($chart['height']) ?>" role="img" aria-labelledby="visit-chart-title visit-chart-description">
                    <title id="visit-chart-title">Kunjungan tujuh hari terakhir</title>
                    <desc id="visit-chart-description">Jumlah kunjungan dari <?= e($chart_points[0]['label']) ?> sampai hari ini.</desc>
                    <defs>
                        <linearGradient id="visit-area-gradient" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0%" stop-color="var(--chart-accent)" stop-opacity=".24"></stop>
                            <stop offset="100%" stop-color="var(--chart-accent)" stop-opacity="0"></stop>
                        </linearGradient>
                    </defs>
                    <?php foreach ($chart_grid_values as $grid_value): ?>
                        <?php $grid_y = round($chart['top'] + (($week_max - $grid_value) / $chart_divisor * $chart['plot_height']), 1); ?>
                        <line class="chart-grid-line" x1="<?= e($chart['left']) ?>" y1="<?= e($grid_y) ?>" x2="<?= e($chart['width'] - $chart['right']) ?>" y2="<?= e($grid_y) ?>"></line>
                        <text class="chart-axis-label" x="0" y="<?= e($grid_y + 4) ?>"><?= e($grid_value) ?></text>
                    <?php endforeach; ?>
                    <line class="chart-focus-line" data-chart-focus-line x1="<?= e($chart_points[count($chart_points) - 1]['x']) ?>" y1="<?= e($chart['top']) ?>" x2="<?= e($chart_points[count($chart_points) - 1]['x']) ?>" y2="<?= e($chart['bottom']) ?>"></line>
                    <path class="chart-area-fill" d="<?= e($chart_area_path) ?>"></path>
                    <polyline class="chart-line" points="<?= e($chart_line_points) ?>"></polyline>
                    <?php foreach ($chart_points as $point): ?>
                        <g class="chart-point-group <?= $point['is_today'] ? 'is-today' : '' ?>" data-chart-point data-x="<?= e($point['x']) ?>" data-y="<?= e($point['y']) ?>">
                            <circle class="chart-point-halo" cx="<?= e($point['x']) ?>" cy="<?= e($point['y']) ?>" r="9"></circle>
                            <circle class="chart-point" cx="<?= e($point['x']) ?>" cy="<?= e($point['y']) ?>" r="4.5"></circle>
                            <text class="chart-point-value" x="<?= e($point['x']) ?>" y="<?= e($point['y'] - 14) ?>" text-anchor="middle"><?= e($point['value']) ?></text>
                            <text class="chart-x-label" x="<?= e($point['x']) ?>" y="<?= e($chart['height'] - 20) ?>" text-anchor="middle"><?= e($point['label']) ?></text>
                        </g>
                    <?php endforeach; ?>
                </svg>
                <div class="chart-hover-tooltip" data-chart-hover-tooltip role="status" aria-live="polite" hidden>
                    <span data-chart-hover-label><?= e($chart_data[count($chart_data) - 1]['label']) ?></span>
                    <strong><span data-chart-hover-value><?= e($chart_data[count($chart_data) - 1]['total']) ?></span> kunjungan</strong>
                    <small data-chart-hover-date><?= e($chart_data[count($chart_data) - 1]['date']) ?></small>
                </div>
                <div class="chart-insight" aria-live="polite">
                    <div class="chart-insight-copy">
                        <span>Hari dipilih</span>
                        <strong data-chart-selected-label><?= e($chart_data[count($chart_data) - 1]['label']) ?></strong>
                        <small data-chart-selected-date><?= e($chart_data[count($chart_data) - 1]['date']) ?></small>
                    </div>
                    <div class="chart-insight-number">
                        <strong data-chart-selected-value><?= e($chart_data[count($chart_data) - 1]['total']) ?></strong>
                        <span>kunjungan</span>
                    </div>
                </div>
                <div class="chart-scrubber">
                    <div class="chart-scrubber-meta"><span>Geser untuk melihat data harian</span><strong>7 hari</strong></div>
                    <input type="range" data-chart-range min="0" max="<?= e(count($chart_data) - 1) ?>" value="<?= e(count($chart_data) - 1) ?>" step="1" aria-label="Geser untuk memilih hari pada grafik kunjungan">
                    <div class="chart-range-labels" aria-hidden="true">
                        <?php foreach ($chart_data as $chart_day): ?><span><?= e($chart_day['label']) ?></span><?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </article>

    <article class="panel">
        <div class="panel-header">
            <div>
                <h2>Antrean hari ini</h2>
                <p>Progress layanan yang sedang berjalan</p>
            </div>
            <a class="panel-link" href="<?= e(base_url('kunjungan/index.php')) ?>">Lihat semua <?= icon('arrow-right') ?></a>
        </div>
        <div class="panel-body">
            <div class="queue-summary">
                <div class="queue-number" style="--queue-progress: <?= e($progress) ?>%;" role="img" aria-label="<?= e($today_finished) ?> kunjungan selesai dari <?= e($today_visits) ?> kunjungan hari ini">
                    <div class="queue-number-content">
                        <strong class="queue-completed-value"><?= e($today_finished) ?></strong>
                        <span class="queue-completed-label">Selesai</span>
                        <small class="queue-total-label">dari <b><?= e($today_visits) ?></b></small>
                    </div>
                </div>
                <div class="queue-summary-copy">
                    <div class="queue-kicker">Progress layanan</div>
                    <strong><?= e($today_finished) ?> kunjungan selesai</strong>
                    <p><?= e($today_waiting) ?> pasien masih menunggu layanan.</p>
                </div>
            </div>
            <div class="queue-progress-meta"><span>Progress hari ini</span><strong><?= e($progress) ?>%</strong></div>
            <div class="queue-progress" aria-label="Progress kunjungan selesai <?= e($progress) ?> persen"><span style="width: <?= e($progress) ?>%;"></span></div>
            <div class="mini-stats">
                <div class="mini-stat mini-stat-waiting"><span>Menunggu</span><strong><?= e($today_waiting) ?></strong></div>
                <div class="mini-stat mini-stat-done"><span>Selesai</span><strong><?= e($today_finished) ?></strong></div>
            </div>
        </div>
    </article>
</section>

<section class="section-grid">
    <article class="panel">
        <div class="panel-header">
            <div>
                <h2>Kunjungan terbaru</h2>
                <p>Aktivitas layanan paling baru di klinik</p>
            </div>
            <a class="panel-link" href="<?= e(base_url('riwayat/index.php')) ?>">Buka riwayat <?= icon('arrow-right') ?></a>
        </div>
        <?php if (empty($recent_visits)): ?>
            <?= render_empty_state('calendar-days', 'Belum ada kunjungan', 'Kunjungan yang didaftarkan akan muncul di sini.', page_action('Daftarkan kunjungan', 'kunjungan/tambah.php', 'plus', 'button button-ghost')) ?>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Pasien</th>
                        <th>Jadwal</th>
                        <th>Poli / dokter</th>
                        <th>Status</th>
                        <th scope="col" class="table-action-heading"><span class="sr-only">Aksi</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recent_visits as $visit): ?>
                        <tr>
                            <td>
                                <div class="patient-cell">
                                    <span class="patient-initial"><?= e(initials($visit['nama'])) ?></span>
                                    <div><div class="cell-primary"><?= e($visit['nama']) ?></div><div class="cell-muted mono"><?= e($visit['no_rm']) ?></div></div>
                                </div>
                            </td>
                            <td><div class="cell-primary"><?= e(format_date_id($visit['tanggal_kunjungan'])) ?></div><div class="cell-muted">Antrean <?= e($visit['nomor_antrian']) ?></div></td>
                            <td><div class="cell-primary"><?= e($visit['nama_poli']) ?></div><div class="cell-muted"><?= e($visit['nama_dokter']) ?></div></td>
                            <td><span class="<?= e(status_class($visit['status'])) ?>"><?= status_icon($visit['status']) ?><?= e($visit['status']) ?></span></td>
                            <td class="action-cell"><a class="table-action" href="<?= e(base_url('kunjungan/detail.php?id=' . $visit['id'])) ?>" aria-label="Lihat detail kunjungan"><?= icon('arrow-right') ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>

    <article class="panel attention-panel">
        <div class="panel-header">
            <div>
                <h2>Perlu perhatian</h2>
                <p>Stok rendah dan obat yang perlu ditindak</p>
            </div>
            <a class="panel-link" href="<?= e(base_url('obat/index.php')) ?>">Kelola obat <?= icon('arrow-right') ?></a>
        </div>
        <div class="panel-body">
            <?php if (empty($medicine_alerts)): ?>
                <?= render_empty_state('badge-check', 'Semua aman', 'Tidak ada stok rendah atau obat expired saat ini.') ?>
            <?php else: ?>
                <div class="notice-list">
                    <?php foreach ($medicine_alerts as $medicine): ?>
                        <?php $is_expired = strtotime($medicine['tanggal_expired']) < strtotime(date('Y-m-d')); ?>
                        <a class="notice-item" href="<?= e(base_url('obat/index.php?q=' . urlencode($medicine['nama_obat']))) ?>">
                            <span class="notice-item-icon <?= $is_expired ? 'is-danger' : '' ?>"><?= icon($is_expired ? 'alert-triangle' : 'pill') ?></span>
                            <span class="notice-copy"><strong><?= e($medicine['nama_obat']) ?></strong><span><?= $is_expired ? 'Expired ' . format_date_id($medicine['tanggal_expired']) : 'Sisa stok ' . $medicine['stok'] . ' ' . strtolower($medicine['nama_kategori']) ?></span></span>
                            <?= icon('chevron-right') ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php if ($expired_count > 0): ?><div class="form-note form-note-danger"><?= icon('alert-triangle') ?><span><?= e($expired_count) ?> obat expired perlu dipisahkan dari stok aktif.</span></div><?php endif; ?>
            <?php endif; ?>
        </div>
    </article>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
