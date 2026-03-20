<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

// Ambil parameter
$kelasid = required_param('kelas', PARAM_INT);
$dari = strtotime(required_param('dari', PARAM_RAW));
$sampai = strtotime(required_param('sampai', PARAM_RAW)) + 86399;
$format = optional_param('format', 'xlsx', PARAM_ALPHA);

require_once($CFG->dirroot . '/local/jurnalmengajar/vendor/autoload.php'); // PhpSpreadsheet
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Ambil nama cohort
$cohort = $DB->get_record('cohort', ['id' => $kelasid], '*', MUST_EXIST);

// Ambil anggota cohort
$members = $DB->get_records('cohort_members', ['cohortid' => $kelasid]);
$userids = array_map(fn($m) => $m->userid, $members);
if (empty($userids)) {
    print_error('Tidak ada murid dalam kelas ini.');
}

list($in_sql, $params) = $DB->get_in_or_equal($userids);
$users = $DB->get_records_sql("
    SELECT id, firstname, lastname
    FROM {user}
    WHERE id $in_sql
    ORDER BY lastname ASC, firstname ASC
", $params);

// Ambil data jurnal dari tabel baru
$jurnals = $DB->get_records_select('local_jurnalpramuka',
    'kelas = :kelas AND timecreated BETWEEN :dari AND :sampai',
    ['kelas' => $cohort->name, 'dari' => $dari, 'sampai' => $sampai]
);

// Hitung data kehadiran
$data = [];
foreach ($users as $u) {
    $data[$u->id] = ['hadir' => 0, 'sakit' => 0, 'ijin' => 0, 'alpa' => 0, 'dispensasi' => 0];
}

foreach ($jurnals as $jurnal) {
    $absen = json_decode($jurnal->absen, true) ?? [];
    foreach ($users as $uid => $u) {
        $namasiswa = trim($u->lastname);
        $found = false;
        foreach ($absen as $nama => $alasan) {
            if (strcasecmp(trim($nama), $namasiswa) == 0) {
                $alasan = strtolower(trim($alasan));
                if (isset($data[$uid][$alasan])) {
                    $data[$uid][$alasan]++;
                }
                $found = true;
                break;
            }
        }
        if (!$found) {
            $data[$uid]['hadir']++;
        }
    }
}

// Buat Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Rekap Pramuka');

// Header
$sheet->fromArray(['No', 'Nama Murid', 'Hadir', 'Sakit', 'Ijin', 'Alpa', 'Dispensasi', 'Persentase'], NULL, 'A1');

// Isi data
$row = 2;
$no = 1;
foreach ($data as $uid => $d) {
    $total = array_sum($d);
    $persen = $total > 0 ? round(($d['hadir'] / $total) * 100, 1) . '%' : '-';
    $namasiswa = ucwords(strtolower($users[$uid]->lastname));

    $sheet->fromArray([
        $no++, $namasiswa,
        $d['hadir'], $d['sakit'], $d['ijin'], $d['alpa'], $d['dispensasi'],
        $persen
    ], NULL, "A$row");
    $row++;
}

// Style sederhana
foreach (range('A', 'H') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}
$sheet->getStyle('A1:H1')->getFont()->setBold(true);

// Output file
$filename = 'Rekap_Pramuka_' . preg_replace('/\s+/', '_', $cohort->name) . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
