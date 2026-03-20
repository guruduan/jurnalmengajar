<?php
// File: local/jurnalmengajar/nilaiharian.php
require_once(__DIR__ . '/../../config.php');
require_login();

use local_jurnalmengajar\form\nilai_form; // letakkan BEFORE output

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

// Timezone WITA
date_default_timezone_set('Asia/Makassar');

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/nilaiharian.php'));
$PAGE->set_title('Input Nilai Harian');
$PAGE->set_heading('Input Nilai Harian');

require_once(__DIR__ . '/classes/form/nilai_form.php');

echo $OUTPUT->header();

/** Helper: normalisasi nomor WA ke format 62 */
function jm_normalize_phone($s) {
    $s = preg_replace('/\D+/', '', (string)$s);
    if ($s === '') return '';
    if ($s[0] === '0') $s = '62' . substr($s, 1);     // 0xxxxx -> 62xxxxx
    if (strpos($s, '62') === 0) return $s;            // sudah 62...
    if (strpos($s, '8') === 0) return '62' . $s;      // 8xxxxx -> 62xxxx
    return $s;
}

/** Helper: ambil label nama cohort dari id */
function jm_get_cohort_label($cohortid) {
    global $DB;
    if (!$cohort = $DB->get_record('cohort', ['id' => $cohortid])) {
        return ['kelas' => '', 'name' => ''];
    }
    // Jika ingin nama saja: pakai $cohort->name
    $label = $cohort->name; // (tanpa idnumber, sesuai permintaan terbaru)
    return ['kelas' => $label, 'name' => $cohort->name];
}

$mform = new nilai_form(null, []);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/jurnalmengajar/index.php'));
}
else if ($data = $mform->get_data()) {
    global $DB, $USER;

    // ====== Ambil input dari form ======
    $mapel    = trim($data->mapel);
    $cohortid = (int)$data->cohortid;
    // Simpan tanggal sebagai YYYY-MM-DD (pakai timezone yang sudah diset)
    $tanggal  = userdate($data->tanggal, '%Y-%m-%d');
    $kelas    = jm_get_cohort_label($cohortid)['kelas'];

    // ====== Ambil anggota cohort ======
    $members = $DB->get_records_sql("
        SELECT u.id, u.firstname, u.lastname
          FROM {cohort_members} cm
          JOIN {user} u ON u.id = cm.userid
         WHERE cm.cohortid = :cid
      ORDER BY u.lastname, u.firstname
    ", ['cid' => $cohortid]);

    // ====== Ambil nilai dari POST (bukan dari $data) ======
    $nilai = optional_param_array('nilai', [], PARAM_RAW);

    // ====== Susun baris nilai ======
    $rows = [];
    $no = 1;
    foreach ($members as $u) {
        $val = (isset($nilai[$u->id]) && $nilai[$u->id] !== '') ? (int)$nilai[$u->id] : null;
        if ($val === null) {
            continue; // hanya simpan yang diisi
        }
        $rows[] = (object)[
            'no'     => $no++,
            'userid' => (int)$u->id,
            'name'   => ucwords(strtolower($u->lastname)), // lastname proper case
            'nilai'  => $val
        ];
    }

    if (empty($rows)) {
        \core\notification::warning('Tidak ada nilai yang diisi.');
        $mform->display();
        echo $OUTPUT->footer();
        exit;
    }

    // ====== Simpan ke DB ======
    $rec = (object)[
        'timecreated'  => time(),
        'timemodified' => time(),
        'userid'       => $USER->id,
        'mapel'        => $mapel,
        'cohortid'     => $cohortid,
        'kelas'        => $kelas,
        'tanggal'      => $tanggal,
        'nilaijson'    => json_encode(array_values($rows), JSON_UNESCAPED_UNICODE)
    ];
    $id = $DB->insert_record('local_jm_nilaiharian', $rec);

    // ====== Susun pesan WA ======
    $hariindo = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $ts = strtotime($tanggal . ' 00:00:00'); // acuan hari
    $labelhari = $hariindo[(int)date('w', $ts)];
    $labeltgl  = date('d-m-Y', $ts);

    $lines = [];
    $lines[] = "*Nilai Mata Pelajaran*: {$mapel}";
    $lines[] = "*Hari/Tanggal*: {$labelhari}, {$labeltgl}";
    $lines[] = "*Kelas*: {$kelas}";
    $lines[] = "";
    foreach ($rows as $r) {
        $lines[] = "{$r->no}. {$r->name} - {$r->nilai}";
    }
    $pesan = implode("\n", $lines);

    // ====== Kirim WA ke guru penginput ======
    $nomor = jm_normalize_phone($USER->phone1 ?: $USER->phone2);
    if (!empty($nomor)) {
        require_once(__DIR__ . '/lib.php');
        if (function_exists('jurnalmengajar_kirim_wa')) {
            jurnalmengajar_kirim_wa($nomor, $pesan);
        } else {
            debugging('Fungsi jurnalmengajar_kirim_wa() tidak ditemukan.', DEBUG_DEVELOPER);
        }
    } else {
        \core\notification::info('Nomor WA tidak ditemukan di profil Anda (phone1/phone2). Nilai tetap tersimpan tanpa notifikasi.');
    }

    \core\notification::success('Nilai harian tersimpan dan notifikasi terkirim.');

    // Tampilkan ringkasan
    echo $OUTPUT->box(nl2br(s($pesan)), 'generalbox');  // tampilkan ringkasan pesan
    echo $OUTPUT->continue_button(new moodle_url('/local/jurnalmengajar/nilaiharian.php'));
    echo $OUTPUT->footer();
    exit;
}

// ====== Tampilkan form (awal/validasi) ======
$mform->display();
echo $OUTPUT->footer();
