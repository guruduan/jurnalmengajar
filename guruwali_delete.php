<?php
require('../../config.php');
require_once(__DIR__.'/lib.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

global $CFG;

$userid = required_param('userid', PARAM_INT);
$nis    = required_param('nis', PARAM_TEXT);

$file = $CFG->dataroot . '/binaan.csv';
$data = [];

// ======================
// Load CSV
// ======================
if (file_exists($file)) {
    if (($handle = fopen($file, 'r')) !== false) {
        $header = fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== false) {
            if (!($row[0] == $userid && $row[2] == $nis)) {
                $data[] = $row;
            }
        }
        fclose($handle);
    }
}

// ======================
// Simpan kembali CSV
// ======================
$handle = fopen($file, 'w');
fputcsv($handle, ['userid','lastname','nis','murid','kelas']);
foreach ($data as $d) {
    fputcsv($handle, $d);
}
fclose($handle);

// Redirect
redirect(
    new moodle_url('/local/jurnalmengajar/guruwali_manage.php'),
    'Data siswa binaan dihapus',
    2
);
