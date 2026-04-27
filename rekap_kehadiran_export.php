<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/phpspreadsheet/vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Ods;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

require_login();
$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

// ===== Params =====
$kelasid = required_param('kelas', PARAM_INT);
$dari    = strtotime(required_param('dari', PARAM_RAW));
$sampai  = strtotime(required_param('sampai', PARAM_RAW)) + 86399;
$format  = optional_param('format', 'xlsx', PARAM_ALPHA);
$mode    = optional_param('mode', 'jam', PARAM_ALPHA); // 'jam' | 'hari'

// ===== Data dasar =====
$kelasnama = $DB->get_field('cohort', 'name', ['id' => $kelasid]) ?? 'Kelas Tidak Diketahui';
$members   = $DB->get_records('cohort_members', ['cohortid' => $kelasid]);
$userids   = array_map(fn($m) => $m->userid, $members);

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

$jurnals = $DB->get_records_select('local_jurnalmengajar',
    'kelas = :kelas AND timecreated BETWEEN :dari AND :sampai',
    ['kelas' => $kelasid, 'dari' => $dari, 'sampai' => $sampai]
);

// ===== Util: normalisasi & prioritas status (selaras halaman) =====
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
// Semakin besar -> semakin dominan jika bentrok dalam 1 hari
$priority = [
    'hadir'       => 0,
    'dispensasi'  => 1,
    'sakit'       => 2,
    'ijin'        => 3,
    'alpa'        => 4,
];

// ===== Hitung =====
$data = [];
foreach ($users as $u) {
    $data[$u->id] = [
        'nama' => $u->lastname,
        'hadir' => 0, 'sakit' => 0, 'ijin' => 0, 'alpa' => 0, 'dispensasi' => 0
    ];
}

if ($mode === 'hari') {
    // === MODE PER HARI (UNIK) ===
    $all_dates = [];
    foreach ($jurnals as $j) {
        $all_dates[date('Y-m-d', $j->timecreated)] = true;
    }
    $uniqdates = array_keys($all_dates);
    sort($uniqdates);

    // default hadir untuk setiap siswa di setiap tanggal
    $perhari = [];
    foreach ($users as $uid => $u) {
        foreach ($uniqdates as $tgl) {
            $perhari[$uid][$tgl] = 'hadir';
        }
    }

    // isi status dari jurnal, pakai prioritas bila bentrok
    foreach ($jurnals as $j) {
        $tgl = date('Y-m-d', $j->timecreated);
        $absen = json_decode($j->absen, true) ?? [];
        if (empty($absen)) continue;

        $lookup = [];
        foreach ($absen as $nama => $alasan) {
            $lookup[mb_strtolower(trim($nama),'UTF-8')] = normalize_status($alasan);
        }

        foreach ($users as $uid => $u) {
            $namasiswa = mb_strtolower(trim($u->lastname), 'UTF-8');
            if (isset($lookup[$namasiswa]) && isset($priority[$lookup[$namasiswa]])) {
                $old = $perhari[$uid][$tgl] ?? 'hadir';
                $new = $lookup[$namasiswa];
                if ($priority[$new] > ($priority[$old] ?? 0)) {
                    $perhari[$uid][$tgl] = $new;
                }
            }
        }
    }

    // akumulasi 1x per tanggal
    foreach ($users as $uid => $u) {
        foreach ($uniqdates as $tgl) {
            $st = $perhari[$uid][$tgl] ?? 'hadir';
            if (!isset($data[$uid][$st])) $st = 'hadir';
            $data[$uid][$st] += 1;
        }
    }

    $total_unit = count($uniqdates); // total hari
    $unit_label = 'hari';

} else {
    // === MODE PER JAM (JAMKE) ===
    foreach ($jurnals as $j) {
        $jamke  = array_filter(array_map('trim', explode(',', (string)($j->jamke ?? ''))));
        $jmljam = count($jamke);
        $absen  = json_decode($j->absen, true) ?? [];

        foreach ($users as $uid => $u) {
            $namasiswa = trim($u->lastname);
            $found = false;
            foreach ($absen as $nama => $alasan) {
                if (strcasecmp(trim($nama), $namasiswa) === 0) {
                    $als = normalize_status($alasan);
                    if (isset($data[$uid][$als])) {
                        $data[$uid][$als] += $jmljam;
                    }
                    $found = true; break;
                }
            }
            if (!$found) {
                $data[$uid]['hadir'] += $jmljam;
            }
        }
    }

    // total jam = total terbesar (selaras tampilan)
    $total_unit = 0;
    foreach ($data as $d) {
        $total_unit = max($total_unit, $d['hadir'] + $d['sakit'] + $d['ijin'] + $d['alpa'] + $d['dispensasi']);
    }
    $unit_label = 'jam';
}

$config = get_config('local_jurnalmengajar');

