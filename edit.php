<?php
require_once(__DIR__ . '/../../config.php');
require_login();

use local_jurnalmengajar\form\jurnal_form;

$id = required_param('id', PARAM_INT); // ✅ Ambil ID dari URL
$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/edit.php', ['id' => $id]));
$PAGE->set_title('Edit Jurnal Mengajar');
$PAGE->set_heading('Edit Jurnal Mengajar');

global $DB, $USER;

// ✅ Ambil data entri jurnal sesuai ID & user login
$record = $DB->get_record('local_jurnalmengajar', [
    'id' => $id,
    'userid' => $USER->id
], '*', MUST_EXIST);

// ✅ Hindari null pada field aktivitas
$record->aktivitas = $record->aktivitas ?? '';

// ✅ Tambahkan ID ke record agar masuk ke dalam form sebagai hidden input
$record->id = $id;

// ✅ Kirim data awal ke form
$mform = new jurnal_form(null, null); // Tidak perlu customdata jika tidak dipakai

if ($mform->is_cancelled()) {
    // ✅ Redirect jika batal
    redirect(new moodle_url('/local/jurnalmengajar/index.php'));

} else if ($data = $mform->get_data()) {
    // ✅ Update data jika disubmit
    $record->kelas = $data->kelas;
    $record->jamke = $data->jamke;
    $record->matapelajaran = $data->matapelajaran;
    $record->materi = $data->materi;
    $record->aktivitas = $data->aktivitas;
    $record->absen = $data->absen;
    $record->keterangan = $data->keterangan;

    $DB->update_record('local_jurnalmengajar', $record);

    // ✅ Tampilkan pesan sukses
    redirect(new moodle_url('/local/jurnalmengajar/index.php'), 'Jurnal berhasil diperbarui.', 2);
}

// ✅ Tampilkan form dengan data lama (termasuk id)
$mform->set_data($record);

echo $OUTPUT->header();
echo $OUTPUT->heading('Edit Jurnal Mengajar');
$mform->display();
echo $OUTPUT->footer();
