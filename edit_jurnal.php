<?php
require_once(__DIR__ . '/../../config.php');
require_login();

use local_jurnalmengajar\form\jurnal_form;

$id = required_param('id', PARAM_INT);

$context = context_system::instance();

// 🔐 hanya admin
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/edit_jurnall.php', ['id' => $id]));
$PAGE->set_title('Edit Jurnal (Admin)');
$PAGE->set_heading('Edit Jurnal (Admin)');

// 🔥 WAJIB: load jQuery
$PAGE->requires->jquery();

// 🔥 JS utama (mirror index.php + preload JSON)
$PAGE->requires->js_init_code(<<<JS
$(document).ready(function() {

function loadSiswa(kelas, absenData = {}) {
    if (!kelas || isNaN(kelas)) {
        console.warn("Kelas tidak valid:", kelas);
        return;
    }

    $.get("/local/jurnalmengajar/get_students.php", {kelas: kelas}, function(data) {
        $("#absen-area").html(data);

        // 🔥 PRELOAD DATA LAMA
        $('.absen-checkbox').each(function() {
            const nama = $(this).data('nama');

            if (absenData[nama]) {
                $(this).prop('checked', true);

                const parent = $(this).closest('.absen-item');
                const dropdown = parent.find('.absen-alasan');

                dropdown.prop('disabled', false);
                dropdown.val(absenData[nama]);
            }
        });

        bindAbsenEvent();
        updateAbsenField();
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

    $('#id_absen').val(JSON.stringify(hasil));
}

// 🔥 load pertama (setelah form benar-benar siap)
$(window).on('load', function() {

    let absenData = {};

    try {
        absenData = JSON.parse($('#id_absen').val() || '{}');
    } catch(e) {
        absenData = {};
    }

    const kelas = $('select[name=kelas]').val();

    console.log("kelas loaded:", kelas);
    console.log("absenData:", absenData);

    loadSiswa(kelas, absenData);
});

// 🔁 saat ganti kelas
$('select[name=kelas]').on('change', function() {
    loadSiswa($(this).val(), {});
});

});
JS
);

global $DB;

// ✅ Ambil data jurnal (tanpa filter userid)
$record = $DB->get_record('local_jurnalmengajar', [
    'id' => $id
], '*', MUST_EXIST);

// Hindari null
$record->aktivitas = $record->aktivitas ?? '';
$record->id = $id;

// ✅ Form
$mform = new jurnal_form(null, null);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/jurnalmengajar/all.php'));

} else if ($data = $mform->get_data()) {

    // 🔥 VALIDASI jamke
    if (!preg_match('/^\\d+(,\\d+)*$/', $data->jamke)) {
        print_error('Isian "Jam Pelajaran Ke" hanya boleh angka dan koma, contoh: 2,3');
    }

    $record->kelas = $data->kelas;
    $record->jamke = $data->jamke;
    $record->matapelajaran = $data->matapelajaran;
    $record->materi = $data->materi;
    $record->aktivitas = $data->aktivitas;
    $record->absen = $data->absen; // JSON dari JS
    $record->keterangan = $data->keterangan;

    $DB->update_record('local_jurnalmengajar', $record);

    redirect(new moodle_url('/local/jurnalmengajar/all_jurnall.php'), 'Jurnal berhasil diperbarui.', 2);
}

// 🔥 isi data lama ke form
$mform->set_data($record);

echo $OUTPUT->header();
echo $OUTPUT->heading('Edit Jurnal (Admin)');
$mform->display();
echo $OUTPUT->footer();
