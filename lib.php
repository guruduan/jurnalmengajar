<?php
defined('MOODLE_INTERNAL') || die();
date_default_timezone_set('Asia/Makassar');

/**
 * Ambil nama kelas dari ID cohort
 */
function get_nama_kelas($id) {
    global $DB;
    return $DB->get_field('cohort', 'name', ['id' => $id]) ?? "Kelas #$id";
}

/**
 * Ambil nowa langsung dari database
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
 * Format waktu Indonesia (GLOBAL)
 */
function format_waktu_indo($timestamp) {
    $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $bulan = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];

    return $hari[date('w',$timestamp)] . ', ' .
           date('j',$timestamp) . ' ' .
           $bulan[date('n',$timestamp)] . ' ' .
           date('Y',$timestamp) .
           ' Pukul ' . date('H:i',$timestamp) . ' WITA';
}

/**
 * Format tanggal untuk judul (contoh: Kamis 19 Maret 2026)
 */
function format_tanggal_judul($timestamp = null) {

    $timestamp = $timestamp ?: time();

    $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $bulan = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];

    return $hari[date('w',$timestamp)] . ' ' .
           date('j',$timestamp) . ' ' .
           $bulan[date('n',$timestamp)] . ' ' .
           date('Y',$timestamp);
}

/**
 * Format jam saja (contoh: 19:25)
 */
function format_jam($timestamp = null) {

    if (!$timestamp) {
        $timestamp = time();
    }

    return date('H:i', $timestamp);
}

/**
 * Kirim WhatsApp via Wablas
 */
function jurnalmengajar_kirim_wa($nomor, $pesan) {
    global $CFG;

    if (empty($nomor)) {
        debugging("Nomor WA kosong", DEBUG_DEVELOPER);
        return false;
    }

    $apikey = get_config('local_jurnalmengajar', 'apikey');
    $secret = get_config('local_jurnalmengajar', 'secretkey');

    if (empty($apikey) || empty($secret)) {
        debugging("API Wablas belum diisi", DEBUG_DEVELOPER);
        return false;
    }

    $token = $apikey . '.' . $secret;
    $url = 'https://sby.wablas.com/api/v2/send-message';

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
        CURLOPT_URL => $url,
        CURLOPT_HTTPHEADER => [
            "Authorization: $token",
            "Content-Type: application/json"
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        debugging('cURL error: ' . curl_error($ch), DEBUG_DEVELOPER);
    }

    curl_close($ch);

    // logging
    $logdir = $CFG->dataroot . '/logs';
    if (!file_exists($logdir)) {
        mkdir($logdir, 0777, true);
    }

    file_put_contents(
        $logdir . '/wablas.log',
        date('Y-m-d H:i:s') . " | Ke: $nomor | $response\n",
        FILE_APPEND
    );

    return $response;
}

/**
 * Ambil nomor wali kelas dari mapping
 */
function get_nomor_wali_kelas($kelasid) {
    $json = get_config('local_jurnalmengajar', 'wali_kelas_mapping');
    $mapping = json_decode($json, true);

    if (empty($mapping[$kelasid])) {
        debugging("Mapping tidak ditemukan untuk kelas ID: $kelasid", DEBUG_DEVELOPER);
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
    $keterangan = $data->keterangan ?? '-';

    $sekolah = get_config('local_jurnalmengajar', 'nama_sekolah') ?: 'Nama Sekolah';

    // absen
    $absen = '-';
    if (!empty($data->absen)) {
        $arr = json_decode($data->absen, true);
        if (is_array($arr)) {
            $i = 1;
            $list = [];
            foreach ($arr as $nama => $alasan) {
                $list[] = $i++ . ". $nama: $alasan";
            }
            $absen = implode("\n", $list);
        }
    }

$tanggal_judul = format_tanggal_judul();
$jam = format_jam();

$pesan = "*📘 Jurnal KBM $tanggal_judul*\n\n"
       . "👤 Guru yang mengajar: $namaguru\n"
       . "🏫 Kelas: $kelas\n"
       . "⏰ Jam ke: $jamke\n"
       . "📚 Mata Pelajaran: $mapel\n"
       . "📒 Materi: $materi\n"
       . "📝 Aktivitas:\n$aktivitas\n\n"
       . "🔴 Murid tidak hadir:\n$absen\n\n"
       . "Keterangan tambahan:\n$keterangan\n\n"
       . "🕒 Waktu: $jam WITA\n"
       . "📌 Tercatat di eJurnal KBM $sekolah\n\n"
       . "_Dikirim ke Wali kelas dan Guru ybs sebagai laporan_";

    $nomor_guru = get_user_nowa($user->id);
    $nomor_wali = get_nomor_wali_kelas($kelasid);

    if ($nomor_guru && $nomor_guru === $nomor_wali) {
        jurnalmengajar_kirim_wa($nomor_guru, $pesan);
    } else {
        if ($nomor_guru) jurnalmengajar_kirim_wa($nomor_guru, $pesan);
        if ($nomor_wali) jurnalmengajar_kirim_wa($nomor_wali, $pesan);
    }
}

/**
 * Notifikasi WA Surat Izin
 */
function jurnalmengajar_notifikasi_izin($record, $pengawas_nama) {
    global $DB;

    $siswa = $DB->get_record('user', ['id' => $record->userid]);
    if (!$siswa) return;

    $kelas = get_nama_kelas($record->kelasid);
    $nama  = ucwords(strtolower($siswa->lastname));

    $gurunama = $DB->get_field('user', 'lastname', ['id' => $record->guru_pengajar]);

    $waktu_full = format_waktu_indo($record->timecreated);

    $pesan = "*[Surat Izin Murid]*\n\n"
           . "📅 Waktu: $waktu_full\n"
           . "👤 Nama: $nama\n"
           . "🏫 Kelas: $kelas\n"
           . "🎓 Guru Pengajar: $gurunama\n"
           . "📝 Alasan: {$record->alasan}\n"
           . "📌 Keperluan: {$record->keperluan}\n"
           . "✍️ Pengawas Hari ini: $pengawas_nama\n\n"
           . "_Dikirim kepada Wali kelas sebagai laporan_";

    $nomor_wali = get_nomor_wali_kelas($record->kelasid);

    if ($nomor_wali) {
        jurnalmengajar_kirim_wa($nomor_wali, $pesan);
    }
}
