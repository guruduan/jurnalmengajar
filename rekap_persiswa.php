<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/rekap_persiswa.php'));
$PAGE->requires->jquery();

$kelasid = required_param('kelas', PARAM_INT);
$siswaid = required_param('siswa', PARAM_INT);

// Ambil parameter rentang tanggal
$dariParam = required_param('dari', PARAM_RAW);
$sampaiParam = required_param('sampai', PARAM_RAW);
$dari = strtotime($dariParam);
$sampai = strtotime($sampaiParam) + 86399;

// Formatter tanggal Indonesia
$fmt = new IntlDateFormatter('id_ID', IntlDateFormatter::LONG, IntlDateFormatter::NONE, 'Asia/Makassar');

// Ambil nama kelas
$kelas = $DB->get_record('cohort', ['id' => $kelasid], 'id, name');
$namakelas = $kelas ? format_string($kelas->name) : '(kelas tidak ditemukan)';

$PAGE->set_title("Rekap Kehadiran Murid [$namakelas]");
$PAGE->set_heading("Rekap Kehadiran Murid [$namakelas]");

echo $OUTPUT->header();
echo $OUTPUT->heading("Rekap Kehadiran Murid [$namakelas]");

// Tombol kembali & cetak
$backurl = new moodle_url('/local/jurnalmengajar/rekap_kehadiran.php', [
    'kelas' => $kelasid,
    'dari' => $dariParam,
    'sampai' => $sampaiParam
]);
$cetakurl = new moodle_url('/local/jurnalmengajar/cetak_persiswa.php', [
    'kelas' => $kelasid,
    'siswa' => $siswaid,
    'dari' => $dariParam,
    'sampai' => $sampaiParam
]);

echo html_writer::start_div('mb-3 d-flex gap-2');
echo html_writer::link($backurl, '← Kembali ke Rekap Kelas', ['class' => 'btn btn-secondary']);
echo html_writer::link($cetakurl, '🖨️ Cetak ke PDF', ['class' => 'btn btn-danger', 'target' => '_blank']);
echo html_writer::end_div();

// Ambil data siswa
$siswa = $DB->get_record('user', ['id' => $siswaid], 'id, firstname, lastname');
if (!$siswa) {
    echo 'Siswa tidak ditemukan.';
    echo $OUTPUT->footer();
    exit;
}
$namasiswa = ucwords(strtolower($siswa->lastname));

// === Tambahkan informasi siswa & rentang tanggal ===
echo html_writer::tag('h3', "Siswa: {$namasiswa}");
$rentangTanggal = $fmt->format($dari) . ' sampai ' . $fmt->format($sampai);
echo html_writer::tag('p', "Rentang Tanggal: $rentangTanggal", ['class' => 'mb-3 fw-bold']);

// Ambil jurnal
$jurnals = $DB->get_records_select('local_jurnalmengajar',
    'kelas = :kelas AND timecreated BETWEEN :dari AND :sampai',
    ['kelas' => $kelasid, 'dari' => $dari, 'sampai' => $sampai],
    'timecreated ASC'
);

// Tabel
echo html_writer::start_tag('table', ['class' => 'generaltable']);
echo html_writer::tag('tr',
    html_writer::tag('th', 'No') .
    html_writer::tag('th', 'Tanggal') .
    html_writer::tag('th', 'Jam ke') .
    html_writer::tag('th', 'Mata Pelajaran') .
    html_writer::tag('th', 'Pengajar') .
    html_writer::tag('th', 'Absen')
);

$no = 1;
$totaljam = 0;
$hariTidakHadir = [];

foreach ($jurnals as $jurnal) {
    $tanggal = $fmt->format($jurnal->timecreated);
    $absen = json_decode($jurnal->absen, true) ?? [];
    $jamke = $jurnal->jamke ?? '-';
    $matpel = $jurnal->matapelajaran ?? '-';

    $alasan = null;
    foreach ($absen as $nama => $als) {
        if (strcasecmp(trim($nama), trim($siswa->lastname)) == 0) {
            $alasan = ucfirst(strtolower(trim($als)));
            break;
        }
    }

    if ($alasan) {
        $jamlist = array_filter(array_map('trim', explode(',', $jamke)));
        $totaljam += count($jamlist);

        $tglKey = date('Y-m-d', $jurnal->timecreated);
        $hariTidakHadir[$tglKey] = true;

        $guru = $DB->get_record('user', ['id' => $jurnal->userid], 'firstname, lastname');
        $namaguru = $guru ? $guru->lastname : '(tidak diketahui)';

        echo html_writer::tag('tr',
            html_writer::tag('td', $no++) .
            html_writer::tag('td', $tanggal) .
            html_writer::tag('td', $jamke) .
            html_writer::tag('td', $matpel) .
            html_writer::tag('td', $namaguru) .
            html_writer::tag('td', $alasan)
        );
    }
}

echo html_writer::end_tag('table');

$jumlahHari = count($hariTidakHadir);
echo html_writer::tag('p', '<strong>Jumlah Jam Murid tidak hadir: ' . $totaljam . ' jam</strong>', ['class' => 'mt-3']);
echo html_writer::tag('p', '<strong>Jumlah Hari Murid tidak hadir: ' . $jumlahHari . ' hari</strong>', ['class' => 'mt-1']);

echo $OUTPUT->footer();
