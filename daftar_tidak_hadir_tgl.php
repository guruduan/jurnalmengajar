
<?php

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/daftar_tidak_hadir_tgl.php'));
$PAGE->set_pagelayout('report');
$PAGE->set_title('Daftar murid tidak hadir per tanggal');
$PAGE->set_heading('Daftar murid tidak hadir per tanggal');

// ==========================
// Ambil tanggal dari parameter GET (WITA)
// ==========================
$tz = new DateTimeZone('Asia/Makassar');
$param_tanggal = optional_param('tanggal', date('Y-m-d'), PARAM_RAW_TRIMMED);

try {
    $selected = new DateTime($param_tanggal, $tz);
} catch (Exception $e) {
    $selected = new DateTime('now', $tz);
}
$start = (clone $selected)->setTime(0, 0, 0)->getTimestamp();
$end   = (clone $selected)->setTime(23, 59, 59)->getTimestamp();

global $DB;

// ==========================
// Fungsi utilitas
// ==========================
function classify_absent_reason(?string $raw): string {
    if ($raw === null) return '';
    $v = trim(mb_strtolower($raw, 'UTF-8'));
    $v = str_replace('izin', 'ijin', $v);
    if (preg_match('/^\s*sakit\b/u', $v))        return 'sakit';
    if (preg_match('/^\s*ijin\b/u', $v))         return 'ijin';
    if (preg_match('/^\s*alpa\b/u', $v))         return 'alpa';
    if (preg_match('/^\s*dispensasi\b/u', $v))   return 'dispensasi';
    return '';
}

function parse_jamke($raw): array {
    $out = [];
    if ($raw === null) return $out;
    if (is_int($raw)) {
        if ($raw > 0) $out[$raw] = true;
        return array_keys($out);
    }
    $s = trim((string)$raw);
    if ($s === '') return $out;
    $s = preg_replace('/[^0-9,\-\s]/', '', $s);
    foreach (explode(',', $s) as $tok) {
        $tok = trim($tok);
        if ($tok === '') continue;
        if (strpos($tok, '-') !== false) {
            [$a, $b] = array_pad(array_map('trim', explode('-', $tok, 2)), 2, '');
            if ($a === '' || $b === '') continue;
            $ia = (int)$a; $ib = (int)$b;
            if ($ia <= 0 || $ib <= 0) continue;
            if ($ia <= $ib) {
                for ($j = $ia; $j <= $ib; $j++) $out[$j] = true;
            } else {
                for ($j = $ib; $j <= $ia; $j++) $out[$j] = true;
            }
        } else {
            $n = (int)$tok;
            if ($n > 0) $out[$n] = true;
        }
    }
    $nums = array_keys($out);
    sort($nums, SORT_NUMERIC);
    return $nums;
}

$cohort_cache = [];
function cohort_name_from_any($kelasraw, array &$cache, \moodle_database $DB): string {
    $kelas = trim((string)$kelasraw);
    if ($kelas === '') return '';
    $kelas = preg_replace('/\s+/', ' ', $kelas);

    if (ctype_digit($kelas)) {
        if (isset($cache[$kelas])) return $cache[$kelas];
        if ($rec = $DB->get_record('cohort', ['id' => (int)$kelas], 'id, name, idnumber')) {
            $label = '';
            if (!empty($rec->name)) {
                $label = (string)$rec->name;
            } else if (!empty($rec->idnumber)) {
                $label = (string)$rec->idnumber;
            } else {
                $label = (string)$rec->id;
            }
            $cache[$kelas] = $label;
            return $label;
        }
        return $kelas;
    }
    return $kelas;
}


// ==========================
// Output
// ==========================
echo $OUTPUT->header();
// Tombol kembali
echo html_writer::div(
    html_writer::link(
        '#',
        '⬅ Kembali',
        [
            'class' => 'btn btn-secondary',
            'onclick' => 'history.back(); return false;'
        ]
    ),
    'mb-3'
);
// 🔁 TAB SWITCH
echo html_writer::start_div('mb-3 text-start');
echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/daftar_tidak_hadir_hari_ini.php'),
    '⏰ Hari ini',
    ['class' => 'btn btn-outline-secondary me-1']
);
echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/daftar_tidak_hadir_tgl.php'),
    '📅 Ke Tanggal',
    ['class' => 'btn btn-primary']
);
echo html_writer::end_div();

// ==========================
// FORM PILIH TANGGAL
// ==========================
echo html_writer::start_div('container mb-3 text-start');

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => '',
    'class'  => 'd-flex align-items-center justify-content-start gap-2'
]);

echo html_writer::label('Pilih tanggal:', 'id_tanggal', [
    'class' => 'fw-bold mb-0'
]);

