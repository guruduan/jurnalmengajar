<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__.'/lib.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

global $DB;

// =====================
// PARAMETER
// =====================
$userid = required_param('userid', PARAM_INT);

// =====================
// DATA USER & SETTING
// =====================
$user = $DB->get_record('user', ['id' => $userid], 'lastname');
if (!$user) {
    print_error('Guru tidak ditemukan.');
}

$nama = ucwords($user->lastname);
$tahunajaran = get_config('local_jurnalmengajar', 'tahun_ajaran') ?: '-';

// =====================
// TANGGAL AWAL MINGGU
// =====================
$tanggalstring = get_config('local_jurnalmengajar', 'tanggalawalminggu');

if (!$tanggalstring) {
    print_error('Tanggal awal minggu belum disetting.');
}
$tanggal_awal = new DateTime($tanggalstring);

// =====================
// DETEKSI SEMESTER
// =====================
$bulan_awal = (int)$tanggal_awal->format('n');

if ($bulan_awal >= 7) {
    $semester = 'Ganjil';
} else {
    $semester = 'Genap';
}

// =====================
// HEADER
// =====================
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/rekap_guru_semester.php', ['userid' => $userid]));
$PAGE->set_title("Rekap Jurnal Mengajar Guru Semester $semester {$user->lastname}");
$PAGE->set_heading("Rekap Jurnal Mengajar Guru");

echo $OUTPUT->header();

echo html_writer::tag('h3', "Tahun Ajaran: $tahunajaran");
echo html_writer::tag('h3', "Semester: $semester");
echo html_writer::tag('h4', "Nama: $nama");

// =====================
// PROSES REKAP
// =====================
$rekap_mingguan = [];
$sekarang = time();

$selisih_hari = floor(($sekarang - $tanggal_awal->getTimestamp()) / (60 * 60 * 24));
$minggu_berjalan = floor($selisih_hari / 7) + 1;

// batas aman
if ($minggu_berjalan < 1) $minggu_berjalan = 1;
if ($minggu_berjalan > 20) $minggu_berjalan = 20;

for ($i = 1; $i <= $minggu_berjalan; $i++) {

    $awal = clone $tanggal_awal;
    $awal->modify('+' . (($i - 1) * 7) . ' days');

    $akhir = clone $awal;
    $akhir->modify('+6 days');

    $start = $awal->getTimestamp();
    $end = $akhir->getTimestamp() + 86399;

    // Ambil jurnal
    $entries = $DB->get_records_select(
        'local_jurnalmengajar',
        'userid = ? AND timecreated BETWEEN ? AND ?',
        [$userid, $start, $end]
    );

    // Hitung jam
    $jumlah = 0;
    foreach ($entries as $e) {
        $jumlah += !empty($e->jamke)
            ? count(array_filter(explode(',', $e->jamke)))
            : 0;
    }

    // Beban jam
    $beban = jurnalmengajar_get_beban_jam_guru_by_date($start);
    $beban_minggu = $beban[$userid] ?? 0;

    $persen = ($beban_minggu > 0)
        ? round(($jumlah / $beban_minggu) * 100)
        : 0;

    // Rentang tanggal
$awal_ts = $start;
$akhir_ts = $end;
$awal_str = tanggal_indo($awal_ts, 'tglbulan');
$akhir_str = tanggal_indo($akhir_ts, 'tanggal');

$rentang = $awal_str . ' - ' . $akhir_str;

    $rekap_mingguan[] = [
        'minggu' => $i,
        'jumlah' => $jumlah,
        'beban' => $beban_minggu,
        'persen' => $persen,
        'rentang' => $rentang
    ];
}

// Cutoff tampilkan selalu
$cutoff = jurnalmengajar_get_cutoff_xii();

if ($cutoff) {
    echo html_writer::div(
        'Kelas XII tidak ada KBM sejak: ' . tanggal_indo($cutoff, 'tanggal') . 
        ' (beban jam mengajar sudah disesuaikan)',
        'alert alert-info'
    );
}
// =====================
// TABEL
// =====================
echo html_writer::start_tag('table', ['class' => 'generaltable']);

echo html_writer::tag('tr',
    html_writer::tag('th', 'No') .
    html_writer::tag('th', 'Minggu ke') .
    html_writer::tag('th', 'Rentang Tanggal') .
    html_writer::tag('th', 'Jumlah Mengajar') .
    html_writer::tag('th', 'Beban Jam') .
    html_writer::tag('th', '% Mingguan') .
    html_writer::tag('th', 'Aksi')
);

$no = 1;

foreach ($rekap_mingguan as $r) {

    // WARNA
    $style = '';
    if ($r['persen'] >= 80) {
        $style = 'color:green;font-weight:bold';
    } elseif ($r['jumlah'] == 0 && $r['beban'] > 0) {
        $style = 'color:red;font-weight:bold';
    } elseif ($r['persen'] < 50) {
        $style = 'color:orange;font-weight:bold';
    }

    $url = new moodle_url('/local/jurnalmengajar/rekap_perguru.php', [
        'userid' => $userid,
        'mingguke' => $r['minggu']
    ]);

    echo html_writer::tag('tr',
        html_writer::tag('td', $no++) .
        html_writer::tag('td', $r['minggu']) .
        html_writer::tag('td', $r['rentang']) .
        html_writer::tag('td', $r['jumlah']) .
        html_writer::tag('td', $r['beban']) .
        html_writer::tag('td', $r['persen'] . '%', ['style' => $style]) .
        html_writer::tag('td', html_writer::link($url, '🔍 Detail'))
    );
}

echo html_writer::end_tag('table');

// =====================
// RINGKASAN
// =====================
$totaljam = array_sum(array_column($rekap_mingguan, 'jumlah'));
$jumlah_minggu = count($rekap_mingguan);
$avgpersen = $jumlah_minggu > 0 
    ? round(array_sum(array_column($rekap_mingguan, 'persen')) / $jumlah_minggu)
    : 0;

#echo html_writer::div("<b>Total Jam Mengajar: $totaljam</b>", 'mt-3');
#echo html_writer::div("<b>Rata-rata Kinerja: $avgpersen%</b>");

// =====================
// KEMBALI
// =====================
echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/jurnalmengajar/rekap_perminggu.php'),
        '⬅ Kembali ke Rekap Mingguan'
    ),
    'mt-3'
);

echo $OUTPUT->footer();