$nama_sekolah = $config->nama_sekolah ?? 'Nama Sekolah';
$tahun_ajaran = $config->tahun_ajaran ?? '';
$tempat       = $config->tempat_ttd ?? 'Tempat';
$nama_kepsek  = $config->nama_kepsek ?? 'Nama Kepala Sekolah';
$nip_kepsek   = $config->nip_kepsek ?? 'NIP';

// ===== Spreadsheet =====
$sheet = new Spreadsheet();
$active = $sheet->getActiveSheet();
$active->setTitle("Rekap Kehadiran");

// Judul
$active->mergeCells("A1:H1");
$active->setCellValue("A1", "Rekap Kehadiran Siswa - {$nama_sekolah}");

$active->mergeCells("A2:H2");
$active->setCellValue("A2", "Kelas: {$kelasnama}");

$active->mergeCells("A3:H3");
$active->setCellValue("A3", "Tahun Ajaran: {$tahun_ajaran}");

$active->mergeCells("A4:H4");
$active->setCellValue("A4", "Periode: " . date('d-m-Y', $dari) . " s.d. " . date('d-m-Y', $sampai));


// Header tabel
$headers = ['No', 'Nama', 'Hadir', 'Sakit', 'Ijin', 'Alpa', 'Dispensasi', "Persentase (dari {$total_unit} {$unit_label})"];
$active->fromArray($headers, null, "A6");

// Format header
$lastCol = chr(ord('A') + count($headers) - 1); // A..H
$active->getStyle("A6:{$lastCol}6")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$active->getStyle("A6:{$lastCol}6")->getFont()->setBold(true);
$active->getStyle("A1:A4")->getFont()->setBold(true);
$active->getStyle("A1:A4")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Data
$row = 7; $no = 1;
foreach ($data as $uid => $d) {
    $total = $total_unit;

    // Format persen seperti di halaman: 96,4% dari 28 hari / 100% dari 28 hari
    if ($total > 0) {
        $p  = ($d['hadir'] / $total) * 100;
        $p1 = round($p, 1);
        $is_int = abs($p1 - round($p1)) < 1e-6;
        $pstr = $is_int ? (string)round($p1) : number_format($p1, 1, ',', '');
        $persen = $pstr . '% dari ' . $total . ' ' . $unit_label;
    } else {
        $persen = '-';
    }

    $active->setCellValue("A{$row}", $no++);
    $active->setCellValue("B{$row}", ucwords(strtolower($d['nama'])));
    $active->setCellValue("C{$row}", $d['hadir']);
    $active->setCellValue("D{$row}", $d['sakit']);
    $active->setCellValue("E{$row}", $d['ijin']);
    $active->setCellValue("F{$row}", $d['alpa']);
    $active->setCellValue("G{$row}", $d['dispensasi']);
    $active->setCellValue("H{$row}", $persen);
    $row++;
}

// Style alignment
$active->getStyle("A7:A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$active->getStyle("B7:B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$active->getStyle("C7:H{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
// Auto width kolom
foreach (range('A', 'H') as $col) {
    $active->getColumnDimension($col)->setAutoSize(true);
}
// TTD
global $USER;
$namaguru = $USER->lastname ?? 'Nama Guru';
$nipfieldid = $DB->get_field('user_info_field', 'id', ['shortname' => 'nip']);
$nipguru = $nipfieldid ? ($DB->get_field('user_info_data', 'data', ['userid' => $USER->id, 'fieldid' => $nipfieldid]) ?? 'NIP Tidak Ditemukan') : 'NIP Tidak Ditemukan';

$row += 2;
$active->setCellValue("H{$row}", $tempat . ', ' . date('d F Y'));
$row++;

$active->setCellValue("B{$row}", 'Mengetahui');
$active->setCellValue("H{$row}", 'Guru Mata Pelajaran');

$row += 4;

// Kepsek
$active->setCellValue("B{$row}", $nama_kepsek);
$active->setCellValue("H{$row}", $namaguru);
$row++;

$active->setCellValue("B{$row}", 'NIP ' . $nip_kepsek);
$active->setCellValue("H{$row}", 'NIP ' . $nipguru);

// ===== Output =====
// Bersihkan nama kelas (hapus spasi & karakter aneh)
$kelasfile = preg_replace('/[^a-zA-Z0-9]/', '_', $kelasnama);

// Rapikan underscore ganda
$kelasfile = preg_replace('/_+/', '_', $kelasfile);

// Hapus underscore di awal/akhir
$kelasfile = trim($kelasfile, '_');

// Format nama file
$filename = 'rekap_kehadiran_kelas_' . $kelasfile . '_' . date('Ymd') . '.' . $format;

if ($format === 'ods') {
    header('Content-Type: application/vnd.oasis.opendocument.spreadsheet');
} else {
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
}
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');

$writer = ($format === 'ods') ? new Ods($sheet) : new Xlsx($sheet);
$writer->save('php://output');
exit;
