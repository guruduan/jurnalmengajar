<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/today_perguru.php'));
$PAGE->set_title('Jurnal Hari Ini Per Guru');
$PAGE->set_heading('Jurnal Mengajar Hari Ini Per Guru');

echo $OUTPUT->header();
echo $OUTPUT->heading('Jurnal Hari Ini (Per Guru)');

// 🔁 TAB SWITCH di today_perguru
echo html_writer::start_div('mb-3');
echo html_writer::link(new moodle_url('/local/jurnalmengajar/today.php'), '⏰ Urut Waktu', ['class' => 'btn btn-outline-secondary']);
echo ' ';
echo html_writer::link(new moodle_url('/local/jurnalmengajar/today_perguru.php'), '🧑‍🏫 Per Guru', ['class' => 'btn btn-primary']);
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
        ORDER BY u.lastname ASC, j.timecreated ASC";
$entries = $DB->get_records_sql($sql, ['start' => $start, 'end' => $end]);

function format_waktu_indonesia($t) {
    $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $bulan = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
    return $hari[date('w',$t)].', '.date('j',$t).' '.$bulan[date('n',$t)].' '.date('Y',$t).', '.date('H:i',$t).' WITA';
}

// 🔁 Group by Guru
$grouped = [];
foreach ($entries as $e) {
    $grouped[$e->lastname][] = $e;
}

if ($grouped) {
    foreach ($grouped as $guru => $list) {
        echo html_writer::tag('h4', '👨‍🏫 ' . $guru);
        echo html_writer::start_tag('ul');
        foreach ($list as $e) {
            $kelas = $DB->get_field('cohort', 'name', ['id' => $e->kelas]) ?? '???';
            $abs = json_decode($e->absen, true);
            $abtxt = is_array($abs) ? implode(', ', array_map(fn($n, $a) => "$n ($a)", array_keys($abs), $abs)) : $e->absen;
            echo html_writer::tag('li', "Kelas $kelas jam ke {$e->jamke} – <em>{$e->matapelajaran}</em> – " .
                shorten_text($e->materi, 40) . ' – ' . format_waktu_indonesia($e->timecreated));
        }
        echo html_writer::end_tag('ul');
    }
} else {
    echo html_writer::div('Belum ada entri jurnal hari ini.', 'alert alert-warning');
}

echo $OUTPUT->footer();
