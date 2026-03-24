<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/jurnalmengajar/lib.php');
require_login();

use local_jurnalmengajar\form\layananbk_form;

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/layananbk.php'));
$PAGE->set_title('Layanan BK');
$PAGE->set_heading('Layanan BK');

$PAGE->requires->jquery();

// ================= JS =================
$PAGE->requires->js_init_code(<<<JS
function loadSiswa(kelasid) {
    if (!kelasid) return;
    $.get("/local/jurnalmengajar/get_students_bk.php", {kelas: kelasid}, function(html) {
        $("#siswa-area").html(html);
        $(".siswa-checkbox").on("change", function() {
            let hasil = [];
            $(".siswa-checkbox:checked").each(function() {
                hasil.push($(this).data("nama"));
            });
            $("input[name='peserta']").val(JSON.stringify(hasil));
        });
    });
}

$(document).ready(function() {
    const awalKelas = $("select[name='kelas']").val();
    if (awalKelas) loadSiswa(awalKelas);

    $("select[name='kelas']").on("change", function() {
        loadSiswa($(this).val());
    });
});
JS
);

// ================= FORM =================
$mform = new layananbk_form();

// ================= PROSES =================
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/jurnalmengajar/layananbk.php'));

} else if ($data = $mform->get_data()) {

    global $DB, $USER;

    $record = new stdClass();
    $record->userid        = $USER->id;
    $record->kelas         = (int)$data->kelas;
    $record->jenislayanan  = $data->jenislayanan;
    $record->topik         = $data->topik;
    $record->peserta       = $data->peserta ?? '[]';
    $record->tindaklanjut  = $data->tindaklanjut ?: '-';
    $record->catatan       = $data->catatan ?: '-';
    $record->timecreated   = time();

    $DB->insert_record('local_jurnallayananbk', $record);

   // ================= WA =================
$guru = $DB->get_record('user', ['id' => $record->userid], 'lastname');
$kelasnama = get_nama_kelas($record->kelas);
$nama = $guru ? $guru->lastname : '-';

$nomorwa = get_nomor_wali_kelas($record->kelas);

if ($nomorwa) {

    $waktu = tanggal_indo($record->timecreated);

    $peserta = json_decode($record->peserta, true);
    $peserta_str = is_array($peserta) && !empty($peserta)
        ? implode(', ', $peserta)
        : '-';

    $pesan = "*📋 Laporan Layanan BK*\n\n"
           . "📅 Hari: $waktu\n"
           . "👥 Murid: $peserta_str\n"
           . "🏫 Kelas: $kelasnama\n"
           . "📝 Jenis Layanan: {$record->jenislayanan}\n"
           . "📌 Topik: {$record->topik}\n"
           . "🔧 Tindak lanjut: {$record->tindaklanjut}\n"
           . "📑 Catatan: {$record->catatan}\n"
           . "👤 Guru BK: $nama\n\n"
           . "_Dikirim kepada Wali kelas sebagai laporan_";

    $tujuan = [$nomorwa];

    jurnalmengajar_kirim_wa($tujuan, $pesan);

} else {
    debugging("Nomor WA wali kelas tidak ditemukan untuk kelas ID: {$record->kelas}", DEBUG_DEVELOPER);
}

    redirect(new moodle_url('/local/jurnalmengajar/layananbk.php'), 'Data berhasil disimpan');
}

// ================= TAMPILAN =================
echo $OUTPUT->header();
echo $OUTPUT->heading('Jurnal Mengajar - Layanan BK');

$mform->display();

// ================= EXPORT =================
$currentmonth = date('m');
$currentyear  = date('Y');

echo html_writer::start_tag('form', [
    'method'=>'get',
    'action'=>'export_layananbk.php',
    'style'=>'margin:10px 0;'
]);

echo html_writer::label('Pilih bulan: ', 'bulan');
echo html_writer::select_time('months', 'bulan', $currentmonth);

$yearoptions = array_combine(range(2025, 2030), range(2025, 2030));

echo html_writer::label('Tahun: ', 'tahun', false, ['style'=>'margin-left:10px;']);
echo html_writer::select($yearoptions, 'tahun', $currentyear);

echo html_writer::empty_tag('input', [
    'type'=>'submit',
    'value'=>'Ekspor ke Excel (XLSX)',
    'class'=>'btn btn-primary',
    'style'=>'margin-left:10px;'
]);

echo html_writer::end_tag('form');

// ================= TABEL =================
$records = $DB->get_records('local_jurnallayananbk', null, 'timecreated DESC');

if ($records) {

    $table = new html_table();
    $table->head = ['Waktu','Kelas','Jenis Layanan','Topik','Peserta','Guru BK'];

    foreach ($records as $r) {

        $namakelas = get_nama_kelas($r->kelas);

        $peserta = json_decode($r->peserta, true);
        $peserta_str = is_array($peserta) ? implode(', ', $peserta) : '-';

        $gurubk = $DB->get_field('user','lastname',['id'=>$r->userid]) ?? '-';

        $waktu = tanggal_indo($r->timecreated);

        $table->data[] = [
            $waktu,
            $namakelas,
            $r->jenislayanan,
            $r->topik,
            shorten_text($peserta_str,50),
            $gurubk
        ];
    }

    echo html_writer::table($table);

} else {
    echo html_writer::notification('Belum ada data Jurnal Layanan BK.', 'notifymessage');
}

echo $OUTPUT->footer();
