<?php
require_once(__DIR__ . '/../../config.php');
require_login();
$context = context_system::instance();

require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/jurnalmengajar/ekstra.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Data Ekstrakurikuler');
$PAGE->set_heading('Data Ekstrakurikuler');

global $DB;

// =======================
// SIMPAN DATA
// =======================
if (isset($_POST['namaekstra'])) {
    $data = new stdClass();
    $data->namaekstra = $_POST['namaekstra'];
    $DB->insert_record('local_jm_ekstra', $data);
    redirect(new moodle_url('/local/jurnalmengajar/ekstra.php'));
}

// =======================
// TAMPILKAN HALAMAN
// =======================
echo $OUTPUT->header();

echo '<h3>Tambah Ekstrakurikuler</h3>';
echo '<form method="post">';
echo '<input type="text" name="namaekstra" required>';
echo '<button type="submit">Simpan</button>';
echo '</form>';

$data = $DB->get_records('local_jm_ekstra');

echo '<h3>Daftar Ekstrakurikuler</h3>';
echo '<table border="1" cellpadding="5">';
echo '<tr><th>No</th><th>Nama Ekstra</th></tr>';

$no = 1;
foreach ($data as $d) {
    echo '<tr>';
    echo '<td>'.$no++.'</td>';
    echo '<td>'.$d->namaekstra.'</td>';
    echo '</tr>';
}

echo '</table>';

echo $OUTPUT->footer();
