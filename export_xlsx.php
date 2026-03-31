<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/vendor/autoload.php');
require_login();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

global $DB, $USER;

/* =========================
   Ambil Parameter
========================= */
$bulan = optional_param('bulan', null, PARAM_TEXT);
$tahun = optional_param('tahun', null, PARAM_INT);

$bulanmap = [
    '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April',
    '05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus',
    '09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
];

$namabulan = $bulanmap[$bulan] ?? '';
$filename = ($bulan && $tahun)
    ? "jurnal_KBM_{$namabulan}_{$tahun}.xlsx"
    : "jurnal_mengajar_semua.xlsx";

/* =========================
   Ambil Setting Sekolah
========================= */
$namasekolah = get_config('local_jurnalmengajar', 'nama_sekolah');
$tahunajaran = get_config('local_jurnalmengajar', 'tahun_ajaran');
$tempat      = get_config('local_jurnalmengajar', 'tempat_ttd');
$namakepsek  = get_config('local_jurnalmengajar', 'nama_kepsek');
$nipkepsek   = get_config('local_jurnalmengajar', 'nip_kepsek');

/* =========================
   Ambil Data Guru
========================= */
$namaguru = $USER->lastname;

$fieldidnip = $DB->get_field('user_info_field', 'id', ['shortname'=>'nip']);
$nipguru = $DB->get_field('user_info_data', 'data', [
    'userid'=>$USER->id,
    'fieldid'=>$fieldidnip
]) ?? '-';

/* =========================
   Ambil Data Jurnal
========================= */
if ($bulan && $tahun) {
    $starttime = strtotime("first day of $tahun-$bulan");
    $endtime = strtotime("first day of +1 month", $starttime);

    $entries = $DB->get_records_sql("
        SELECT * FROM {local_jurnalmengajar}
        WHERE userid = :userid
        AND timecreated >= :start
        AND timecreated < :end
        ORDER BY timecreated ASC
    ", [
        'userid'=>$USER->id,
        'start'=>$starttime,
        'end'=>$endtime
    ]);
} else {
    $entries = $DB->get_records('local_jurnalmengajar',
        ['userid'=>$USER->id],
        'timecreated ASC'
    );
}

/* =========================
   Buat Spreadsheet
========================= */
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

/* =========================
   Judul
========================= */
$sheet->mergeCells('A1:I1')->setCellValue('A1','JURNAL KEGIATAN BELAJAR MENGAJAR');
$sheet->mergeCells('A2:I2')->setCellValue('A2', strtoupper($namasekolah));
$sheet->mergeCells('A3:I3')->setCellValue('A3', 'TAHUN AJARAN '.$tahunajaran);

$sheet->getStyle('A1:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A1:A3')->getFont()->setBold(true)->setSize(14);

/* =========================
   Identitas
========================= */
$sheet->setCellValue('B5','Nama Guru:');
$sheet->setCellValue('C5',$namaguru);
$sheet->setCellValue('B6','Bulan:');
$sheet->setCellValue('C6',$namabulan.' '.$tahun);

/* =========================
   Header Tabel
========================= */
$header = ['No','Hari Tanggal','Kelas','Jam Ke','Mata Pelajaran','Materi','Aktivitas KBM','Absen','Keterangan'];
$sheet->fromArray($header, NULL, 'A8');

// Lebar kolom
$sheet->getColumnDimension('A')->setWidth(5);
$sheet->getColumnDimension('B')->setWidth(22);
$sheet->getColumnDimension('C')->setWidth(10);
$sheet->getColumnDimension('D')->setWidth(8);
$sheet->getColumnDimension('E')->setWidth(22);
$sheet->getColumnDimension('F')->setWidth(30);
$sheet->getColumnDimension('G')->setWidth(35);
$sheet->getColumnDimension('H')->setWidth(30);
$sheet->getColumnDimension('I')->setWidth(25);

// Wrap text
$sheet->getStyle('F:I')->getAlignment()->setWrapText(true);

$sheet->getStyle('A8:I8')->getFont()->setBold(true);
$sheet->getStyle('A8:I8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A8:I8')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle('A8:I8')->getFill()->setFillType(Fill::FILL_SOLID)
      ->getStartColor()->setRGB('E0FFFF');

/* =========================
   Isi Data
========================= */
$row = 9;
$no = 1;

foreach ($entries as $e) {

    $absenlist = [];
    $absendata = json_decode($e->absen, true);

    if (is_array($absendata)) {
        foreach ($absendata as $nama => $alasan) {
            $absenlist[] = "$nama: $alasan";
        }
    }

    $sheet->fromArray([
        $no++,
        tanggal_indo($e->timecreated,'judul'),
        get_nama_kelas($e->kelas),
        $e->jamke,
        $e->matapelajaran,
        $e->materi,
        $e->aktivitas,
        implode(', ', $absenlist),
        $e->keterangan
    ], NULL, "A$row");

    $sheet->getStyle("A{$row}:I{$row}")
          ->getBorders()->getAllBorders()
          ->setBorderStyle(Border::BORDER_THIN);

    $row++;
}

// Alignment isi tabel
$sheet->getStyle('A9:I'.$row)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

/* =========================
   Tanda Tangan
========================= */
$row += 2;

$sheet->setCellValue("B{$row}", 'Mengetahui');
$sheet->setCellValue("H{$row}", $tempat.', '.tanggal_indo(time(),'tanggal'));

$row++;
$sheet->setCellValue("B{$row}", 'Kepala '.$namasekolah);
$sheet->setCellValue("H{$row}", 'Guru Mata Pelajaran');

$row += 4;

$sheet->setCellValue("B{$row}", $namakepsek);
$sheet->setCellValue("H{$row}", $namaguru);

$row++;
$sheet->setCellValue("B{$row}", 'NIP '.$nipkepsek);
$sheet->setCellValue("H{$row}", 'NIP '.$nipguru);

/* =========================
   Output File
========================= */
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
