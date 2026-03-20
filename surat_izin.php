<?php
require_once('../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_url(new moodle_url('/local/jurnalmengajar/surat_izin.php'));
$PAGE->set_context($context);
$PAGE->set_title('Surat Izin Keluar/Masuk');
$PAGE->set_heading('Surat Izin Keluar/Masuk');

global $DB, $USER, $CFG;
$pengawas = $USER->lastname;

require_once($CFG->dirroot . '/local/jurnalmengajar/lib.php');

// --------------------------------------------------
// Data awal: cohort & guru
// --------------------------------------------------
$cohorts = $DB->get_records_menu('cohort', null, 'name ASC', 'id, name');

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
        $guruoptions[$g->id] = ucwords(strtolower($g->lastname));
    }
}

$kelasid = optional_param('kelasid', 0, PARAM_INT);

// --------------------------------------------------
// Hapus (GET) — tetap minta sesskey
// --------------------------------------------------
if ($hapusid = optional_param('hapusid', 0, PARAM_INT)) {
    require_sesskey();
    $DB->delete_records('local_jurnalmengajar_suratizin', ['id' => $hapusid]);
    redirect(new moodle_url('/local/jurnalmengajar/surat_izin.php', ['kelasid' => $kelasid]), 'Data berhasil dihapus.', 1);
}

// --------------------------------------------------
// Opsi siswa per kelas
// --------------------------------------------------
$siswaoptions = [];
if ($kelasid) {
    $members = $DB->get_records('cohort_members', ['cohortid' => $kelasid]);
    foreach ($members as $m) {
        $u = $DB->get_record('user', ['id' => $m->userid], 'id, firstname, lastname');
        if ($u) {
            $siswaoptions[$u->id] = ucwords(strtolower($u->lastname));
        }
    }
}

// --------------------------------------------------
// Submit form (POST)
// --------------------------------------------------
if (optional_param('submit', false, PARAM_BOOL) && confirm_sesskey()) {
    $guru_pengajar = required_param('guru_pengajar', PARAM_INT);

    $record = new stdClass();
    $record->userid        = required_param('siswaid', PARAM_INT);
    $record->kelasid       = required_param('kelasid', PARAM_INT);
    $record->guru_pengajar = $guru_pengajar;
    $record->alasan        = required_param('alasan', PARAM_TEXT);
    $record->keperluan     = required_param('keperluan', PARAM_TEXT);
    $record->penginput     = $USER->id;
    $record->timecreated   = time();

    // Cegah duplikat 10 menit terakhir (entri identik)
    $tenminutesago = time() - 600;
    $cek_duplikat = $DB->get_record_sql("
        SELECT *
          FROM {local_jurnalmengajar_suratizin}
         WHERE userid        = :userid
           AND kelasid       = :kelasid
           AND guru_pengajar = :guru
           AND alasan        = :alasan
           AND keperluan     = :keperluan
           AND timecreated  >= :timewindow
         ORDER BY timecreated DESC
         LIMIT 1
    ", [
        'userid' => $record->userid,
        'kelasid' => $record->kelasid,
        'guru' => $record->guru_pengajar,
        'alasan' => $record->alasan,
        'keperluan' => $record->keperluan,
        'timewindow' => $tenminutesago
    ]);

    if ($cek_duplikat) {
        $id = $cek_duplikat->id;
    } else {
        $id = $DB->insert_record('local_jurnalmengajar_suratizin', $record);
    }

    // Kirim WA ke wali kelas
    $siswa = $DB->get_record('user', ['id' => $record->userid], 'firstname, lastname');
    $kelas = get_nama_kelas($record->kelasid);
    $nama  = ucwords(strtolower($siswa->lastname));
    $nomorwa = get_nomor_wali_kelas($kelas);

    if ($nomorwa) {
        // Format tanggal WITA
        date_default_timezone_set('Asia/Makassar');
        $hari  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
        $bulan = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
        $ts = $record->timecreated;
        $tanggal_format = sprintf('%s, %d %s %d %s WITA',
            $hari[date('w',$ts)], date('j',$ts), $bulan[date('n',$ts)], date('Y',$ts), date('H:i',$ts)
        );

        $gurunama = $DB->get_field('user', 'lastname', ['id' => $guru_pengajar]);

        $pesan = "*[Surat Izin Murid]*\n"
               . "📅 Waktu: $tanggal_format\n"
               . "👤 Nama: $nama\n"
               . "🏫 Kelas: $kelas\n"
               . "🎓 Guru Pengajar: $gurunama\n"
               . "📝 Alasan: {$record->alasan}\n"
               . "📌 Keperluan: {$record->keperluan}\n"
               . "✍️ Pengawas Hari ini: $pengawas\n"
               . "_Dikirim kepada Wali kelas sebagai laporan_";

        jurnalmengajar_kirim_wa($nomorwa, $pesan);
    }

    redirect(new moodle_url('/local/jurnalmengajar/cetak_surat_izin.php', ['id' => $id]));
}

// --------------------------------------------------
// Tampilan
// --------------------------------------------------
echo $OUTPUT->header();
echo $OUTPUT->heading('Input Surat Izin Murid');

