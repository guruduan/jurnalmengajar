<?php
require_once(__DIR__ . '/../../config.php');
require_login();

require_once($CFG->libdir . '/pdflib.php');

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);
$PAGE->set_context($context);


// Ambil parameter
$kelasid = required_param('kelas', PARAM_INT);
$siswaid = required_param('siswa', PARAM_INT);
$dari = strtotime(required_param('dari', PARAM_RAW));
$sampai = strtotime(required_param('sampai', PARAM_RAW)) + 86399;

// Ambil data kelas dan siswa
$kelas = $DB->get_record('cohort', ['id' => $kelasid], 'name');
$siswa = $DB->get_record('user', ['id' => $siswaid], 'firstname, lastname');
if (!$kelas || !$siswa) {
    print_error('Data tidak ditemukan');
}

$namakelas = format_string($kelas->name);
$namasiswa = ucwords(strtolower($siswa->lastname));

// Format tanggal Indonesia
$fmt = new IntlDateFormatter('id_ID', IntlDateFormatter::FULL, IntlDateFormatter::NONE, 'Asia/Makassar');

// Ambil jurnal
$jurnals = $DB->get_records_select('local_jurnalmengajar',
    'kelas = :kelas AND timecreated BETWEEN :dari AND :sampai',
    ['kelas' => $kelasid, 'dari' => $dari, 'sampai' => $sampai],
    'timecreated ASC'
);

// Siapkan PDF
$pdf = new pdf();
$pdf->SetTitle("Rekap Kehadiran - $namasiswa");
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 10);

// Judul
$html = "<h3>Rekap Ketidakhadiran Siswa</h3>";
$html .= "<p><strong>Nama:</strong> {$namasiswa}<br>";
$html .= "<strong>Kelas:</strong> {$namakelas}<br>";
$html .= "<strong>Rentang:</strong> " . date('d M Y', $dari) . " - " . date('d M Y', $sampai) . "</p>";

// Tabel
$html .= '<table border="1" cellpadding="4" cellspacing="0" width="100%">';
$html .= '<thead>
<tr style="font-weight:bold; background-color:#f0f0f0;">
    <th width="6%" align="center">No</th>
    <th width="24%">Hari dan Tanggal</th>
    <th width="10%" align="center">Jam ke</th>
    <th width="28%">Mata Pelajaran</th>
    <th width="20%">Guru Pengajar</th>
    <th width="12%">Absen</th>
</tr>
</thead><tbody>';


$no = 1;
foreach ($jurnals as $jurnal) {
    $absen = json_decode($jurnal->absen, true) ?? [];
    $alasan = null;

    foreach ($absen as $nama => $als) {
        if (strcasecmp(trim($nama), trim($siswa->lastname)) == 0) {
            $alasan = ucfirst(strtolower(trim($als)));
            break;
        }
    }

    if ($alasan) {
        $tanggal = $fmt->format($jurnal->timecreated);
        $jamke = $jurnal->jamke ?? '-';
        $matpel = $jurnal->matapelajaran ?? '-';

        $guru = $DB->get_record('user', ['id' => $jurnal->userid], 'firstname, lastname');
        $namaguru = $guru ? ucwords(strtolower($guru->lastname)) : '(tidak diketahui)';

        $html .= "<tr>
    <td align=\"center\" width=\"6%\">{$no}</td>
    <td width=\"24%\">{$tanggal}</td>
    <td align=\"center\" width=\"10%\">{$jamke}</td>
    <td width=\"28%\">{$matpel}</td>
    <td width=\"20%\">{$namaguru}</td>
    <td width=\"12%\">{$alasan}</td>
        </tr>";

        $no++;
    }
}

if ($no === 1) {
    $html .= '<tr><td colspan="6" align="center">Tidak ada data absen.</td></tr>';
}

$html .= '</tbody></table>';

// Output PDF
$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output("Rekap_{$namasiswa}.pdf", 'I');
