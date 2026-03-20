<?php
// File: local/jurnalmengajar/rekapnilai.php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

date_default_timezone_set('Asia/Makassar');

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/rekapnilai.php'));
$PAGE->set_title('Rekap Nilai Harian');
$PAGE->set_heading('Rekap Nilai Harian');

global $DB, $USER, $OUTPUT;

// ====== Ambil filter ======
$mapel    = optional_param('mapel', '', PARAM_TEXT);
$cohortid = optional_param('cohortid', 0, PARAM_INT);
$export   = optional_param('export', '', PARAM_ALPHA);

// ====== Opsi Mapel: hanya mapel yang pernah diisi oleh user ini ======
$mapelopts = [];
$myMapels = $DB->get_fieldset_sql(
    "SELECT DISTINCT mapel
       FROM {local_jm_nilaiharian}
      WHERE userid = :uid AND mapel <> ''
   ORDER BY mapel ASC",
   ['uid' => $USER->id]
);
foreach ($myMapels as $m) {
    $m = trim($m);
    if ($m !== '') { $mapelopts[$m] = $m; }
}
// Jika ?mapel tidak ada di opsi user, kosongkan
if (!array_key_exists($mapel, $mapelopts)) {
    $mapel = '';
}

// ====== Opsi Cohort (nama saja, dedup nama) ======
// ====== Opsi Cohort: hanya kelas yang pernah diinput oleh user ini ======
$cohortopts = [];
$seen = [];

// ambil daftar cohortid yang pernah diinput user ini
$mycohortids = $DB->get_fieldset_sql("
    SELECT DISTINCT cohortid
      FROM {local_jm_nilaiharian}
     WHERE userid = :uid AND cohortid > 0
  ORDER BY cohortid ASC
", ['uid' => $USER->id]);

if (!empty($mycohortids)) {
    // ambil nama cohort berdasarkan id yang ditemukan
    $cohorts = $DB->get_records_list('cohort', 'id', $mycohortids, 'name ASC', 'id,name');
    foreach ($cohorts as $c) {
        if (isset($seen[$c->name])) { continue; } // dedup by name (opsional)
        $cohortopts[$c->id] = $c->name;
        $seen[$c->name] = true;
    }
}

// jika cohortid yang dipilih tidak ada di opsi -> reset
if ($cohortid && !array_key_exists($cohortid, $cohortopts)) {
    $cohortid = 0;
}


// ----------------------------------------------------
// Siapkan data (members, entries, matrix) lebih dulu,
// agar EXPORT CSV bisa dikerjakan sebelum ada output.
// ----------------------------------------------------
$students = [];   // userid => name
$attempts = [];   // daftar entri (untuk keterangan)
$matrix   = [];   // [userid][attempt_index] = nilai (0 default)
$N        = 0;    // jumlah kolom Nilai

if (!empty($cohortid)) {
    // Ambil daftar murid cohort
    $members = $DB->get_records_sql("
        SELECT u.id, u.firstname, u.lastname
          FROM {cohort_members} cm
          JOIN {user} u ON u.id = cm.userid
         WHERE cm.cohortid = :cid
      ORDER BY u.lastname, u.firstname
    ", ['cid' => $cohortid]);

    foreach ($members as $u) {
        $students[$u->id] = ucwords(strtolower($u->lastname)); // lastname Proper Case
    }

    // Ambil entri nilai (milik user ini), filter mapel jika dipilih
    $where  = "cohortid = :cohortid AND userid = :uid";
    $params = ['cohortid' => $cohortid, 'uid' => $USER->id];
    if (!empty($mapel)) {
        $where .= " AND mapel = :mapel";
        $params['mapel'] = $mapel;
    }

    // Urut kronologis: Nilai 1 = entri awal
    $entries = $DB->get_records_select('local_jm_nilaiharian', $where, $params, 'tanggal ASC, timecreated ASC');

    // Build attempts
    $idx = 0;
    foreach ($entries as $rec) {
        $attempts[$idx] = [
            'idx'     => $idx + 1,
            'mapel'   => $rec->mapel,
            'kelas'   => $rec->kelas,
            'tanggal' => $rec->tanggal,
            'guru'    => $rec->userid
        ];
        $idx++;
    }
    $N = count($attempts);

    // Inisialisasi matrix 0
    foreach ($students as $uid => $name) {
        for ($i = 0; $i < max(1, $N); $i++) {
            $matrix[$uid][$i] = 0;
        }
    }

    // Isi matrix dari JSON
    $idx = 0;
    foreach ($entries as $rec) {
        $rows = json_decode($rec->nilaijson ?? '[]');
        if (is_array($rows)) {
            foreach ($rows as $r) {
                if (!isset($r->userid)) { continue; }
                $uid = (int)$r->userid;
                if (!array_key_exists($uid, $students)) { continue; } // hanya murid di cohort ini
                $matrix[$uid][$idx] = isset($r->nilai) && $r->nilai !== '' ? (int)$r->nilai : 0;
            }
        }
        $idx++;
    }
}

// ====== EXPORT CSV (harus dieksekusi sebelum output HTML) ======
// ====== EXPORT CSV ======
if ($export === 'csv' && !empty($cohortid)) {
    $filename = 'rekap_nilai_per_murid_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $out = fopen('php://output', 'w');

    // --- Tambahan judul rekap ---
    fputcsv($out, ['REKAP NILAI HARIAN']);
    fputcsv($out, []); // baris kosong

    // Ambil label mapel & kelas untuk dicetak
    $mapellabel  = $mapel ?: '- Semua Mapel -';
    $kelaslabel  = isset($cohortopts[$cohortid]) ? $cohortopts[$cohortid] : '';
    $tahunajaran = '2025/2026';

    fputcsv($out, ['Mata Pelajaran :', $mapellabel]);
    fputcsv($out, ['Kelas :', $kelaslabel]);
    fputcsv($out, ['Tahun :', $tahunajaran]);
    fputcsv($out, []); // baris kosong sebelum tabel

    // --- Header tabel ---
    $header = ['No', 'Nama Murid', 'Rata-rata'];
    for ($i=1; $i <= max(1,$N); $i++) { 
        $header[] = 'Nilai ' . $i; 
    }
    fputcsv($out, $header);

    // --- Isi data ---
    $no  = 1;
    $den = max(1, $N);
    foreach ($students as $uid => $name) {
        $sum = 0;
        for ($i=0; $i < $den; $i++) { $sum += (int)$matrix[$uid][$i]; }
        $avg = $den > 0 ? round($sum / $den, 2) : 0;

        $row = [$no++, $name, $avg];
        for ($i=0; $i < $den; $i++) {
            $row[] = $matrix[$uid][$i];
        }
        fputcsv($out, $row);
    }

    fclose($out);
    exit;
}


// ====== Mulai output HTML ======
echo $OUTPUT->header();

// Form filter (GET)
$url = new moodle_url('/local/jurnalmengajar/rekapnilai.php');
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $url, 'class' => 'mform']);
echo html_writer::start_div('filters', ['style' => 'display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;']);

