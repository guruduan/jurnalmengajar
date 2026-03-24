<?php
require_once(__DIR__ . '/../../config.php');
require_login();

use local_jurnalmengajar\form\jurnal_form;

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/index.php'));
$PAGE->set_title('Isi Jurnal Mengajar');
$PAGE->set_heading('Jurnal Mengajar');

require_once(__DIR__ . '/lib.php');

// ================= JS =================
$PAGE->requires->jquery();
$PAGE->requires->js_init_code(<<<JS
$(document).ready(function() {

    $('input[name="jamke"]').on('input', function () {
        const val = $(this).val();
        const valid = /^(\d+(,\d+)*)?$/.test(val);
        if (!valid) {
            this.setCustomValidity("Isian hanya boleh angka dan koma, misalnya: 2,3");
        } else {
            this.setCustomValidity("");
        }
    });

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
            if (alasan) {
                hasil[nama] = alasan;
            }
        });
        $('textarea[name="absen"]').val(JSON.stringify(hasil));
    }

    $('select[name=kelas]').on('change', function() {
        const kelas = $(this).val();
        loadSiswa(kelas);
    });

    loadSiswa($('select[name=kelas]').val());

});
JS);

// ================= FORM =================
$mform = new jurnal_form();

// ================= PROSES SIMPAN =================
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/my'));

} else if ($data = $mform->get_data()) {

    // Validasi jam ke
    if (!preg_match('/^\d+(,\d+)*$/', $data->jamke)) {
        print_error('Isian "Jam Pelajaran Ke" hanya boleh angka dan koma, contoh: 2,3');
    }

    // Validasi kelas
    if (empty($data->kelas)) {
        print_error('Kelas tidak boleh kosong');
    }

    $record = new stdClass();
    $record->userid = $USER->id;

    // Nomor otomatis
    $last = $DB->get_record_sql("
        SELECT MAX(nomor) AS maxnomor
        FROM {local_jurnalmengajar}
        WHERE userid = ?
    ", [$USER->id]);

    $record->nomor = ($last && $last->maxnomor) ? $last->maxnomor + 1 : 1;

    $record->kelas = (int)$data->kelas;
    $record->jamke = $data->jamke;
    $record->matapelajaran = $data->matapelajaran;
    $record->materi = $data->materi;
    $record->aktivitas = $data->aktivitas;
    $record->absen = $data->absen ?? '{}';
    $record->keterangan = $data->keterangan ?: '-';
    $record->timecreated = time();

    // Simpan ke database
    $DB->insert_record('local_jurnalmengajar', $record);

    // ================= KIRIM NOTIF WA =================
$kelasid = $record->kelas ?? null;

if ($kelasid) {

    $namaguru = !empty($USER->lastname) ? $USER->lastname : $USER->firstname;
    $kelas = get_nama_kelas($kelasid);

    $jamke = $record->jamke ?? '-';
    $mapel = $record->matapelajaran ?? '-';
    $materi = $record->materi ?? '-';
    $aktivitas = $record->aktivitas ?? '-';

    $absenjson = $record->absen ?? '{}';
    $absenarr = json_decode($absenjson, true);

    $absen = '-';
    if (!empty($absenarr)) {
        $formatted = [];
        $no = 1;
        foreach ($absenarr as $nama => $alasan) {
            $formatted[] = $no++ . ". {$nama}: {$alasan}";
        }
        $absen = implode("\n", $formatted);
    }

    $keterangan = $record->keterangan ?? '-';

    $sekolah = get_config('local_jurnalmengajar', 'nama_sekolah') ?: 'Nama Sekolah';

    $tanggal = tanggal_indo(time(), 'judul');
    $jam = tanggal_indo(time(), 'jam');

    $pesan = "*📘 Jurnal KBM _{$tanggal}_*\n\n"
       . "👤 Guru: $namaguru\n"
       . "🏫 Kelas: $kelas\n"
       . "⏰ Jam ke: $jamke\n"
       . "📚 Mata Pelajaran: $mapel\n"
       . "📒 Materi: $materi\n"
       . "📝 Aktivitas:\n$aktivitas\n\n"
       . "🔴 Murid tidak hadir:\n$absen\n\n"
       . "Keterangan tambahan:\n$keterangan\n\n"
       . "🕒 Waktu: $jam WITA\n"
       . "📌 Tercatat di eJurnal KBM $sekolah\n\n"
       . "_Dikirim ke Wali kelas dan Guru ybs sebagai laporan_";

    // Tujuan
    $tujuan = [
        get_user_nowa($USER->id),
        get_nomor_wali_kelas($kelasid)
    ];

    // Kirim WA
    jurnalmengajar_kirim_wa($tujuan, $pesan);
}

    redirect(
        new moodle_url('/local/jurnalmengajar/index.php'),
        '✅ Jurnal berhasil disimpan.',
        2
    );
}

$PAGE->requires->js_call_amd('local_jurnalmengajar/absen', 'init');

// ================= TAMPILAN =================
echo $OUTPUT->header();
echo $OUTPUT->heading('Input Jurnal Mengajar');

$mform->display();

// Tombol bawah
echo html_writer::start_tag('div', [
    'style' => 'display:flex; justify-content:flex-end; gap:10px; margin-top:10px;'
]);

echo html_writer::link(
    '#',
    '⬅ Kembali',
    [
        'class' => 'btn btn-secondary',
        'onclick' => 'history.back(); return false;'
    ]
);

echo html_writer::link(
    new moodle_url('/local/jurnalmengajar/riwayat_jurnal.php'),
    '📚 Riwayat Jurnal',
    ['class' => 'btn btn-primary']
);

echo html_writer::end_tag('div');

echo $OUTPUT->footer();
