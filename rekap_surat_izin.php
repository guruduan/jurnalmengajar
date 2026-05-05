<?php
require_once('../../config.php');
require_once(__DIR__.'/lib.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$PAGE->set_url(new moodle_url('/local/jurnalmengajar/rekap_surat_izin.php'));
$PAGE->set_context($context);
$PAGE->set_title('Rekap Surat Izin');
$PAGE->set_heading('Rekap Surat Izin Keluar/Masuk Murid');

global $DB, $OUTPUT;

// ================= PARAMETER =================
$kelasfilter = optional_param('kelas', 0, PARAM_INT);
$siswafilter = optional_param('siswaid', 0, PARAM_INT);
$dari = optional_param('dari', '', PARAM_RAW);
$sampai = optional_param('sampai', '', PARAM_RAW);
$page = optional_param('page', 0, PARAM_INT);
$keperluanfilter = optional_param('keperluan', '', PARAM_TEXT);

$perpage = 20;
$offset  = $page * $perpage;

$starttime = $dari ? strtotime($dari) : 0;
$endtime   = $sampai ? strtotime($sampai) + 86399 : time();

// ================= FILTER DROPDOWN =================
$cohorts = $DB->get_records_menu('cohort', null, 'name ASC', 'id, name');

$siswaoptions = [];
if ($kelasfilter) {
    $members = $DB->get_records('cohort_members', ['cohortid' => $kelasfilter]);
    foreach ($members as $m) {
        $user = $DB->get_record('user', ['id' => $m->userid], 'id, lastname');
        if ($user) {
            $siswaoptions[$user->id] = format_nama_siswa($user->lastname);
        }
    }
}

// ================= PARAMS SQL =================
$params = ['start' => $starttime, 'end' => $endtime];

// ================= HITUNG TOTAL =================
$countsql = "SELECT COUNT(1)
               FROM {local_jurnalmengajar_suratizin} si
               WHERE si.timecreated BETWEEN :start AND :end";

if ($kelasfilter) {
    $countsql .= " AND si.kelasid = :kelas";
    $params['kelas'] = $kelasfilter;
}

if ($siswafilter) {
    $countsql .= " AND si.userid = :siswa";
    $params['siswa'] = $siswafilter;
}
if ($keperluanfilter) {
    $countsql .= " AND si.keperluan = :keperluan";
    $params['keperluan'] = $keperluanfilter;
}

$total = $DB->count_records_sql($countsql, $params);

// ================= QUERY DATA =================
$sql = "SELECT si.*, 
               u.lastname AS siswa, 
               gp.lastname AS gurupengajar, 
               pi.lastname AS penginput,
               si.kelasid
          FROM {local_jurnalmengajar_suratizin} si
          JOIN {user} u ON u.id = si.userid
          JOIN {user} gp ON gp.id = si.guru_pengajar
          JOIN {user} pi ON pi.id = si.penginput
         WHERE si.timecreated BETWEEN :start AND :end";

if ($kelasfilter) {
    $sql .= " AND si.kelasid = :kelas";
}

if ($siswafilter) {
    $sql .= " AND si.userid = :siswa";
}

if ($keperluanfilter) {
    $sql .= " AND si.keperluan = :keperluan";
}

$sql .= " ORDER BY si.timecreated DESC
          LIMIT $perpage OFFSET $offset";

$results = $DB->get_records_sql($sql, $params);

// =====================
// TAMPILKAN HALAMAN
// =====================
echo $OUTPUT->header();
echo $OUTPUT->heading('Rekap Surat Izin Murid');

// ================= FILTER FORM =================
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
echo html_writer::label('Keperluan', 'keperluan') . ' ';
echo html_writer::select([
    '' => 'Semua',
    'izin masuk' => 'Izin Masuk',
    'izin keluar' => 'Izin Keluar',
    'izin pulang' => 'Izin Pulang'
], 'keperluan', $keperluanfilter);
echo html_writer::end_div();

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

// =====================
// TABEL
// =====================
if ($results) {

    echo "<table class='generaltable'>";
    echo "<tr>
    <th>No</th>
    <th>Tanggal</th>
    <th>Nama Murid</th>
    <th>Kelas</th>
    <th>Guru Pengajar</th>
    <th>Alasan</th>
    <th>Keperluan</th>
    <th>Pengawas</th>
    </tr>";

    $no = $offset + 1;

    foreach ($results as $row) {
        $tanggal = tanggal_indo($row->timecreated);
        $kelas   = get_nama_kelas($row->kelasid);

        // WARNA
        switch (strtolower($row->keperluan)) {
            case 'izin masuk':
                $warna = '#b7d3b6';
                $label = 'Izin Masuk';
                break;
            case 'izin keluar':
                $warna = '#f3d6a4';
                $label = 'Izin Keluar';
                break;
            case 'izin pulang':
                $warna = '#e6b0bd';
                $label = 'Izin Pulang';
                break;
            default:
                $warna = '#f5f5f5';
                $label = $row->keperluan;
        }

        echo "<tr style='background:$warna'>";
        echo "<td>$no</td>";
        echo "<td>$tanggal</td>";
        echo "<td>" . format_nama_siswa($row->siswa) . "</td>";
        echo "<td>$kelas</td>";
        echo "<td>{$row->gurupengajar}</td>";
        echo "<td>{$row->alasan}</td>";
        echo "<td><b>$label</b></td>";
        echo "<td>{$row->penginput}</td>";
        echo "</tr>";

        $no++;
    }

    echo "</table>";

} else {
    echo $OUTPUT->notification('Tidak ada data surat izin pada filter ini.', 'notifymessage');
}
// =====================
// PAGING
// =====================
$baseurl = new moodle_url('/local/jurnalmengajar/rekap_surat_izin.php', [
    'kelas' => $kelasfilter,
    'siswaid' => $siswafilter,
    'dari' => $dari,
    'sampai' => $sampai,
    'keperluan' => $keperluanfilter
]);

echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);

echo $OUTPUT->footer();
