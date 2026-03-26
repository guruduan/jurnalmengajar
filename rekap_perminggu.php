<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__.'/lib.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/rekap_perminggu.php'));
$PAGE->set_title('Rekap Mingguan Guru');
$PAGE->set_heading('Rekap Mengajar Mingguan Guru');

// Tambahkan CSS untuk sticky header
//$PAGE->requires->css('/local/jurnalmengajar/css/stickyheader.css');

// Ambil tanggal awal minggu
$tanggalstring = get_config('local_jurnalmengajar', 'tanggalawalminggu') ?: '2025-06-23';
$tanggal_awal = new DateTime($tanggalstring);
$timestart = $tanggal_awal->getTimestamp();

// Hitung minggu berjalan (default)
$param_mingguke = optional_param('mingguke', 0, PARAM_INT);
if ($param_mingguke > 0) {
    $mingguke = $param_mingguke;
} else {
    $selisih_hari = floor((time() - $timestart) / (60 * 60 * 24));
    $mingguke = floor($selisih_hari / 7) + 1;
    if ($mingguke < 1) $mingguke = 1;
    if ($mingguke > 20) $mingguke = 20;
}

// Filter tambahan
$filter_lastname = optional_param('lastname', '', PARAM_RAW);
$filter_bulan = optional_param('bulan', 0, PARAM_INT);

// Tentukan rentang waktu minggu ke-$mingguke
$awal_minggu = clone $tanggal_awal;
$awal_minggu->modify('+' . (($mingguke - 1) * 7) . ' days');
$tanggal_awal_minggu_ini = $awal_minggu->getTimestamp();

$akhir_minggu = clone $awal_minggu;
$akhir_minggu->modify('+6 days');
$tanggal_akhir_minggu_ini = $akhir_minggu->getTimestamp() + 86399; // hingga akhir hari

// Ambil entri jurnal hanya minggu ke-N
global $DB;
$entries = $DB->get_records_select(
    'local_jurnalmengajar',
    'timecreated BETWEEN ? AND ?',
    [$tanggal_awal_minggu_ini, $tanggal_akhir_minggu_ini]
);

// Ambil beban guru bukan dari file JSON
$beban = jurnalmengajar_get_beban_jam_guru();

// Siapkan user
$all_userids = array_unique(array_map(fn($e) => $e->userid, $entries));
$all_users = [];
if (!empty($all_userids)) {
    list($in_sql, $params) = $DB->get_in_or_equal($all_userids);
    $all_users = $DB->get_records_select_menu('user', "id $in_sql", $params, 'lastname', 'id, lastname');
}

// Tampilkan header dan form filter
echo $OUTPUT->header();
// Tombol kembali
echo html_writer::div(
    html_writer::link(
        '#',
        '⬅ Kembali',
        [
            'class' => 'btn btn-secondary',
            'onclick' => 'history.back(); return false;'
        ]
    ),
    'mb-3'
);
echo html_writer::tag('h3', "Rekap Minggu ke-$mingguke (" . 
    $awal_minggu->format('d M') . " - " . 
    $akhir_minggu->format('d M Y') . ")");

echo '<form method="get">';
echo '<label for="mingguke">Pilih Minggu ke: </label>';
echo '<select name="mingguke">';
for ($i = 1; $i <= 20; $i++) {
    $selected = ($i == $mingguke) ? 'selected' : '';
    echo "<option value=\"$i\" $selected>$i</option>";
}
echo '</select> ';

echo '<label for="bulan">🔍 Filter Bulan: </label>';
echo '<select name="bulan">';
echo '<option value="0"' . ($filter_bulan == 0 ? ' selected' : '') . '>Semua</option>';
for ($i = 1; $i <= 12; $i++) {
    $selected = ($filter_bulan == $i) ? ' selected' : '';
    echo "<option value=\"$i\"$selected>" . date('F', mktime(0, 0, 0, $i, 1)) . "</option>";
}
echo '</select> ';

echo '<label for="lastname">Filter Guru: </label>';
echo '<select name="lastname" onchange="this.form.submit()">';
echo '<option value="">Semua</option>';
foreach ($all_users as $id => $ln) {
    $formatted_ln = ucwords(strtolower($ln));
    $selected = ($filter_lastname === $ln) ? 'selected' : '';
    echo "<option value=\"$ln\" $selected>$formatted_ln</option>";
}
echo '</select> ';

echo '<input type="submit" value="Tampilkan">';
echo '</form>';

// Proses rekap
$rekap = [];
$user_cache = [];

foreach ($entries as $e) {
    $userid = $e->userid;

    if (!isset($user_cache[$userid])) {
        $user_cache[$userid] = $DB->get_record('user', ['id' => $userid], 'id, lastname');
    }
    $user = $user_cache[$userid];

    if ($filter_lastname && strtolower($user->lastname) !== strtolower($filter_lastname)) continue;
    if ($filter_bulan > 0 && (int)date('n', $e->timecreated) !== $filter_bulan) continue;

    $jam = count(array_filter(explode(',', $e->jamke)));

    if (!isset($rekap[$userid])) $rekap[$userid] = 0;
    $rekap[$userid] += $jam;
}

// Urutkan berdasarkan lastname
uksort($rekap, function($a, $b) use ($user_cache) {
    return strcmp(strtolower($user_cache[$a]->lastname ?? ''), strtolower($user_cache[$b]->lastname ?? ''));
});

// === Tambahkan wrapper scroll untuk sticky header ===
echo html_writer::start_div('table-wrapper');
echo html_writer::start_tag('table', ['class' => 'generaltable']);
echo html_writer::start_tag('thead');
echo html_writer::tag('tr',
    html_writer::tag('th', 'No') .
    html_writer::tag('th', 'Nama Guru') .
    html_writer::tag('th', 'Minggu ke') .
    html_writer::tag('th', 'Jumlah Mengajar') .
    html_writer::tag('th', 'Beban Jam') .
    html_writer::tag('th', '% Mingguan') .
    html_writer::tag('th', 'Aksi')
);
echo html_writer::end_tag('thead');
echo html_writer::start_tag('tbody');

$no = 1;
foreach ($rekap as $userid => $jumlahjam) {
    $user = $user_cache[$userid];

    $beban_minggu = $beban[$userid] ?? 0;

    if ($beban_minggu > 0) {
        $persen = round(($jumlahjam / $beban_minggu) * 100);
    } else {
        $persen = 0;
    }

    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', $no++);
    echo html_writer::tag('td', $user->lastname);
    echo html_writer::tag('td', $mingguke);
    echo html_writer::tag('td', $jumlahjam);
    echo html_writer::tag('td', $beban_minggu);
    echo html_writer::tag('td', $persen . '%');

    $url = new moodle_url('/local/jurnalmengajar/rekap_perguru.php', ['userid' => $userid, 'mingguke' => $mingguke]);
    echo html_writer::tag('td', html_writer::link($url, '🔍 Lihat Detail'));
    echo html_writer::end_tag('tr');
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');
echo html_writer::end_div(); // end wrapper

echo $OUTPUT->footer();
