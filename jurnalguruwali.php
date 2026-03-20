<?php
require('../../config.php');
require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/local/jurnalmengajar/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/jurnalguruwali.php'));
$PAGE->set_title('Jurnal Guru Wali');
$PAGE->set_heading('Jurnal Guru Wali');

global $DB, $USER;

/* ======================= HELPERS ======================= */

function jw_load_binaan_csv(): array {
    global $CFG;
    $csvpath = $CFG->dataroot . '/binaan.csv';
    if (!file_exists($csvpath)) return [];

    $content = file_get_contents($csvpath);
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

    $lines = preg_split("/\r\n|\n|\r/", trim($content));
    if (count($lines) < 2) return [];

    $delimiter = (substr_count($lines[0], ';') > substr_count($lines[0], ',')) ? ';' : ',';

    $header = str_getcsv(array_shift($lines), $delimiter);

    $idx = [
        'userid' => array_search('userid', $header),
        'nis'    => array_search('nis', $header),
        'murid'  => array_search('murid', $header),
        'kelas'  => array_search('kelas', $header),
    ];

    $rows = [];
    foreach ($lines as $line) {
        $r = str_getcsv($line, $delimiter);
        $rows[] = [
            'guruid' => (int)$r[$idx['userid']],
            'nis'    => $r[$idx['nis']],
            'murid'  => $r[$idx['murid']],
            'kelas'  => $r[$idx['kelas']],
        ];
    }
    return $rows;
}

