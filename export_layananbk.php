<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/jurnalmengajar/lib.php');

require_login();
$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$bulanmap = [
    '01' => 'Januari','02' => 'Februari','03' => 'Maret',
    '04' => 'April','05' => 'Mei','06' => 'Juni',
    '07' => 'Juli','08' => 'Agustus','09' => 'September',
    '10' => 'Oktober','11' => 'November','12' => 'Desember'
];

function format_tanggal_indonesia($timestamp) {
    $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli',
              'Agustus','September','Oktober','November','Desember'];
    return $hari[date('w',$timestamp)].', '.date('j',$timestamp).' '.$bulan[date('n',$timestamp)-1].' '.date('Y',$timestamp);
}

$bulan = optional_param('bulan', date('m'), PARAM_TEXT);
$tahun = optional_param('tahun', date('Y'), PARAM_INT);
$namabulan = $bulanmap[str_pad($bulan, 2, '0', STR_PAD_LEFT)] ?? $bulan;

$starttime = strtotime("first day of $tahun-$bulan");
$endtime   = strtotime("+1 month", $starttime);

global $DB, $USER;
$config = get_config('local_jurnalmengajar');

$nama_sekolah = $config->nama_sekolah ?? 'Nama Sekolah';
$tahun_ajaran = $config->tahun_ajaran ?? '';
$tempat       = $config->tempat_ttd ?? 'Tempat';
$nama_kepsek  = $config->nama_kepsek ?? 'Nama Kepala Sekolah';
$nip_kepsek   = $config->nip_kepsek ?? 'NIP';

$records = $DB->get_records_sql(
    "SELECT * FROM {local_jurnallayananbk} 
     WHERE timecreated >= :start AND timecreated < :end 
     ORDER BY timecreated ASC",
    ['start'=>$starttime, 'end'=>$endtime]
);

// ambil NIP
$fieldid = $DB->get_field('user_info_field', 'id', ['shortname' => 'nip']);
$nipguru = $fieldid ? $DB->get_field('user_info_data', 'data', [
    'userid' => $USER->id, 'fieldid' => $fieldid
]) : '-';

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Judul
$sheet->mergeCells('A1:G1')->setCellValue('A1','LAPORAN LAYANAN BK');
$sheet->mergeCells('A2:G2')->setCellValue('A2', mb_strtoupper($nama_sekolah,'UTF-8'));
$sheet->mergeCells('A3:G3')->setCellValue('A3','Tahun Ajaran '.$tahun_ajaran);
$sheet->mergeCells('A4:G4')->setCellValue('A4',"Periode: {$namabulan} {$tahun}");

$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A2:A3')->getFont()->setBold(true)->setSize(12);
$sheet->getStyle('A1:A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Header kolom
$header = ['No','Waktu','Kelas','Jenis Layanan','Topik','Peserta','Tindak Lanjut / Catatan'];
$sheet->fromArray($header,null,'A6');
$sheet->getStyle('A6:G6')->getFont()->setBold(true);
$sheet->getStyle('A6:G6')->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle('A6:G6')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle('A6:G6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0FFFF');

// Isi data
$row = 7; $no = 1;
foreach ($records as $r) {
    $kelas = $DB->get_field('cohort','name',['id'=>$r->kelas]) ?? '-';
    $peserta = json_decode($r->peserta,true);
    $peserta_str = is_array($peserta) ? implode(', ',$peserta) : $r->peserta;

    $sheet->fromArray([
        $no++, 
        format_tanggal_indonesia($r->timecreated),
        $kelas,
        $r->jenislayanan,
        $r->topik,
        $peserta_str,
        $r->tindaklanjut.' / '.$r->catatan
    ],null,"A$row");

    $sheet->getStyle("A{$row}:G{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle("A{$row}:G{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
    $row++;
}

for ($i = 7; $i < $row; $i++) {
    $sheet->getRowDimension($i)->setRowHeight(-1);
}

// tanda tangan
$row += 2;
$sheet->setCellValue("B{$row}",'Mengetahui');
$sheet->setCellValue("F{$row}",$tempat.', '.format_tanggal_indonesia(time()));
$row++;

$sheet->setCellValue("B{$row}",'Kepala Sekolah');
$sheet->setCellValue("F{$row}",'Guru BK');
$row += 4;

$sheet->setCellValue("B{$row}",$nama_kepsek);
$sheet->setCellValue("F{$row}",$DB->get_field('user','lastname',['id'=>$USER->id]));
$row++;

$sheet->setCellValue("B{$row}",'NIP '.$nip_kepsek);
$sheet->setCellValue("F{$row}",'NIP '.$nipguru);

// Auto width
foreach (['A','C'] as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// manual
$sheet->getColumnDimension('B')->setWidth(18);
$sheet->getColumnDimension('D')->setWidth(22);
$sheet->getColumnDimension('E')->setWidth(25);
$sheet->getColumnDimension('F')->setWidth(30);
$sheet->getColumnDimension('G')->setWidth(35);
    
$sheet->getStyle("E7:G{$row}")
    ->getAlignment()
    ->setWrapText(true)
    ->setVertical(Alignment::VERTICAL_TOP);
    
// Output
$bulanfile = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $namabulan));
$filename = "layananbk_{$bulanfile}_{$tahun}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
