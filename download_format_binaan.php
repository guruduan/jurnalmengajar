<?php
require_once('../../config.php');
require_once(__DIR__.'/lib.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="format_binaan.csv"');

echo "userid,lastname,nis,murid,kelas\n";

global $DB;

// Ambil role gurujurnal
$role = $DB->get_record('role', ['shortname' => 'gurujurnal']);
if (!$role) {
    die('Role gurujurnal tidak ditemukan');
}

// Ambil semua user dengan role gurujurnal
$sql = "SELECT u.id, u.lastname
        FROM {role_assignments} ra
        JOIN {user} u ON u.id = ra.userid
        WHERE ra.roleid = :roleid
        ORDER BY u.lastname";

$users = $DB->get_records_sql($sql, ['roleid' => $role->id]);

// Tampilkan satu baris kosong per guru (untuk template)
foreach ($users as $u) {
    echo $u->id . ',' .
         '"' . $u->lastname . '",,,,' . "\n";
}

exit;
