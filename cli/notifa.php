<?php
define('CLI_SCRIPT', true);
require_once(__DIR__.'/../../../config.php');

$today   = date('Y-m-d');
$dayname = date('l'); // Monday, Tuesday...
$current = time();
$acuanfile = __DIR__.'/acuan.csv';

// ======== Map Hari Inggris -> Indonesia ========
$mapHari = [
    'Monday'    => 'Senin',
    'Tuesday'   => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday'  => 'Kamis',
    'Friday'    => 'Jumat',
    'Saturday'  => 'Sabtu',
    'Sunday'    => 'Minggu'
];
$hariIndo = $mapHari[$dayname] ?? $dayname;
$bulanIndo = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$tanggalIndo = date('j', strtotime($today)) . ' ' .
                $bulanIndo[(int)date('n', strtotime($today))] . ' ' .
                date('Y', strtotime($today));
$todayLabel = $hariIndo . ', ' . $tanggalIndo;


// ======== SETTING JAM PELAJARAN =========
$jam_pelajaran = [
    1 => ['07:30', '08:10'],
    2 => ['08:10', '08:50'],
    3 => ['08:50', '09:30'],
    4 => ['09:30', '10:10'],
    5 => ['10:25', '11:05'],
    6 => ['11:05', '11:45'],
    7 => ['11:45', '12:25'],
    8 => ['13:10', '13:50'],
    9 => ['13:50', '14:30'],
    10 => ['14:10', '15:10'],
    11 => ['15:10', '15:50']
];

// ====== Tentukan jam terakhir yang sudah selesai ======
$jam_terlewat = [];
foreach ($jam_pelajaran as $jamke => [$mulai, $selesai]) {
    if ($current > strtotime("$today $selesai")) $jam_terlewat[] = $jamke;
}
if (empty($jam_terlewat)) {
    mtrace("Belum ada jam pelajaran yang terlewat. Tidak ada notifikasi.");
    exit(0);
}
mtrace("Jam terlewat: " . implode(',', $jam_terlewat));

// ===== Ambil jurnal hari ini (buat key unik) =====
$starttoday = strtotime("$today 00:00:00");
$endtoday   = strtotime("$today 23:59:59");

$jurnaltoday = $DB->get_records_sql("
    SELECT CONCAT(userid,'-',id) AS uniqid, userid, jamke
      FROM {local_jurnalmengajar}
     WHERE timecreated BETWEEN :starttoday AND :endtoday",
    ['starttoday' => $starttoday, 'endtoday' => $endtoday]
);

$filled = [];
foreach ($jurnaltoday as $row) {
    foreach (explode(',', $row->jamke) as $j) {
        $j = (int)trim($j);
        $filled[$row->userid.'-'.$j] = true;
    }
}

// ====== Baca acuan.csv sesuai hari ======
if (!file_exists($acuanfile)) {
    mtrace("File acuan tidak ditemukan: $acuanfile");
    exit(1);
}
$handle = fopen($acuanfile, 'r');
$header = fgetcsv($handle); // skip header
$jadwal = [];
while (($data = fgetcsv($handle)) !== false) {
    list($hari, $userid, $lastname, $kelas, $jamkes) = $data;
    if (strcasecmp(trim($hari), $hariIndo) !== 0) continue;

    foreach (explode(',', $jamkes) as $jamke) {
        $jadwal[] = [
            'userid'   => (int)$userid,
            'lastname' => $lastname,
            'kelas'    => $kelas,
            'jamke'    => (int)trim($jamke)
        ];
    }
}
fclose($handle);

if (empty($jadwal)) {
    mtrace("Tidak ada jadwal di acuan untuk hari $hariIndo");
    exit(0);
}

// ===== Grouping per guru: [userid => [lastname, kelas => [jamke...]]] =====
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

// ===== Kirim WA per guru =====
$mengirim = 0;
foreach ($pending as $userid => $info) {
    $user = $DB->get_record('user', ['id'=>$userid], 'id, firstname, lastname');
    $nowa = $DB->get_field_sql("
        SELECT d.data FROM {user_info_data} d
        JOIN {user_info_field} f ON f.id = d.fieldid
        WHERE d.userid = :userid AND f.shortname = 'nowa'",
        ['userid' => $userid]
    );
    if (empty($nowa)) {
        mtrace("Tidak ada nomor WA untuk {$user->firstname} {$user->lastname}");
        continue;
    }
    $nomor = preg_replace('/[^0-9]/', '', $nowa);

   // Buat list kelas dan jam
$listkelas = "";
$ringkasParts = []; // <-- inisialisasi aman

if (!empty($info['kelasjam']) && is_array($info['kelasjam'])) {
    foreach ($info['kelasjam'] as $kelas => $jamlist) {
        $jamlist = array_map('intval', (array)$jamlist);
        sort($jamlist);
        $listkelas      .= "$kelas jam ke " . implode(',', $jamlist) . "\n";
        $ringkasParts[]  = $kelas . ':' . implode(',', $jamlist);
    }
}
$ringkas = implode('; ', $ringkasParts);


    $pesan = "Notifikasi server SiM❗ Bpk/Ibu Guru {$info['lastname']}, mohon maaf, pian belum mengisi jurnal mengajar "
           . "hari ini ($todayLabel) untuk kelas:\n"
           . $listkelas
           . "agar bisa diisi, _bisa 5 menit sebelum keluar dari kelas_\n"
           . "\nTerima kasih.\n_abaikan jika sudah mengisi_";

    $res = jurnalmengajar_kirim_wa($nomor, $pesan);
    mtrace("Kirim ke $nomor (Guru {$info['lastname']}) -> $res");
    $mengirim++;

  // === Tambahan log txt ===
    $logtxt = __DIR__ . '/notif_log_' . date('Y-m-d') . '.txt'; // per hari
    $line = date('Y-m-d H:i:s')
          . " | Guru: {$user->lastname}"
          . " | Nomor: $nomor"
          . " | Kelas/Jam: $ringkas"
          . " | Status: " . preg_replace("/\r|\n/", " ", (string)$res) . "\n";
    file_put_contents($logtxt, $line, FILE_APPEND);
}
mtrace("Selesai. Total notifikasi dikirim: $mengirim");

function jurnalmengajar_kirim_wa($nomor, $pesan) {
    global $CFG;
//    $apikey = 'ISI_APIKEY_WABLAS_ANDA';
    $apikey = '4L94T0YIsPSOmB1W3Q8Gzlj637DMLigCMrucozQjwVtvAd1JnkqulZT.zNYpFdMZ';
    $url = 'https://sby.wablas.com/api/v2/send-message';
    $data = ['data' => [[
        'phone' => $nomor,
        'message' => $pesan,
        'secret' => false,
        'priority' => false
    ]]];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: $apikey", "Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

    $response = curl_exec($ch);
    if (curl_errno($ch)) debugging('cURL error: ' . curl_error($ch), DEBUG_DEVELOPER);
    curl_close($ch);

    $logdir = $CFG->dataroot . '/logs';
    if (!file_exists($logdir)) mkdir($logdir, 0777, true);
    file_put_contents($logdir . '/wablas.log', date('Y-m-d H:i:s') .
        " | Ke: $nomor | Pesan: $pesan | Respon: $response\n", FILE_APPEND);
    return $response;
}
