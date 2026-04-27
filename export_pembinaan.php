<?php
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
$config = get_config('local_jurnalmengajar');

$nama_sekolah = $config->nama_sekolah ?? 'Nama Sekolah';
$tahun_ajaran = $config->tahun_ajaran ?? '';
$tempat       = $config->tempat_ttd ?? 'Tempat';
$nama_kepsek  = $config->nama_kepsek ?? 'Nama Kepala Sekolah';
$nip_kepsek   = $config->nip_kepsek ?? 'NIP';

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
$bulanfile = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $namabulan));
$filename = $bulan && $tahun 
    ? "pembinaan_{$bulanfile}_{$tahun}.xlsx" 
    : "pembinaan_semua.xlsx";

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();


// Judul
$sheet->mergeCells('A1:G1')->setCellValue('A1', 'LAPORAN PEMBINAAN SISWA');
$sheet->mergeCells('A2:G2')->setCellValue('A2', strtoupper($nama_sekolah));
$sheet->mergeCells('A3:G3')->setCellValue('A3', 'Tahun Ajaran '.$tahun_ajaran);
$sheet->getStyle('A1:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A2:A3')->getFont()->setBold(true)->setSize(12);

$sheet->setCellValue('B5', 'Bulan:');
$sheet->setCellValue('C5', $namabulan . ' ' . $tahun);

// Header kolom
$header = ['No','Hari Tanggal','Nama Murid','Kelas','Permasalahan','Upaya yang Dilakukan','Guru BK'];
$sheet->fromArray($header, NULL, 'A7');
$sheet->getStyle('A7:G7')->getFont()->setBold(true);
$sheet->getStyle('A7:G7')->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);
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

for ($i = 8; $i < $row; $i++) {
    $sheet->getRowDimension($i)->setRowHeight(-1);
}

// === Ambil NIP dari profile field user ===
$nipguru = $DB->get_field('user_info_data', 'data', [
    'userid' => $USER->id,
    'fieldid' => $DB->get_field('user_info_field', 'id', ['shortname' => 'nip'])
]) ?? '-';

// === Tanda tangan ===
$row += 2;
$sheet->setCellValue("B{$row}", 'Mengetahui');
$sheet->setCellValue("F{$row}", $tempat.', '.format_tanggal_indonesia(time()));
$row++;

$sheet->setCellValue("B{$row}", 'Kepala Sekolah');
$sheet->setCellValue("F{$row}", 'Guru BK');
$row += 4;

$sheet->setCellValue("B{$row}", $nama_kepsek);
$sheet->setCellValue("F{$row}", $DB->get_field('user','lastname',['id'=>$USER->id]));
$row++;

$sheet->setCellValue("B{$row}", 'NIP '.$nip_kepsek);
$sheet->setCellValue("F{$row}", 'NIP '.$nipguru);


// Auto width
foreach (['A','D','G'] as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Lebar kolom manual
$sheet->getColumnDimension('B')->setWidth(20); // tanggal
$sheet->getColumnDimension('C')->setWidth(25); // nama siswa
$sheet->getColumnDimension('E')->setWidth(30); // permasalahan
$sheet->getColumnDimension('F')->setWidth(30); // tindakan

$sheet->getStyle("E8:E{$row}")
    ->getAlignment()->setWrapText(true);

$sheet->getStyle("F8:F{$row}")
    ->getAlignment()->setWrapText(true);

$sheet->getStyle("E8:F{$row}")
    ->getAlignment()
    ->setVertical(Alignment::VERTICAL_TOP);    

// TAMBAHKAN DI SINI
$sheet->getStyle("A8:G{$row}")
    ->getAlignment()
    ->setVertical(Alignment::VERTICAL_TOP);
    
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
