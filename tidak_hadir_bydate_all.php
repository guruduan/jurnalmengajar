<?php
// File: local/jurnalmengajar/tidak_hadir_bydate_all.php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/tidak_hadir_bydate_all.php'));
$PAGE->set_title('Murid Tidak Hadir — Pilih Tanggal');
$PAGE->set_heading('Murid Tidak Hadir — Pilih Tanggal');

echo $OUTPUT->header();

// ====== Zona waktu WITA & ambil tanggal ======
$tz = new DateTimeZone('Asia/Makassar');
$datestr = optional_param('tanggal', '', PARAM_RAW_TRIMMED);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datestr)) {
    $datestr = (new DateTime('now', $tz))->format('Y-m-d');
}
$dtstart = DateTime::createFromFormat('Y-m-d H:i:s', $datestr.' 00:00:00', $tz);
$dtend   = DateTime::createFromFormat('Y-m-d H:i:s', $datestr.' 23:59:59', $tz);
$start   = $dtstart->getTimestamp();
$end     = $dtend->getTimestamp();

// ====== Helper tanggal (indo, bulan huruf kecil) ======
function indo_tanggal_lower($ts, DateTimeZone $tz) {
    $dt = (new DateTime('@'.$ts))->setTimezone($tz);
    $hari  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $bulan = [1=>'januari','februari','maret','april','mei','juni','juli','agustus','september','oktober','november','desember'];
    return $hari[(int)$dt->format('w')].' '.$dt->format('j').' '.$bulan[(int)$dt->format('n')].' '.$dt->format('Y');
}

// ====== Normalisasi & cek tidak hadir ======
function normalize_status(?string $s): string {
    $s = trim(mb_strtolower((string)$s));
    $map = ['ijin'=>'izin','alpha'=>'alpa','tk'=>'alpa','-'=>''];
    return $map[$s] ?? $s;
}
function is_tidak_hadir(string $status): bool {
    $s = normalize_status($status);
    if ($s === '' || $s === 'hadir') return false;
    return in_array($s, ['sakit','izin','alpa','dispensasi','dinas','bolos','tanpa keterangan'], true);
}

// ====== Header + switch ======
echo html_writer::tag('h2', 'Murid tidak hadir');

echo html_writer::start_div('mb-3');
echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/tidak_hadir_today_all.php'),
    'Hari ini',
    ['class'=>'btn btn-outline-secondary btn-sm']
);
echo ' ';
echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/tidak_hadir_bydate_all.php', ['tanggal'=>$datestr]),
    'Ke tanggal',
    ['class'=>'btn btn-primary btn-sm']
);
echo html_writer::end_div();

// ====== Form pilih tanggal ======
echo html_writer::start_tag('form', ['method'=>'get', 'action'=>$PAGE->url]);
echo html_writer::tag('label', 'Pilih tanggal: ', ['for'=>'tanggal']);
echo html_writer::empty_tag('input', [
    'type'=>'date', 'id'=>'tanggal', 'name'=>'tanggal', 'value'=>$datestr, 'required'=>'required'
]);
echo ' ';
echo html_writer::empty_tag('input', ['type'=>'submit', 'class'=>'btn btn-secondary btn-sm', 'value'=>'Tampilkan']);
echo ' ';
echo html_writer::link(new moodle_url($PAGE->url), 'Reset', ['class'=>'btn btn-link btn-sm']);
echo html_writer::end_tag('form');

// Baris "hari ..."
echo html_writer::tag('div', 'Hari '.indo_tanggal_lower($start, $tz), ['style'=>'margin:10px 0;']);

// ====== Ambil semua cohort (kelas) ======
global $DB;
$cohorts = $DB->get_records('cohort', null, 'name ASC', 'id, name');

if (empty($cohorts)) {
    echo html_writer::div('Belum ada cohort/kelas.', 'alert alert-info');
    echo $OUTPUT->footer();
    exit;
}

// ====== Proses tiap kelas ======
foreach ($cohorts as $c) {
    $kelasid = (int)$c->id;

    // Ambil entri jurnal pada tanggal terpilih untuk kelas ini
    $sql = "SELECT j.id, j.absen, j.timecreated, j.jamke
              FROM {local_jurnalmengajar} j
             WHERE j.kelas = :kelas
               AND j.timecreated BETWEEN :start AND :end
          ORDER BY j.timecreated ASC, j.id ASC";
    $params = ['kelas'=>$kelasid, 'start'=>$start, 'end'=>$end];
    $records = $DB->get_records_sql($sql, $params);

    // Rekap per murid: status terakhir + jam ke terakhir + waktu input terakhir
    // [nama => ['status'=>..., 'last_ts'=>int, 'last_jamke'=>int|null]]
    $rekap = [];
    foreach ($records as $r) {
        if (empty($r->absen)) continue;
        $arr = json_decode($r->absen, true);
        if (!is_array($arr)) continue;

        foreach ($arr as $nama => $status) {
            $status = normalize_status((string)$status);
            if (!is_tidak_hadir($status)) continue;

            if (!isset($rekap[$nama])) {
                $rekap[$nama] = [
                    'status'     => $status,
                    'last_ts'    => (int)$r->timecreated,
                    'last_jamke' => isset($r->jamke) ? (int)$r->jamke : null,
                ];
            } else {
                if ($status !== '') $rekap[$nama]['status'] = $status;
                if ((int)$r->timecreated > $rekap[$nama]['last_ts']) {
                    $rekap[$nama]['last_ts']    = (int)$r->timecreated;
                    $rekap[$nama]['last_jamke'] = isset($r->jamke) ? (int)$r->jamke : $rekap[$nama]['last_jamke'];
                }
            }
        }
    }

    // ====== Judul kelas & tabel ======
    echo html_writer::tag('div', 'Kelas '.s($c->name).', tidak hadir', ['style'=>'font-weight:bold; margin-top:14px;']);

    echo html_writer::start_tag('table', ['class'=>'generaltable', 'style'=>'margin-top:6px;']);
    echo html_writer::start_tag('thead');
    echo html_writer::tag('tr',
        html_writer::tag('th','No').
        html_writer::tag('th','Nama').
        html_writer::tag('th','absen').
        html_writer::tag('th','Jam Ke').
        html_writer::tag('th','waktu input')
    );
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    if (empty($rekap)) {
        // Satu baris saja ketika tidak ada data
        echo html_writer::tag('tr',
            html_writer::tag('td','-').
            html_writer::tag('td','-').
            html_writer::tag('td','').
            html_writer::tag('td','').
            html_writer::tag('td','')
        );
    } else {
        ksort($rekap, SORT_NATURAL | SORT_FLAG_CASE);
        $no = 1;
        foreach ($rekap as $nama => $info) {
            $jamke = $info['last_jamke'] !== null ? (string)$info['last_jamke'] : '';
            $waktu = (new DateTime('@'.$info['last_ts']))->setTimezone($tz)->format('H:i').' WITA';
            echo html_writer::tag('tr',
                html_writer::tag('td', (string)$no++).
                html_writer::tag('td', s($nama)).
                html_writer::tag('td', strtoupper($info['status'])).
                html_writer::tag('td', $jamke).
                html_writer::tag('td', $waktu)
            );
        }
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
}

echo $OUTPUT->footer();
