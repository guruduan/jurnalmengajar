<?php
require_once('../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_url(new moodle_url('/local/jurnalmengajar/surat_izin.php'));
$PAGE->set_context($context);
$PAGE->set_title('ID Surat Izin Keluar/Masuk');
$PAGE->set_heading('ID Surat Izin Keluar/Masuk');

global $DB, $USER, $CFG;
$pengawas = $USER->lastname;

require_once($CFG->dirroot . '/local/jurnalmengajar/lib.php');

// --------------------------------------------------
// Tampilan
// --------------------------------------------------
echo $OUTPUT->header();
echo $OUTPUT->heading('ID Surat Izin Murid');

// Tombol Kembali ke Form
echo html_writer::start_div('d-flex justify-content-between align-items-center', ['style' => 'margin-top: 40px;']);
echo html_writer::tag('h3', 'Riwayat Surat Izin');
$keformurl = new moodle_url('/local/jurnalmengajar/cetak_surat_izin_form.php');
echo html_writer::link($keformurl, 'Kembali untuk Mencetak Surat Izin sesuai ID', ['class' => 'btn btn-primary', 'style' => 'margin-bottom: 5px;']);
echo html_writer::end_div();

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
    , 'ID'
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
    , $s->id
];

    }

    echo html_writer::table($table);
} else {
    echo html_writer::tag('p', 'Belum ada surat izin yang dicatat.', ['class' => 'alert alert-info']);
}

echo $OUTPUT->footer();
