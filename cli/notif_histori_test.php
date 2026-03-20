<?php
define('CLI_SCRIPT', true);   // <- WAJIB untuk script CLI Moodle
require_once(__DIR__.'/../../../config.php');

$today   = date('Y-m-d');
$dayname = date('l');

// 1. Ambil jadwal dari histori (24–25 Juli)
$starttime = strtotime('2025-07-24 00:00:00');
$endtime   = strtotime('2025-07-25 23:59:59');

$sqljadwal = "SELECT CONCAT(userid, '-', jamke) AS uniqid, userid, kelas, jamke
              FROM {local_jurnalmengajar}
              WHERE timecreated BETWEEN :starttime AND :endtime
                AND FROM_UNIXTIME(timecreated, '%W') = :dayname";

$paramsjadwal = [
    'starttime' => $starttime,
    'endtime'   => $endtime,
    'dayname'   => $dayname
];
$jadwal = $DB->get_records_sql($sqljadwal, $paramsjadwal);

// 2. Ambil jurnal hari ini
$starttoday = strtotime(date('Y-m-d 00:00:00'));
$endtoday   = strtotime(date('Y-m-d 23:59:59'));

$sqltoday = "SELECT CONCAT(userid, '-', jamke) AS uniqid, userid, jamke
             FROM {local_jurnalmengajar}
             WHERE timecreated BETWEEN :starttoday AND :endtoday";
$jurnaltoday = $DB->get_records_sql($sqltoday, [
    'starttoday' => $starttoday,
    'endtoday'   => $endtoday
]);

$filled = [];
foreach ($jurnaltoday as $row) {
    $filled[$row->userid.'-'.$row->jamke] = true;
}

$mengirim = 0;
foreach ($jadwal as $j) {
    if (!isset($filled[$j->userid.'-'.$j->jamke])) {
        $user = $DB->get_record('user', ['id'=>$j->userid], 'id, firstname, lastname, nowa');
        if (empty($user->nowa)) {
            mtrace("Tidak ada nomor WA untuk {$user->firstname} {$user->lastname}");
            continue;
        }
        $nomor = preg_replace('/[^0-9]/', '', $user->nowa);

        // ==== PESAN WA YANG BARU ====
        $pesan = "Bpk. Ibu, {$user->lastname}, mohon maaf, ini notif \n"
               . "Agar dapat mengisi jurnal mengajar hari $today untuk "
               . "kelas $j->kelas, jam ke-$j->jamke.\n"
               . "Terima kasih.\n"
               . "_abaikan pesan ini bila sudah mengisi jurnal mengajar_";

        // Kirim WA pakai fungsi yang sudah sukses
        $res = jurnalmengajar_kirim_wa($nomor, $pesan);
        mtrace("Kirim ke $nomor ({$user->firstname} {$user->lastname}) -> $res");
        $mengirim++;
    }
}

mtrace("Selesai. Total notifikasi dikirim: $mengirim");

// === FUNGSI WABLAS YANG SUDAH TERUJI ===
function jurnalmengajar_kirim_wa($nomor, $pesan) {
    global $CFG; // agar bisa akses moodledata
    $apikey = '4L94T0YIsPSOmB1W3Q8Gzlj637DMLigCMrucozQjwVtvAd1JnkqulZT.MQmnZVjd';
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

    // Optional: log ke file moodledata/logs/wablas.log
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
