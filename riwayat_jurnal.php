<?php
require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $USER, $PAGE, $OUTPUT;

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/riwayat_jurnal.php'));
$PAGE->set_title('Riwayat Jurnal');
$PAGE->set_heading('Riwayat Jurnal Mengajar');

require_once(__DIR__ . '/lib.php');

echo $OUTPUT->header();
echo $OUTPUT->heading('Riwayat Jurnal Saya');
$bulan = optional_param('bulan', date('n'), PARAM_INT);
$tahun = optional_param('tahun', date('Y'), PARAM_INT);

echo html_writer::start_tag('form', ['method' => 'get', 'style' => 'margin-bottom:15px']);

$bulanopsi = [
    1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',
    5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',
    9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
];

echo 'Bulan: ';
echo html_writer::select($bulanopsi, 'bulan', $bulan, false);

echo ' Tahun: ';
$tahunopsi = [];
for ($t = date('Y'); $t >= date('Y')-5; $t--) {
    $tahunopsi[$t] = $t;
}
echo html_writer::select($tahunopsi, 'tahun', $tahun, false);

echo html_writer::tag('button', 'Tampilkan', [
    'type'=>'submit',
    'class'=>'btn btn-primary',
    'style'=>'margin-left:10px'
]);

echo html_writer::end_tag('form');
// Tombol kembali dan export
echo html_writer::start_tag('div', ['style' => 'margin-bottom:15px']);

echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/index.php'),
    '⬅ Kembali ke Input',
    ['class' => 'btn btn-secondary', 'style' => 'margin-right:10px']
);

// Tombol export
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => new moodle_url('/local/jurnalmengajar/export_form.php'),
    'style' => 'display:inline'
]);

echo html_writer::tag('button', '🌏 Ekspor Jurnal per Bulan', [
    'type' => 'submit',
    'class' => 'btn btn-success'
]);

echo html_writer::end_tag('form');

echo html_writer::end_tag('div');

// Ambil data bulan berjalan
$awalbulan = strtotime("$tahun-$bulan-01 00:00:00");
$akhirbulan = strtotime("+1 month", $awalbulan);

$sql = "SELECT *
          FROM {local_jurnalmengajar}
         WHERE userid = :userid
           AND timecreated >= :awal
           AND timecreated < :akhir
      ORDER BY id DESC";

$params = [
    'userid' => $USER->id,
    'awal' => $awalbulan,
    'akhir' => $akhirbulan
];

$entries = $DB->get_records_sql($sql, $params);

echo html_writer::tag('h4',
    'Riwayat Bulan ' . $bulanopsi[$bulan] . ' ' . $tahun
);
if ($entries) {

    echo html_writer::start_tag('table', ['class' => 'generaltable']);
    echo html_writer::start_tag('thead');
    echo html_writer::tag('tr',
        html_writer::tag('th', 'No') .
        html_writer::tag('th', 'Kelas') .
        html_writer::tag('th', 'Jam Ke') .
        html_writer::tag('th', 'Mapel') .
        html_writer::tag('th', 'Materi') .
        html_writer::tag('th', 'Absen') .
        html_writer::tag('th', 'Waktu') .
        html_writer::tag('th', 'Aksi')
    );
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    $no = 1;

    foreach ($entries as $e) {

        $absendata = json_decode($e->absen, true);
        $absentext = '';

        if (is_array($absendata)) {
            foreach ($absendata as $nama => $alasan) {
                $absentext .= "$nama ($alasan), ";
            }
            $absentext = rtrim($absentext, ', ');
        }

        $namakelas = get_nama_kelas($e->kelas);

        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', $no++);
        echo html_writer::tag('td', $namakelas);
        echo html_writer::tag('td', $e->jamke);
        echo html_writer::tag('td', $e->matapelajaran);
        echo html_writer::tag('td', shorten_text($e->materi, 40));
        echo html_writer::tag('td', shorten_text($absentext, 30));
        echo html_writer::tag('td', tanggal_indo($e->timecreated));

        $editurl = new moodle_url('/local/jurnalmengajar/edit.php', ['id' => $e->id]);
        echo html_writer::tag('td', html_writer::link($editurl, 'Edit'));

        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');

} else {
    echo html_writer::tag('p', 'Belum ada jurnal.');
}

echo $OUTPUT->footer();
