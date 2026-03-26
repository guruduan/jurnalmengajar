<?php
defined('MOODLE_INTERNAL') || die();
date_default_timezone_set('Asia/Makassar');

/**
 * Format tanggal Indonesia
 */
function tanggal_indo($timestamp = null, $mode = 'full') {
    $timestamp = $timestamp ?: time();

    $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $bulan = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];

    if ($mode == 'judul') {
        return $hari[date('w',$timestamp)] . ' ' .
               date('j',$timestamp) . ' ' .
               $bulan[date('n',$timestamp)] . ' ' .
               date('Y',$timestamp);
    }
if ($mode == 'tanggal') {
    return date('j',$timestamp) . ' ' .
           $bulan[date('n',$timestamp)] . ' ' .
           date('Y',$timestamp);
}
    if ($mode == 'jam') {
        return date('H:i', $timestamp);
    }

    return $hari[date('w',$timestamp)] . ', ' .
           date('j',$timestamp) . ' ' .
           $bulan[date('n',$timestamp)] . ' ' .
           date('Y',$timestamp) .
           ' Pukul ' . date('H:i',$timestamp) . ' WITA';
}

// nama murid
function format_nama_siswa($nama) {
    return ucwords(strtolower(trim($nama)));
}

/**
 * Ambil nama kelas dari ID cohort
 */
function get_nama_kelas($id) {
    global $DB;
    return $DB->get_field('cohort', 'name', ['id' => $id]) ?? "Kelas #$id";
}

/**
 * Ambil nomor WA user dari profile field nowa
 */
function get_user_nowa($userid) {
    global $DB;

    $sql = "SELECT d.data
              FROM {user_info_data} d
              JOIN {user_info_field} f ON f.id = d.fieldid
             WHERE d.userid = :userid
               AND f.shortname = :shortname";

    $nowa = $DB->get_field_sql($sql, [
        'userid' => $userid,
        'shortname' => 'nowa'
    ]);

    if (empty($nowa)) {
        debugging("Field nowa kosong untuk user: $userid", DEBUG_DEVELOPER);
        return null;
    }

    return preg_replace('/[^0-9]/', '', $nowa);
}

/**
 * Ambil nomor wali kelas dari mapping
 */
function get_nomor_wali_kelas($kelasid) {
    $json = get_config('local_jurnalmengajar', 'wali_kelas_mapping');
    $mapping = json_decode($json, true);

    if (empty($mapping[$kelasid])) {
        return null;
    }

    return get_user_nowa($mapping[$kelasid]);
}

/**
 * Ambil nomor kepala sekolah dari setting plugin
 */
function get_nomor_kepala_sekolah() {
    $nowa = get_config('local_jurnalmengajar', 'nomor_kepsek');

    if (empty($nowa)) {
        return null;
    }

    // bersihkan selain angka
    return preg_replace('/[^0-9]/', '', $nowa);
}

// fungsi hari sekolah
function jurnalmengajar_get_hari_sekolah() {
    $hari = get_config('local_jurnalmengajar', 'harisekolah');

    if (empty($hari)) {
        $hari = 'Senin,Selasa,Rabu,Kamis,Jumat';
    }

    $hari_array = explode(',', $hari);

    $result = [];
    foreach ($hari_array as $h) {
        $h = trim($h);
        $result[$h] = $h;
    }

    return $result;
}

//Fungsi urutan hari
function jurnalmengajar_get_urutan_hari() {
    $hari = get_config('local_jurnalmengajar', 'harisekolah');

    if (empty($hari)) {
        $hari = 'Senin,Selasa,Rabu,Kamis,Jumat';
    }

    $hari_array = explode(',', $hari);

    $urut = [];
    $no = 1;

    foreach ($hari_array as $h) {
        $urut[trim($h)] = $no;
        $no++;
    }

    return $urut;
}

//Fungsi hari ini
function jurnalmengajar_get_hari_ini() {
    $map = [
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu',
        'Sunday' => 'Minggu'
    ];

    return $map[date('l')] ?? '';
}
/**
 * Cek tanggal libur
 */
