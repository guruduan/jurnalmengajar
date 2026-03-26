<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/jurnalmengajar/lib.php');
require_login();

use local_jurnalmengajar\form\pembinaan_form;

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/pembinaan.php'));
$PAGE->set_title('Laporan Pembinaan Siswa');
$PAGE->set_heading('Laporan Pembinaan Siswa oleh BK');
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
$mform = new pembinaan_form();

// ================= PROSES =================
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/jurnalmengajar/pembinaan.php'));

} else if ($data = $mform->get_data()) {

    global $DB, $USER;

    $record = new stdClass();
    $record->userid        = $USER->id;
    $record->kelas         = (int)$data->kelas; // ✅ pakai ID
    $record->peserta       = $data->peserta ?? '[]';
    $record->permasalahan  = $data->permasalahan ?: '-';
    $record->tindakan      = $data->tindakan ?: '-';
    $record->tempat        = '-';
    $record->timecreated   = time();

    $DB->insert_record('local_jurnalpembinaan', $record);

    // ================= WA =================
    $guru = $DB->get_record('user', ['id' => $record->userid], 'lastname');
    $kelasnama = get_nama_kelas($record->kelas);
    $nama = $guru ? $guru->lastname : '-';

    // ✅ FIX: pakai kelasid
    $nomorwa = get_nomor_wali_kelas($record->kelas);

    if ($nomorwa) {

        $waktu = tanggal_indo($record->timecreated);

        $peserta = json_decode($record->peserta, true);
if (is_array($peserta) && !empty($peserta)) {
    $peserta = array_map('format_nama_siswa', $peserta);
    $peserta_str = implode(', ', $peserta);
} else {
    $peserta_str = '-';
}

        $pesan = "*📋 Laporan Pembinaan Siswa*\n\n"
               . "📅 Waktu: $waktu\n"
               . "👥 Murid: $peserta_str\n"
               . "🏫 Kelas: $kelasnama\n"
               . "📌 Permasalahan: {$record->permasalahan}\n"
               . "🔧 Upaya: {$record->tindakan}\n"
               . "👤 Guru BK: $nama\n\n"
               . "_Dikirim kepada Wali kelas sebagai laporan_";

$tujuan = [$nomorwa];
jurnalmengajar_kirim_wa($tujuan, $pesan);

    } else {
        debugging("Nomor WA wali kelas tidak ditemukan untuk kelas ID: {$record->kelas}", DEBUG_DEVELOPER);
    }

    redirect(new moodle_url('/local/jurnalmengajar/pembinaan.php'), 'Data berhasil disimpan');
}

// ================= TAMPILAN =================
echo $OUTPUT->header();
echo $OUTPUT->heading('Laporan Pembinaan Siswa');

$mform->display();

// ================= EXPORT =================
$bulanlist = [
    '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
    '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
];

$tahunlist = array_combine(range(2025, 2030), range(2025, 2030));

echo html_writer::start_tag('form', [
    'method'=>'get',
    'action'=>'export_pembinaan.php',
    'style'=>'margin:10px 0;'
]);

echo html_writer::label('Pilih bulan: ', 'bulan');
echo html_writer::select($bulanlist, 'bulan', date('m'));

echo html_writer::label(' Tahun: ', 'tahun', false, ['style'=>'margin-left:10px;']);
echo html_writer::select($tahunlist, 'tahun', date('Y'));

echo html_writer::empty_tag('input', [
    'type'=>'submit',
    'value'=>'Ekspor ke Excel (XLSX)',
    'class'=>'btn btn-primary',
    'style'=>'margin-left:10px;'
]);

echo html_writer::end_tag('form');

// ================= PAGING =================
$page = optional_param('page', 0, PARAM_INT);
$perpage = 20;
$offset = $page * $perpage;

// ================= TOTAL DATA =================
$total = $DB->count_records('local_jurnalpembinaan');

// ================= TABEL =================
$records = $DB->get_records_sql("
    SELECT *
    FROM {local_jurnalpembinaan}
    ORDER BY timecreated DESC
    LIMIT $perpage OFFSET $offset
");

if ($records) {

    $table = new html_table();
    $table->head = ['No','Waktu','Nama Murid','Kelas','Permasalahan','Upaya','Guru BK'];

    $no = $offset + 1;

    foreach ($records as $r) {

        $namakelas = get_nama_kelas($r->kelas);

        $peserta = json_decode($r->peserta ?? '[]', true);

        if (is_array($peserta) && !empty($peserta)) {
            $peserta = array_map('format_nama_siswa', $peserta);
            $peserta_str = implode(', ', $peserta);
        } else {
            $peserta_str = '-';
        }

        $gurubk = $DB->get_field('user', 'lastname', ['id' => $r->userid]) ?? '-';

        $waktu = tanggal_indo($r->timecreated);

        $table->data[] = [
            $no++,
            $waktu,
            shorten_text($peserta_str, 50),
            $namakelas,
            format_string($r->permasalahan),
            format_string($r->tindakan),
            $gurubk
        ];
    }

    echo html_writer::table($table);

} else {
    echo $OUTPUT->notification('Belum ada data Laporan Pembinaan.', 'notifymessage');
}

// ================= PAGING BAR =================
$baseurl = new moodle_url('/local/jurnalmengajar/pembinaan.php');
echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);

echo $OUTPUT->footer();
