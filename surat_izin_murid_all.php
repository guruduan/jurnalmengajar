<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_url(new moodle_url('/local/jurnalmengajar/surat_izin_murid_all.php'));
$PAGE->set_context($context);
$PAGE->set_title('Riwayat Surat Izin Murid');
$PAGE->set_heading('Riwayat Surat Izin Murid');

global $DB, $CFG;

// ✅ WAJIB (ini penyebab error tadi)
require_once($CFG->dirroot . '/local/jurnalmengajar/lib.php');

// =======================
// HAPUS DATA
// =======================
if ($hapusid = optional_param('hapusid', 0, PARAM_INT)) {
    require_sesskey();
    $DB->delete_records('local_jurnalmengajar_suratizin', ['id' => $hapusid]);

    redirect(
        new moodle_url('/local/jurnalmengajar/surat_izin_murid_all.php'),
        'Data berhasil dihapus.',
        1
    );
}

echo $OUTPUT->header();

// =======================
// FILTER
// =======================
$cohorts = $DB->get_records_menu('cohort', null, 'name ASC', 'id, name');

echo html_writer::start_tag('form', ['method' => 'get']);
echo html_writer::label('Filter Kelas: ', 'riwayat_kelasid');
echo html_writer::select($cohorts, 'riwayat_kelasid', optional_param('riwayat_kelasid', 0, PARAM_INT), ['' => 'Semua kelas']);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => 'Tampilkan']);
echo html_writer::end_tag('form');

// =======================
// QUERY
// =======================
$riwayatkelasid = optional_param('riwayat_kelasid', 0, PARAM_INT);

$params = [];
$where = "";

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

// =======================
// TABEL
// =======================
if ($riwayatsurat) {

    $table = new html_table();
    $table->head  = [
        'No', 'Tanggal', 'Nama Murid', 'Kelas',
        'Guru Pengajar', 'Alasan', 'Keperluan',
        'Guru Pengawas', 'Aksi'
    ];

    $table->align = [
        'center','center','left','center',
        'left','left','left','left','center'
    ];

    $hari  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $bulan = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];

    $no = 1;

    foreach ($riwayatsurat as $s) {

        $kelasnama = get_nama_kelas($s->kelasid);

        $ts = $s->timecreated;

        $tgl_display = sprintf(
            '%s, %d %s %d %s WITA',
            $hari[date('w',$ts)],
            date('j',$ts),
            $bulan[date('n',$ts)],
            date('Y',$ts),
            date('H:i',$ts)
        );

        // tombol hapus
        $hapusurl = new moodle_url('/local/jurnalmengajar/surat_izin_murid_all.php', [
            'hapusid' => $s->id,
            'sesskey' => sesskey()
        ]);

        $btnhapus = html_writer::link(
            $hapusurl,
            '🗑 Hapus',
            [
                'class' => 'btn btn-danger btn-sm',
                'onclick' => "return confirm('Yakin ingin menghapus data ini?')"
            ]
        );

        $table->data[] = [
            $no++,
            $tgl_display,
            ucwords(strtolower($s->siswa_nama)),
            $kelasnama,
            $s->guru_nama,
            format_string($s->alasan),
            format_string($s->keperluan),
            $s->pengawas_nama,
            $btnhapus
        ];
    }

    echo html_writer::table($table);

} else {
    echo html_writer::tag('p', 'Belum ada surat izin yang dicatat.', ['class' => 'alert alert-info']);
}

echo $OUTPUT->footer();
