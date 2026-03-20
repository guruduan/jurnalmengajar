<?php
require_once(__DIR__ . '/../../config.php');
require_login();
    
if (\core_useragent::is_moodle_app()) {
    redirect(new moodle_url('/local/jurnalmengajar/mobile.php'));
}

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

// ================= PROSES =================
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

    // 🔥 PENTING: pakai kelasid
    $record->kelas = (int)$data->kelas;

    $record->jamke = $data->jamke;
    $record->matapelajaran = $data->matapelajaran;
    $record->materi = $data->materi;
    $record->aktivitas = $data->aktivitas;
    $record->absen = $data->absen ?? '{}';
    $record->keterangan = $data->keterangan ?: '-';
    $record->timecreated = time();

    $DB->insert_record('local_jurnalmengajar', $record);

    // Kirim WA
    jurnalmengajar_notifikasi_wa($record, $USER);

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

// ================= RIWAYAT =================
echo html_writer::tag('h3', 'Riwayat Jurnal Saya');

$sql = "SELECT *
          FROM {local_jurnalmengajar}
         WHERE userid = :userid
      ORDER BY id DESC
         LIMIT 15";

$params = ['userid' => $USER->id];
$entries = $DB->get_records_sql($sql, $params);

if ($entries) {

    echo html_writer::start_tag('table', ['class' => 'generaltable']);
    echo html_writer::start_tag('thead');
    echo html_writer::tag('tr',
        html_writer::tag('th', 'Nomor') .
        html_writer::tag('th', 'Kelas') .
        html_writer::tag('th', 'Jam Ke') .
        html_writer::tag('th', 'Mata Pelajaran') .
        html_writer::tag('th', 'Materi') .
        html_writer::tag('th', 'Absen') .
        html_writer::tag('th', 'Waktu') .
        html_writer::tag('th', 'Aksi')
    );
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    $no = 1;

    foreach ($entries as $e) {

        $absendata = json_decode($e->absen, true);
        $absentext = '';

        if (is_array($absendata)) {
            foreach ($absendata as $nama => $alasan) {
                $absentext .= "$nama ($alasan), ";
            }
            $absentext = rtrim($absentext, ', ');
        } else {
            $absentext = $e->absen;
        }

        $namakelas = get_nama_kelas($e->kelas);

        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', $no++);
        echo html_writer::tag('td', $namakelas);
        echo html_writer::tag('td', $e->jamke);
        echo html_writer::tag('td', $e->matapelajaran);
        echo html_writer::tag('td', shorten_text($e->materi, 30), ['title' => $e->materi]);
        echo html_writer::tag('td', shorten_text($absentext, 25), ['title' => $absentext]);
        echo html_writer::tag('td', format_waktu_indo($e->timecreated));

        $editurl = new moodle_url('/local/jurnalmengajar/edit.php', ['id' => $e->id]);

        echo html_writer::tag('td', html_writer::link($editurl, 'Edit'));
        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');

    // Tombol export
    echo html_writer::start_tag('form', [
        'method' => 'get',
        'action' => new moodle_url('/local/jurnalmengajar/export_form.php'),
        'class' => 'd-inline'
    ]);

    echo html_writer::tag('button', '<strong>🌏 Ekspor Jurnal per Bulan</strong>', [
        'type' => 'submit',
        'class' => 'btn btn-outline-secondary'
    ]);

    echo html_writer::end_tag('form');

} else {
    echo html_writer::tag('p', 'Belum ada entri jurnal.', ['class' => 'alert alert-info']);
}

echo $OUTPUT->footer();
