<?php
require('../../config.php');
require_once(__DIR__.'/lib.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

global $DB, $PAGE, $OUTPUT;

// Ambil daftar guru dari role gurujurnal
$sqlguru = "SELECT DISTINCT u.id, u.firstname, u.lastname
            FROM {role_assignments} ra
            JOIN {user} u ON u.id = ra.userid
            JOIN {role} r ON r.id = ra.roleid
            WHERE r.shortname = 'gurujurnal'
            AND u.deleted = 0
            ORDER BY u.lastname";

$dataguru = $DB->get_records_sql($sqlguru);

$listguru = [];
foreach ($dataguru as $g) {
    $listguru[$g->id] = $g->firstname . ' ' . $g->lastname;
}

// Proses simpan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_sesskey();

    $userid = required_param('userid', PARAM_INT);
    $hari   = required_param('hari', PARAM_TEXT);
    $kelas  = required_param('kelas', PARAM_TEXT);
    $jamke  = required_param('jamke', PARAM_TEXT);

    // simpan untuk ditampilkan kembali
    $selected_userid = $userid;
    $selected_hari   = $hari;
    $selected_kelas  = $kelas;
    $selected_jamke  = $jamke;

    $jamarray = explode(',', $jamke);

    foreach ($jamarray as $jam) {
        $jam = (int) trim($jam);
        if ($jam <= 0) continue;

        $record = new stdClass();
        $record->userid = $userid;
        $record->hari = $hari;
        $record->kelas = $kelas;
        $record->jamke = $jam;
        $record->timecreated = time();

        $DB->insert_record('local_jurnalmengajar_jadwal', $record);
    }

    echo $OUTPUT->notification('Jadwal berhasil ditambahkan', 'notifysuccess');
}

// Tampilan
$PAGE->set_context($context);
$PAGE->set_url('/local/jurnalmengajar/jadwal_add.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Tambah Jadwal Mengajar');
$PAGE->set_heading('Tambah Jadwal Mengajar');

echo $OUTPUT->header();

echo "<form method='post'>";
echo "<input type='hidden' name='sesskey' value='".sesskey()."'>";

echo "<table class='generaltable'>";

echo "<tr><td>Guru</td><td>";
echo html_writer::select($listguru, 'userid', $selected_userid);
echo "</td></tr>";

$hari_list = jurnalmengajar_get_hari_sekolah();

echo "<tr><td>Hari</td><td>";
echo "<select name='hari'>";
foreach ($hari_list as $h) {
    $selected = ($h == $selected_hari) ? "selected" : "";
    echo "<option value='$h' $selected>$h</option>";
}
echo "</select>";
echo "</td></tr>";

echo "<tr><td>Kelas</td><td>
<input type='text' name='kelas'>
</td></tr>";

echo "<tr><td>Jam (pisahkan koma)</td><td>
<input type='text' name='jamke'>
Contoh: 1,2,3
</td></tr>";

echo "</table>";
echo "<br>";
echo "<input type='submit' value='Simpan' class='btn btn-primary'>";
echo " ";
echo "<a href='jadwal_manage.php' class='btn btn-secondary'>Kembali</a>";

echo "</form>";

echo $OUTPUT->footer();
