<?php
require_once(__DIR__ . '/../../config.php');
require_login();
require_once($CFG->libdir . '/pdflib.php');
require_once($CFG->dirroot . '/local/jurnalmengajar/lib.php');

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);
$PAGE->set_context($context);

// ===== Params =====
$kelasid   = required_param('kelas', PARAM_INT);
$siswaid   = required_param('siswa', PARAM_INT);
$dariRaw   = required_param('dari', PARAM_RAW);
$sampaiRaw = required_param('sampai', PARAM_RAW);
$mode      = optional_param('mode', 'jam', PARAM_ALPHA); // 'jam' | 'hari'
$onlymine  = optional_param('onlymine', 0, PARAM_BOOL);
$matpel    = optional_param('matpel', '', PARAM_TEXT);

$dari = strtotime($dariRaw) ?: time();
$sampai = (strtotime($sampaiRaw) ?: time()) + 86399;

// ===== Data kelas & siswa =====
$kelas = $DB->get_record('cohort', ['id' => $kelasid], 'name');
$siswa = $DB->get_record('user', ['id' => $siswaid], 'firstname, lastname');
if (!$kelas || !$siswa) {
    print_error('Data tidak ditemukan');
}
$namakelas = $kelas->name;
$namasiswa  = ucwords(strtolower($siswa->lastname));

// ===== Util: normalisasi & prioritas (selaras halaman) =====
function normalize_status($s) {
    $s = strtolower(trim($s));
    $map = [
        'ijin' => 'ijin', 'izin' => 'ijin',
        'sakit' => 'sakit', 'skt' => 'sakit',
        'alpha' => 'alpa', 'alpa' => 'alpa', 'absen' => 'alpa',
        'disp' => 'dispensasi', 'dispen' => 'dispensasi', 'dispensasi' => 'dispensasi',
        'hadir' => 'hadir'
    ];
    return $map[$s] ?? $s;
}
// semakin besar => lebih dominan pada bentrok di hari yang sama
$priority = [
    'hadir'       => 0,
    'dispensasi'  => 1,
    'sakit'       => 2,
    'ijin'        => 3,
    'alpa'        => 4,
];

// ===== Ambil jurnal (hormati filter) =====
$params = ['kelas' => $kelasid, 'dari' => $dari, 'sampai' => $sampai];
$wheres = ['kelas = :kelas', 'timecreated BETWEEN :dari AND :sampai'];

if ($onlymine) {
    global $USER;
    $wheres[] = 'userid = :uid';
    $params['uid'] = $USER->id;
}
if ($matpel !== '') {
    // exact match; jika mau LIKE, ganti 2 baris di bawah dengan versi LIKE (lihat komentar)
    $wheres[] = 'matapelajaran = :matpel';
    $params['matpel'] = $matpel;
    // Alternatif LIKE:
    // $wheres[] = $DB->sql_like('matapelajaran', ':matpel', false, false);
    // $params['matpel'] = "%{$matpel}%";
}

$select = implode(' AND ', $wheres);
$jurnals = $DB->get_records_select('local_jurnalmengajar', $select, $params, 'timecreated ASC');

// ===== Siapkan PDF =====
$pdf = new pdf();
$pdf->SetTitle("Rekap Kehadiran - $namasiswa");
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 10);

// ===== Header =====
$html  = "<h3>Rekap Ketidakhadiran Murid</h3>";
$html .= "<p><strong>Nama:</strong> {$namasiswa}<br>";
$html .= "<strong>Kelas:</strong> {$namakelas}<br>";
$html .= "<strong>Rentang:</strong> " 
    . tanggal_indo($dari, 'judul') 
    . " - " 
    . tanggal_indo($sampai, 'judul') . "<br>";
$html .= "<strong>Mode:</strong> " . ($mode === 'hari' ? 'Per Hari (unik)' : 'Per Jam (jamke)');

$filters = [];
if ($onlymine) { $filters[] = 'Hanya jurnal saya'; }
if ($matpel !== '') { $filters[] = 'Matpel: ' . s($matpel); }
if ($filters) {
    $html .= "<br><em>" . implode(' | ', $filters) . "</em>";
}
$html .= "</p>";

