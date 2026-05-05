<?php
require('../../config.php');
require_once(__DIR__.'/lib.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/jurnalmengajar/guruwali_add.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Tambah Murid Binaan');
$PAGE->set_heading('Tambah Murid Binaan');

global $DB, $CFG;

// ======================
// Ambil daftar guru
// ======================
$role = $DB->get_record('role', ['shortname' => 'gurujurnal']);

$sql = "SELECT u.id, u.lastname
        FROM {role_assignments} ra
        JOIN {user} u ON u.id = ra.userid
        WHERE ra.roleid = :roleid
        ORDER BY u.lastname";

$dataguru = $DB->get_records_sql($sql, ['roleid' => $role->id]);

$listguru = [];
foreach ($dataguru as $g) {
    $listguru[$g->id] = $g->lastname;
}

// ======================
// Ambil kelas
// ======================
$listkelas = jurnalmengajar_get_all_kelas();

// ======================
// Ambil parameter
// ======================
$kelas  = optional_param('kelas', '', PARAM_TEXT);
$userid = optional_param('userid', 0, PARAM_INT);

// ======================
// Ambil siswa dari kelas
// ======================
$listsiswa = [];
if ($kelas) {
    $siswa = jurnalmengajar_get_siswa_by_kelas($kelas);
    foreach ($siswa as $s) {
        $listsiswa[$s->id] = $s->lastname;
    }
}

// ======================
// Simpan CSV
// ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_sesskey();

    $userid  = required_param('userid', PARAM_INT);
    $kelas   = required_param('kelas', PARAM_TEXT);
    $muridid = required_param('muridid', PARAM_INT);

    $muriduser = $DB->get_record('user', ['id'=>$muridid], 'lastname');
    $nis = jurnalmengajar_get_nis_user($muridid);

    if (!$nis) {
        print_error('NIS siswa belum diisi di profile.');
    }

    $namaguru = $listguru[$userid];

    $file = $CFG->dataroot . '/binaan.csv';
    $data = [];

    // Load CSV lama
    if (file_exists($file)) {
        if (($handle = fopen($file, 'r')) !== false) {
            $header = fgetcsv($handle);
            if ($header) {
                while (($row = fgetcsv($handle)) !== false) {
                    $data[] = $row;
                }
            }
            fclose($handle);
        }
    }

    // Update atau tambah
    $newdata = [];
    $found = false;

    foreach ($data as $d) {
        if ($d[2] == $nis) {
            $newdata[] = [
                $userid,
                $namaguru,
                $nis,
                $muriduser->lastname,
                $kelas
            ];
            $found = true;
        } else {
            $newdata[] = $d;
        }
    }

    if (!$found) {
        $newdata[] = [
            $userid,
            $namaguru,
            $nis,
            $muriduser->lastname,
            $kelas
        ];
    }

    // Simpan ulang CSV
    $handle = fopen($file, 'w');
    fputcsv($handle, ['userid','lastname','nis','murid','kelas']);

    foreach ($newdata as $d) {
        fputcsv($handle, $d);
    }

    fclose($handle);

    redirect(
        new moodle_url('/local/jurnalmengajar/guruwali_manage.php'),
        'Murid binaan berhasil disimpan',
        2
    );
}

echo $OUTPUT->header();

// ======================
// FORM PILIH KELAS
// ======================
echo html_writer::start_tag('form', ['method'=>'get']);

echo html_writer::label('Guru Wali', 'userid');
echo html_writer::select($listguru, 'userid', $userid, null, [
    'onchange' => 'this.form.submit()'
]);
echo "<br><br>";

echo html_writer::label('Kelas', 'kelas');
echo html_writer::select($listkelas, 'kelas', $kelas, null, [
    'onchange' => 'this.form.submit()'
]);


echo html_writer::end_tag('form');

echo "<br>";

// ======================
// FORM SIMPAN
// ======================
if ($kelas && $listsiswa && $userid) {

    echo html_writer::start_tag('form', ['method'=>'post']);

    echo html_writer::empty_tag('input', [
        'type'=>'hidden',
        'name'=>'sesskey',
        'value'=>sesskey()
    ]);

    echo html_writer::empty_tag('input', [
        'type'=>'hidden',
        'name'=>'kelas',
        'value'=>$kelas
    ]);

    echo html_writer::empty_tag('input', [
        'type'=>'hidden',
        'name'=>'userid',
        'value'=>$userid
    ]);

    echo html_writer::label('Murid', 'muridid');
    echo html_writer::select($listsiswa, 'muridid');
    echo "<br><br>";

    echo html_writer::empty_tag('input', [
        'type'=>'submit',
        'value'=>'Simpan',
        'class'=>'btn btn-success'
    ]);

    echo html_writer::end_tag('form');
}

echo $OUTPUT->footer();
