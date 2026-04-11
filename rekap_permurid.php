<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/jurnalmengajar/lib.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);
global $DB, $PAGE, $OUTPUT;

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/rekap_permurid.php'));
$PAGE->requires->jquery();

$kelasid     = required_param('kelas', PARAM_INT);
$siswaid     = required_param('siswa', PARAM_INT);
$dariParam   = required_param('dari', PARAM_RAW);
$sampaiParam = required_param('sampai', PARAM_RAW);
$mode        = optional_param('mode', 'jam', PARAM_ALPHA); // 'jam' | 'hari'
$onlymine    = optional_param('onlymine', 0, PARAM_BOOL);
$matpel      = optional_param('matpel', '', PARAM_TEXT);

$dari = strtotime($dariParam) ?: time();
$sampai = (strtotime($sampaiParam) ?: time()) + 86399;

// Ambil nama kelas
$kelas = $DB->get_record('cohort', ['id' => $kelasid], 'id, name');
$namakelas = $kelas ? $kelas->name : '(kelas tidak ditemukan)';

$PAGE->set_title("Rekap Kehadiran Murid [$namakelas]");
$PAGE->set_heading("Rekap Kehadiran Murid [$namakelas]");

// ==== Util: normalisasi & prioritas status (konsisten dengan rekap_kehadiran.php) ====
function normalize_status($s) {
    $s = strtolower(trim($s));
    $map = [
        'ijin' => 'ijin', 'izin' => 'ijin',
        'sakit' => 'sakit', 'skt' => 'sakit',
        'alpha' => 'alpa', 'alpa' => 'alpa', 'absen' => 'alpa',
        'disp' => 'dispensasi', 'dispen' => 'dispensasi', 'dispensasi' => 'dispensasi',
        'hadir' => 'hadir'
    ];
    return $map[$s] ?? $s;
}
// Semakin besar nilainya -> semakin dominan jika bentrok dalam satu hari
$priority = [
    'hadir'       => 0,
    'dispensasi'  => 1,
    'sakit'       => 2,
    'ijin'        => 3,
    'alpa'        => 4,
];

// ==== Header ====
echo $OUTPUT->header();
echo $OUTPUT->heading("Rekap Kehadiran Murid [$namakelas]");

// Tombol kembali & cetak (ikut bawa mode + filter)
// Kembali ke rekap per kelas
$back_kelas = new moodle_url('/local/jurnalmengajar/rekap_kehadiran.php', [
    'kelas'    => $kelasid,
    'dari'     => $dariParam,
    'sampai'   => $sampaiParam,
    'mode'     => $mode,
    'onlymine' => $onlymine ? 1 : 0,
    'matpel'   => $matpel
]);

// Kembali ke rekap murid binaan (guru wali)
$back_wali = new moodle_url('/local/jurnalmengajar/rekap_kehadiran_muridwali.php', [
    'dari'   => $dariParam,
    'sampai'=> $sampaiParam,
    'mode'  => $mode
]);

$cetakurl = new moodle_url('/local/jurnalmengajar/cetak_permurid.php', [
    'kelas'    => $kelasid,
    'siswa'    => $siswaid,
    'dari'     => $dariParam,
    'sampai'   => $sampaiParam,
    'mode'     => $mode,
    'onlymine' => $onlymine ? 1 : 0,
    'matpel'   => $matpel
]);

echo html_writer::start_div('mb-3 d-flex gap-2');
echo html_writer::link(
    $back_kelas,
    '← Kembali ke Rekap Kelas',
    ['class' => 'btn btn-secondary']
);
echo html_writer::link(
    $back_wali,
    '👥 Kembali ke Murid Binaan',
    ['class' => 'btn btn-outline-secondary']
);
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

// Info siswa & rentang + mode + filter
echo html_writer::tag('h3', "Siswa: {$namasiswa}");
$rentangTanggal = tanggal_indo($dari, 'judul') . ' sampai ' . tanggal_indo($sampai, 'judul');
echo html_writer::tag('p', "Rentang Tanggal: $rentangTanggal", ['class' => 'mb-1 fw-bold']);
$badges = ["Mode: " . ($mode === 'hari' ? 'Per Hari' : 'Per Jam (jamke)')];
if ($onlymine) { $badges[] = 'Hanya jurnal saya'; }
if ($matpel !== '') { $badges[] = 'Matpel: ' . s($matpel); }
echo html_writer::tag('p', implode(' | ', $badges), ['class' => 'mb-3']);

// Ambil jurnal kelas dalam rentang (hormati filter)
$params = ['kelas' => $kelasid, 'dari' => $dari, 'sampai' => $sampai];
$wheres = ['kelas = :kelas', 'timecreated BETWEEN :dari AND :sampai'];

if ($onlymine) {
    global $USER;
    $wheres[] = 'userid = :uid';
    $params['uid'] = $USER->id;
}
if ($matpel !== '') {
    // exact match; jika mau LIKE, ganti dua baris di bawah
    $wheres[] = 'matapelajaran = :matpel';
    $params['matpel'] = $matpel;
    // LIKE alternatif:
    // $wheres[] = $DB->sql_like('matapelajaran', ':matpel', false, false);
    // $params['matpel'] = "%{$matpel}%";
}

$select  = implode(' AND ', $wheres);
$jurnals = $DB->get_records_select('local_jurnalmengajar', $select, $params, 'timecreated ASC');