function jurnalmengajar_cek_libur($tanggal) {
    $tanggallibur = get_config('local_jurnalmengajar', 'tanggallibur');

    if (empty($tanggallibur)) return false;

    $lines = preg_split('/\r\n|\r|\n/', $tanggallibur);

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line == '') continue;

        if (stripos($line, 's/d') !== false) {
            list($start, $end) = explode('s/d', $line);
            $start = trim($start);
            $end   = trim($end);

            if ($tanggal >= $start && $tanggal <= $end) {
                return true;
            }
        } else {
            if ($tanggal == $line) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Cek boleh kirim WA atau tidak
 */
function jurnalmengajar_boleh_kirim_wa() {

    $hariIndoList = [
        1=>'Senin',2=>'Selasa',3=>'Rabu',
        4=>'Kamis',5=>'Jumat',6=>'Sabtu',7=>'Minggu'
    ];

    $hariSekolah = get_config('local_jurnalmengajar', 'harisekolah');
    $hariSekolah = array_map('trim', explode(',', $hariSekolah));

    if (!in_array($hariIndoList[(int)date('N')], $hariSekolah)) {
        return false;
    }

    if (jurnalmengajar_cek_libur(date('Y-m-d'))) {
        return false;
    }

    return true;
}

/**
 * Kirim WhatsApp via Wablas
 */
function jurnalmengajar_kirim_wa($tujuan, $pesan) {
    global $CFG;

    // Pastikan array
    if (!is_array($tujuan)) {
        $tujuan = [$tujuan];
    }

    // Hapus duplikat
    $tujuan = array_unique($tujuan);

    // Siapkan log
    $logdir = $CFG->dataroot . '/logs';
    if (!file_exists($logdir)) {
        mkdir($logdir, 0777, true);
    }

    $logfile = $logdir . '/wa_debug.log';

    // Cek boleh kirim WA
    if (!jurnalmengajar_boleh_kirim_wa()) {
        file_put_contents($logfile,
            date('Y-m-d H:i:s') . " | DIBATALKAN: Hari libur / bukan hari sekolah\n",
            FILE_APPEND
        );
        return false;
    }

    // Ambil config Wablas
    $apikey = get_config('local_jurnalmengajar', 'apikey');
    $secret = get_config('local_jurnalmengajar', 'secretkey');
    $wablas_url = get_config('local_jurnalmengajar', 'wablas_url');

    if (empty($apikey) || empty($secret)) {
        file_put_contents($logfile,
            date('Y-m-d H:i:s') . " | ERROR API KEY\n",
            FILE_APPEND
        );
        return false;
    }

    $token = $apikey . '.' . $secret;
file_put_contents($logfile,
    "----------------------------------------\n" .
    date('Y-m-d H:i:s') . " | Mulai kirim notifikasi\n",
    FILE_APPEND
);
file_put_contents($logfile,
    date('Y-m-d H:i:s') . " | Pesan: " . str_replace("\n"," | ",$pesan) . "\n",
    FILE_APPEND
);
foreach ($tujuan as $nomor) {

    if (empty($nomor)) {
        file_put_contents($logfile,
            date('Y-m-d H:i:s') . " | Nomor kosong, dilewati\n",
            FILE_APPEND
        );
        continue;
    }

    file_put_contents($logfile,
        date('Y-m-d H:i:s') . " | Kirim WA ke $nomor\n",
        FILE_APPEND
    );

    $data = [
        'data' => [[
            'phone' => $nomor,
            'message' => $pesan,
            'secret' => false,
            'priority' => false
        ]]
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
    CURLOPT_URL => $wablas_url,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            "Authorization: $token",
            "Content-Type: application/json"
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

        file_put_contents($logfile,
        date('Y-m-d H:i:s') . " | Response: $response\n",
        FILE_APPEND
    );
}

return true;
} // <-- PENUTUP fungsi jurnalmengajar_kirim_wa()


// ===============================
// Fungsi ambil beban mengajar guru
// ===============================
function jurnalmengajar_get_beban_jam_guru() {
    require_once(__DIR__.'/jadwal_acuan_lib.php');

    $jadwal = jurnalmengajar_get_jadwal_acuan();
    $beban = [];

    foreach ($jadwal as $j) {
        $userid = $j['userid'];

        if (!isset($beban[$userid])) {
            $beban[$userid] = 0;
        }

        $beban[$userid]++;
    }

    return $beban;
}//
// ===============================
// Ambil semua kelas (cohort)
// ===============================
function jurnalmengajar_get_all_kelas() {
    global $DB;

    $sql = "SELECT name FROM {cohort} ORDER BY name ASC";
    $records = $DB->get_records_sql($sql);

    $kelas = [];
    foreach ($records as $r) {
        $kelas[$r->name] = $r->name;
    }

    return $kelas;
}

// ===============================
// Ambil siswa dari kelas (cohort)
// ===============================
function jurnalmengajar_get_siswa_by_kelas($kelas) {
    global $DB;

    return $DB->get_records_sql("
        SELECT u.id, u.firstname, u.lastname
        FROM {user} u
        JOIN {cohort_members} cm ON cm.userid = u.id
        JOIN {cohort} c ON c.id = cm.cohortid
        WHERE c.name = ?
        ORDER BY u.lastname
    ", [$kelas]);
}

// ===============================
// Ambil NIS user dari profile field
// ===============================
function jurnalmengajar_get_nis_user($userid) {
    global $DB;

    return $DB->get_field_sql("
        SELECT d.data
        FROM {user_info_data} d
        JOIN {user_info_field} f ON f.id = d.fieldid
        WHERE f.shortname = 'nis' AND d.userid = ?
    ", [$userid]);
}
