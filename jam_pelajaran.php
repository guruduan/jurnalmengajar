<?php
require('../../config.php');
require_once(__DIR__.'/jam_pelajaran_lib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$context = context_system::instance();
$PAGE->set_context($context);

$PAGE->set_url('/local/jurnalmengajar/jam_pelajaran.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Jam Pelajaran');
$PAGE->set_heading('Jam Pelajaran (JP)');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'jumlah_jam' => $_POST['jumlah_jam'],
        'durasi_jam' => $_POST['durasi_jam'],
        'jam_mulai' => $_POST['jam_mulai'],
        'ist1_setelah' => $_POST['ist1_setelah'],
        'ist1_durasi' => $_POST['ist1_durasi'],
        'ist2_setelah' => $_POST['ist2_setelah'],
        'ist2_durasi' => $_POST['ist2_durasi']
    ];

    jurnalmengajar_save_config_jam($data);
}

$config = jurnalmengajar_get_config_jam();
$jam = jurnalmengajar_generate_jam();

echo $OUTPUT->header();

echo "<form method='post'>";
echo "<table class='generaltable'>";

echo "<tr><td>Jumlah JP </td><td><input type='number' name='jumlah_jam' value='".($config['jumlah_jam'] ?? '')."'></td></tr>";
echo "<tr><td>Durasi 1 JP dalam menit</td><td><input type='number' name='durasi_jam' value='".($config['durasi_jam'] ?? '')."'></td></tr>";
echo "<tr><td>JP pertama mulai pukul</td><td><input type='time' name='jam_mulai' value='".($config['jam_mulai'] ?? '')."'></td></tr>";

echo "<tr><td>Istirahat pertama setelah JP ke</td><td><input type='number' name='ist1_setelah' value='".($config['ist1_setelah'] ?? '')."'></td></tr>";
echo "<tr><td>Durasi istirahat pertama dalam menit</td><td><input type='number' name='ist1_durasi' value='".($config['ist1_durasi'] ?? '')."'></td></tr>";

echo "<tr><td>Istirahat kedua setelah JP ke</td><td><input type='number' name='ist2_setelah' value='".($config['ist2_setelah'] ?? '')."'></td></tr>";
echo "<tr><td>Durasi istirahat kedua dalam menit</td><td><input type='number' name='ist2_durasi' value='".($config['ist2_durasi'] ?? '')."'></td></tr>";

echo "</table>";
echo "<br><input type='submit' value='Simpan & Generate' class='btn btn-primary'>";
echo "</form>";

if (!empty($jam)) {
    echo "<h3>Hasil Jam Pelajaran</h3>";
    echo "<table class='generaltable'>";
    echo "<tr><th>Jam</th><th>Mulai</th><th>Selesai</th></tr>";

    foreach ($jam as $j => $w) {
    echo "<tr>";
    echo "<td>$j</td>";
    echo "<td>{$w['mulai']}</td>";
    echo "<td>{$w['selesai']}</td>";
    echo "</tr>";

    if (!empty($w['istirahat_setelah'])) {
        echo "<tr style='background:#ffeeba; font-weight:bold;'>";
        echo "<td colspan='3'>Istirahat {$w['istirahat_setelah']} menit</td>";
        echo "</tr>";
    }
}

    echo "</table>";
}

echo $OUTPUT->footer();
