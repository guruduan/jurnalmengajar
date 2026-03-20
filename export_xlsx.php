<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/vendor/autoload.php');
require_login();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border; // ✅
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);
global $DB, $USER;

// Ambil setting identitas sekolah
$namasekolah = get_config('local_jurnalmengajar', 'nama_sekolah');
$tahunajaran = get_config('local_jurnalmengajar', 'tahun_ajaran');
$tempat      = get_config('local_jurnalmengajar', 'tempat_ttd');
$namakepsek  = get_config('local_jurnalmengajar', 'nama_kepsek');
$nipkepsek   = get_config('local_jurnalmengajar', 'nip_kepsek');


// ✅ Fungsi format tanggal Indonesia
function format_tanggal_indonesia($timestamp) {
    $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $namahari = $hari[date('w', $timestamp)];
    $tanggal = date('j', $timestamp);
    $namabulan = $bulan[date('n', $timestamp) - 1];
    $tahun = date('Y', $timestamp);
    return "$namahari, $tanggal $namabulan $tahun";
}

// Ambil parameter bulan & tahun
$bulan = optional_param('bulan', null, PARAM_TEXT);
$tahun = optional_param('tahun', null, PARAM_INT);

$bulanmap = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
    '04' => 'April', '05' => 'Mei', '06' => 'Juni',
    '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
];
$namabulan = $bulanmap[$bulan] ?? $bulan;
$filename = $bulan && $tahun ? "jurnal_KBM_{$namabulan}_{$tahun}.xlsx" : "jurnal_mengajar_semua.xlsx";

// Data guru
$guru = $DB->get_record_sql("SELECT u.lastname FROM {user} u
    JOIN {role_assignments} ra ON ra.userid = u.id
    JOIN {context} c ON c.id = ra.contextid
    JOIN {role} r ON r.id = ra.roleid
    WHERE r.shortname = 'gurujurnal' AND u.id = ?
    LIMIT 1", [$USER->id]);

$namaguru = $guru->lastname ?? 'Tidak ditemukan';
$nipguru = $DB->get_field('user_info_data', 'data', [
    'userid' => $USER->id,
    'fieldid' => $DB->get_field('user_info_field', 'id', ['shortname' => 'nip'])
]) ?? '**belum diisi**';

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// ✅ Style Judul Utama
$sheet->mergeCells('A1:I1')->setCellValue('A1', 'JURNAL KEGIATAN BELAJAR MENGAJAR');
$sheet->mergeCells('A2:I2')->setCellValue('A2', strtoupper($namasekolah));
$sheet->mergeCells('A3:I3')->setCellValue('A3', 'TAHUN AJARAN ' . $tahunajaran);
$sheet->getStyle('A1:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A1:A3')->getFont()->setBold(true)->setSize(14);

// Identitas
$sheet->setCellValue('B5', 'Nama Guru:');
$sheet->setCellValue('C5', $namaguru);
$sheet->setCellValue('B6', 'Bulan:');
$sheet->setCellValue('C6', $namabulan . ' ' . $tahun);
//senin
// ✅ Atur lebar kolom otomatis sesuai isi
foreach (range('A', 'I') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}
//senin

// ✅ Judul Kolom Data
$header = ['No', 'Hari Tanggal', 'Kelas', 'Jam Ke', 'Mata Pelajaran', 'Materi', 'Aktivitas KBM', 'Absen', 'Keterangan'];
$sheet->fromArray($header, NULL, 'A8');
$sheet->getStyle('A8:I8')->getFont()->setBold(true);
$sheet->getStyle('A8:I8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A8:I8')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle('A8:I8')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0FFFF'); // 💠 warna muda

// Ambil data jurnal
if ($bulan && $tahun) {
    $starttime = strtotime("first day of $tahun-$bulan");
    $endtime = strtotime("first day of " . ($bulan === '12' ? ($tahun + 1) . "-01" : "$tahun-" . str_pad((int)$bulan + 1, 2, '0', STR_PAD_LEFT)));
    $entries = $DB->get_records_sql("SELECT * FROM {local_jurnalmengajar}
        WHERE userid = :userid AND timecreated >= :start AND timecreated < :end
        ORDER BY timecreated ASC", [
        'userid' => $USER->id, 'start' => $starttime, 'end' => $endtime
    ]);
} else {
    $entries = $DB->get_records('local_jurnalmengajar', ['userid' => $USER->id], 'timecreated ASC');
}

// ✅ Isi data
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
    $absentext = implode(', ', $absenlist);
    $namakelas = $DB->get_field('cohort', 'name', ['id' => $e->kelas]) ?? $e->kelas;
    $tanggal = format_tanggal_indonesia($e->timecreated);

    $sheet->fromArray([
        $no++, $tanggal, $namakelas, $e->jamke, $e->matapelajaran,
        $e->materi, $e->aktivitas, $absentext, $e->keterangan
    ], NULL, "A$row");

    $sheet->getStyle("A{$row}:I{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle("A{$row}:I{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
    $row++;
}

// ✅ Tanda tangan
$row += 2;
$sheet->setCellValue("B{$row}", 'Mengetahui');
$sheet->setCellValue("H{$row}", $tempat . ', ' . format_tanggal_indonesia(time()));
$row++;
$sheet->setCellValue("B{$row}", 'Kepala ' . $namasekolah);
$sheet->setCellValue("H{$row}", 'Guru Mata Pelajaran');

$row += 4;

$sheet->setCellValue("B{$row}", $namakepsek);
$sheet->setCellValue("H{$row}", $namaguru);

$row++;

$sheet->setCellValue("B{$row}", 'NIP ' . $nipkepsek);
$sheet->setCellValue("H{$row}", 'NIP ' . $nipguru);

// ✅ Auto width
foreach (range('A', 'I') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Output file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
