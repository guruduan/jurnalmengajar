<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/jurnalmengajar/lib.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/pramuka.php'));
$PAGE->set_title('Jurnal Kegiatan Pramuka');
$PAGE->set_heading('Jurnal Kegiatan Pramuka');

global $DB, $USER, $OUTPUT;

$kelaslist = $DB->get_records_menu('cohort', null, 'name ASC', 'id, name');

// ================= JS =================
$PAGE->requires->jquery();
$PAGE->requires->js_init_code(<<<JS
$(document).ready(function() {

function loadSiswa(kelas) {
    if (!kelas) return;
    $.get("/local/jurnalmengajar/get_students.php", {kelas: kelas}, function(data) {
        $("#absen-area").html(data);
        bindAbsenEvent();
    });
}

function bindAbsenEvent() {
    $('.absen-checkbox').on('change', function() {
        const parent = $(this).closest('.absen-item');
        const dropdown = parent.find('.absen-alasan');
        if ($(this).is(':checked')) {
            dropdown.prop('disabled', false);
        } else {
            dropdown.prop('disabled', true).val('');
        }
        updateAbsenField();
    });

    $('.absen-alasan').on('change', updateAbsenField);
}

function updateAbsenField() {
    const hasil = {};
    $('.absen-checkbox:checked').each(function() {
        const nama = $(this).data('nama');
        const alasan = $(this).closest('.absen-item').find('.absen-alasan').val();
        if (alasan) hasil[nama] = alasan;
    });
    $('textarea[name="absen"]').val(JSON.stringify(hasil));
}

$('select[name=kelas]').on('change', function() {
    loadSiswa($(this).val());
});

loadSiswa($('select[name=kelas]').val());

});
JS
);

// ================= PROSES =================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_sesskey();

    $kelasid = required_param('kelas', PARAM_INT);
    $materi  = required_param('materi', PARAM_TEXT);
    $catatan = optional_param('catatan', '', PARAM_TEXT);
    $absen   = optional_param('absen', '{}', PARAM_RAW);

    $record = new stdClass();
    $record->userid      = $USER->id;
    $record->kelas       = $kelasid; // ✅ FIX pakai ID
    $record->materi      = $materi;
    $record->absen       = $absen;
    $record->catatan     = $catatan ?: '-';
    $record->timecreated = time();

    $DB->insert_record('local_jurnalpramuka', $record);

    // ================= WA =================
$kelasnama = get_nama_kelas($kelasid);
$nomorwa   = get_nomor_wali_kelas($kelasid);

if ($nomorwa) {

    $waktu = tanggal_indo($record->timecreated);

    $absen_data = json_decode($record->absen, true);
    $daftarabsen = '-';

    if (!empty($absen_data)) {
        $list = [];
        foreach ($absen_data as $nama => $alasan) {
            $nama = format_nama_siswa($nama);
            $list[] = "$nama ($alasan)";
        }
        $daftarabsen = implode(", ", $list);
    }

    $namaguru = format_nama_siswa($USER->lastname);

    $pesan = "*🏕 Kegiatan Pramuka*\n\n"
           . "📅 Waktu: $waktu\n"
           . "🏫 Kelas: $kelasnama\n"
           . "👤 Guru: $namaguru\n"
           . "📖 Materi: {$record->materi}\n"
           . "📌 Catatan: {$record->catatan}\n"
           . "🙍‍♂️ Tidak hadir: $daftarabsen\n\n"
           . "_Dikirim kepada Wali kelas sebagai laporan_";

    $tujuan = [$nomorwa];
    jurnalmengajar_kirim_wa($tujuan, $pesan);
}

    redirect(new moodle_url('/local/jurnalmengajar/pramuka.php'), 'Jurnal pramuka berhasil disimpan');
}

// ================= TAMPILAN =================
echo $OUTPUT->header();
echo $OUTPUT->heading('Input Jurnal Pramuka');
?>

<form method="post">
    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

    <label>Kelas:</label><br>
    <select name="kelas">
        <?php foreach ($kelaslist as $id => $name): ?>
            <option value="<?= $id ?>"><?= format_string($name) ?></option>
        <?php endforeach; ?>
    </select><br><br>

    <label>Materi / Kegiatan:</label><br>
    <textarea name="materi" rows="3" cols="50" required></textarea><br><br>

    <div id="absen-area"></div>
    <textarea name="absen" style="display:none;"></textarea>

    <label>Catatan:</label><br>
    <textarea name="catatan" rows="2" cols="50"></textarea><br><br>

    <button type="submit">Simpan</button>
</form>

<hr>

<?php
$page = optional_param('page', 0, PARAM_INT);
$perpage = 20;
$offset = $page * $perpage;

$total = $DB->count_records('local_jurnalpramuka');
// ================= RIWAYAT =================
$riwayat = $DB->get_records_sql("
    SELECT *
    FROM {local_jurnalpramuka}
    ORDER BY timecreated DESC
    LIMIT $perpage OFFSET $offset
");

if ($riwayat) {

    echo html_writer::tag('h3', 'Data Kegiatan Pramuka');

    $table = new html_table();
    $table->head = ['No','Waktu','Guru','Kelas','Materi','Catatan','Tidak hadir'];

$no = $offset + 1;

    foreach ($riwayat as $r) {

    $guru = $DB->get_field('user', 'lastname', ['id' => $r->userid]) ?? '-';
    $guru = format_nama_siswa($guru);

    if (is_numeric($r->kelas)) {
        $kelas = get_nama_kelas($r->kelas);
    } else {
        $kelas = $r->kelas ?: '-';
    }

    $absen_data = json_decode($r->absen, true);
    $daftarabsen = '-';

    if (!empty($absen_data)) {
        $list = [];
        foreach ($absen_data as $nama => $alasan) {
            $nama = format_nama_siswa($nama);
            $list[] = "$nama ($alasan)";
        }
        $daftarabsen = implode(", ", $list);
    }

    $table->data[] = [
        $no++,
        tanggal_indo($r->timecreated),
        $guru,
        $kelas,
        format_string($r->materi),
        format_string($r->catatan),
        $daftarabsen
    ];
}

    echo html_writer::table($table);
}

$baseurl = new moodle_url('/local/jurnalmengajar/pramuka.php', [
    'page' => $page
]);
echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);
echo $OUTPUT->footer();
