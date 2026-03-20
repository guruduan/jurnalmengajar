<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

function format_tanggal_indonesia($timestamp) {
    $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];

    $d = getdate($timestamp);
    $hari_indo = $hari[$d['wday']];
    $tanggal = $d['mday'];
    $bulan_indo = $bulan[$d['mon']];
    $tahun = $d['year'];

    return "$hari_indo, $tanggal $bulan_indo $tahun";
}


$userid = required_param('userid', PARAM_INT);
$mingguke = required_param('mingguke', PARAM_INT);

if ($mingguke < 1 || $mingguke > 20) {
    print_error('Parameter minggu ke tidak valid.');
}

// Tanggal awal minggu pertama
$tanggalstring = get_config('local_jurnalmengajar', 'tanggalawalminggu') ?: '2025-06-23';
$tanggal_awal = new DateTime($tanggalstring);
$tanggal_awal->modify('+' . (($mingguke - 1) * 7) . ' days');
$tanggal_akhir = clone $tanggal_awal;
$tanggal_akhir->modify('+6 days');

$timestart = $tanggal_awal->getTimestamp();
$timeend = $tanggal_akhir->getTimestamp() + (60 * 60 * 24);

// Ambil entri jurnal guru untuk minggu ini
global $DB;
$entries = $DB->get_records_select('local_jurnalmengajar',
    'userid = ? AND timecreated BETWEEN ? AND ?',
    [$userid, $timestart, $timeend],
    'timecreated ASC'
);

// Ambil data user
$user = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname');
if (!$user) {
    print_error('Guru tidak ditemukan.');
}

// Header
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/rekap_perguru.php', ['userid' => $userid, 'mingguke' => $mingguke]));
$PAGE->set_title("Riwayat Mengajar {$user->lastname}");
$PAGE->set_heading("Rekap Mengajar {$user->lastname} - Minggu ke-$mingguke");

echo $OUTPUT->header();
//echo html_writer::tag('h3', "Rekap Mengajar {$user->lastname}, Tanggal " . $tanggal_awal->format('d M Y') . " s.d. " . $tanggal_akhir->format('d M Y'));
echo html_writer::tag('h3', "Rekap Mengajar {$user->lastname}, Tanggal " . format_tanggal_indonesia($tanggal_awal->getTimestamp()) . " s.d. " . format_tanggal_indonesia($tanggal_akhir->getTimestamp()));


// Tabel
echo html_writer::start_tag('table', ['class' => 'generaltable']);
echo html_writer::start_tag('thead');
echo html_writer::tag('tr',
    html_writer::tag('th', 'No') .
    html_writer::tag('th', 'Hari, Tanggal') .
    html_writer::tag('th', 'Kelas') .
    html_writer::tag('th', 'Jamke') .
    html_writer::tag('th', 'Mata Pelajaran') .
    html_writer::tag('th', 'Materi') .
    html_writer::tag('th', 'Absen') .
    html_writer::tag('th', 'Keterangan')
);
echo html_writer::end_tag('thead');
echo html_writer::start_tag('tbody');

$no = 1;
foreach ($entries as $entry) {
//    $tanggal = date('l, d-m-Y', $entry->timecreated);
    $tanggal = format_tanggal_indonesia($entry->timecreated);

    $jamke = $entry->jamke ?: '-';
//    $kelas = $entry->kelas ?: '-';
if (is_numeric($entry->kelas)) {
    $cohort = $DB->get_record('cohort', ['id' => (int)$entry->kelas], 'name');
    $kelas = $cohort ? $cohort->name : '(tidak ditemukan)';
} else {
    $kelas = $entry->kelas ?: '-';
}

$mapel = $entry->matapelajaran ?: '-';
    $materi = $entry->materi ?: '-';
//    $absen = $entry->absen ?: '-';
$absen = '-';
$absendata = json_decode($entry->absen, true);
if (is_array($absendata)) {
    $absen = '';
    foreach ($absendata as $nama => $alasan) {
        $absen .= "$nama ($alasan), ";
    }
    $absen = rtrim($absen, ', ');
} elseif (!empty($entry->absen)) {
    $absen = $entry->absen;
}

    $keterangan = $entry->keterangan ?: '-';

    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', $no++);
    echo html_writer::tag('td', $tanggal);
    echo html_writer::tag('td', $kelas);
    echo html_writer::tag('td', $jamke);
    echo html_writer::tag('td', $mapel);
    echo html_writer::tag('td', $materi);
    echo html_writer::tag('td', $absen);
    echo html_writer::tag('td', $keterangan);
    echo html_writer::end_tag('tr');
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/rekap_perminggu.php', ['mingguke' => $mingguke]),
    '← Kembali ke Rekap Mingguan'
);

echo $OUTPUT->footer();
