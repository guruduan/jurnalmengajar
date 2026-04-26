<?php
require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('local/jurnalmengajar:submit', context_system::instance());

$context = context_system::instance();
$PAGE->set_context($context); // Wajib agar $PAGE->context tersedia

header('Content-Type: text/html; charset=utf-8');

$cohortid = required_param('kelas', PARAM_INT);
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

$members = array_values($members);
$total = count($members);
$setengah = ceil($total / 2);

// Bagi ke 2 kolom
$kolom1 = array_slice($members, 0, $setengah);
$kolom2 = array_slice($members, $setengah);

echo '<div><strong>Pilih siswa tidak hadir & pilih alasannya:</strong></div>';
echo '<div style="margin-top: 10px;">';
echo '<div style="display: flex; gap: 40px;">';

// Fungsi bantu tampil siswa
function tampilkan_siswa($daftar, $offset = 0) {
    foreach ($daftar as $i => $user) {
        $no = $i + 1 + $offset;
        $lastname = ucwords(strtolower(trim($user->lastname)));
        if (!$lastname) $lastname = 'Tanpa Nama';

        echo '<div class="absen-item" style="margin-bottom: 8px; display: flex; gap: 10px;">';
        echo '<input type="checkbox" class="absen-checkbox" data-nama="' . s($lastname) . '" id="cb_' . $user->id . '">';
        echo '<label for="cb_' . $user->id . '" style="min-width: 150px;">' . $no . '. ' . format_string($lastname) . '</label>';
        echo '<select class="absen-alasan" disabled style="min-width: 130px;">
                <option value="">-- Alasan --</option>
                <option value="ijin">Ijin</option>
                <option value="sakit">Sakit</option>
                <option value="alpa">Alpa</option>
                <option value="dispensasi">Dispensasi</option>
              </select>';
        echo '</div>';
    }
}

// Kolom 1
echo '<div style="flex:1;">';
tampilkan_siswa($kolom1, 0);
echo '</div>';

// Kolom 2
echo '<div style="flex:1;">';
tampilkan_siswa($kolom2, $setengah);
echo '</div>';

echo '</div>'; // end flex
echo '</div>'; // end wrapper
