<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/edit_jam_guru.php'));
$PAGE->set_title('Edit Jam Mengajar per Guru');
$PAGE->set_heading('Edit Jam Mengajar per Guru');

global $DB;

// Ambil semua user dengan role "gurujurnal"
$roleid = $DB->get_field('role', 'id', ['shortname' => 'gurujurnal']);
$guru_users = get_role_users($roleid, $context);

// File config sederhana sebagai penyimpan (bisa diganti database)
//$configfile = __DIR__ . '/jam_guru.json';
$configfile = $CFG->dataroot . '/jam_guru.json';

// Jika ada POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = $_POST['jam'] ?? [];
    $saved = [];
foreach ($posted as $userid => $jam) {
    $user = $DB->get_record('user', ['id' => $userid]);
    $namaguru = $user->lastname ?: fullname($user);
//    $saved[$namaguru] = max(0, (int)$jam);
$saved[$userid] = max(0, (int)$jam);

}

    file_put_contents($configfile, json_encode($saved));
    redirect(new moodle_url('/local/jurnalmengajar/edit_jam_guru.php'), 'Berhasil disimpan', 2);
}

// Ambil konfigurasi lama
$jam_perminggu = file_exists($configfile) ? json_decode(file_get_contents($configfile), true) : [];

echo $OUTPUT->header();
echo html_writer::tag('h3', 'Edit Jam Mengajar per Guru');
echo '<form method="post">';
echo '<table class="generaltable"><thead><tr><th>No</th><th>Nama Guru</th><th>Username</th><th>Jam/Minggu</th></tr></thead><tbody>';
$no = 1; // Tambahkan/tegaskan ini di awal sebelum foreach
foreach ($guru_users as $user) {
    $userid = $user->id;
$namaguru = $user->lastname;  //tampilan hanya nama belakang
    $username = $user->username;
//    $current = $jam_perminggu[$namaguru] ?? 24;
$current = $jam_perminggu[$userid] ?? 24;

    echo '<tr>';
    echo '<td>' . $no++ . '</td>';
    echo '<td>' . $namaguru . '</td>';
    echo '<td>' . $username . '</td>';
    echo '<td><input type="number" name="jam[' . $userid . ']" value="' . $current . '" style="width:60px"></td>';
    echo '</tr>';
}

echo '</tbody></table>';
echo '<br><input type="submit" value="Simpan Perubahan">';
echo '</form>';
echo $OUTPUT->footer();
