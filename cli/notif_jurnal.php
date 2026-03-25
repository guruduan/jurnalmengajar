<?php
define('CLI_SCRIPT', true);
require_once(__DIR__.'/../../../config.php');

require_once(__DIR__.'/../jam_pelajaran_lib.php');
require_once(__DIR__.'/../jadwal_acuan_lib.php');
require_once(__DIR__.'/../lib.php'); // fungsi kirim WA

global $DB;

$today = date('Y-m-d');
$hariIndo = jurnalmengajar_get_hari_ini();
$current = time();
$todayLabel = date('d-m-Y');

// ===== Cek hari sekolah =====
$hariSekolah = get_config('local_jurnalmengajar', 'harisekolah');
$hariSekolah = array_map('trim', explode(',', $hariSekolah));

if (!in_array($hariIndo, $hariSekolah)) {
    mtrace("Hari $hariIndo bukan hari sekolah.");
    exit(0);
}

// ===== Cek tanggal libur =====
if (jurnalmengajar_cek_libur($today)) {
    mtrace("Hari ini tanggal libur.");
    exit(0);
}

mtrace("=== Notifikasi Jurnal Rekap ===");
mtrace("Hari: $hariIndo");

// ===== Ambil jam pelajaran =====
$jam_pelajaran = jurnalmengajar_generate_jam();

// ===== Tentukan jam yang sudah selesai =====
$jam_terlewat = [];
foreach ($jam_pelajaran as $jamke => $jam) {
    $selesai = $jam['selesai'];
    if ($current > strtotime("$today $selesai")) {
        $jam_terlewat[] = $jamke;
    }
}

if (empty($jam_terlewat)) {
    mtrace("Belum ada jam pelajaran yang terlewat.");
    exit(0);
}

mtrace("Jam terlewat: " . implode(',', $jam_terlewat));

// ===== Ambil jurnal hari ini =====
$starttoday = strtotime("$today 00:00:00");
$endtoday   = strtotime("$today 23:59:59");

$jurnaltoday = $DB->get_records_sql("
    SELECT userid, jamke
    FROM {local_jurnalmengajar}
    WHERE timecreated BETWEEN :starttoday AND :endtoday
", [
    'starttoday' => $starttoday,
    'endtoday'   => $endtoday
]);

$filled = [];
foreach ($jurnaltoday as $row) {
    foreach (explode(',', $row->jamke) as $j) {
        $j = (int)trim($j);
        $filled[$row->userid.'-'.$j] = true;
    }
}

// ===== Ambil jadwal dari database =====
$jadwal_db = $DB->get_records_sql("
    SELECT j.userid, j.kelas, j.jamke, u.lastname
    FROM {local_jurnalmengajar_jadwal} j
    JOIN {user} u ON u.id = j.userid
    WHERE j.hari = :hari
", [
    'hari' => $hariIndo
]);

$jadwal = [];

foreach ($jadwal_db as $j) {
    $jadwal[] = [
        'userid'   => $j->userid,
        'lastname' => $j->lastname,
        'kelas'    => $j->kelas,
        'jamke'    => $j->jamke
    ];
}

if (empty($jadwal)) {
    mtrace("Tidak ada jadwal di database untuk hari $hariIndo");
    exit(0);
}

// ===== Group jurnal yang belum diisi =====
$pending = [];

foreach ($jadwal as $j) {

    if (!in_array($j['jamke'], $jam_terlewat)) continue;
    if (isset($filled[$j['userid'].'-'.$j['jamke']])) continue;

    if (!isset($pending[$j['userid']])) {
        $pending[$j['userid']] = [
            'lastname' => $j['lastname'],
            'kelasjam' => []
        ];
    }

    if (!isset($pending[$j['userid']]['kelasjam'][$j['kelas']])) {
        $pending[$j['userid']]['kelasjam'][$j['kelas']] = [];
    }

    $pending[$j['userid']]['kelasjam'][$j['kelas']][] = $j['jamke'];
}

if (empty($pending)) {
    mtrace("Semua jurnal sudah diisi.");
    exit(0);
}
// ===== Kirim WA per guru =====
$mengirim = 0;

foreach ($pending as $userid => $info) {

    $user = $DB->get_record('user', ['id'=>$userid], 'id, firstname, lastname');

    // Ambil nomor WA
    $nowa = $DB->get_field_sql("
        SELECT d.data
        FROM {user_info_data} d
        JOIN {user_info_field} f ON f.id = d.fieldid
        WHERE d.userid = :userid AND f.shortname = 'nowa'
    ", ['userid' => $userid]);

    if (empty($nowa)) {
        mtrace("Tidak ada nomor WA untuk {$info['lastname']}");
        continue;
    }

    $nomor = preg_replace('/[^0-9]/', '', $nowa);

    // Urutkan berdasarkan jam pertama
$urut = [];

foreach ($info['kelasjam'] as $kelas => $jamlist) {
    sort($jamlist);
    $urut[$kelas] = $jamlist;
}

// Sort berdasarkan jam pertama
uasort($urut, function($a, $b) {
    return $a[0] <=> $b[0];
});

$listkelas = "";
$ringkasParts = [];

foreach ($urut as $kelas => $jamlist) {
    $listkelas .= "$kelas jam ke " . implode(',', $jamlist) . "\n";
    $ringkasParts[] = $kelas . ':' . implode(',', $jamlist);
}

    $ringkas = implode('; ', $ringkasParts);

    $pesan = "Notifikasi SiM ❗\n"
           . "Bpk/Ibu Guru {$info['lastname']}, mohon mengisi jurnal mengajar hari ini ($todayLabel) untuk:\n"
           . $listkelas
           . "\nTerima kasih.\n_abaikan jika sudah mengisi_";

    $res = jurnalmengajar_kirim_wa($nomor, $pesan);

    mtrace("Kirim ke $nomor ({$info['lastname']}) -> $res");
    $mengirim++;

    // ===== Log TXT =====
    $logtxt = __DIR__ . '/notif_log_' . date('Y-m-d') . '.txt';

    $line = date('Y-m-d H:i:s')
          . " | Guru: {$info['lastname']}"
          . " | Nomor: $nomor"
          . " | Kelas/Jam: $ringkas"
          . " | Status: " . preg_replace("/\r|\n/", " ", (string)$res)
          . "\n";

    file_put_contents($logtxt, $line, FILE_APPEND);
}

mtrace("Selesai. Total notifikasi dikirim: $mengirim");
