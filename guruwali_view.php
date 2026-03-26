<?php
require('../../config.php');
require_once(__DIR__.'/lib.php');
require_once(__DIR__.'/jadwal_acuan_lib.php');

require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/guruwali_view.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Data Murid Binaan Guru Wali');
$PAGE->set_heading('Data Murid Binaan Guru Wali');

echo $OUTPUT->header();

global $USER;

// ============================
// Load binaan.csv
// ============================
$binaanfile = $CFG->dataroot . '/binaan.csv';
$data = [];

if (file_exists($binaanfile)) {
    if (($handle = fopen($binaanfile, 'r')) !== false) {
        $header = fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== false) {
            $data[] = [
                'userid' => $row[0],
                'lastname' => $row[1],
                'nis' => $row[2],
                'murid' => $row[3],
                'kelas' => $row[4]
            ];
        }
        fclose($handle);
    }
}

// ============================
// List guru wali
// ============================
$listguru = [];
foreach ($data as $d) {
    $listguru[$d['userid']] = $d['lastname'];
}
asort($listguru);

// Default = guru login
$filterguru = optional_param('guru', $USER->id, PARAM_INT);

// ============================
// Filter data
// ============================
$filtered = [];
foreach ($data as $d) {
    if ($d['userid'] == $filterguru) {
        $filtered[] = $d;
    }
}

// ============================
// Filter dropdown
// ============================
echo html_writer::start_tag('form', [
    'method' => 'get',
    'style' => 'margin-bottom:15px;'
]);

echo "Filter Guru Wali: ";
echo html_writer::select($listguru, 'guru', $filterguru);

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => 'Tampilkan',
    'class' => 'btn btn-secondary',
    'style' => 'margin-left:5px'
]);

echo html_writer::end_tag('form');

// ============================
// Tabel binaan
// ============================
echo "<table class='generaltable'>";
echo "<tr>
        <th>No</th>
        <th>NIS</th>
        <th>Nama Murid</th>
        <th>Kelas</th>
        <th>Guru Wali</th>
      </tr>";

$no = 1;
foreach ($filtered as $d) {
    echo "<tr>";
    echo "<td>$no</td>";
    echo "<td>{$d['nis']}</td>";
    echo "<td>{$d['murid']}</td>";
    echo "<td>{$d['kelas']}</td>";
    echo "<td>{$d['lastname']}</td>";
    echo "</tr>";
    $no++;
}

echo "</table>";

echo $OUTPUT->footer();
