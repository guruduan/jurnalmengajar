<?php
require_once('../../config.php');
require_once(__DIR__.'/lib.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_url(new moodle_url('/local/jurnalmengajar/izin_id.php'));
$PAGE->set_context($context);
$PAGE->set_title('ID Surat Izin Keluar/Masuk');
$PAGE->set_heading('ID Surat Izin Keluar/Masuk');

global $DB, $USER, $OUTPUT;

// ================= PAGING =================
$page = optional_param('page', 0, PARAM_INT);
$perpage = 20;
$offset = $page * $perpage;

// ================= FILTER =================
$riwayatkelasid = optional_param('riwayat_kelasid', 0, PARAM_INT);

$params = [];
$where = "";

if ($riwayatkelasid) {
    $where = "WHERE s.kelasid = :kelasid";
    $params['kelasid'] = $riwayatkelasid;
}

// ================= HITUNG TOTAL =================
$countsql = "SELECT COUNT(1)
               FROM {local_jurnalmengajar_suratizin} s
               $where";

$total = $DB->count_records_sql($countsql, $params);

// ================= QUERY DATA =================
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
         LIMIT $perpage OFFSET $offset";

$riwayatsurat = $DB->get_records_sql($sql, $params);

// ================= TAMPILAN =================
echo $OUTPUT->header();
echo $OUTPUT->heading('ID Surat Izin Murid');

// Tombol kembali
echo html_writer::start_div('d-flex justify-content-between align-items-center', ['style' => 'margin-top: 40px;']);
echo html_writer::tag('h3', 'Riwayat Surat Izin');
$keformurl = new moodle_url('/local/jurnalmengajar/cetak_surat_izin_form.php');
echo html_writer::link($keformurl, 'Kembali untuk Mencetak Surat Izin sesuai ID', ['class' => 'btn btn-primary', 'style' => 'margin-bottom: 5px;']);
echo html_writer::end_div();

// ================= TABEL =================
if ($riwayatsurat) {
    $table = new html_table();
    $table->head  = [
        'No', 'Tanggal', 'Nama Murid', 'Kelas', 
        'Guru Pengajar', 'Alasan', 'Keperluan', 'Guru Pengawas', 'ID'
    ];

    $no = $offset + 1;

    foreach ($riwayatsurat as $s) {

        $tanggal = tanggal_indo($s->timecreated);
        $kelasnama = get_nama_kelas($s->kelasid);

        $table->data[] = [
            $no++,
            $tanggal,
            format_nama_siswa($s->siswa_nama),
            $kelasnama,
            $s->guru_nama,
            $s->alasan,
            $s->keperluan,
            $s->pengawas_nama,
            $s->id
        ];
    }

    echo html_writer::table($table);
} else {
    echo html_writer::tag('p', 'Belum ada surat izin yang dicatat.', ['class' => 'alert alert-info']);
}

// ================= PAGING =================
$baseurl = new moodle_url('/local/jurnalmengajar/izin_id.php', [
    'riwayat_kelasid' => $riwayatkelasid
]);

echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);

echo $OUTPUT->footer();