// Filter kelas (GET)
echo html_writer::start_tag('form', ['method' => 'get']);
echo html_writer::label('Kelas ', 'kelasid');
echo html_writer::select($cohorts, 'kelasid', $kelasid, ['' => 'Pilih kelas'], ['onchange' => 'this.form.submit()']);
echo html_writer::end_tag('form');

// Form input (POST)
if ($kelasid) {
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => (new moodle_url('/local/jurnalmengajar/surat_izin.php'))->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'kelasid', 'value' => $kelasid]);

    echo html_writer::label('Nama Murid 🔴', 'siswaid');
    echo html_writer::select($siswaoptions, 'siswaid', '', ['' => 'Pilih Murid'], ['required' => 'required']);
    echo html_writer::empty_tag('br');

    if (!empty($guruoptions)) {
        echo html_writer::label('Guru Pengajar 🔴', 'guru_pengajar');
        echo html_writer::select($guruoptions, 'guru_pengajar', '', ['' => 'Pilih Guru'], ['required' => 'required']);
        echo html_writer::empty_tag('br');
    } else {
        echo html_writer::tag('p', '⚠️ Tidak ditemukan pengguna dengan role Guru Jurnal.');
    }

    echo html_writer::label('Alasan 🔴', 'alasan') . html_writer::empty_tag('br');
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
        'Izin Masuk'  => 'Izin Masuk',
        'Izin Keluar' => 'Izin Keluar',
        'Izin Pulang' => 'Izin Pulang'
    ], 'keperluan', '', ['' => 'Pilih Keperluan'], ['required' => 'required']);

    echo html_writer::empty_tag('br') . html_writer::empty_tag('br');
    echo html_writer::empty_tag('input', ['type' => 'submit', 'name' => 'submit', 'value' => 'Cetak Surat']);
    echo html_writer::end_tag('form');
}

// Header rekap & link
echo html_writer::start_div('d-flex justify-content-between align-items-center', ['style' => 'margin-top: 40px;']);
echo html_writer::tag('h3', 'Riwayat Surat Izin');
$rekapurl = new moodle_url('/local/jurnalmengajar/rekap_surat_izin.php');
echo html_writer::link($rekapurl, '📄 Rekap Surat Izin', ['class' => 'btn btn-primary', 'style' => 'margin-bottom: 5px;']);
echo html_writer::end_div();

echo $OUTPUT->heading('Riwayat Surat Izin', 3);

// Filter riwayat (GET)
echo html_writer::start_tag('form', ['method' => 'get']);
echo html_writer::label('Filter Kelas untuk Riwayat: ', 'riwayat_kelasid');
echo html_writer::select($cohorts, 'riwayat_kelasid', optional_param('riwayat_kelasid', 0, PARAM_INT), ['' => 'Semua kelas']);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => 'Tampilkan']);
echo html_writer::end_tag('form');

// Query riwayat
$riwayatkelasid = optional_param('riwayat_kelasid', 0, PARAM_INT);
$params = [];
$where = "";
if ($riwayatkelasid) {
    $where = "WHERE s.kelasid = :kelasid";
    $params['kelasid'] = $riwayatkelasid;
}

$sql = "SELECT s.*, u.lastname AS siswa_nama, g.lastname AS guru_nama, p.lastname AS pengawas_nama
          FROM {local_jurnalmengajar_suratizin} s
          JOIN {user} u ON u.id = s.userid
          JOIN {user} g ON g.id = s.guru_pengajar
          JOIN {user} p ON p.id = s.penginput
          $where
      ORDER BY s.timecreated DESC
         LIMIT 30";

$riwayatsurat = $DB->get_records_sql($sql, $params);

// Tabel riwayat
if ($riwayatsurat) {
    $table = new html_table();
$table->head  = [
    'No', 'Tanggal', 'Nama Murid', 'Kelas', 'Guru Pengajar', 'Alasan', 'Keperluan', 'Guru Pengawas'
    // , 'ID'
];
$table->align = [
    'center', 'center', 'left', 'center', 'left', 'left', 'left', 'left'
    // , 'center'
];

    // sekali set TZ, tidak di dalam loop
    date_default_timezone_set('Asia/Makassar');
    $hari  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $bulan = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];

    $no = 1;
    foreach ($riwayatsurat as $s) {
        $kelasnama  = get_nama_kelas($s->kelasid);
        $ts         = $s->timecreated;
        $tgl_display = sprintf('%s, %d %s %d %s WITA',
            $hari[date('w',$ts)], date('j',$ts), $bulan[date('n',$ts)], date('Y',$ts), date('H:i',$ts)
        );

$table->data[] = [
    $no++,
    $tgl_display,
    ucwords(strtolower($s->siswa_nama)),
    $kelasnama,
    $s->guru_nama,
    $s->alasan,
    $s->keperluan,
    $s->pengawas_nama
    // , $s->id
];

    }

    echo html_writer::table($table);
} else {
    echo html_writer::tag('p', 'Belum ada surat izin yang dicatat.', ['class' => 'alert alert-info']);
}

echo $OUTPUT->footer();
