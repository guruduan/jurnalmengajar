<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/rekap_guru.php'));
$PAGE->set_title('Rekap Keaktifan Guru');
$PAGE->set_heading('Rekap Keaktifan Guru Mengajar');

global $DB, $CFG;

// Ambil rentang tanggal dari GET
$tanggal_awal = optional_param('tanggal_awal', date('Y-m-01'), PARAM_RAW);
$tanggal_akhir = optional_param('tanggal_akhir', date('Y-m-d'), PARAM_RAW);

$starttime = strtotime($tanggal_awal);
$endtime = strtotime($tanggal_akhir . ' +1 day');

$jumlah_hari_kalender = (int) ceil(($endtime - $starttime) / 86400);
$jumlah_minggu = (int) ceil($jumlah_hari_kalender / 6);

// Ambil semua user dengan role "gurujurnal"
$systemcontext = context_system::instance();
$roleid = $DB->get_field('role', 'id', ['shortname' => 'gurujurnal']);
$guru_users = get_role_users($roleid, $systemcontext);

// BACA dari file JSON hasil edit_jam_guru.php
$jamgurupath = $CFG->dataroot . '/jam_guru.json';
$jam_perminggu_guru = [];
if (file_exists($jamgurupath)) {
    $jam_perminggu_guru = json_decode(file_get_contents($jamgurupath), true);
}

// Siapkan data guru
$guru_data = [];
foreach ($guru_users as $user) {
    $namaguru = trim($user->lastname ?: fullname($user));
    $jamperminggu = $jam_perminggu_guru[$namaguru] ?? 24;
    $guru_data[$user->id] = [
        'nama' => $namaguru,
        'jamperminggu' => $jamperminggu
    ];
}

// Tampilkan halaman
echo $OUTPUT->header();
echo '<form method="get" class="mb-3">';
echo 'Dari tanggal: <input type="date" name="tanggal_awal" value="' . $tanggal_awal . '"> ';
echo 's.d. <input type="date" name="tanggal_akhir" value="' . $tanggal_akhir . '"> ';
echo '<input type="submit" value="Tampilkan">';
echo '</form>';

// Tabel Rekap
echo '<table class="generaltable">';
echo '<thead><tr>
        <th>No</th>
        <th>Nama Guru</th>
        <th>Jam Mengajar / Minggu</th>
        <th>Jam Dilaksanakan</th>
        <th>Persentase</th>
        <th>Keterangan</th>
      </tr></thead><tbody>';

$no = 1;
foreach ($guru_data as $uid => $data) {
    $namaguru = $data['nama'];
    $jamperminggu = $data['jamperminggu'];

    // Ambil semua entri jurnal dalam rentang waktu
    $entries = $DB->get_records_select('local_jurnalmengajar',
        'userid = :uid AND timecreated >= :start AND timecreated < :end',
        ['uid' => $uid, 'start' => $starttime, 'end' => $endtime]
    );

    $totaljam = 0;
    foreach ($entries as $e) {
        // Hitung jumlah jam dari rentang jamke
        if (preg_match('/(\d+)(?:-(\d+))?/', $e->jamke, $m)) {
            $dari = (int) $m[1];
            $sampai = isset($m[2]) ? (int) $m[2] : $dari;
            $totaljam += max(0, $sampai - $dari + 1);
        }
    }

    $jam_target = $jamperminggu * $jumlah_minggu;
    $persen = ($jam_target > 0) ? round(($totaljam / $jam_target) * 100) : 0;
    $warna = ($persen >= 80) ? 'green' : (($persen < 50) ? 'red' : 'black');

    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', $no++);
    echo html_writer::tag('td', $namaguru);
    echo html_writer::tag('td', $jamperminggu);
    echo html_writer::tag('td', $totaljam);
    echo html_writer::tag('td', "<span style='color:$warna;'>$persen%</span>");
    echo html_writer::tag('td', '');
    echo html_writer::end_tag('tr');
}

echo '</tbody></table>';
echo $OUTPUT->footer();
