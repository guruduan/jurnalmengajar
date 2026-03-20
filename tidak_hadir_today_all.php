<?php
// File: local/jurnalmengajar/tidak_hadir_today_all.php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/tidak_hadir_today_all.php'));
$PAGE->set_title('Murid Tidak Hadir — Hari Ini');
$PAGE->set_heading('Murid Tidak Hadir — Hari Ini');

echo $OUTPUT->header();

// ====== Timezone & rentang hari ini (WITA) ======
date_default_timezone_set('Asia/Makassar');
$start = strtotime('today midnight');
$end   = strtotime('tomorrow midnight') - 1;

// ====== Helper tanggal (indo, bulan lowercase) ======
function indo_tanggal_lower($ts) {
    $hari  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $bulan = [1=>'januari','februari','maret','april','mei','juni','juli','agustus','september','oktober','november','desember'];
    return $hari[date('w',$ts)].' '.date('j',$ts).' '.$bulan[date('n',$ts)].' '.date('Y',$ts);
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
echo html_writer::link(new moodle_url('/local/jurnalmengajar/tidak_hadir_today_all.php'),
    'Hari ini', ['class'=>'btn btn-primary btn-sm']);
echo ' ';
echo html_writer::link(new moodle_url('/local/jurnalmengajar/tidak_hadir_bydate_all.php'),
    'Ke tanggal', ['class'=>'btn btn-outline-secondary btn-sm']);
echo html_writer::end_div();

echo html_writer::tag('div', 'Hari '.indo_tanggal_lower($start), ['style'=>'margin-bottom:10px;']);

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

    // Ambil entri jurnal HARI INI untuk kelas ini
    $sql = "SELECT j.id, j.absen, j.timecreated, j.jamke
              FROM {local_jurnalmengajar} j
             WHERE j.kelas = :kelas
               AND j.timecreated BETWEEN :start AND :end
          ORDER BY j.timecreated ASC, j.id ASC";
    $params = ['kelas'=>$kelasid, 'start'=>$start, 'end'=>$end];
    $records = $DB->get_records_sql($sql, $params);

    // Rekap per murid: status terakhir + jamke terakhir + waktu input terakhir
    // Struktur: [nama => ['status'=>..., 'last_ts'=>int, 'last_jamke'=>int|null]]
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
                    'last_ts'    => $r->timecreated,
                    'last_jamke' => isset($r->jamke) ? (int)$r->jamke : null,
                ];
            } else {
                // Simpan status terbaru non-empty, dan timestamp/jamke dari entri paling akhir
                if ($status !== '') $rekap[$nama]['status'] = $status;
                if ($r->timecreated > $rekap[$nama]['last_ts']) {
                    $rekap[$nama]['last_ts']    = $r->timecreated;
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
        // hanya satu baris kosong
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
            $waktu = date('H:i', $info['last_ts']).' WITA';
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
} // ← ini penutup foreach yang tadi hilang

echo $OUTPUT->footer();
