<?php
require('../../config.php');
require_once(__DIR__.'/lib.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

global $DB, $PAGE, $OUTPUT;

$userid = required_param('userid', PARAM_INT);
$hari   = required_param('hari', PARAM_TEXT);
$kelas  = required_param('kelas', PARAM_TEXT);

// Ambil nama guru
$user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

// Ambil jam lama
$sql = "SELECT jamke
        FROM {local_jurnalmengajar_jadwal}
        WHERE userid = :userid
        AND hari = :hari
        AND kelas = :kelas
        ORDER BY jamke";

$params = [
    'userid' => $userid,
    'hari' => $hari,
    'kelas' => $kelas
];

$records = $DB->get_records_sql($sql, $params);

$jamarray = [];
foreach ($records as $r) {
    $jamarray[] = $r->jamke;
}

$jamgabung = implode(',', $jamarray);

// ============================
// Proses Simpan
// ============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_sesskey();

    $hari_baru  = required_param('hari', PARAM_TEXT);
    $kelas_baru = required_param('kelas', PARAM_TEXT);
    $jamlist    = optional_param('jamke', '', PARAM_TEXT);

    // Hapus jadwal lama
    $DB->delete_records('local_jurnalmengajar_jadwal', [
        'userid' => $userid,
        'hari' => $hari,
        'kelas' => $kelas
    ]);

    // Insert jadwal baru
    if (!empty($jamlist)) {
        $jamarray = explode(',', $jamlist);

        $jamarray = array_unique($jamarray);

        foreach ($jamarray as $jam) {
            $jam = (int) trim($jam);
            if ($jam <= 0) continue;

            $record = new stdClass();
            $record->userid = $userid;
            $record->hari = $hari_baru;
            $record->kelas = $kelas_baru;
            $record->jamke = $jam;
            $record->timecreated = time();

            $DB->insert_record('local_jurnalmengajar_jadwal', $record);
        }
    }

    redirect(new moodle_url('/local/jurnalmengajar/jadwal_manage.php'),
             'Jadwal berhasil disimpan', 2);
}

// ============================
// Tampilan Halaman
// ============================
$PAGE->set_context($context);
$PAGE->set_url('/local/jurnalmengajar/jadwal_edit.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Edit Jadwal Mengajar');
$PAGE->set_heading('Edit Jadwal Mengajar');

echo $OUTPUT->header();

echo "<form method='post'>";
echo "<input type='hidden' name='sesskey' value='".sesskey()."'>";

echo "<table class='generaltable'>";

echo "<tr><td>Guru</td><td>{$user->firstname} {$user->lastname}</td></tr>";

// Dropdown hari
$hari_list = jurnalmengajar_get_hari_sekolah();

echo "<tr><td>Hari</td><td>";
echo "<select name='hari'>";
foreach ($hari_list as $h) {
    $selected = ($hari == $h) ? 'selected' : '';
    echo "<option value='$h' $selected>$h</option>";
}
echo "</select>";
echo "</td></tr>";

echo "<tr><td>Kelas</td><td>
<input type='text' name='kelas' value='$kelas'>
</td></tr>";

echo "<tr><td>Jam (pisahkan koma)</td><td>
<input type='text' name='jamke' value='$jamgabung'>
Contoh: 1,2,3
</td></tr>";

echo "</table>";
echo "<br>";
echo "<input type='submit' value='Simpan' class='btn btn-primary'>";
echo " ";
echo "<a href='jadwal_manage.php' class='btn btn-secondary'>Kembali</a>";

echo "</form>";

echo $OUTPUT->footer();