// Mapel (tampil hanya jika user ini pernah input)
if (!empty($mapelopts)) {
    $mapelchoices = ['' => '- Semua Mapel -'] + $mapelopts;
    echo html_writer::div(
        html_writer::label(get_string('matapelajaran', 'local_jurnalmengajar'), 'id_mapel', false) .
        html_writer::select($mapelchoices, 'mapel', $mapel, null, ['id' => 'id_mapel']),
        'fitem'
    );
}

// Kelas (wajib)
$kelaschoices = ['' => '-- Pilih Kelas --'] + $cohortopts;
echo html_writer::div(
    html_writer::label(get_string('kelas', 'local_jurnalmengajar'), 'id_cohortid', false) .
    html_writer::select($kelaschoices, 'cohortid', $cohortid, null, ['id' => 'id_cohortid', 'required' => 'required']),
    'fitem'
);

// Tombol
echo html_writer::div(
    html_writer::empty_tag('input', ['type' => 'submit', 'value' => 'Tampilkan', 'class' => 'btn btn-primary']) . ' ' .
    html_writer::empty_tag('input', ['type' => 'submit', 'name' => 'export', 'value' => 'csv', 'class' => 'btn btn-secondary']),
    'fitem'
);

echo html_writer::end_div();
echo html_writer::end_tag('form');

// Jika belum pilih kelas → info saja
if (empty($cohortid)) {
    echo $OUTPUT->notification('Silakan pilih Kelas terlebih dahulu.', \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

// ====== Tabel hasil ======
$table = new html_table();
$head = ['No', 'Nama Murid', 'Rata-rata'];
for ($i=1; $i <= max(1,$N); $i++) { $head[] = 'Nilai ' . $i; }
$table->head  = $head;
$table->align = array_merge(['center','left','center'], array_fill(0, max(1,$N), 'center'));

$data = [];
$no  = 1;
$den = max(1, $N);
foreach ($students as $uid => $name) {
    $sum = 0;
    for ($i=0; $i < $den; $i++) { $sum += (int)$matrix[$uid][$i]; }
    $avg = $den > 0 ? round($sum / $den, 2) : 0;

    $row = [$no++, s($name), s($avg)];
    for ($i=0; $i < $den; $i++) {
        $row[] = s($matrix[$uid][$i]); // 0 jika kosong
    }
    $data[] = new html_table_row($row);
}
$table->data = $data;

// Render
if ($N === 0) {
    echo $OUTPUT->notification('Belum ada entri nilai untuk filter ini. Tabel menampilkan siswa dengan kolom Nilai kosong (0).', \core\output\notification::NOTIFY_INFO);
}
echo html_writer::table($table);

// Keterangan kolom (opsional)
if ($N > 0) {
    $ket = [];
    for ($i=0; $i<$N; $i++) {
        $guru = fullname(\core_user::get_user($attempts[$i]['guru']));
        $ket[] = 'Nilai '.($i+1).': '.$attempts[$i]['mapel'].' • '.$attempts[$i]['kelas'].' • '.$attempts[$i]['tanggal'].' • '.$guru;
    }
    echo $OUTPUT->box(implode('<br>', array_map('s', $ket)), 'generalbox');
}

echo $OUTPUT->footer();
