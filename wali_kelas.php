<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
$PAGE->set_context($context);
require_capability('moodle/site:config', $context);

global $DB;

// ===== Ambil mapping lama
$json = get_config('local_jurnalmengajar', 'wali_kelas_mapping');
$mapping = json_decode($json, true);
if (!is_array($mapping)) {
    $mapping = [];
}

// ===== AUTO CLEAN (hapus mapping yang cohort sudah tidak ada)
foreach ($mapping as $kelasid => $userid) {
    if (!$DB->record_exists('cohort', ['id' => $kelasid])) {
        unset($mapping[$kelasid]);
    }
}

// Simpan ulang jika ada perubahan
set_config('wali_kelas_mapping', json_encode($mapping), 'local_jurnalmengajar');

// ===== PROSES POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $kelasid = required_param('kelas', PARAM_INT);
    $userid  = required_param('userid', PARAM_INT);

    if (!$kelasid || !$userid) {
        redirect(new moodle_url('/local/jurnalmengajar/wali_kelas.php'), 'Data tidak valid', 2);
    }

    $mapping[$kelasid] = $userid;

    set_config('wali_kelas_mapping', json_encode($mapping), 'local_jurnalmengajar');

    redirect(new moodle_url('/local/jurnalmengajar/wali_kelas.php'), '✅ Mapping berhasil disimpan', 2);
}

// ===== SET PAGE
$PAGE->set_url('/local/jurnalmengajar/wali_kelas.php');
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Mapping Wali Kelas');
$PAGE->set_heading('Mapping Wali Kelas');

echo $OUTPUT->header();

// ===== Ambil cohort
$cohorts = $DB->get_records('cohort', null, 'name ASC');
$kelas_options = [];
foreach ($cohorts as $c) {
    $kelas_options[$c->id] = $c->name;
}

// ===== Ambil user role gurujurnal
$role = $DB->get_record('role', ['shortname' => 'gurujurnal']);
$users = get_role_users($role->id, $context);

$user_options = [];
foreach ($users as $u) {
    $nama = !empty($u->lastname) ? $u->lastname : $u->firstname;
    $user_options[$u->id] = $nama;
}

// ===== FORM INPUT
echo html_writer::start_tag('form', ['method' => 'post']);
echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'sesskey',
    'value' => sesskey()
]);

echo html_writer::tag('h3', 'Tambah / Update Mapping');

// Dropdown kelas
echo html_writer::label('Kelas', 'kelas');
echo html_writer::select($kelas_options, 'kelas', '', ['' => 'Pilih kelas']);

// Dropdown wali kelas
echo html_writer::empty_tag('br');
echo html_writer::label('Wali Kelas', 'userid');
echo html_writer::select($user_options, 'userid', '', ['' => 'Pilih wali kelas']);

echo html_writer::empty_tag('br');
echo html_writer::empty_tag('br');
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => '💾 Simpan Mapping',
    'class' => 'btn btn-primary'
]);

echo html_writer::end_tag('form');

// ===== TABEL MAPPING
echo html_writer::tag('h3', 'Data Mapping Wali Kelas');

$table = new html_table();
$table->head = ['No', 'Kelas', 'Wali Kelas', 'Status'];

// SORT mapping
ksort($mapping);

$no = 1;
foreach ($mapping as $kelasid => $userid) {

    $kelas = $DB->get_field('cohort', 'name', ['id' => $kelasid]);
    $status = '✅ Aktif';

    if (!$kelas) {
        $kelas = "❌ ID $kelasid (kelas sudah dihapus)";
        $status = '⚠️ Tidak valid';
    }

    $user = $DB->get_record('user', ['id' => $userid]);

    $nama = '-';
    if ($user) {
        $nama = !empty($user->lastname) ? $user->lastname : $user->firstname;
    } else {
        $nama = "❌ User tidak ditemukan";
        $status = '⚠️ Tidak valid';
    }

    $table->data[] = [
        $no++,
        $kelas,
        $nama,
        $status
    ];
}

echo html_writer::table($table);

echo $OUTPUT->footer();