// ==============================
// TABEL (beda sesuai mode)
// ==============================
if ($mode === 'hari') {
    // ---------- MODE PER HARI ----------
    // Kumpulkan status per tanggal untuk siswa ini, terapkan prioritas bila bentrok.
    $per_tanggal = [];   // 'Y-m-d' => ['status' => ..., 'rincian' => [..]]
    foreach ($jurnals as $j) {
        $tglKey = date('Y-m-d', $j->timecreated);
        $absen = json_decode($j->absen, true);
if (!is_array($absen)) $absen = [];
        $statusJurnal = null;

        foreach ($absen as $nama => $als) {
            if (strcasecmp(trim($nama), trim($siswa->lastname)) == 0) {
                $statusJurnal = normalize_status($als);
                break;
            }
        }

        // Inisialisasi container hari
        if (!isset($per_tanggal[$tglKey])) {
            $per_tanggal[$tglKey] = [
                'status_count' => [],   // hitung jam per status
                'rincian' => []
            ];
        }

        // Rincian
        $guru = $DB->get_record('user', ['id' => $j->userid], 'firstname, lastname');
        $per_tanggal[$tglKey]['rincian'][] = [
            'jamke'  => $j->jamke ?? '-',
            'mapel'  => $j->matapelajaran ?? '-',
            'guru'   => $guru ? $guru->lastname : '(tidak diketahui)'
        ];

        // Status dominan per hari
        if ($statusJurnal && isset($priority[$statusJurnal])) {
    $jamlist = array_filter(array_map('trim', explode(',', $j->jamke ?? '')));
    $jumlahjam = count($jamlist) ?: 1;

    if (!isset($per_tanggal[$tglKey]['status_count'][$statusJurnal])) {
        $per_tanggal[$tglKey]['status_count'][$statusJurnal] = 0;
    }
    $per_tanggal[$tglKey]['status_count'][$statusJurnal] += $jumlahjam;
}

    }

foreach ($per_tanggal as $tgl => &$info) {
    if (empty($info['status_count'])) {
        $info['status'] = 'hadir';
        continue;
    }

    $statusDominan = 'hadir';
    $maxJam = -1;

    foreach ($info['status_count'] as $status => $jumlah) {
        if (
            $jumlah > $maxJam ||
            ($jumlah === $maxJam && $priority[$status] > $priority[$statusDominan])
        ) {
            $statusDominan = $status;
            $maxJam = $jumlah;
        }
    }

    $info['status'] = $statusDominan;
}
unset($info);


    // Render tabel: satu baris per tanggal (urut naik)
    ksort($per_tanggal);

    echo html_writer::start_tag('table', ['class' => 'generaltable']);
    echo html_writer::tag('tr',
        html_writer::tag('th', 'No') .
        html_writer::tag('th', 'Tanggal') .
        html_writer::tag('th', 'Absensi (dominan)') .
        html_writer::tag('th', 'Rincian perhari')
    );

    $no = 1;
    $hari_tidak_hadir = 0;
    foreach ($per_tanggal as $tglKey => $info) {
        $tanggalDisplay = tanggal_indo(strtotime($tglKey), 'judul');
        $st = $info['status'];

        if ($st !== 'hadir') { $hari_tidak_hadir++; }

        $chunks = [];
        foreach ($info['rincian'] as $r) {
            $chunks[] = '[' . ($r['jamke'] ?: '-') . '] ' . ($r['mapel'] ?: '-') . ' (' . $r['guru'] . ')';
        }
        $rincian = $chunks ? implode('; ', $chunks) : '-';

        echo html_writer::tag('tr',
            html_writer::tag('td', $no++) .
            html_writer::tag('td', $tanggalDisplay) .
            html_writer::tag('td', ucfirst($st)) .
            html_writer::tag('td', $rincian)
        );
    }
    echo html_writer::end_tag('table');

    echo html_writer::tag('p', '<strong>Jumlah Hari Murid tidak hadir: ' . $hari_tidak_hadir . ' hari</strong>', ['class' => 'mt-3']);

} else {
    // ---------- MODE PER JAM (lama) ----------
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

    foreach ($jurnals as $jurnal) {
        $tanggal = tanggal_indo($jurnal->timecreated, 'judul');
        $absen = json_decode($jurnal->absen, true);
if (!is_array($absen)) $absen = [];
        $jamke = $jurnal->jamke ?? '-';
        $matpelj = $jurnal->matapelajaran ?? '-';

        $alasan = null;
        foreach ($absen as $nama => $als) {
            if (strcasecmp(trim($nama), trim($siswa->lastname)) == 0) {
                $alasan = normalize_status($als);
                break;
            }
        }

        // Tampilkan hanya jika siswa tercatat tidak 'hadir' pada jurnal tsb
        if ($alasan && $alasan !== 'hadir') {
            $jamlist = array_filter(array_map('trim', explode(',', $jamke)));
            $totaljam += count($jamlist);

            $guru = $DB->get_record('user', ['id' => $jurnal->userid], 'firstname, lastname');
            $namaguru = $guru ? $guru->lastname : '(tidak diketahui)';

            echo html_writer::tag('tr',
                html_writer::tag('td', $no++) .
                html_writer::tag('td', $tanggal) .
                html_writer::tag('td', $jamke) .
                html_writer::tag('td', $matpelj) .
                html_writer::tag('td', $namaguru) .
                html_writer::tag('td', ucfirst($alasan))
            );
        }
    }

    echo html_writer::end_tag('table');

    echo html_writer::tag('p', '<strong>Jumlah Jam Murid tidak hadir: ' . $totaljam . ' jam</strong>', ['class' => 'mt-3']);
}

echo $OUTPUT->footer();
