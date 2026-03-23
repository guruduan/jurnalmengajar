<?php
require('../../config.php');
require_once(__DIR__.'/jam_pelajaran_lib.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);

$PAGE->set_url('/local/jurnalmengajar/jam_pelajaran_view.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Jam Pelajaran');
$PAGE->set_heading('Jam Pelajaran');

$jam = jurnalmengajar_generate_jam();

echo $OUTPUT->header();

echo "<h3>Jam Pelajaran</h3>";
echo "<table class='generaltable'>";
echo "<tr><th>Jam</th><th>Mulai</th><th>Selesai</th></tr>";

foreach ($jam as $j => $w) {
    echo "<tr>";
    echo "<td>$j</td>";
    echo "<td>{$w['mulai']}</td>";
    echo "<td>{$w['selesai']}</td>";
    echo "</tr>";

    if (!empty($w['istirahat_setelah'])) {
        echo "<tr style='background:#fff3cd; text-align:center; font-weight:bold;'>";
        echo "<td colspan='3'>ISTIRAHAT {$w['istirahat_setelah']} MENIT</td>";
        echo "</tr>";
    }
}

echo "</table>";
echo html_writer::link(
    '#',
    '⬅ Kembali',
    [
        'class' => 'btn btn-secondary',
        'onclick' => 'history.back(); return false;',
        'title' => 'Kembali ke halaman sebelumnya'
    ]
);
echo $OUTPUT->footer();