function jw_find_user_by_nis($nis) {
    global $DB;
    return $DB->get_field_sql("
        SELECT u.id FROM {user} u
        JOIN {user_info_data} d ON d.userid=u.id
        JOIN {user_info_field} f ON f.id=d.fieldid
        WHERE f.shortname='nis' AND d.data=?
    ", [$nis]);
}

function jw_get_murid_options_from_csv($guruid): array {
    global $DB;

    $rows = jw_load_binaan_csv();
    $ids = [];

    foreach ($rows as $r) {
        if ($r['guruid'] != $guruid) continue;

        $id = jw_find_user_by_nis($r['nis']);
        if ($id) $ids[$id] = true;
    }

    if (!$ids) return [];

    list($in, $params) = $DB->get_in_or_equal(array_keys($ids));

    $users = $DB->get_records_select('user', "id $in", $params, 'lastname ASC', 'id,firstname,lastname');

    $opts = [];
    foreach ($users as $u) {
        $opts[$u->id] = trim($u->firstname.' '.$u->lastname);
    }

    return $opts;
}

function jw_get_kelas_siswa($userid) {
    global $DB;

    return $DB->get_field_sql("
        SELECT c.name
        FROM {cohort} c
        JOIN {cohort_members} cm ON cm.cohortid=c.id
        WHERE cm.userid=?
        ORDER BY c.name ASC
    ", [$userid]);
}

/* ======================= FORM ======================= */

class jw_form extends moodleform {
    public function definition() {
        global $USER;

        $m = $this->_form;

        $now = time();
        $m->addElement('static', 'waktu', 'Waktu', format_waktu_indo($now));

        $muridopts = jw_get_murid_options_from_csv($USER->id);

// Bagi jadi 2 kolom
$col1 = array_slice($muridopts, 0, ceil(count($muridopts)/2), true);
$col2 = array_slice($muridopts, ceil(count($muridopts)/2), null, true);

$html = '<div class="jw-grid">';

// === KOLOM 1 ===
$html .= '<div class="jw-col">';
$no = 1;
foreach ($col1 as $id => $name) {
    $html .= '<label class="jw-item">
        <span class="jw-no">'.$no++.'.</span>
        <input type="checkbox" name="muridids[]" value="'.$id.'">
        <span class="jw-name">'.$name.'</span>
    </label>';
}
$html .= '</div>';

// === KOLOM 2 ===
$html .= '<div class="jw-col">';
foreach ($col2 as $id => $name) {
    $html .= '<label class="jw-item">
        <span class="jw-no">'.$no++.'.</span>
        <input type="checkbox" name="muridids[]" value="'.$id.'">
        <span class="jw-name">'.$name.'</span>
    </label>';
}
$html .= '</div>';

$html .= '</div>';

// CSS
$html .= '
<style>
.jw-grid {
    display: flex;
    gap: 40px;
    margin: 10px 0;
}
.jw-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.jw-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}
.jw-no {
    width: 25px;
    color: #666;
}
.jw-name {
    flex: 1;
}
</style>
';

        $m->addElement('html', $html);

        $m->addElement('text','topik','Topik',['size'=>80]);
        $m->setType('topik', PARAM_TEXT);

        $m->addElement('textarea','tindaklanjut','Tindak Lanjut',['rows'=>3]);
        $m->setType('tindaklanjut', PARAM_TEXT);

        $m->addElement('textarea','keterangan','Keterangan',['rows'=>3]);
        $m->setType('keterangan', PARAM_TEXT);

        $this->add_action_buttons(true, 'Simpan');
    }
}

/* ======================= HANDLE SUBMIT ======================= */

$mform = new jw_form();

if ($data = $mform->get_data()) {

    require_sesskey();

    $muridids = optional_param_array('muridids', [], PARAM_INT);

if (empty($muridids)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification('Pilih minimal satu murid.', 'error');
    $mform->display();
    echo $OUTPUT->footer();
    exit;
}

    $now = time();

    foreach ($muridids as $muridid) {

        $murid = $DB->get_record('user', ['id'=>$muridid], 'lastname');

        $record = new stdClass();
        $record->guruid = $USER->id;
        $record->muridid = $muridid;
        $record->topik = $data->topik;
        $record->tindaklanjut = $data->tindaklanjut;
        $record->keterangan = $data->keterangan;
        $record->timecreated = $now;

        $DB->insert_record('local_jurnalguruwali', $record);

        // ===== WA =====
        $kelas = jw_get_kelas_siswa($muridid);

        $pesan = "*📋 Jurnal Guru Wali*\n\n"
               . "📅 Waktu: ".format_waktu_indo($now)."\n"
               . "👤 Murid: ".ucwords(strtolower($murid->lastname))."\n"
               . "🏫 Kelas: ".$kelas."\n"
               . "🧩 Topik: ".$data->topik."\n"
               . "💡 Tindak lanjut: ".$data->tindaklanjut."\n"
               . "📝 Keterangan: ".$data->keterangan."\n"
               . "👨‍🏫 Guru Wali: ".$USER->lastname;

        $nomorwa = get_nomor_wali_kelas($kelas);

        if ($nomorwa) {
            jurnalmengajar_kirim_wa($nomorwa, $pesan);
        }
    }

    redirect($PAGE->url, 'Data berhasil disimpan');
}

/* ======================= TAMPILAN ======================= */

echo $OUTPUT->header();

$mform->display();

echo html_writer::tag('h3','Riwayat');

$rows = $DB->get_records_sql("
    SELECT j.*, u.lastname
    FROM {local_jurnalguruwali} j
    JOIN {user} u ON u.id=j.muridid
    WHERE j.guruid=?
    ORDER BY j.timecreated DESC
", [$USER->id], 0, 10);

$table = new html_table();
$table->head = ['No','Waktu','Murid','Kelas','Topik','Tindak','Ket','Aksi'];

$no = 1;

foreach ($rows as $r) {
$editurl = new moodle_url('/local/jurnalmengajar/jurnalguruwali.php', [
    'editid' => $r->id,
    'sesskey' => sesskey()
]);

$aksi = html_writer::link($editurl, '✏️ Edit');
    $kelas = jw_get_kelas_siswa($r->muridid);

    $table->data[] = [
        $no++,
        format_waktu_indo($r->timecreated),
        s($r->lastname),
        s($kelas),
	s($r->topik),
        s($r->tindaklanjut),
        s($r->keterangan),
        $aksi
    ];
}

echo html_writer::table($table);
echo html_writer::start_div('mt-3');

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => (new moodle_url('/local/jurnalmengajar/exportguruwali_form.php'))->out(false),
    'class'  => 'd-inline'
]);

echo html_writer::tag('button', '<strong>💾 Ekspor Jurnal Guru Wali per Bulan</strong>', [
    'type'  => 'submit',
    'class' => 'btn btn-outline-secondary'
], false);

echo html_writer::end_tag('form');
echo html_writer::end_div();

echo $OUTPUT->footer();
