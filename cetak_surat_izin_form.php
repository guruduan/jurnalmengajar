<?php
// /local/jurnalmengajar/cetak_surat_izin_form.php
require_once('../../config.php');

require_login();
$context = context_system::instance();
// Atur capability sesuai kebijakan:
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_url(new moodle_url('/local/jurnalmengajar/cetak_surat_izin_form.php'));
$PAGE->set_context($context);
$PAGE->set_title('Cetak Banyak Surat Izin');
$PAGE->set_heading('Cetak Banyak Surat Izin');

$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'print' && confirm_sesskey()) {
    $raw = optional_param('ids', '', PARAM_RAW_TRIMMED);

    // Validasi simple: hanya angka, koma, strip, spasi
    if ($raw === '' || !preg_match('/^[0-9,\-\s]+$/', $raw)) {
        \core\notification::error('Masukan tidak valid. Gunakan angka, koma, atau tanda minus. Contoh: 1526-1528 atau 1526,1528');
    } else {
        // Normalisasi ringan: rapikan spasi di sekitar koma dan strip
        $norm = preg_replace('/\s*-\s*/', '-', $raw);
        $norm = preg_replace('/\s*,\s*/', ',', $norm);
        $norm = preg_replace('/\s+/', ',', trim($norm)); // spasi jadi koma

        // Redirect ke pencetak massal
        $target = new moodle_url('/local/jurnalmengajar/cetak_surat_izin_banyak.php', ['ids' => $norm]);
        redirect($target); // Akan menampilkan PDF
        exit;
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading('Cetak Surat Izin sesuai ID');

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => new moodle_url('/local/jurnalmengajar/cetak_surat_izin_form.php')
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'print']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::start_div('fitem');
echo html_writer::tag('label', 'Input ID Surat Izin Murid: ', ['for' => 'ids', 'class' => 'fitemtitle']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'ids',
    'id' => 'ids',
    'size' => 50,
    'placeholder' => 'Contoh: 1526-1528 atau 1526,1528'
]);
echo html_writer::end_div();

echo html_writer::tag('p', 'Format didukung: rentang (mis. 1526-1528) dan/atau daftar koma (mis. 1526,1528).');

echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary', 'value' => 'Cetak']);
echo html_writer::end_tag('form');

// Tombol CEK ID SURAT IZIN
echo html_writer::start_div('d-flex justify-content-between align-items-center', [
    'style' => 'margin-top: 40px; margin-bottom: 20px;'
]);
echo html_writer::tag('h3', 'Lihat ID Surat Izin yang Mau DiCetak tekan tombol -->', ['class' => 'mb-0']);

$cekidurl = new moodle_url('/local/jurnalmengajar/izin_id.php');
echo html_writer::link(
    $cekidurl,
    'Lihat ID Surat Izin Murid',
    [
        'class' => 'btn btn-primary',
        'style' => 'margin-bottom: 5px; margin-left:auto;'
    ]
);
echo html_writer::end_div();


echo $OUTPUT->footer();
