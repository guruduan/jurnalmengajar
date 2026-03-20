<?php
require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('local/jurnalmengajar:view', context_system::instance());

$PAGE->set_context(context_system::instance());

header('Content-Type: text/html; charset=utf-8');

$cohortid = required_param('kelas', PARAM_INT);
$mode     = optional_param('mode', '', PARAM_TEXT);

$cohort = $DB->get_record('cohort', ['id' => $cohortid], '*', IGNORE_MISSING);
if (!$cohort) {
    echo "<div class='alert alert-warning'>Cohort tidak ditemukan.</div>";
    exit;
}

$members = $DB->get_records_sql("
    SELECT u.id, u.firstname, u.lastname
    FROM {cohort_members} cm
    JOIN {user} u ON u.id = cm.userid
    WHERE cm.cohortid = ?
    ORDER BY u.lastname ASC
", [$cohortid]);

if (!$members) {
    echo "<div class='alert alert-info'>Tidak ada siswa dalam cohort ini.</div>";
    exit;
}

// === Mode dropdown untuk pembinaan ===
if ($mode === 'dropdown') {
    echo '<div id="peserta-dropdown-wrapper">';
    echo '<select name="peserta" id="peserta-dropdown" class="custom-select">';
    foreach ($members as $user) {
        $lastname = ucwords(strtolower(trim($user->lastname))) ?: 'Tanpa Nama';
        echo '<option value="' . s($lastname) . '">' . format_string($lastname) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    exit;
}

// === Default (checkbox untuk layanan BK) ===
echo '<div><strong>Pilih siswa yang diberikan layanan:</strong></div>';
echo '<div style="display:flex; gap:40px; margin-top:10px;">';
$members = array_values($members);
$half = ceil(count($members) / 2);

function tampilkan_siswa_bk($daftar, $offset = 0) {
    foreach ($daftar as $i => $user) {
        $no = $i + 1 + $offset;
        $lastname = ucwords(strtolower(trim($user->lastname))) ?: 'Tanpa Nama';
        echo '<div style="margin-bottom:8px;">';
        echo '<input type="checkbox" class="siswa-checkbox" data-nama="' . s($lastname) . '" id="bkcb_' . $user->id . '">';
        echo '<label for="bkcb_' . $user->id . '"> ' . $no . '. ' . format_string($lastname) . '</label>';
        echo '</div>';
    }
}

echo '<div style="flex:1;">';
tampilkan_siswa_bk(array_slice($members, 0, $half), 0);
echo '</div>';
echo '<div style="flex:1;">';
tampilkan_siswa_bk(array_slice($members, $half), $half);
echo '</div>';
echo '</div>';
