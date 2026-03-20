<?php
require_once(__DIR__ . '/../../config.php');
require_login();

use core_user\fields;

$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);
global $DB, $USER;

// --- Fungsi bantu ---
function format_waktu_indonesia($timestamp) {
    $bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];

    $tanggal = (int)date('j', $timestamp);
    $bulan_index = (int)date('n', $timestamp) - 1;
    $tahun = date('Y', $timestamp);
    $hari_index = (int)date('w', $timestamp);

    return "{$hari[$hari_index]}, {$tanggal} {$bulan[$bulan_index]} {$tahun}";
}

function tanggal_indonesia_singkat($timestamp) {
    $bulan = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
        '04' => 'April',   '05' => 'Mei',      '06' => 'Juni',
        '07' => 'Juli',    '08' => 'Agustus',  '09' => 'September',
        '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
    ];
    $tanggal = date('d', $timestamp);
    $bulan_text = $bulan[date('m', $timestamp)];
    $tahun = date('Y', $timestamp);
    return "{$tanggal} {$bulan_text} {$tahun}";
}

// Ambil parameter GET bulan & tahun (jika ada)
$bulan = optional_param('bulan', null, PARAM_TEXT);  // contoh '06'
$tahun = optional_param('tahun', null, PARAM_INT);   // contoh 2025

// Penamaan file ekspor
$bulanmap = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
    '04' => 'April', '05' => 'Mei', '06' => 'Juni',
    '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
];

if ($bulan && $tahun) {
    $namabulan = $bulanmap[$bulan] ?? $bulan;
    $filename = "jurnal_{$namabulan}_{$tahun}.csv";
} else {
    $filename = "jurnal_mengajar_semua.csv";
}

header('Content-Type: text/csv; charset=utf-8');
header("Content-Disposition: attachment; filename=\"$filename\"");

$output = fopen('php://output', 'w');
//ahad
// 🔷 Judul Tengah 9 Kolom
fputcsv($output, ['','','','','JURNAL KEGIATAN BELAJAR MENGAJAR','','','','']);
fputcsv($output, ['','','','','SMAN 2 KANDANGAN','','','','']);
fputcsv($output, ['','','','','TAHUN AJARAN 2025/2026','','','','']);
fputcsv($output, []); // Baris kosong

// 🔶 Identitas Guru
$guru = $DB->get_record_sql("SELECT u.lastname
    FROM {user} u
    JOIN {role_assignments} ra ON ra.userid = u.id
    JOIN {context} c ON c.id = ra.contextid
    JOIN {role} r ON r.id = ra.roleid
    WHERE r.shortname = 'gurujurnal' AND u.id = ?
    LIMIT 1", [$USER->id]);

$namaguru = $guru->lastname ?? 'Tidak ditemukan';
fputcsv($output, ['', 'Nama Guru:', $namaguru]);         // Kolom 2: Nama Guru
//fputcsv($output, ['',  'Bulan:', date('F Y')]);       // Kolom 2: Bulan
$bulanIndonesia = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
// ahad $bulan = $bulanIndonesia[date('n')] . ' ' . date('Y');
$nomorbulan = date('n'); // 🔧 Tambahan
$tahun = date('Y');       // 🔧 Tambahan
$bulan = $bulanIndonesia[$nomorbulan] . ' ' . $tahun;

//ahad
fputcsv($output, ['', 'Bulan:', $bulan]);


fputcsv($output, []); // Baris kosong
//ahad
fputcsv($output, ['No', 'Tanggal', 'Kelas', 'Jam Ke', 'Mata Pelajaran', 'Materi', 'Aktivitas KBM', 'Absen', 'Keterangan']);

// Filter entri jurnal
// ahad if ($bulan && $tahun) {
//    $starttime = strtotime("first day of $tahun-$bulan");
//    $endtime = strtotime("first day of " . (($bulan === '12') ? ($tahun + 1) . "-01" : "$tahun-" . str_pad(((int)$bulan + 1), 2, '0', STR_PAD_LEFT)));
// ahad
// 🔧 Perbaikan filter agar pakai $nomorbulan dan $tahun
if ($nomorbulan && $tahun) {
    $starttime = strtotime("first day of $tahun-" . str_pad($nomorbulan, 2, '0', STR_PAD_LEFT));
    $endtime = strtotime("first day of " . ($nomorbulan == 12 ? ($tahun + 1) . "-01" : "$tahun-" . str_pad($nomorbulan + 1, 2, '0', STR_PAD_LEFT)));

//ahad
    $sql = "SELECT * FROM {local_jurnalmengajar}
            WHERE userid = :userid AND timecreated >= :start AND timecreated < :end
            ORDER BY timecreated ASC";
    $params = [
        'userid' => $USER->id,
        'start' => $starttime,
        'end' => $endtime
    ];
    $entries = $DB->get_records_sql($sql, $params);
} else {
    $entries = $DB->get_records('local_jurnalmengajar', ['userid' => $USER->id], 'timecreated ASC');
}

// Output isi jurnal
$no = 1;
foreach ($entries as $e) {
    // Decode absen JSON
    $absenlist = [];
    $absendata = json_decode($e->absen, true);
    if (is_array($absendata)) {
        foreach ($absendata as $nama => $alasan) {
            $absenlist[] = "$nama: $alasan";
        }
    }
    $absentext = implode(', ', $absenlist);

    $namakelas = $DB->get_field('cohort', 'name', ['id' => $e->kelas]) ?? $e->kelas;

    fputcsv($output, [
        $no++,
        format_waktu_indonesia($e->timecreated),
        $namakelas,
        $e->jamke,
        $e->matapelajaran,
        $e->materi,
        $e->aktivitas,
        $absentext,
        $e->keterangan
    ]);
}

// Tambahan tanda tangan di bawah
$user = core_user::get_user($USER->id);
$nipguru = $DB->get_field('user_info_data', 'data', [
    'userid' => $USER->id,
    'fieldid' => $DB->get_field('user_info_field', 'id', ['shortname' => 'nip'])
]) ?? '**belum diisi**';

$lokasi_tanggal = 'Kandangan, ' . tanggal_indonesia_singkat(time());

// Baris kosong dan tanda tangan
fputcsv($output, []);
fputcsv($output, []);
fputcsv($output, []);
fputcsv($output, ['', 'Mengetahui', '', '', '', '', '', $lokasi_tanggal]);
fputcsv($output, ['', 'Kepala Sekolah', '', '', '', '', '', 'Guru Mata Pelajaran']);
fputcsv($output, []);
fputcsv($output, []);
fputcsv($output, []);
fputcsv($output, ['', 'Jainuddin, S.Ag., M.Pd.I', '', '', '', '', '', $user->lastname]);
fputcsv($output, ['', 'NIP 19771005 200904 1 002', '', '', '', '', '', 'NIP ' . $nipguru]);

fclose($output);
exit;
