<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/jurnalmengajar/pembina_ekstra.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Mapping Pembina Ekstrakurikuler');
$PAGE->set_heading('Mapping Pembina Ekstrakurikuler');

global $DB;

// =======================
// SIMPAN MAPPING
// =======================
if (isset($_POST['ekstraid'])) {

    $ekstraid = $_POST['ekstraid'];

    // hapus mapping lama
    $DB->delete_records('local_jm_ekstra_pembina', ['ekstraid' => $ekstraid]);

    if (!empty($_POST['pembina'])) {
        foreach ($_POST['pembina'] as $userid) {
            $data = new stdClass();
            $data->ekstraid = $ekstraid;
            $data->userid = $userid;
            $DB->insert_record('local_jm_ekstra_pembina', $data);
        }
    }

    redirect(
    new moodle_url('/local/jurnalmengajar/pembina_ekstra.php'),
    'Mapping pembina ekstrakurikuler berhasil disimpan',
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
}

echo $OUTPUT->header();
echo '<div style="margin-bottom:15px;">';
echo '<a href="'.$CFG->wwwroot.'/local/jurnalmengajar/pembina_ekstra_view.php"
        style="padding:8px 12px; background:#28a745; color:white; text-decoration:none; border-radius:5px;">
        Lihat Data Pembina Ekstrakurikuler
      </a>';
echo '</div>';
// =======================
// AMBIL DATA EKSTRA
// =======================
$ekstra_list = $DB->get_records('local_jm_ekstra');

// Ambil semua guru (role teacher)
// Ambil guru berdasarkan role gurujurnal
$role = $DB->get_record('role', ['shortname' => 'gurujurnal']);

$guru_list = [];
if ($role) {
    $sql = "SELECT u.id, u.firstname, u.lastname
            FROM {user} u
            JOIN {role_assignments} ra ON ra.userid = u.id
            WHERE ra.roleid = :roleid
            ORDER BY u.firstname";
    $guru_list = $DB->get_records_sql($sql, ['roleid' => $role->id]);
}

// ekstra dipilih
$selected_ekstra = $_GET['ekstraid'] ?? 0;

// ambil pembina yg sudah ada
$pembina_terpilih = [];
if ($selected_ekstra) {
    $records = $DB->get_records('local_jm_ekstra_pembina', ['ekstraid' => $selected_ekstra]);
    foreach ($records as $r) {
        $pembina_terpilih[] = $r->userid;
    }
}

// =======================
// FORM
// =======================
echo '<form method="post">';

echo '<h3>Pilih Ekstrakurikuler</h3>';
echo '<select name="ekstraid" onchange="location.href=\'?ekstraid=\'+this.value">';
echo '<option value="">-- Pilih Ekstra --</option>';

foreach ($ekstra_list as $e) {
    $sel = ($selected_ekstra == $e->id) ? 'selected' : '';
    echo '<option value="'.$e->id.'" '.$sel.'>'.$e->namaekstra.'</option>';
}

echo '</select>';

if ($selected_ekstra) {

    echo '<h3>Pilih Pembina</h3>';

    echo '<table>';
$i = 0;

foreach ($guru_list as $g) {
    if ($i % 3 == 0) echo '<tr>';

    $checked = in_array($g->id, $pembina_terpilih) ? 'checked' : '';

    echo '<td style="padding:5px 20px;">';
    echo '<input type="checkbox" name="pembina[]" value="'.$g->id.'" '.$checked.'> ';
    echo $g->firstname.' '.$g->lastname;
    echo '</td>';

    if ($i % 3 == 2) echo '</tr>';

    $i++;
}

echo '</table>';

    echo '<br><button type="submit">Simpan Mapping</button>';
}

echo '</form>';

echo $OUTPUT->footer();
