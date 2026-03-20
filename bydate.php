<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/bydate.php'));
$PAGE->set_title('Jurnal Mengajar per Tanggal');
$PAGE->set_heading('Jurnal Mengajar per Tanggal');

echo $OUTPUT->header();
echo $OUTPUT->heading('Jurnal Mengajar per Tanggal');

// 🔁 TAB SWITCH di bydate
echo html_writer::start_div('mb-3');
echo html_writer::link(new moodle_url('/local/jurnalmengajar/today.php'), '⏰ Urut Waktu', ['class' => 'btn btn-outline-secondary']);
echo ' ';
echo html_writer::link(new moodle_url('/local/jurnalmengajar/today_perguru.php'), '🧑‍🏫 Per Guru', ['class' => 'btn btn-outline-secondary']);
echo ' ';
echo html_writer::link(new moodle_url('/local/jurnalmengajar/bydate.php'), '📅 Ke Tanggal', ['class' => 'btn btn-primary']);
echo html_writer::end_div();

global $DB;
date_default_timezone_set('Asia/Makassar');

// Tangani input tanggal
$tanggal = optional_param('tanggal', date('Y-m-d'), PARAM_RAW);
$timestamp = strtotime($tanggal);
$start = strtotime('midnight', $timestamp);
$end = strtotime('tomorrow midnight', $timestamp) - 1;

// Fungsi format lokal Indonesia
function format_waktu_indonesia($timestamp) {
    $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $bulan = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
    return $hari[date('w',$timestamp)].', '.date('j',$timestamp).' '.$bulan[date('n',$timestamp)].' '.date('Y',$timestamp).', '.date('H:i',$timestamp).' WITA';
}

// Tampilkan form tanggal
echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'mb-3']);

// Tampilkan label dengan format Indonesia
if (!empty($tanggal)) {
    $bulan = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
        '04' => 'April', '05' => 'Mei', '06' => 'Juni',
        '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
        '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
    ];
    $parts = explode('-', $tanggal); // Format: YYYY-MM-DD
    $tanggalindo = $parts[2] . ' ' . $bulan[$parts[1]] . ' ' . $parts[0];
    $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][date('w', $timestamp)];
    echo html_writer::div("📅 Tanggal dipilih: <strong>$tanggalindo</strong> (Hari: <strong>$hari</strong>)", 'mb-2');

}

echo html_writer::start_div('form-group');
echo html_writer::label('Pilih Tanggal', 'tanggal');
echo html_writer::empty_tag('input', [
    'type' => 'date',
    'name' => 'tanggal',
    'value' => $tanggal,
    'class' => 'form-control',
    'id' => 'tanggal'
]);
echo html_writer::end_div();
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => 'Tampilkan', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

echo html_writer::div('', 'mt-2', ['id' => 'hari-terpilih']);


// Ambil entri
$sql = "SELECT j.*, u.lastname
        FROM {local_jurnalmengajar} j
        JOIN {user} u ON j.userid = u.id
        WHERE j.timecreated BETWEEN :start AND :end
        ORDER BY j.timecreated ASC";
$entries = $DB->get_records_sql($sql, ['start' => $start, 'end' => $end]);

// Tampilkan entri
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
    echo html_writer::div('Tidak ada entri jurnal pada tanggal tersebut.', 'alert alert-warning');
}

// Tampilkan nama hari dengan JS
echo html_writer::script("
    document.addEventListener('DOMContentLoaded', function () {
        const inputTanggal = document.getElementById('tanggal');
        const divHari = document.getElementById('hari-terpilih');

        const hariIndo = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

        function tampilkanHari(dateStr) {
            const date = new Date(dateStr);
            if (!isNaN(date.getTime())) {
                const hari = hariIndo[date.getDay()];
                divHari.innerHTML = '<strong>Hari: ' + hari + '</strong>';
            } else {
                divHari.innerHTML = '';
            }
        }

        inputTanggal.addEventListener('change', function () {
            tampilkanHari(this.value);
        });

        // Tampilkan saat awal jika sudah ada value
        if (inputTanggal.value) {
            tampilkanHari(inputTanggal.value);
        }
    });
");


echo $OUTPUT->footer();
