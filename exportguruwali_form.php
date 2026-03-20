
<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/exportguruwali_form.php'));
$PAGE->set_title('Ekspor Jurnal Guru Wali per Bulan');
$PAGE->set_heading('Ekspor Jurnal Guru Wali per Bulan');

echo $OUTPUT->header();
echo $OUTPUT->heading('Pilih bulan dan tahun yang mau diunduh');

$bulan = [
    '01' => 'Januari',
    '02' => 'Februari',
    '03' => 'Maret',
    '04' => 'April',
    '05' => 'Mei',
    '06' => 'Juni',
    '07' => 'Juli',
    '08' => 'Agustus',
    '09' => 'September',
    '10' => 'Oktober',
    '11' => 'November',
    '12' => 'Desember',
];

// Ambil tahun sekarang
$tahun_ini = date('Y');

echo $OUTPUT->box_start();

// export XLSX
echo '<form method="get" action="exportguruwali_xlsx.php" id="exportform">';

//BULAN
echo '<label for="bulan">Pilih Bulan: </label>';
echo '<select name="bulan" id="bulan">';

$bulan_sekarang = date('m');

foreach ($bulan as $num => $nama) {
    $selected = ($num == $bulan_sekarang) ? 'selected' : '';
    echo "<option value=\"" . s($num) . "\" $selected>" . s($nama) . "</option>";
}

echo '</select> ';

// TAHUN
echo '<label for="tahun">Tahun: </label>';
echo '<select name="tahun" id="tahun">';
for ($t = $tahun_ini - 1; $t <= $tahun_ini + 3; $t++) {
    $selected = ($t == $tahun_ini) ? 'selected' : '';
    echo "<option value=\"" . s($t) . "\" $selected>$t</option>";
}
echo '</select> ';

// Tombol ekspor
echo '<button type="submit" class="btn btn-primary">Ekspor ke file (Excel)</button>';
echo '</form>';


echo $OUTPUT->box_end();

echo $OUTPUT->footer();
