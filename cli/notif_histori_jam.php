<?php
define('CLI_SCRIPT', true);
require_once(__DIR__.'/../../../config.php');

$today   = date('Y-m-d');
$dayname = date('l');
$current = time();

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
    $selesai_ts = strtotime("$today $selesai");
    if ($current > $selesai_ts) {
        $jam_terlewat[] = $jamke;
    }
}
if (empty($jam_terlewat)) {
    mtrace("Belum ada jam pelajaran yang terlewat. Tidak ada notifikasi.");
    exit(0);
}
mtrace("Jam terlewat: " . implode(',', $jam_terlewat));

// ===== Ambil pola jadwal (2 minggu ke belakang) =====
$starttime = strtotime('-14 days 00:00:00');
$endtime   = strtotime('today 23:59:59');
$sqljadwal = "SELECT DISTINCT CONCAT(userid,'-',jamke) AS uniqid,
                             userid, jamke
                FROM {local_jurnalmengajar}
               WHERE timecreated BETWEEN :starttime AND :endtime
                 AND FROM_UNIXTIME(timecreated, '%W') = :dayname";
$paramsjadwal = [
    'starttime' => $starttime,
    'endtime'   => $endtime,
    'dayname'   => $dayname
];
$jadwal = $DB->get_records_sql($sqljadwal, $paramsjadwal);

// ===== Ambil jurnal hari ini =====
$starttoday = strtotime("$today 00:00:00");
$endtoday   = strtotime("$today 23:59:59");
$sqltoday = "SELECT userid, jamke
               FROM {local_jurnalmengajar}
              WHERE timecreated BETWEEN :starttoday AND :endtoday";
$jurnaltoday = $DB->get_records_sql($sqltoday, [
    'starttoday' => $starttoday,
    'endtoday'   => $endtoday
]);

$filled = [];
foreach ($jurnaltoday as $row) {
    // Pecah jamke yang berisi multiple angka
    $jamkes = explode(',', $row->jamke);
    foreach ($jamkes as $jk) {
        $jk = trim($jk);
        if ($jk !== '') {
            $filled[$row->userid.'-'.$jk] = true;
        }
    }
}

// ===== Kirim WA hanya untuk jam yang terlewat dan belum isi =====
$mengirim = 0;
foreach ($jadwal as $j) {
    // Jamke di histori pola jadwal juga bisa lebih dari satu
    $jamkesjadwal = explode(',', $j->jamke);
    foreach ($jamkesjadwal as $jamke) {
        $jamke = trim($jamke);
        if ($jamke === '' || !in_array((int)$jamke, $jam_terlewat)) {
            continue; // hanya kirim untuk jam yang sudah lewat
        }
        if (!isset($filled[$j->userid.'-'.$jamke])) {

            // Ambil user
            $user = $DB->get_record('user', ['id'=>$j->userid], 'id, firstname, lastname');

            // Ambil nomor WA
            $nowa = $DB->get_field_sql("
                SELECT d.data
                  FROM {user_info_data} d
                  JOIN {user_info_field} f ON f.id = d.fieldid
                 WHERE d.userid = :userid AND f.shortname = 'nowa'",
                ['userid' => $j->userid]
            );
            if (empty($nowa)) {
                mtrace("Tidak ada nomor WA untuk {$user->firstname} {$user->lastname}");
                continue;
            }
            $nomor = preg_replace('/[^0-9]/', '', $nowa);

            // Ambil nama kelas dari cohort
            $kelas = $DB->get_field_sql("
                SELECT c.name
                  FROM {local_jurnalmengajar} lj
                  JOIN {cohort} c ON c.id = lj.kelas
                 WHERE lj.userid = :userid
                   AND lj.timecreated BETWEEN :starttime AND :endtime
              ORDER BY lj.timecreated DESC
                 LIMIT 1",
                [
                    'userid'    => $j->userid,
                    'starttime' => $starttime,
                    'endtime'   => $endtime
                ]
            );
            if (empty($kelas)) {
                $kelas = 'Tidak diketahui';
            }

            $pesan = "Bpk. Ibu, {$user->lastname}, mohon maaf, ini notif\n"
                   . "Agar dapat mengisi jurnal mengajar hari $today untuk kelas $kelas, jam ke-$jamke.\n"
                   . "Terima kasih.\n"
                   . "_abaikan pesan ini bila sudah mengisi jurnal mengajar_";

            $res = jurnalmengajar_kirim_wa($nomor, $pesan);
            mtrace("Kirim ke $nomor ({$user->firstname} {$user->lastname}) -> $res");
            $mengirim++;
        }
    }
}

mtrace("Selesai. Total notifikasi dikirim: $mengirim");

// ==== FUNGSI WA ====
function jurnalmengajar_kirim_wa($nomor, $pesan) {
    global $CFG;
    $apikey = '4L94T0YIsPSOmB1W3Q8Gzlj637DMLigCMrucozQjwVtvAd1JnkqulZT.MQmnZV';
    $url = 'https://sby.wablas.com/api/v2/send-message';

    $data = ['data' => [[
        'phone' => $nomor,
        'message' => $pesan,
        'secret' => false,
        'priority' => false
    ]]];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: $apikey",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        debugging('cURL error: ' . curl_error($ch), DEBUG_DEVELOPER);
    }
    curl_close($ch);

    $logdir = $CFG->dataroot . '/logs';
    if (!file_exists($logdir)) {
        mkdir($logdir, 0777, true);
    }
    file_put_contents(
        $logdir . '/wablas.log',
        date('Y-m-d H:i:s') . " | Ke: $nomor | Pesan: $pesan | Respon: $response\n",
        FILE_APPEND
    );

    return $response;
}
