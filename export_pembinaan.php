<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/../../config.php');
require_login();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);
global $DB, $USER;

function format_tanggal_indonesia($timestamp) {
    $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return $hari[date('w',$timestamp)] . ', ' . date('j',$timestamp) . ' ' . $bulan[date('n',$timestamp)-1] . ' ' . date('Y',$timestamp);
}

$bulan = optional_param('bulan', null, PARAM_TEXT);
$tahun = optional_param('tahun', null, PARAM_INT);

$bulanmap = [
    '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
    '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
];
$namabulan = $bulanmap[$bulan] ?? $bulan;
$filename = $bulan && $tahun ? "pembinaan_{$namabulan}_{$tahun}.xlsx" : "pembinaan_semua.xlsx";

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Judul
$sheet->mergeCells('A1:G1')->setCellValue('A1', 'LAPORAN PEMBINAAN SISWA');
$sheet->mergeCells('A2:G2')->setCellValue('A2', 'SMAN 2 KANDANGAN');
$sheet->mergeCells('A3:G3')->setCellValue('A3', 'TAHUN AJARAN 2025/2026');
$sheet->getStyle('A1:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A1:A3')->getFont()->setBold(true)->setSize(14);

$sheet->setCellValue('B5', 'Bulan:');
$sheet->setCellValue('C5', $namabulan . ' ' . $tahun);

// Header kolom
$header = ['No','Hari Tanggal','Nama Murid','Kelas','Permasalahan','Upaya yang Dilakukan','Guru BK'];
$sheet->fromArray($header, NULL, 'A7');
$sheet->getStyle('A7:G7')->getFont()->setBold(true);
$sheet->getStyle('A7:G7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A7:G7')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle('A7:G7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0FFFF');

// Data
if ($bulan && $tahun) {
    $start = strtotime("first day of $tahun-$bulan");
    $end   = strtotime("first day of ".($bulan=='12'?($tahun+1)."-01":$tahun."-".str_pad((int)$bulan+1,2,'0',STR_PAD_LEFT)));
    $records = $DB->get_records_select('local_jurnalpembinaan','timecreated BETWEEN ? AND ?',[$start,$end],'timecreated ASC');
} else {
    $records = $DB->get_records('local_jurnalpembinaan',null,'timecreated ASC');
}

$row=8;
$no=1;
foreach ($records as $r) {
    $murid = implode(', ', json_decode($r->peserta ?? '[]',true) ?? []);
    $kelas = $DB->get_field('cohort','name',['id'=>$r->kelas]) ?? '-';
    $gurubk = $DB->get_field('user','lastname',['id'=>$r->userid]) ?? '-';

    $sheet->fromArray([
        $no++, format_tanggal_indonesia($r->timecreated), $murid, $kelas,
        $r->permasalahan, $r->tindakan, $gurubk
    ], NULL, "A$row");

    $sheet->getStyle("A{$row}:G{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $row++;
}

// === Ambil NIP dari profile field user ===
$nipguru = $DB->get_field('user_info_data', 'data', [
    'userid' => $USER->id,
    'fieldid' => $DB->get_field('user_info_field', 'id', ['shortname' => 'nip'])
]) ?? '-';

// === Tanda tangan ===
$row += 2;
$sheet->setCellValue("B{$row}", 'Mengetahui');
$sheet->setCellValue("F{$row}", 'Hulu Sungai Selatan, '.format_tanggal_indonesia(time()));
$row++;
$sheet->setCellValue("B{$row}", 'Kepala SMAN 2 Kandangan');
$sheet->setCellValue("F{$row}", 'Guru BK');
$row += 4;
$sheet->setCellValue("B{$row}", 'Jainuddin, S.Ag., M.Pd.I');
$sheet->setCellValue("F{$row}", $DB->get_field('user','lastname',['id'=>$USER->id]));
$row++;
$sheet->setCellValue("B{$row}", 'NIP 19771005 200904 1 002');
$sheet->setCellValue("F{$row}", 'NIP '.$nipguru);


// Auto width
foreach(range('A','G') as $col){$sheet->getColumnDimension($col)->setAutoSize(true);}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
