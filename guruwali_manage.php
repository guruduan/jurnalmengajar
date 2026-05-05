<?php
require('../../config.php');
require_once(__DIR__.'/lib.php');
require_once(__DIR__.'/jadwal_acuan_lib.php'); // nanti bisa dipakai load csv juga

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/jurnalmengajar/guruwali_manage.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Manajemen Guru Wali');
$PAGE->set_heading('Manajemen Guru Wali / Murid Binaan');

echo $OUTPUT->header();

global $DB, $USER;

// ============================
// Load data binaan.csv
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
// Ambil daftar guru wali untuk filter
// ============================
$listguru = [];
foreach ($data as $d) {
    $listguru[$d['userid']] = $d['lastname'];
}

// Urutkan A-Z
asort($listguru);

// Default guru = user login
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
// Tombol atas
// ============================
echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/import_binaan.php'),
    'Import CSV',
    ['class' => 'btn btn-primary']
);

echo " ";

echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/guruwali_add.php'),
    'Tambah/Update Murid Binaan',
    ['class' => 'btn btn-success']
);

echo "<br><br>";

// ============================
// Filter guru
// ============================
echo html_writer::start_tag('form', [
    'method' => 'get',
    'style' => 'margin-bottom:15px;'
]);

echo "Filter Guru Wali: ";
echo html_writer::select(
    $listguru,
    'guru',
    $filterguru,
    null,
    ['onchange' => 'this.form.submit()']
);

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
        <th>Hapus</th>
      </tr>";

$no = 1;

foreach ($filtered as $d) {

    $hapusurl = new moodle_url('/local/jurnalmengajar/guruwali_delete.php', [
        'userid' => $d['userid'],
        'nis' => $d['nis']
    ]);

    echo "<tr>";
    echo "<td>$no</td>";
    echo "<td>{$d['nis']}</td>";
    echo "<td>{$d['murid']}</td>";
    echo "<td>{$d['kelas']}</td>";
    echo "<td>{$d['lastname']}</td>";
    echo "<td><a class='btn btn-danger' href='$hapusurl' onclick=\"return confirm('Hapus siswa binaan?')\">Hapus</a></td>";
    echo "</tr>";

    $no++;
}

echo "</table>";

echo $OUTPUT->footer();
