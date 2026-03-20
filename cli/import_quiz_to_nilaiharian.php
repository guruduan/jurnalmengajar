<?php
// Import nilai akhir Quiz → Nilai Harian (local_jm_nilaiharian).
// Pakai salah satu: --cmid=... (id di /mod/quiz/view.php?id=CMID) ATAU --quizid=...
//
// Contoh:
// php local/jurnalmengajar/cli/import_quiz_to_nilaiharian.php \
//   --cmid=1285 --cohortid=82 --mapel="Biologi" --tanggal=2025-09-12 --teacherid=14 --replace=1
//
// php local/jurnalmengajar/cli/import_quiz_to_nilaiharian.php \
//   --quizid=123 --cohortid=82 --mapel="Biologi" --tanggal=2025-09-12 --teacherid=14 --replace=1

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params([
    'quizid'    => null,
    'cmid'      => null, // new: bisa pakai cmid
    'cohortid'  => null,
    'mapel'     => null,
    'tanggal'   => null, // YYYY-MM-DD
    'teacherid' => null, // user id guru (pemilik entri)
    'replace'   => 0,    // 1 = hapus entri existing (kombinasi sama) sebelum insert
    'help'      => 0
], []);

$help = "Impor nilai akhir Quiz ke Nilai Harian (0..100).

Options:
--quizid=ID          ID dari tabel quiz (mdl_quiz.id)
--cmid=ID            Course module id (id di /mod/quiz/view.php?id=CMID); mengisi quizid otomatis
--cohortid=ID        ID cohort (kelas)
--mapel=STRING       Nama mata pelajaran, contoh: 'Biologi'
--tanggal=YYYY-MM-DD Tanggal penilaian (WITA/kalender sekolah)
--teacherid=ID       User id guru penginput (disimpan ke field userid)
--replace=1          (opsional) Hapus entri existing (teacherid+mapel+cohortid+tanggal) sebelum insert
--help               Tampilkan bantuan

Contoh:
php local/jurnalmengajar/cli/import_quiz_to_nilaiharian.php --cmid=1285 --cohortid=82 --mapel=\"Biologi\" --tanggal=2025-09-12 --teacherid=14 --replace=1
";

if (!empty($options['help'])) {
    cli_writeln($help);
    exit(0);
}

global $DB;

// Resolve quizid dari cmid bila perlu.
if (empty($options['quizid']) && !empty($options['cmid'])) {
    $cmid = (int)$options['cmid'];
    $sql = "SELECT q.id
              FROM {quiz} q
              JOIN {course_modules} cm ON cm.instance = q.id
              JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
             WHERE cm.id = :cmid";
    $qid = $DB->get_field_sql($sql, ['cmid' => $cmid]);
    if (!$qid) {
        cli_error("cmid #$cmid bukan modul quiz atau tidak ditemukan.");
    }
    $options['quizid'] = $qid;
}

$quizid    = isset($options['quizid'])    ? (int)$options['quizid']    : 0;
$cohortid  = isset($options['cohortid'])  ? (int)$options['cohortid']  : 0;
$mapel     = isset($options['mapel'])     ? trim($options['mapel'])    : '';
$tanggal   = isset($options['tanggal'])   ? trim($options['tanggal'])  : '';
$teacherid = isset($options['teacherid']) ? (int)$options['teacherid'] : 0;
$replace   = !empty($options['replace'])  ? 1 : 0;

if (empty($quizid) || empty($cohortid) || $mapel === '' || $tanggal === '' || empty($teacherid)) {
    cli_writeln($help);
    cli_error("Parameter wajib kurang. Minimal: --quizid/--cmid, --cohortid, --mapel, --tanggal, --teacherid");
}

// Validasi dasar.
if (!$quiz = $DB->get_record('quiz', ['id' => $quizid], '*', IGNORE_MISSING)) {
    cli_error("Quiz #$quizid tidak ditemukan.");
}
if (!$cohort = $DB->get_record('cohort', ['id' => $cohortid], 'id,name')) {
    cli_error("Cohort #$cohortid tidak ditemukan.");
}
if (!$teacher = $DB->get_record('user', ['id' => $teacherid], 'id,firstname,lastname')) {
    cli_error("Teacher ID #$teacherid tidak ditemukan.");
}
$kelas = $cohort->name;

