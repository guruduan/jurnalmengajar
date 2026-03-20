<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/today.php'));
$PAGE->set_title('Jurnal Hari Ini');
$PAGE->set_heading('Jurnal Mengajar Hari Ini');

echo $OUTPUT->header();
echo $OUTPUT->heading('Jurnal Hari Ini (Urut Waktu)');

// 🔁 TAB SWITCH di today.php
echo html_writer::start_div('mb-3');
echo html_writer::link(new moodle_url('/local/jurnalmengajar/today.php'), '⏰ Urut Waktu', ['class' => 'btn btn-primary']);
echo ' ';
echo html_writer::link(new moodle_url('/local/jurnalmengajar/today_perguru.php'), '🧑‍🏫 Per Guru', ['class' => 'btn btn-outline-secondary']);
echo ' ';
echo html_writer::link(new moodle_url('/local/jurnalmengajar/bydate.php'), '📅 Ke Tanggal', ['class' => 'btn btn-outline-secondary']);
echo html_writer::end_div();


global $DB;
date_default_timezone_set('Asia/Makassar');
$start = strtotime('today midnight');
$end = strtotime('tomorrow midnight') - 1;

$sql = "SELECT j.*, u.lastname
        FROM {local_jurnalmengajar} j
        JOIN {user} u ON j.userid = u.id
        WHERE j.timecreated BETWEEN :start AND :end
        ORDER BY j.timecreated ASC";
$entries = $DB->get_records_sql($sql, ['start' => $start, 'end' => $end]);

function format_waktu_indonesia($timestamp) {
    $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $bulan = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
    return $hari[date('w',$timestamp)].', '.date('j',$timestamp).' '.$bulan[date('n',$timestamp)].' '.date('Y',$timestamp).', '.date('H:i',$timestamp).' WITA';
}

if ($entries) {
    echo html_writer::start_tag('table', ['class' => 'generaltable']);
    echo html_writer::start_tag('thead');
    echo html_writer::tag('tr', implode('', array_map(fn($t) => html_writer::tag('th', $t), [
        'No', 'Nama Guru', 'Kelas', 'Jam Ke', 'Mata Pelajaran', 'Materi', 'Absen', 'Waktu', 'Keterangan'
    ])));
    echo html_writer::end_tag('thead') . html_writer::start_tag('tbody');
    $no = 1;
    foreach ($entries as $e) {
        $abs = json_decode($e->absen, true);
        $abtxt = is_array($abs) ? implode(', ', array_map(fn($n, $a) => "$n ($a)", array_keys($abs), $abs)) : $e->absen;
        $kelas = $DB->get_field('cohort', 'name', ['id' => $e->kelas]) ?? '???';
        echo html_writer::tag('tr', implode('', [
            html_writer::tag('td', $no++),
            html_writer::tag('td', $e->lastname),
            html_writer::tag('td', $kelas),
            html_writer::tag('td', $e->jamke),
            html_writer::tag('td', $e->matapelajaran),
            html_writer::tag('td', shorten_text($e->materi, 30), ['title' => $e->materi]),
            html_writer::tag('td', shorten_text($abtxt, 25), ['title' => $abtxt]),
            html_writer::tag('td', format_waktu_indonesia($e->timecreated)),
            html_writer::tag('td', shorten_text($e->keterangan, 25), ['title' => $e->keterangan])
        ]));
    }
    echo html_writer::end_tag('tbody') . html_writer::end_tag('table');
} else {
    echo html_writer::div('Belum ada entri jurnal hari ini.', 'alert alert-warning');
}

echo $OUTPUT->footer();
