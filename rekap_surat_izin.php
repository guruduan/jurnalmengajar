<?php
require_once('../../config.php');
require_login();
$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_url(new moodle_url('/local/jurnalmengajar/rekap_surat_izin.php'));
$PAGE->set_context($context);
$PAGE->set_title('Rekap Surat Izin');
$PAGE->set_heading('Rekap Surat Izin Keluar/Masuk Murid');

global $DB, $OUTPUT;

$kelasfilter = optional_param('kelas', 0, PARAM_INT);
$siswafilter = optional_param('siswaid', 0, PARAM_INT);
$dari = optional_param('dari', '', PARAM_RAW);
$sampai = optional_param('sampai', '', PARAM_RAW);

$starttime = $dari ? strtotime(str_replace('-', '/', $dari)) : 0;
$endtime = $sampai ? strtotime(str_replace('-', '/', $sampai)) + 86399 : time();

// Ambil daftar cohort
$cohorts = $DB->get_records_menu('cohort', null, 'name ASC', 'id, name');

// Ambil siswa jika kelas terpilih
$siswaoptions = [];
if ($kelasfilter) {
    $members = $DB->get_records('cohort_members', ['cohortid' => $kelasfilter]);
    foreach ($members as $m) {
        $user = $DB->get_record('user', ['id' => $m->userid], 'id, lastname');
        if ($user) {
            $siswaoptions[$user->id] = $user->lastname;
        }
    }
}

$params = ['start' => $starttime, 'end' => $endtime];
$sql = "SELECT si.*, 
               u.lastname AS siswa, 
               gp.lastname AS gurupengajar, 
               ci.name AS kelas,
               pi.lastname AS penginput
          FROM {local_jurnalmengajar_suratizin} si
          JOIN {user} u ON u.id = si.userid
          JOIN {user} gp ON gp.id = si.guru_pengajar
          JOIN {user} pi ON pi.id = si.penginput
          JOIN {cohort} ci ON ci.id = si.kelasid
         WHERE si.timecreated BETWEEN :start AND :end";

// Tambahkan filter kelas
if ($kelasfilter) {
    $sql .= " AND si.kelasid = :kelas";
    $params['kelas'] = $kelasfilter;
}

// Tambahkan filter siswa
if ($siswafilter) {
    $sql .= " AND si.userid = :siswa";
    $params['siswa'] = $siswafilter;
}

$results = $DB->get_records_sql($sql, $params);

// Tampilkan filter
echo $OUTPUT->header();
echo $OUTPUT->heading('Rekap Surat Izin Murid');

echo html_writer::start_tag('form', ['method' => 'get']);

echo html_writer::start_div();
echo html_writer::label('Kelas', 'kelas') . ' ';
echo html_writer::select($cohorts, 'kelas', $kelasfilter, ['0' => 'Pilih kelas'], ['onchange' => 'this.form.submit()']);
echo html_writer::end_div();

if ($kelasfilter) {
    echo html_writer::start_div();
    echo html_writer::label('Nama Murid', 'siswaid') . ' ';
    echo html_writer::select($siswaoptions, 'siswaid', $siswafilter, ['0' => 'Semua Murid']);
    echo html_writer::end_div();
}

echo html_writer::start_div();
echo html_writer::label('Dari', 'dari') . ' ';
echo html_writer::empty_tag('input', ['type' => 'date', 'name' => 'dari', 'value' => $dari]);
echo ' ';
echo html_writer::label('Sampai', 'sampai') . ' ';
echo html_writer::empty_tag('input', ['type' => 'date', 'name' => 'sampai', 'value' => $sampai]);
echo ' ';
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => 'Tampilkan']);
echo html_writer::end_div();

echo html_writer::end_tag('form');
//
function format_tanggal_wita($timestamp) {
    $hari = [
        'Sunday' => 'Minggu',
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu'
    ];

    $bulan = [
        '01' => 'Januari',
        '02' => 'Februari',
        '03' => 'Maret',
        '04' => 'April',
        '05' => 'Mei',
        '06' => 'Juni',
        '07' => 'Juli',
        '08' => 'Agustus',
        '09' => 'September',
        '10' => 'Oktober',
        '11' => 'November',
        '12' => 'Desember'
    ];

    $dayname = $hari[date('l', $timestamp)] ?? date('l', $timestamp);
    $day = date('d', $timestamp);
    $month = $bulan[date('m', $timestamp)] ?? date('m', $timestamp);
    $year = date('Y', $timestamp);
    $time = date('H:i', $timestamp);

    return "$dayname, $day $month $year Pukul $time WITA";
}

//
// Tampilkan tabel rekap
if ($results) {
    $table = new html_table();
    $table->head = ['No', 'Tanggal', 'Nama Murid', 'Kelas', 'Guru Pengajar', 'Alasan', 'Keperluan', 'Pengawas'];
    $table->data = [];
    
    
    $no = 1;
    foreach ($results as $row) {
//        $tanggal = userdate($row->timecreated, '%d-%m-%Y', 99, 'Asia/Makassar');
//        $tanggal = userdate($row->timecreated, '%A, %d-%m-%Y %H:%M', 99, 'Asia/Makassar');
//$tanggal = terjemah_hari($row->timecreated) . ', ' . userdate($row->timecreated, '%d-%m-%Y %H:%M', 99, 'Asia/Makassar');
$tanggal = format_tanggal_wita($row->timecreated);

        $namasiswa = ucwords(strtolower($row->siswa));
        $namaguru = $row->gurupengajar;
        $pengawas = $row->penginput;
//        $namaguru = ucwords(strtolower($row->gurupengajar));
//        $pengawas = ucwords(strtolower($row->penginput));

        $table->data[] = [
            $no++,
            $tanggal,
            $namasiswa,
            $row->kelas,
            $namaguru,
            $row->alasan,
            $row->keperluan,
            $pengawas
        ];
    }
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification('Tidak ada data surat izin pada filter ini.', 'notifymessage');
}

echo $OUTPUT->footer();
