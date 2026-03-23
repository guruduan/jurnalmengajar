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

    if ($mode == 'jam') {
        return date('H:i', $timestamp);
    }

    return $hari[date('w',$timestamp)] . ', ' .
           date('j',$timestamp) . ' ' .
           $bulan[date('n',$timestamp)] . ' ' .
           date('Y',$timestamp) .
           ' Pukul ' . date('H:i',$timestamp) . ' WITA';
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
function jurnalmengajar_kirim_wa($nomor, $pesan) {
    global $CFG;

    $logdir = $CFG->dataroot . '/logs';
    if (!file_exists($logdir)) {
        mkdir($logdir, 0777, true);
    }

    $logfile = $logdir . '/wa_debug.log';

    file_put_contents($logfile,
        date('Y-m-d H:i:s') . " | Kirim WA ke $nomor\n",
        FILE_APPEND
    );

    if (!jurnalmengajar_boleh_kirim_wa()) {
        file_put_contents($logfile,
            date('Y-m-d H:i:s') . " | DIBATALKAN: Hari libur\n",
            FILE_APPEND
        );
        return false;
    }

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

    return $response;
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
 * Notifikasi WA Jurnal KBM
 */
function jurnalmengajar_notifikasi_wa($data, $user) {

    $kelasid = $data->kelas ?? null;
    if (!$kelasid) return;

    $namaguru = !empty($user->lastname) ? $user->lastname : $user->firstname;
    $kelas = get_nama_kelas($kelasid);

    $jamke = $data->jamke ?? '-';
    $mapel = $data->matapelajaran ?? '-';
    $materi = $data->materi ?? '-';
    $aktivitas = $data->aktivitas ?? '-';

    $sekolah = get_config('local_jurnalmengajar', 'nama_sekolah') ?: 'Nama Sekolah';

    $tanggal = tanggal_indo(time(), 'judul');
    $jam = tanggal_indo(time(), 'jam');

    $pesan = "*📘 Jurnal KBM $tanggal*\n\n"
       . "👤 Guru: $namaguru\n"
       . "🏫 Kelas: $kelas\n"
       . "⏰ Jam ke: $jamke\n"
       . "📚 Mata Pelajaran: $mapel\n"
       . "📒 Materi: $materi\n"
       . "📝 Aktivitas:\n$aktivitas\n\n"
       . "🕒 Waktu: $jam WITA\n"
       . "📌 Tercatat di eJurnal KBM $sekolah";

    $nomor_guru = get_user_nowa($user->id);
    $nomor_wali = get_nomor_wali_kelas($kelasid);

    $tujuan = [];

    if ($nomor_guru) $tujuan[] = $nomor_guru;
    if ($nomor_wali && $nomor_wali != $nomor_guru) $tujuan[] = $nomor_wali;

    foreach ($tujuan as $no) {
        jurnalmengajar_kirim_wa($no, $pesan);
    }
}
