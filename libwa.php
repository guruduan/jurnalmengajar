<?php
defined('MOODLE_INTERNAL') || die();

function get_nomor_kepsek(): string {
    return '628115002681'; // Ganti ke nomor kepala sekolah yang benar
}

function jurnalmengajar_kirim_wa($nomor, $pesan): void {
    $apikey = '4L94T0YIsPSOmB1W3Q8Gzlj637DMLigCMrucozQjwVtvAd1JnkqulZT.MQmnZVjd'; // Sesuaikan dengan API key aktif
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
}

function kirim_wa_izin_guru($guru, $nip, $alasan, $keperluan, $tanggal, $jam, $dicatatoleh): void {
    $nomor_kepsek = get_nomor_kepsek();
$jam = date('H:i');
    $pesan = "*-Surat Izin Keluar Guru/Pegawai-*\n"
           . "👮‍Nama: {$guru->lastname}\n"
           . "NIP/NIPPPK: {$nip}\n"
           . "Alasan: {$alasan}\n"
           . "Keperluan: {$keperluan}\n"
           . "Hari/Tanggal: {$tanggal}\n"
           . "🕒Pukul: {$jam}\n"
           . "📝Diinput oleh: {$dicatatoleh}";

    jurnalmengajar_kirim_wa($nomor_kepsek, $pesan);
}
