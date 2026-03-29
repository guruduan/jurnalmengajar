<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/export_jurnal_ekstra.php'));
$PAGE->set_title('Ekspor Jurnal Ekstrakurikuler');
$PAGE->set_heading('Ekspor Jurnal Ekstrakurikuler');

echo $OUTPUT->header();
echo $OUTPUT->heading('Pilih Bulan untuk Ekspor Jurnal Ekstrakurikuler');

$bulan = [
    '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April',
    '05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus',
    '09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
];

$tahun_ini = date('Y');
$bulan_sekarang = date('m');

echo '<form method="get" action="export_jurnal_ekstra_xlsx.php">';

// BULAN
echo 'Bulan: <select name="bulan">';
foreach ($bulan as $num => $nama) {
    $selected = ($num == $bulan_sekarang) ? 'selected' : '';
    echo "<option value=\"$num\" $selected>$nama</option>";
}
echo '</select> ';

// TAHUN
echo 'Tahun: <select name="tahun">';
for ($t = $tahun_ini - 2; $t <= $tahun_ini + 3; $t++) {
    $selected = ($t == $tahun_ini) ? 'selected' : '';
    echo "<option value=\"$t\" $selected>$t</option>";
}
echo '</select> ';

echo '<button type="submit" class="btn btn-primary">Ekspor Excel</button>';
echo '</form>';

echo $OUTPUT->footer();