echo html_writer::empty_tag('input', [
    'type'  => 'date',
    'id'    => 'id_tanggal',
    'name'  => 'tanggal',
    'value' => $selected->format('Y-m-d'),
    'class' => 'form-control w-auto'
]);

echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => 'Tampilkan',
    'class' => 'btn btn-success'
]);

echo html_writer::end_tag('form');

echo html_writer::div('', 'mt-2', ['id' => 'hari-terpilih']);
echo html_writer::end_div();

// JS hari realtime
echo html_writer::script("
document.addEventListener('DOMContentLoaded', function () {
    const inputTanggal = document.getElementById('id_tanggal');
    const divHari = document.getElementById('hari-terpilih');
    const hariIndo = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
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
    if (inputTanggal.value) {
        tampilkanHari(inputTanggal.value);
    }
});
");

// ==========================
// Ambil data dari tabel untuk tanggal yang dipilih
// ==========================
$select = 'timecreated BETWEEN :s AND :e AND absen IS NOT NULL AND absen <> :empty';
$params = ['s' => $start, 'e' => $end, 'empty' => ''];
$fields = 'id, kelas, jamke, absen, timecreated';
$journals = $DB->get_records_select('local_jurnalmengajar', $select, $params, 'timecreated ASC', $fields);

// Struktur:
// $data[kelas][lastname] = [
//   'reasons'   => [...],
//   'jam'       => [...],
//   'firsttime' => timestamp
// ];
$data = [];
foreach ($journals as $jr) {
    $kelaslabel = cohort_name_from_any($jr->kelas, $cohort_cache, $DB);
    if ($kelaslabel === '') continue;

    $jamlist = parse_jamke($jr->jamke);
    $json = json_decode($jr->absen, true);
    if (!is_array($json)) continue;

    foreach ($json as $lastname => $alasanraw) {
        $lastname = trim((string)$lastname);
        if ($lastname === '') continue;
        $cls = classify_absent_reason(is_null($alasanraw) ? '' : (string)$alasanraw);
        if ($cls === '') continue;

        if (!isset($data[$kelaslabel])) $data[$kelaslabel] = [];
        if (!isset($data[$kelaslabel][$lastname])) {
            $data[$kelaslabel][$lastname] = [
                'reasons'   => ['sakit'=>0, 'ijin'=>0, 'alpa'=>0, 'dispensasi'=>0],
                'jam'       => [],
                'firsttime' => $jr->timecreated,
            ];
        } else {
            // ambil waktu paling awal
            if ($jr->timecreated < $data[$kelaslabel][$lastname]['firsttime']) {
                $data[$kelaslabel][$lastname]['firsttime'] = $jr->timecreated;
            }
        }

        $data[$kelaslabel][$lastname]['reasons'][$cls]++;
        foreach ($jamlist as $j) {
            $data[$kelaslabel][$lastname]['jam'][$j] = true;
        }
    }
}

// ==========================
// Flatten & tampilkan
// ==========================
$rows = [];
foreach ($data as $kelas => $students) {
    foreach ($students as $lastname => $info) {
        arsort($info['reasons'], SORT_NUMERIC);
        $absen = key($info['reasons']);
        $jams = array_keys($info['jam']);
        sort($jams, SORT_NUMERIC);
        $rows[] = (object)[
            'kelas'     => $kelas,
            'lastname'  => $lastname,
            'absen'     => $absen,
            'jamke'     => implode(',', $jams),
            'timeinput' => $info['firsttime'],
        ];
    }
}
usort($rows, function($a, $b) {
    $c = strnatcasecmp($a->kelas, $b->kelas);
    if ($c !== 0) return $c;
    return strnatcasecmp($a->lastname, $b->lastname);
});

// Header tanggal
echo html_writer::tag(
    'p',
    html_writer::tag('strong', 'Hari/Tanggal: ') . tanggal_indo($selected->getTimestamp(), 'judul'),
    ['class' => 'mb-3']
);

if (empty($rows)) {
    echo $OUTPUT->notification('Tidak ada murid yang tercatat tidak hadir (sakit/ijin/alpa/dispensasi) pada tanggal ini.', 'notifymessage');
    echo $OUTPUT->footer();
    exit;
}

// Tabel
$table = new html_table();
$table->head = ['No', 'Kelas', 'Nama Murid', 'Absen', 'Jamke', 'Waktu Input'];
$table->attributes['class'] = 'generaltable boxaligncenter';
$table->data = [];

$no = 1;
foreach ($rows as $r) {
    $table->data[] = [
        $no++,
        format_string($r->kelas),
        format_string($r->lastname),
        format_string($r->absen),
        format_string($r->jamke === '' ? '-' : $r->jamke),
        format_string(tanggal_indo($r->timeinput, 'jam')),
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