// Ambil anggota cohort.
$members = $DB->get_fieldset_select('cohort_members', 'userid', 'cohortid = :cid', ['cid' => $cohortid]);
if (empty($members)) {
    cli_error("Cohort #$cohortid tidak punya anggota.");
}

// Ambil grade final untuk item quiz ini dari gradebook.
list($insql, $inparams) = $DB->get_in_or_equal($members, SQL_PARAMS_NAMED);
$params = array_merge(['quizid' => $quizid], $inparams);

$grades = $DB->get_records_sql("
    SELECT gg.userid, gg.finalgrade, gi.grademax
      FROM {grade_items} gi
      JOIN {grade_grades} gg ON gg.itemid = gi.id
     WHERE gi.itemtype   = 'mod'
       AND gi.itemmodule = 'quiz'
       AND gi.iteminstance = :quizid
       AND gg.userid $insql
", $params);

// Susun rows (0..100) pakai lastname Proper Case (konsisten dengan input form).
$rows = [];
$have = [];

if (!empty($grades)) {
    $userids = array_keys($grades);
    list($insql2, $inparams2) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
    $users = $DB->get_records_select('user', "id $insql2", $inparams2, '', 'id,firstname,lastname');

    foreach ($grades as $uid => $g) {
        $u = $users[$uid] ?? null;
        if (!$u) { continue; }
        $name = ucwords(strtolower($u->lastname));
        $max  = (float)($g->grademax ?? 0.0);
        $val  = (float)($g->finalgrade ?? 0.0);
        $nilai100 = ($max > 0) ? (int)round(($val / $max) * 100) : 0;

        $rows[] = (object)[
            'no'     => 0, // isi setelah sorting
            'userid' => (int)$uid,
            'name'   => $name,
            'nilai'  => $nilai100
        ];
        $have[$uid] = true;
    }
}

// Tambahkan siswa cohort yang tidak punya nilai → 0.
$missing = array_diff($members, array_keys($have));
if (!empty($missing)) {
    list($insql3, $inparams3) = $DB->get_in_or_equal($missing, SQL_PARAMS_NAMED);
    $users2 = $DB->get_records_select('user', "id $insql3", $inparams3, '', 'id,firstname,lastname');
    foreach ($missing as $uid) {
        if (!isset($users2[$uid])) { continue; }
        $u = $users2[$uid];
        $name = ucwords(strtolower($u->lastname));
        $rows[] = (object)[
            'no'     => 0,
            'userid' => (int)$uid,
            'name'   => $name,
            'nilai'  => 0
        ];
    }
}

// Urutkan by nama, isi no berurutan.
usort($rows, function($a, $b) { return strcmp($a->name, $b->name); });
$seq = 1; foreach ($rows as $r) { $r->no = $seq++; }

// Optional replace (hapus entri existing dengan kombinasi sama).
if ($replace) {
    $DB->delete_records('local_jm_nilaiharian', [
        'userid'   => $teacherid,
        'mapel'    => $mapel,
        'cohortid' => $cohortid,
        'tanggal'  => $tanggal
    ]);
}

// Simpan ke local_jm_nilaiharian.
$rec = (object)[
    'timecreated'  => time(),
    'timemodified' => time(),
    'userid'       => $teacherid,
    'mapel'        => $mapel,
    'cohortid'     => $cohortid,
    'kelas'        => $kelas,
    'tanggal'      => $tanggal,
    'nilaijson'    => json_encode(array_values($rows), JSON_UNESCAPED_UNICODE)
];
$id = $DB->insert_record('local_jm_nilaiharian', $rec);

cli_writeln("SUKSES: tersimpan ke local_jm_nilaiharian id=$id, siswa=" . count($rows) . ", quizid=$quizid, cohortid=$cohortid, mapel='$mapel', tanggal=$tanggal");
