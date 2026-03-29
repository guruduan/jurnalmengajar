<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/lib.php');
require_login();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);
global $DB, $USER;

// Setting sekolah
$namasekolah = get_config('local_jurnalmengajar', 'nama_sekolah');
$tahunajaran = get_config('local_jurnalmengajar', 'tahun_ajaran');
$tempat      = get_config('local_jurnalmengajar', 'tempat_ttd');
$namakepsek  = get_config('local_jurnalmengajar', 'nama_kepsek');
$nipkepsek   = get_config('local_jurnalmengajar', 'nip_kepsek');

// Nama guru
$namaguru = $DB->get_field('user', 'lastname', ['id'=>$USER->id]);

// NIP guru
$nipguru = $DB->get_field('user_info_data', 'data', [
    'userid' => $USER->id,
    'fieldid' => $DB->get_field('user_info_field', 'id', ['shortname' => 'nip'])
]);

// Parameter
$bulan = required_param('bulan', PARAM_INT);
$tahun = required_param('tahun', PARAM_INT);

$starttime = strtotime("$tahun-$bulan-01 00:00:00");
$endtime   = strtotime("+1 month", $starttime);

// Nama bulan Indonesia
$bulanindo = [
    1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',
    5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',
    9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
];
$namabulan = $bulanindo[(int)$bulan] . ' ' . $tahun;

// Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Judul
$sheet->mergeCells('A1:F1')->setCellValue('A1', 'JURNAL KEGIATAN EKSTRAKURIKULER');
$sheet->mergeCells('A2:F2')->setCellValue('A2', strtoupper($namasekolah));
$sheet->mergeCells('A3:F3')->setCellValue('A3', 'TAHUN AJARAN ' . $tahunajaran);

$sheet->getStyle('A1:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A1:A3')->getFont()->setBold(true);

// Identitas
$sheet->setCellValue('B5', 'Nama Guru');
$sheet->setCellValue('C5', ': '.$namaguru);
$sheet->setCellValue('B6', 'Bulan');
$sheet->setCellValue('C6', ': '.$namabulan);

// Header tabel
$header = ['No', 'Hari/Tanggal', 'Ekstrakurikuler', 'Materi', 'Tidak Hadir', 'Catatan'];
$sheet->fromArray($header, NULL, 'A8');

$sheet->getStyle('A8:F8')->getFont()->setBold(true);
$sheet->getStyle('A8:F8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A8:F8')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle('A8:F8')->getFill()->setFillType(Fill::FILL_SOLID)
      ->getStartColor()->setRGB('E0FFFF');

// Query
$sql = "SELECT j.*, e.namaekstra,
        GROUP_CONCAT(
            CASE 
                WHEN a.status <> 'Hadir' 
                THEN CONCAT(u.firstname, ' ', u.lastname, ' (', a.status, ')')
                ELSE NULL
            END
            SEPARATOR ', '
        ) AS absensi
        FROM {local_jm_ekstra_jurnal} j
        JOIN {local_jm_ekstra} e ON e.id = j.ekstraid
        LEFT JOIN {local_jm_ekstra_absen} a ON a.jurnalid = j.id
        LEFT JOIN {user} u ON u.id = a.userid
        WHERE j.pembinaid = :userid
        AND j.timecreated >= :awal
        AND j.timecreated < :akhir
        GROUP BY j.id
        ORDER BY j.tanggal ASC";

$params = [
    'userid' => $USER->id,
    'awal' => $starttime,
    'akhir' => $endtime
];

$data = $DB->get_records_sql($sql, $params);

// Isi data
$row = 9;
$no = 1;

foreach ($data as $d) {

    $absen = $d->absensi ? $d->absensi : '-';

    $sheet->fromArray([
        $no++,
        tanggal_indo($d->tanggal, 'judul'),
        $d->namaekstra,
        $d->materi,
        $absen,
        $d->catatan
    ], NULL, "A$row");

    $sheet->getStyle("A{$row}:F{$row}")
          ->getBorders()
          ->getAllBorders()
          ->setBorderStyle(Border::BORDER_THIN);

    $row++;
}

// Auto width
foreach (range('A', 'F') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Tanda tangan
$row += 2;

$sheet->setCellValue("B{$row}", 'Mengetahui');
$sheet->setCellValue("E{$row}", $tempat . ', ' . tanggal_indo(time(), 'tanggal'));
$row++;

$sheet->setCellValue("B{$row}", 'Kepala ' . $namasekolah);
$sheet->setCellValue("E{$row}", 'Guru Ekstrakurikuler');

$row += 4;

$sheet->setCellValue("B{$row}", $namakepsek);
$sheet->setCellValue("E{$row}", $namaguru);
$row++;

$sheet->setCellValue("B{$row}", 'NIP ' . $nipkepsek);
$sheet->setCellValue("E{$row}", 'NIP ' . $nipguru);

// Download
$filename = "Jurnal_Ekstrakurikuler_{$namabulan}.xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
