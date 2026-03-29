<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/jurnalmengajar/pembina_ekstra_view.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Data Pembina Ekstrakurikuler');
$PAGE->set_heading('Data Pembina Ekstrakurikuler');

echo $OUTPUT->header();

global $DB;

// Ambil semua ekstra
$ekstra = $DB->get_records('local_jm_ekstra');

echo '<table border="1" cellpadding="5">';
echo '<tr>';
echo '<th>No</th>';
echo '<th>Ekstrakurikuler</th>';
echo '<th>Pembina</th>';
echo '</tr>';

$no = 1;

foreach ($ekstra as $e) {

    // Ambil semua pembina untuk ekstra ini
    $sql = "SELECT u.id, u.firstname, u.lastname
            FROM {local_jm_ekstra_pembina} p
            JOIN {user} u ON u.id = p.userid
            WHERE p.ekstraid = ?
            ORDER BY u.lastname";
    $pembina = $DB->get_records_sql($sql, [$e->id]);

    // Gabungkan nama pembina
    $nama_pembina = [];
    foreach ($pembina as $p) {
        $nama_pembina[] = $p->firstname . ' ' . $p->lastname;
    }

    echo '<tr>';
    echo '<td>'.$no++.'</td>';
    echo '<td>'.$e->namaekstra.'</td>';
    echo '<td>'.implode(', ', $nama_pembina).'</td>';
    echo '</tr>';
}

echo '</table>';

echo $OUTPUT->footer();
