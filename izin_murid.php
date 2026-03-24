<?php
require_once('../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_url(new moodle_url('/local/jurnalmengajar/izin_murid.php'));
$PAGE->set_context($context);
$PAGE->set_title('Surat Izin Keluar/Masuk');
$PAGE->set_heading('Surat Izin Keluar/Masuk');

global $DB, $USER, $CFG;
$pengawas = $USER->lastname;

require_once($CFG->dirroot . '/local/jurnalmengajar/lib.php');

// ================= DATA AWAL =================
$cohorts = $DB->get_records_menu('cohort', null, 'name ASC', 'id, name');

// Ambil guru (role gurujurnal)
$guruoptions = [];
$roleid = $DB->get_field('role', 'id', ['shortname' => 'gurujurnal']);

if ($roleid) {
    $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname
              FROM {user} u
              JOIN {role_assignments} ra ON ra.userid = u.id
             WHERE ra.roleid = :roleid
          ORDER BY u.lastname ASC";

    $gurus = $DB->get_records_sql($sql, ['roleid' => $roleid]);

    foreach ($gurus as $g) {
        $guruoptions[$g->id] = !empty($g->lastname) ? $g->lastname : $g->firstname;
    }
}

$kelasid = optional_param('kelasid', 0, PARAM_INT);

// ================= HAPUS =================
if ($hapusid = optional_param('hapusid', 0, PARAM_INT)) {
    require_sesskey();
    $DB->delete_records('local_jurnalmengajar_suratizin', ['id' => $hapusid]);

    redirect(
        new moodle_url('/local/jurnalmengajar/izin_murid.php', ['kelasid' => $kelasid]),
        'Data berhasil dihapus.',
        1
    );
}

// ================= SISWA =================
$siswaoptions = [];

if ($kelasid) {
    $members = $DB->get_records('cohort_members', ['cohortid' => $kelasid]);

    foreach ($members as $m) {
        $u = $DB->get_record('user', ['id' => $m->userid], 'id, firstname, lastname');
        if ($u) {
            $siswaoptions[$u->id] = !empty($u->lastname) ? $u->lastname : $u->firstname;
        }
    }
}

// ================= PROSES =================
$action = optional_param('action', '', PARAM_ALPHA);
$do_submit = ($action === 'print');
$do_save = ($action === 'save');

if (($do_submit || $do_save) && confirm_sesskey()) {

    $record = new stdClass();
    $record->userid        = required_param('siswaid', PARAM_INT);
    $record->kelasid       = required_param('kelasid', PARAM_INT);
    $record->guru_pengajar = required_param('guru_pengajar', PARAM_INT);
    $record->alasan        = required_param('alasan', PARAM_TEXT);
    $record->keperluan     = required_param('keperluan', PARAM_TEXT);
    $record->penginput     = $USER->id;
    $record->timecreated   = time();

    // Anti duplikat (10 menit)
    $cek = $DB->get_record_sql("
        SELECT id
          FROM {local_jurnalmengajar_suratizin}
         WHERE userid = :userid
           AND kelasid = :kelas
           AND timecreated >= :waktu
         LIMIT 1
    ", [
        'userid' => $record->userid,
        'kelas' => $record->kelasid,
        'waktu' => time() - 600
    ]);

    if ($cek) {
        $id = $cek->id;
    } else {
        $id = $DB->insert_record('local_jurnalmengajar_suratizin', $record);
    }

// ================= KIRIM WA =================
$siswa = $DB->get_record('user', ['id' => $record->userid]);
if ($siswa) {
    $kelas = get_nama_kelas($record->kelasid);
    $nama  = ucwords(strtolower($siswa->lastname));
    $gurunama = $DB->get_field('user', 'lastname', ['id' => $record->guru_pengajar]);
    $waktu_full = tanggal_indo($record->timecreated);

    $pesan = "*📄 Surat Izin Murid*\n\n"
           . "📅 Waktu: $waktu_full\n"
           . "👤 Nama: $nama\n"
           . "🏫 Kelas: $kelas\n"
           . "🎓 Guru Pengajar: $gurunama\n"
           . "📝 Alasan: {$record->alasan}\n"
           . "📌 Keperluan: {$record->keperluan}\n"
           . "✍️ Pengawas Hari ini: $pengawas\n\n"
           . "_Dikirim kepada Wali kelas sebagai laporan_";

$tujuan = [
    get_nomor_wali_kelas($record->kelasid)
];

jurnalmengajar_kirim_wa($tujuan, $pesan);
}

    // ================= REDIRECT =================
    if ($do_save) {
        redirect(
            new moodle_url('/local/jurnalmengajar/izin_murid.php', ['kelasid' => $record->kelasid]),
            'Data berhasil disimpan.',
            1
        );
    } else {
        redirect(
            new moodle_url('/local/jurnalmengajar/cetak_surat_izin.php', ['id' => $id])
        );
    }
}

// ================= TAMPILAN =================
echo $OUTPUT->header();
echo $OUTPUT->heading('Input Surat Izin Murid');

// Filter kelas
echo html_writer::start_tag('form', ['method' => 'get']);
echo html_writer::label('Kelas ', 'kelasid');
echo html_writer::select($cohorts, 'kelasid', $kelasid, ['' => 'Pilih kelas'], ['onchange' => 'this.form.submit()']);
echo html_writer::end_tag('form');

