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
$sheet->mergeCells('A2:G2')->setCellValue('A2','SMAN 2 KANDANGAN');
$sheet->mergeCells('A3:G3')->setCellValue('A3',"Periode: {$namabulan}-{$tahun}");
$sheet->getStyle('A1:A3')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Header kolom
$header = ['No','Waktu','Kelas','Jenis Layanan','Topik','Peserta','Tindak Lanjut / Catatan'];
$sheet->fromArray($header,null,'A5');
$sheet->getStyle('A5:G5')->getFont()->setBold(true);
$sheet->getStyle('A5:G5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A5:G5')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle('A5:G5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0FFFF');

// Isi data
$row = 6; $no = 1;
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

// tanda tangan
$row += 2;
$sheet->setCellValue("B{$row}",'Mengetahui');
$sheet->setCellValue("F{$row}",'Hulu Sungai Selatan, '.format_tanggal_indonesia(time()));
$row++;
$sheet->setCellValue("B{$row}",'Kepala SMAN 2 Kandangan');
$sheet->setCellValue("F{$row}",'Guru BK');
$row+=4;
$sheet->setCellValue("B{$row}",'Jainuddin, S.Ag., M.Pd.I');
$sheet->setCellValue("F{$row}",$DB->get_field('user','lastname',['id'=>$USER->id]));
$row++;
$sheet->setCellValue("B{$row}",'NIP 19771005 200904 1 002');
$sheet->setCellValue("F{$row}",'NIP '.$nipguru);

// Auto width
foreach(range('A','G') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Output
$filename = "layananbk_{$namabulan}_{$tahun}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
