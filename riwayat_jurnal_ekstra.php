<?php
require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $USER;

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/riwayat_jurnal_ekstra.php'));
$PAGE->set_title('Riwayat Jurnal Ekstrakurikuler');
$PAGE->set_heading('Riwayat Jurnal Ekstrakurikuler');

require_once(__DIR__ . '/lib.php');

echo $OUTPUT->header();

echo '<h2>Riwayat Jurnal Ekstrakurikuler</h2>';

echo '<div style="margin-bottom:15px;">
    <a class="btn btn-secondary" href="jurnal_ekstra.php">⬅️ Kembali ke Input</a>
    <a class="btn btn-success" href="export_jurnal_ekstra.php">🌍 Ekspor Jurnal per Bulan</a>
</div>';

// Filter bulan ini berdasarkan TANGGAL jurnal
$awalbulan  = strtotime(date('Y-m-01 00:00:00'));
$akhirbulan = strtotime(date('Y-m-01 00:00:00', strtotime('+1 month')));

$sql = "SELECT j.*, e.namaekstra, u.firstname, u.lastname,
               GROUP_CONCAT(CONCAT(us.firstname, ' ', us.lastname, ' (', a.status, ')') SEPARATOR ', ') AS absensi
          FROM {local_jm_ekstra_jurnal} j
          JOIN {local_jm_ekstra} e ON e.id = j.ekstraid
          JOIN {user} u ON u.id = j.pembinaid
     LEFT JOIN {local_jm_ekstra_absen} a ON a.jurnalid = j.id
     LEFT JOIN {user} us ON us.id = a.userid
         WHERE j.pembinaid = :userid
           AND j.tanggal >= :awal
           AND j.tanggal < :akhir
      GROUP BY j.id
      ORDER BY j.tanggal DESC";

$params = [
    'userid' => $USER->id,
    'awal'   => $awalbulan,
    'akhir'  => $akhirbulan
];

$entries = $DB->get_records_sql($sql, $params);

if ($entries) {

    echo html_writer::start_tag('table', ['class' => 'generaltable']);
    echo html_writer::start_tag('thead');

    echo html_writer::tag('tr',
        html_writer::tag('th', 'No') .
        html_writer::tag('th', 'Tanggal') .
        html_writer::tag('th', 'Ekstra') .
        html_writer::tag('th', 'Materi') .
        html_writer::tag('th', 'Kegiatan') .
        html_writer::tag('th', 'Catatan') .
        html_writer::tag('th', 'Absen') .
        html_writer::tag('th', 'Waktu Input')
    );

    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    $no = 1;

    foreach ($entries as $e) {

        echo html_writer::start_tag('tr');

        echo html_writer::tag('td', $no++);
        echo html_writer::tag('td', tanggal_indo($e->tanggal));
        echo html_writer::tag('td', $e->namaekstra);
        echo html_writer::tag('td', shorten_text($e->materi, 40));
        echo html_writer::tag('td', shorten_text($e->kegiatan, 40));
        echo html_writer::tag('td', shorten_text($e->catatan, 40));
        echo html_writer::tag('td', shorten_text($e->absensi, 40));
        echo html_writer::tag('td', tanggal_indo($e->timecreated));

        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');

} else {
    echo html_writer::tag('p', 'Belum ada jurnal ekstrakurikuler bulan ini.');
}

echo $OUTPUT->footer();