// ================= FORM =================
if ($kelasid) {

    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => (new moodle_url('/local/jurnalmengajar/izin_murid.php'))->out(false)
    ]);

    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'sesskey',
        'value' => sesskey()
    ]);

    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'kelasid',
        'value' => $kelasid
    ]);

    echo html_writer::label('Nama Murid 🔴', 'siswaid');
    echo html_writer::select($siswaoptions, 'siswaid', '', ['' => 'Pilih Murid'], ['required' => 'required']);
    echo html_writer::empty_tag('br');

    echo html_writer::label('Guru Pengajar 🔴', 'guru_pengajar');
    echo html_writer::select($guruoptions, 'guru_pengajar', '', ['' => 'Pilih Guru'], ['required' => 'required']);
    echo html_writer::empty_tag('br');

    echo html_writer::label('Alasan 🔴', 'alasan');
    echo html_writer::tag('textarea', '', [
        'name' => 'alasan',
        'rows' => 3,
        'cols' => 50,
        'required' => 'required',
        'placeholder' => 'Isi alasan'
    ]);
    echo html_writer::empty_tag('br');

    echo html_writer::label('Keperluan 🔴', 'keperluan');
    echo html_writer::select([
        'Izin Masuk' => 'Izin Masuk',
        'Izin Keluar' => 'Izin Keluar',
        'Izin Pulang' => 'Izin Pulang'
    ], 'keperluan', '', ['' => 'Pilih Keperluan'], ['required' => 'required']);

    echo html_writer::empty_tag('br');
    echo html_writer::empty_tag('br');

    echo html_writer::tag('button', '🖨️ Cetak Surat', [
        'type' => 'submit',
        'name' => 'action',
        'value' => 'print',
        'class' => 'btn btn-success'
    ]);

    echo html_writer::tag('button', '💾 Simpan', [
        'type' => 'submit',
        'name' => 'action',
        'value' => 'save',
        'class' => 'btn btn-secondary'
    ]);

    echo html_writer::end_tag('form');
}

// ================= RIWAYAT SURAT IZIN =================

// Header + tombol rekap
echo html_writer::start_div('d-flex justify-content-between align-items-center', [
    'style' => 'margin-top: 40px;'
]);

echo html_writer::tag('h3', 'Riwayat Surat Izin');

$rekapurl = new moodle_url('/local/jurnalmengajar/rekap_surat_izin.php');

echo html_writer::link($rekapurl, '📄 Rekap Surat Izin', [
    'class' => 'btn btn-primary',
    'style' => 'margin-bottom: 5px;'
]);

echo html_writer::end_div();

// Judul
echo $OUTPUT->heading('Riwayat Surat Izin', 3);

// ================= FILTER =================
$riwayatkelasid = optional_param('riwayat_kelasid', 0, PARAM_INT);

echo html_writer::start_tag('form', ['method' => 'get']);

echo html_writer::label('Filter Kelas: ', 'riwayat_kelasid');

echo html_writer::select(
    $cohorts,
    'riwayat_kelasid',
    $riwayatkelasid,
    ['' => 'Semua kelas']
);

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => 'Tampilkan',
    'class' => 'btn btn-secondary'
]);

echo html_writer::end_tag('form');

// ================= QUERY =================
$params = [];
$where = '';

if ($riwayatkelasid) {
    $where = "WHERE s.kelasid = :kelasid";
    $params['kelasid'] = $riwayatkelasid;
}

$sql = "SELECT s.*, 
               u.lastname AS siswa_nama, 
               g.lastname AS guru_nama, 
               p.lastname AS pengawas_nama
          FROM {local_jurnalmengajar_suratizin} s
          JOIN {user} u ON u.id = s.userid
          JOIN {user} g ON g.id = s.guru_pengajar
          JOIN {user} p ON p.id = s.penginput
          $where
      ORDER BY s.timecreated DESC
         LIMIT 30";

$riwayatsurat = $DB->get_records_sql($sql, $params);

// ================= TABEL =================
if ($riwayatsurat) {

    $table = new html_table();

    $table->head = [
        'No',
        'Tanggal',
        'Nama Murid',
        'Kelas',
        'Guru Pengajar',
        'Alasan',
        'Keperluan',
        'Pengawas'
    ];

    $table->align = [
        'center',
        'center',
        'left',
        'center',
        'left',
        'left',
        'left',
        'left'
    ];

    $no = 1;

    foreach ($riwayatsurat as $s) {

        $kelasnama = get_nama_kelas($s->kelasid);

        $tgl_display = tanggal_indo($s->timecreated);

        $table->data[] = [
            $no++,
            $tgl_display,
            ucwords(strtolower($s->siswa_nama)),
            $kelasnama,
            $s->guru_nama,
            $s->alasan,
            $s->keperluan,
            $s->pengawas_nama
        ];
    }

    echo html_writer::table($table);

} else {
    echo html_writer::tag(
        'p',
        'Belum ada surat izin yang dicatat.',
        ['class' => 'alert alert-info']
    );
}

// ================= FOOTER =================
