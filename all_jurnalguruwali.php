<?php
require_once(__DIR__ . '/../../config.php');
require_login();
require_once(__DIR__ . '/lib.php');
require_once(__DIR__.'/jadwal_acuan_lib.php');

$context = context_system::instance();
require_capability('moodle/site:config', $context); // hanya admin

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/all_jurnalguruwali.php'));
$PAGE->set_title('Semua Jurnal Guru Wali');
$PAGE->set_heading('Semua Jurnal Guru Wali');

// ================= PARAMETER FILTER =================
$guruid = optional_param('guruid', 0, PARAM_INT);
$kelas  = optional_param('kelas', 0, PARAM_INT);
$bulan  = optional_param('bulan', 0, PARAM_INT);
$page   = optional_param('page', 0, PARAM_INT);

$perpage = 20;
$offset  = $page * $perpage;

// ================= HAPUS DATA =================
$deleteid = optional_param('deleteid', 0, PARAM_INT);

if ($deleteid) {
    require_sesskey();
    $DB->delete_records('local_jurnalguruwali', ['id' => $deleteid]);
    redirect(new moodle_url('/local/jurnalmengajar/all_jurnalguruwali.php', [
    'guruid' => $guruid,
    'kelas' => $kelas,
    'bulan' => $bulan,
    'page' => $page
]), 'Data berhasil dihapus');
}

// ================= FILTER SQL =================
$where = " WHERE 1=1 ";
$params = [];

if ($guruid) {
    $where .= " AND j.guruid = :guruid ";
    $params['guruid'] = $guruid;
}

if ($bulan) {
    $where .= " AND MONTH(FROM_UNIXTIME(j.timecreated)) = :bulan ";
    $params['bulan'] = $bulan;
}

if ($kelas) {
    $where .= " AND c.id = :kelas ";
    $params['kelas'] = $kelas;
}

// ================= HITUNG TOTAL DATA =================
$total = $DB->count_records_sql("
    SELECT COUNT(1)
    FROM {local_jurnalguruwali} j
    JOIN {user} u2 ON u2.id = j.muridid
    LEFT JOIN {cohort_members} cm ON cm.userid = u2.id
    LEFT JOIN {cohort} c ON c.id = cm.cohortid
    $where
", $params);

// ================= ORDER =================
$order = " ORDER BY j.timecreated DESC ";

if ($kelas) {
    $order = " ORDER BY u2.lastname ASC, j.timecreated DESC ";
}

// ================= AMBIL DATA =================
$rows = $DB->get_records_sql("
    SELECT j.*, 
           u1.firstname AS gurufirst, u1.lastname AS gurulast,
           u2.firstname AS muridfirst, u2.lastname AS muridlast,
           c.name AS kelas
    FROM {local_jurnalguruwali} j
    JOIN {user} u1 ON u1.id = j.guruid
    JOIN {user} u2 ON u2.id = j.muridid
    LEFT JOIN {cohort_members} cm ON cm.userid = u2.id
    LEFT JOIN {cohort} c ON c.id = cm.cohortid
    $where
    $order
    LIMIT $perpage OFFSET $offset
", $params);

// ================= TAMPILAN =================
echo $OUTPUT->header();
echo $OUTPUT->heading('Semua Jurnal Guru Wali');

// ================= FORM FILTER =================
echo html_writer::start_tag('form', ['method' => 'get']);

echo 'Guru: ';
$jadwal = jurnalmengajar_get_jadwal_acuan();
$daftarguru = [];

foreach ($jadwal as $j) {
    if (!isset($daftarguru[$j['userid']])) {
        $daftarguru[$j['userid']] = $j['lastname'];
    }
}
asort($daftarguru);

echo html_writer::select($daftarguru, 'guruid', $guruid, ['0' => 'Semua Guru']);

echo ' &nbsp; Kelas: ';
$kelaslist = $DB->get_records_menu('cohort', null, 'name ASC', 'id, name');
echo html_writer::select($kelaslist, 'kelas', $kelas, ['0' => 'Semua Kelas']);

echo ' &nbsp; Bulan: ';
$bulanlist = [
    1=>'Januari',
    2=>'Februari',
    3=>'Maret',
    4=>'April',
    5=>'Mei',
    6=>'Juni',
    7=>'Juli',
    8=>'Agustus',
    9=>'September',
    10=>'Oktober',
    11=>'November',
    12=>'Desember'
];
echo html_writer::select($bulanlist, 'bulan', $bulan, ['0' => 'Semua Bulan']);

echo ' ';
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => 'Filter']);

echo html_writer::end_tag('form');

echo '<br>';

// ================= TABEL =================
$table = new html_table();
$table->head = ['No', 'Tanggal', 'Guru Wali', 'Murid', 'Kelas', 'Topik', 'Tindak Lanjut', 'Keterangan', 'Aksi'];

$no = $offset + 1;

foreach ($rows as $r) {

$tanggal = tanggal_indo($r->timecreated, 'judul') . '<br>' .
           tanggal_indo($r->timecreated, 'jam');

    // Pakai lastname saja
// Pakai format dari lib.php
$guru = $r->gurulast;
$murid = format_nama_siswa($r->muridlast);
$kelassiswa = $r->kelas ?? '-';

    $deleteurl = new moodle_url('/local/jurnalmengajar/all_jurnalguruwali.php', [
        'deleteid' => $r->id,
        'sesskey' => sesskey(),
        'guruid' => $guruid,
        'kelas' => $kelas,
        'bulan' => $bulan,
        'page' => $page
    ]);

    $hapus = html_writer::link(
        $deleteurl,
        '🗑 Hapus',
        ['onclick' => "return confirm('Hapus data ini?')"]
    );

    $table->data[] = [
        $no++,
        $tanggal,
        $guru,
        $murid,
        $kelassiswa,
        $r->topik,
        $r->tindaklanjut,
        $r->keterangan,
        $hapus
    ];
}

echo html_writer::table($table);

// ================= PAGING =================
$baseurl = new moodle_url('/local/jurnalmengajar/all_jurnalguruwali.php', [
    'guruid' => $guruid,
    'kelas' => $kelas,
    'bulan' => $bulan
]);

echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);

echo $OUTPUT->footer();
