<?php
require('../../config.php');
require_once(__DIR__.'/lib.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
global $DB, $USER;
$filterguru = optional_param('guru', $USER->id, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/jurnalmengajar/jadwal_manage.php', [
    'guru' => $filterguru
]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Manajemen Jadwal Mengajar');
$PAGE->set_heading('Manajemen Jadwal Mengajar');



// ============================
// Hapus jadwal (per grup)
// ============================
$userid = optional_param('userid', 0, PARAM_INT);
$hari   = optional_param('hari', '', PARAM_TEXT);
$kelas  = optional_param('kelas', '', PARAM_TEXT);

if ($userid && $hari && $kelas) {
    $DB->delete_records('local_jurnalmengajar_jadwal', [
        'userid' => $userid,
        'hari' => $hari,
        'kelas' => $kelas
    ]);

    redirect(new moodle_url('/local/jurnalmengajar/jadwal_manage.php', [
        'guru' => $filterguru
    ]), 'Jadwal berhasil dihapus', 2);
}

echo $OUTPUT->header();
// ============================
// Ambil daftar guru untuk filter
// ============================
$sqlguru = "SELECT DISTINCT u.id, u.lastname
            FROM {role_assignments} ra
            JOIN {user} u ON u.id = ra.userid
            JOIN {role} r ON r.id = ra.roleid
            WHERE r.shortname = 'gurujurnal'
            AND u.deleted = 0
            ORDER BY u.lastname";

$dataguru = $DB->get_records_sql($sqlguru);

$listguru = [];
foreach ($dataguru as $g) {
    $listguru[$g->id] = $g->lastname;
}

// ============================
// Ambil jadwal
// ============================
$sql = "SELECT j.id, j.userid, j.hari, j.kelas, j.jamke, u.lastname
        FROM {local_jurnalmengajar_jadwal} j
        JOIN {user} u ON u.id = j.userid
        WHERE j.userid = :userid
        ORDER BY u.lastname, j.hari, j.jamke";

$jadwal = $DB->get_records_sql($sql, ['userid' => $filterguru]);

// ============================
// Urutan hari dari lib.php
// ============================
$hariurut = jurnalmengajar_get_urutan_hari();

// ============================
// Grouping jadwal
// ============================
$grouped = [];

foreach ($jadwal as $j) {
    $key = $j->hari . '|' . $j->userid . '|' . $j->kelas;

    if (!isset($grouped[$key])) {
        $grouped[$key] = [
            'userid' => $j->userid,
            'hari' => $j->hari,
            'hari_no' => $hariurut[$j->hari] ?? 99,
            'lastname' => $j->lastname,
            'kelas' => $j->kelas,
            'jamke' => []
        ];
    }

    $grouped[$key]['jamke'][] = $j->jamke;
}


// Urutkan berdasarkan hari
usort($grouped, function($a, $b) {
    return $a['hari_no'] <=> $b['hari_no'];
});

// ============================
// Tombol atas
// ============================
echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/import_acuan.php'),
    'Import CSV',
    ['class' => 'btn btn-secondary']
);
echo " ";

echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/jadwal_add.php'),
    'Tambah Jadwal',
    ['class' => 'btn btn-success']
);
echo " ";

echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/jadwal_view.php'),
    'Lihat Jadwal',
    ['class' => 'btn btn-primary']
);

echo "<br><br>";

// ============================
// Filter guru
// ============================
echo html_writer::start_tag('form', [
    'method' => 'get',
    'style' => 'margin-bottom:15px;'
]);

echo "Filter Guru: ";
echo html_writer::select($listguru, 'guru', $filterguru);

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => 'Tampilkan',
    'class' => 'btn btn-secondary',
    'style' => 'margin-left:5px'
]);

echo html_writer::end_tag('form');
$namaguru = $listguru[$filterguru] ?? '-';
echo "<h4>Jadwal Guru: $namaguru</h4>";
$totaljam = 0;
foreach ($grouped as $g) {
    $totaljam += count($g['jamke']);
}
echo "<p>Total Jam Mengajar: <b>$totaljam</b></p>";
if (!empty($grouped)) {

    echo "<table class='generaltable'>";
    echo "<tr>
            <th>No</th>
            <th>Hari</th>
            <th>Guru</th>
            <th>Kelas</th>
            <th>Jam</th>
            <th>Edit</th>
            <th>Hapus</th>
          </tr>";

    $no = 1;
    $hari_sebelumnya = '';

    foreach ($grouped as $g) {

        sort($g['jamke']);
        $jamgabung = implode(',', $g['jamke']);

        $hapusurl = new moodle_url('/local/jurnalmengajar/jadwal_manage.php', [
            'userid' => $g['userid'],
            'hari' => $g['hari'],
            'kelas' => $g['kelas'],
            'guru' => $filterguru
        ]);

        $editurl = new moodle_url('/local/jurnalmengajar/jadwal_edit.php', [
            'userid' => $g['userid'],
            'hari' => $g['hari'],
            'kelas' => $g['kelas'],
            'guru' => $filterguru
        ]);

        echo "<tr>";

        if ($hari_sebelumnya != $g['hari']) {
            echo "<td>$no</td>";
            echo "<td>{$g['hari']}</td>";
            $hari_sebelumnya = $g['hari'];
            $no++;
        } else {
            echo "<td></td>";
            echo "<td></td>";
        }

        echo "<td>{$g['lastname']}</td>";
        echo "<td>{$g['kelas']}</td>";
        echo "<td>$jamgabung</td>";
        echo "<td><a class='btn btn-warning' href='$editurl'>Edit</a></td>";
        echo "<td><a class='btn btn-danger' href='$hapusurl' onclick=\"return confirm('Hapus jadwal?')\">Hapus</a></td>";

        echo "</tr>";
    }

    echo "</table>";
}

echo $OUTPUT->footer();
