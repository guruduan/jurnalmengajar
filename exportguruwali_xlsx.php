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
$filename = $bulan && $tahun ? "jurnalguruwali_{$namabulan}_{$tahun}.xlsx" : "jurnal_guruwali_semua.xlsx";

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
$sheet->mergeCells('A1:G1')->setCellValue('A1', 'JURNAL GURU WALI');
$sheet->mergeCells('A2:G2')->setCellValue('A2', strtoupper($namasekolah));
$sheet->mergeCells('A3:G3')->setCellValue('A3', 'TAHUN AJARAN ' . $tahunajaran);
$sheet->getStyle('A1:G3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A1:G3')->getFont()->setBold(true)->setSize(14);

// Identitas
$sheet->setCellValue('B5', 'Nama Guru:');
$sheet->setCellValue('C5', $namaguru);
$sheet->setCellValue('B6', 'Bulan:');
$sheet->setCellValue('C6', $namabulan . ' ' . $tahun);

// ✅ Judul Kolom Data
$header = ['No', 'Hari Tanggal', 'Kelas', 'Nama Murid', 'Topik', 'Tindak Lanjut', 'Keterangan'];
$sheet->fromArray($header, NULL, 'A8');
$sheet->freezePane('A9');
$sheet->getStyle('A8:G8')->getFont()->setBold(true);
$sheet->getStyle('A8:G8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A8:G8')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle('A8:G8')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0FFFF'); // 💠 warna muda
$sheet->getStyle('A8:G8')->getAlignment()->setWrapText(true);

// =========================
// Ambil data jurnal (sesuai skema tabel)
// Filter: berdasarkan guruid = $USER->id
// =========================
$params = ['guruid' => $USER->id];
$wheres = ['jg.guruid = :guruid'];

if (!empty($bulan) && !empty($tahun)) {
    // rentang awal-akhir bulan
    $starttime = strtotime("first day of $tahun-$bulan 00:00:00");
    if ($bulan === '12') {
        $endtime = strtotime(($tahun + 1) . "-01-01 00:00:00");
    } else {
        $endtime = strtotime($tahun . '-' . str_pad(((int)$bulan + 1), 2, '0', STR_PAD_LEFT) . '-01 00:00:00');
    }
    $params['start'] = $starttime;
    $params['end']   = $endtime;
    $wheres[] = 'jg.timecreated >= :start AND jg.timecreated < :end';
}

// =========================
// Query utama: hanya ambil lastname untuk nama murid
// =========================
$sql = "
    SELECT
        jg.id,
        jg.guruid,
        jg.muridid,
        jg.topik,
        jg.tindaklanjut,
        jg.keterangan,
        jg.timecreated,
        u.lastname AS namamurid,
        (
            SELECT c2.name
            FROM {cohort_members} cm2
            JOIN {cohort} c2 ON c2.id = cm2.cohortid
            WHERE cm2.userid = jg.muridid
            ORDER BY c2.name ASC
            LIMIT 1
        ) AS kelas
    FROM {local_jurnalguruwali} jg
    JOIN {user} u ON u.id = jg.muridid
    WHERE " . implode(' AND ', $wheres) . "
    ORDER BY jg.timecreated ASC
";

$entries = $DB->get_records_sql($sql, $params);

// =========================
// Tulis isi data ke sheet
// =========================
$row = 9;
$no  = 1;

foreach ($entries as $e) {
    $tanggal = format_tanggal_indonesia($e->timecreated);

    $sheet->fromArray([
        $no++,
        $tanggal,
        $e->kelas ?? '-',
        $e->namamurid ?? '—',
        $e->topik ?? '',
        $e->tindaklanjut ?? '',
        $e->keterangan ?? ''
    ], NULL, "A{$row}");

    $sheet->getStyle("A{$row}:G{$row}")
        ->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
    $sheet->getStyle("A{$row}:G{$row}")
        ->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
    $sheet->getStyle("E{$row}:G{$row}")
        ->getAlignment()->setWrapText(true);

    $row++;
}

// ✅ Tanda tangan
$row += 2;

$sheet->setCellValue("B{$row}", 'Mengetahui');
$sheet->setCellValue("F{$row}", $tempat . ', ' . format_tanggal_indonesia(time()));

$row++;

$sheet->setCellValue("B{$row}", 'Kepala ' . $namasekolah);
$sheet->setCellValue("F{$row}", 'Guru Wali');

$row += 4;

$sheet->setCellValue("B{$row}", $namakepsek);
$sheet->setCellValue("F{$row}", $namaguru);

$row++;

$sheet->setCellValue("B{$row}", 'NIP ' . $nipkepsek);
$sheet->setCellValue("F{$row}", 'NIP ' . $nipguru);

// ✅ Auto width
foreach (range('A', 'G') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Output file
$filename = clean_filename($filename);

ob_clean();
flush();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
