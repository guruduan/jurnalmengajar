<?php
require('../../config.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/jurnalmengajar/import_binaan.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Import Data Binaan CSV');
$PAGE->set_heading('Import Data Binaan Guru Wali file CSV');

echo $OUTPUT->header();

echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/download_format_binaan.php'),
    'Download Format CSV',
    ['class' => 'btn btn-secondary', 'style' => 'margin-bottom:10px;']
);

global $CFG;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_FILES['csvfile']['tmp_name'])) {

        $dest = $CFG->dataroot . '/binaan.csv';

        if (move_uploaded_file($_FILES['csvfile']['tmp_name'], $dest)) {
            echo $OUTPUT->notification('Upload binaan.csv berhasil', 'notifysuccess');
        } else {
            echo $OUTPUT->notification('Upload gagal', 'notifyproblem');
        }
    }
}

echo "<h3>Upload File binaan.csv</h3>";

echo "<form method='post' enctype='multipart/form-data'>";
echo "<input type='file' name='csvfile' accept='.csv' required>";
echo "<br><br>";
echo "<input type='submit' value='Upload CSV' class='btn btn-primary'>";
echo "</form>";

echo "<br>";
echo "<b>Format CSV:</b>";
echo "<pre>
userid,lastname,nis,murid,kelas
13,\"Drs. Abdullah, M.Pd\",1105,Noor Salima,XD
13,\"Drs. Abdullah, M.Pd\",1957,Muhammad Noor Azmi,XE
13,\"Drs. Abdullah, M.Pd\",1910,Ilis Dahlia,XA
</pre>";

echo $OUTPUT->footer();