// ===== Tabel =====
if ($mode === 'hari') {
    // --------- MODE PER HARI (1 baris per tanggal) ---------
    // Kumpulkan status dominan per tanggal + rincian (jamke-mapel-guru)
    $per_tanggal = []; // 'Y-m-d' => ['status'=>..., 'rincian'=>[[jamke,mapel,guru], ...]]
    foreach ($jurnals as $j) {
        $tglKey = date('Y-m-d', $j->timecreated);
        $absen = json_decode($j->absen, true);
if (!is_array($absen)) $absen = [];
        $statusJurnal = null;
        foreach ($absen as $nama => $als) {
            if (strcasecmp(trim($nama), trim($siswa->lastname)) == 0) {
                $statusJurnal = normalize_status($als);
                break;
            }
        }

        if (!isset($per_tanggal[$tglKey])) {
            $per_tanggal[$tglKey] = [
                'status'  => 'hadir',
                'rincian' => []
            ];
        }

        $guru = $DB->get_record('user', ['id' => $j->userid], 'firstname, lastname');
        $per_tanggal[$tglKey]['rincian'][] = [
            'jamke' => $j->jamke ?? '-',
            'mapel' => $j->matapelajaran ?? '-',
            'guru'  => $guru ? ucwords(strtolower($guru->lastname)) : '(tidak diketahui)'
        ];

        if ($statusJurnal && isset($priority[$statusJurnal])) {
            $old = $per_tanggal[$tglKey]['status'] ?? 'hadir';
            if ($priority[$statusJurnal] > ($priority[$old] ?? 0)) {
                $per_tanggal[$tglKey]['status'] = $statusJurnal;
            }
        }
    }

    ksort($per_tanggal);

    $html .= '<table border="1" cellpadding="4" cellspacing="0" width="100%">';
    $html .= '<thead>
<tr style="font-weight:bold; background-color:#f0f0f0;">
    <th width="8%"  align="center">No</th>
    <th width="32%">Hari dan Tanggal</th>
    <th width="18%" align="center">Status (dominan)</th>
    <th width="42%">Rincian Hari Itu</th>
</tr>
</thead><tbody>';

    $no = 1;
    $hari_tidak_hadir = 0;
    foreach ($per_tanggal as $tglKey => $info) {
        $tanggal = tanggal_indo(strtotime($tglKey), 'judul');
        $st = $info['status'];
        if ($st !== 'hadir') { $hari_tidak_hadir++; }

        $rincianParts = [];
        foreach ($info['rincian'] as $r) {
            $rincianParts[] = '[' . ($r['jamke'] ?: '-') . '] ' . ($r['mapel'] ?: '-') . ' (' . $r['guru'] . ')';
        }
        $rincian = $rincianParts ? implode('; ', $rincianParts) : '-';

        $html .= "<tr>
    <td align=\"center\" width=\"8%\">{$no}</td>
    <td width=\"32%\">{$tanggal}</td>
    <td align=\"center\" width=\"18%\">".ucfirst($st)."</td>
    <td width=\"42%\">{$rincian}</td>
</tr>";
        $no++;
    }

    if ($no === 1) {
        $html .= '<tr><td colspan="4" align="center">Tidak ada data.</td></tr>';
    }

    $html .= '</tbody></table>';
    $html .= '<p><strong>Jumlah Hari Murid tidak hadir: ' . $hari_tidak_hadir . ' hari</strong></p>';

} else {
    // --------- MODE PER JAM (baris per jurnal; hanya jika ≠ hadir) ---------
    $html .= '<table border="1" cellpadding="4" cellspacing="0" width="100%">';
    $html .= '<thead>
<tr style="font-weight:bold; background-color:#f0f0f0;">
    <th width="6%"  align="center">No</th>
    <th width="24%">Hari dan Tanggal</th>
    <th width="10%" align="center">Jam ke</th>
    <th width="28%">Mata Pelajaran</th>
    <th width="20%">Guru Pengajar</th>
    <th width="12%">Absen</th>
</tr>
</thead><tbody>';

    $no = 1;
    $totaljam = 0;

    foreach ($jurnals as $j) {
        $absen = json_decode($j->absen, true);
if (!is_array($absen)) $absen = [];
        $alasan = null;
        foreach ($absen as $nama => $als) {
            if (strcasecmp(trim($nama), trim($siswa->lastname)) == 0) {
                $alasan = normalize_status($als);
                break;
            }
        }

        // tampilkan hanya jika tidak 'hadir'
        if ($alasan && $alasan !== 'hadir') {
            $tanggal = tanggal_indo($j->timecreated, 'judul');
            $jamke   = $j->jamke ?? '-';
            $matpelj = $j->matapelajaran ?? '-';

            $jamlist  = array_filter(array_map('trim', explode(',', $jamke)));
            $totaljam += count($jamlist);

            $guru = $DB->get_record('user', ['id' => $j->userid], 'firstname, lastname');
            $namaguru = $guru ? ucwords(strtolower($guru->lastname)) : '(tidak diketahui)';

            $html .= "<tr>
    <td align=\"center\" width=\"6%\">{$no}</td>
    <td width=\"24%\">{$tanggal}</td>
    <td align=\"center\" width=\"10%\">{$jamke}</td>
    <td width=\"28%\">{$matpelj}</td>
    <td width=\"20%\">{$namaguru}</td>
    <td width=\"12%\">".ucfirst($alasan)."</td>
</tr>";
            $no++;
        }
    }

    if ($no === 1) {
        $html .= '<tr><td colspan="6" align="center">Tidak ada data absen.</td></tr>';
    }

    $html .= '</tbody></table>';
    $html .= '<p><strong>Jumlah Jam Murid tidak hadir: ' . ($totaljam ?? 0) . ' jam</strong></p>';
}

// ===== Output PDF =====
$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output("Rekap_{$namasiswa}.pdf", 'I');
