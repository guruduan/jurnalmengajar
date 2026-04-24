<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__.'/lib.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/rekap_perminggu.php'));

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
$PAGE->set_title("Rekap Mingguan Jurnal Mengajar Guru minggu ke-$mingguke");
$PAGE->set_heading("Rekap Mingguan Jurnal Mengajar Guru minggu ke-$mingguke");
// Filter tambahan
$filter_userid = optional_param('userid', 0, PARAM_INT);

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
$beban = jurnalmengajar_get_beban_jam_guru_by_date($tanggal_awal_minggu_ini);

// Siapkan user
$all_userids = array_keys($beban);
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
echo '<select name="mingguke" onchange="this.form.submit()">';
for ($i = 1; $i <= 20; $i++) {
    $selected = ($i == $mingguke) ? 'selected' : '';
    echo "<option value=\"$i\" $selected>$i</option>";
}
echo '</select> ';

echo '<label for="userid">Filter Guru: </label>';
echo '<select name="userid" onchange="this.form.submit()">';
echo '<option value="0">Semua</option>';
foreach ($all_users as $id => $ln) {
    $formatted_ln = ucwords(strtolower($ln));
    $selected = ($filter_userid == $id) ? 'selected' : '';
    echo "<option value=\"$id\" $selected>$formatted_ln</option>";
}
echo '</select> ';

echo '<input type="submit" value="Tampilkan">';
echo '</form>';

// Proses rekap
$rekap = [];

// ambil semua guru dari beban
foreach ($beban as $userid => $beban_minggu) {

    // filter user jika dipilih
    if ($filter_userid && $userid != $filter_userid) continue;

    $rekap[$userid] = 0;
}

// isi jumlah jam dari jurnal
foreach ($entries as $e) {
    $userid = $e->userid;

    // hanya hitung kalau dia ada di beban
    if (!isset($rekap[$userid])) continue;

    $jam = !empty($e->jamke)
        ? count(array_filter(explode(',', $e->jamke)))
        : 0;

    $rekap[$userid] += $jam;
}

// Urutkan berdasarkan nama guru
uksort($rekap, function($a, $b) use ($all_users) {
    return strcmp(
        strtolower($all_users[$a] ?? ''),
        strtolower($all_users[$b] ?? '')
    );
});

// Cutoff tambahkan di atas tabel
$cutoff = jurnalmengajar_get_cutoff_xii($tanggal_awal_minggu_ini);

if ($cutoff) {

    if ($tanggal_akhir_minggu_ini >= $cutoff) {
        echo html_writer::div(
            'Kelas XII tidak ada KBM sejak: ' . tanggal_indo($cutoff, 'tanggal') . ' (beban jam mengajar sudah disesuaikan)',
            'alert alert-info'
        );
    }

} else {
    echo html_writer::div(
        '⚠️ Cutoff kelas XII belum disetting',
        'alert alert-warning'
    );
}
// Tabel
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
$sekarang = time();

if ($tanggal_awal_minggu_ini > $sekarang) {
    $pesan = 'Minggu ini belum dimulai';
} elseif ($tanggal_akhir_minggu_ini > $sekarang) {
    $pesan = 'Minggu ini sedang berjalan, data belum diisi';
} else {
    $pesan = 'Tidak ada data minggu ini';
}

echo html_writer::start_tag('tbody');

$no = 1;

if (empty($rekap)) {
    echo html_writer::start_tag('tr');
    echo html_writer::tag(
        'td',
        $pesan,
        ['colspan' => 7, 'style' => 'text-align:center; font-style:italic;']
    );
    echo html_writer::end_tag('tr');
} else {
    foreach ($rekap as $userid => $jumlahjam) {

    $lastname = $all_users[$userid] ?? '(tidak ditemukan)';
    $nama = ucwords($lastname);

    $beban_minggu = $beban[$userid] ?? 0;

    $persen = ($beban_minggu > 0)
        ? round(($jumlahjam / $beban_minggu) * 100)
        : 0;

    // WARNA
    $style = '';
    if ($persen >= 80) {
        $style = 'color:green;font-weight:bold';
    } elseif ($persen < 50) {
        $style = 'color:red;font-weight:bold';
    }

// WARNA BARIS
$tr_style = '';
if ($persen < 50) {
    $tr_style = 'background-color:#ffe5e5';
}

echo html_writer::start_tag('tr', ['style' => $tr_style]);

    echo html_writer::tag('td', $no++);
    $urlguru = new moodle_url('/local/jurnalmengajar/rekap_guru_semester.php', [
    'userid' => $userid
]);

echo html_writer::tag('td', html_writer::link($urlguru, $nama));
    echo html_writer::tag('td', $mingguke);
    echo html_writer::tag('td', $jumlahjam);
    echo html_writer::tag('td', $beban_minggu);
    echo html_writer::tag('td', $persen . '%', ['style' => $style]);

    $url = new moodle_url('/local/jurnalmengajar/rekap_perguru.php', [
        'userid' => $userid,
        'mingguke' => $mingguke
    ]);

    echo html_writer::tag('td', html_writer::link($url, '🔍 Lihat Detail'));
    echo html_writer::end_tag('tr');
}
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');
echo html_writer::end_div();

echo $OUTPUT->footer();
