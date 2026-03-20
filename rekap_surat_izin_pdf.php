<?php
require_once('../../config.php');
require_login();
require_once($CFG->libdir.'/pdflib.php');

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

$kelasfilter = optional_param('kelas', 0, PARAM_INT);
$siswafilter = optional_param('siswaid', 0, PARAM_INT);
$dari = optional_param('dari', '', PARAM_RAW);
$sampai = optional_param('sampai', '', PARAM_RAW);

$starttime = $dari ? strtotime(str_replace('-', '/', $dari)) : 0;
$endtime = $sampai ? strtotime(str_replace('-', '/', $sampai)) + 86399 : time();

global $DB;

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

if ($kelasfilter) {
    $sql .= " AND si.kelasid = :kelas";
    $params['kelas'] = $kelasfilter;
}
if ($siswafilter) {
    $sql .= " AND si.userid = :siswa";
    $params['siswa'] = $siswafilter;
}

$results = $DB->get_records_sql($sql, $params);

// Inisialisasi PDF
$pdf = new pdf();
$pdf->SetTitle('Rekap Surat Izin');
$pdf->AddPage();

$html = '<h3 style="text-align:center;">Rekap Surat Izin</h3>';
$html .= '<table border="1" cellpadding="4">
<thead>
<tr>
<th width="5%">No</th>
<th width="13%">Tanggal</th>
<th width="20%">Nama Siswa</th>
<th width="12%">Kelas</th>
<th width="18%">Guru Pengajar</th>
<th width="17%">Alasan</th>
<th width="15%">Keperluan</th>
</tr>
</thead>
<tbody>';

$no = 1;
foreach ($results as $row) {
    $tanggal = userdate($row->timecreated, '%d-%m-%Y', 99, 'Asia/Makassar');
    $html .= '<tr>
        <td>'.$no++.'</td>
        <td>'.$tanggal.'</td>
        <td>'.ucwords(strtolower($row->siswa)).'</td>
        <td>'.$row->kelas.'</td>
        <td>'.$row->gurupengajar.'</td>
        <td>'.$row->alasan.'</td>
        <td>'.$row->keperluan.'</td>
    </tr>';
}
$html .= '</tbody></table>';

$pdf->writeHTML($html);
$pdf->Output('rekap_surat_izin.pdf', 'I');
