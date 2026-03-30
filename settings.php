<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    // =========================
    // KATEGORI PLUGIN
    // =========================
    $ADMIN->add('localplugins', new admin_category(
        'local_jurnalmengajar_cat',
        'Jurnal Mengajar'
    ));

    // =========================
    // HALAMAN SETTING UTAMA
    // =========================
    $settings = new admin_settingpage(
        'local_jurnalmengajar',
        'Pengaturan Umum'
    );

    $ADMIN->add('local_jurnalmengajar_cat', $settings);

    // =========================
    // PENGATURAN UMUM
    // =========================
    $settings->add(new admin_setting_configtext(
        'local_jurnalmengajar/tanggalawalminggu',
        'Tanggal Awal Minggu Pertama',
        'Format: YYYY-MM-DD. Contoh: 2025-06-23',
        '2025-06-23',
        PARAM_RAW
    ));

    $settings->add(new admin_setting_configtext(
        'local_jurnalmengajar/mapel_list',
        'Daftar Mapel (pisahkan dengan koma)',
        'Contoh: Fisika,Matematika,Bahasa Indonesia,PPKN',
        'Fisika,Matematika,Kimia,Biologi,Bahasa Indonesia,Bahasa Inggris,PPKN,Sejarah'
    ));

    // =========================
    // IDENTITAS SEKOLAH
    // =========================
    $settings->add(new admin_setting_heading(
        'local_jurnalmengajar/identitas_sekolah',
        'Identitas Sekolah',
        'Digunakan untuk cetak laporan dan tanda tangan'
    ));

    $settings->add(new admin_setting_configtext(
        'local_jurnalmengajar/nama_sekolah',
        'Nama Sekolah',
        '',
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_jurnalmengajar/tahun_ajaran',
        'Tahun Ajaran',
        'Contoh: 2025/2026',
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_jurnalmengajar/tempat_ttd',
        'Tempat',
        'Contoh: Kandangan',
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_jurnalmengajar/nama_kepsek',
        'Nama Kepala Sekolah',
        '',
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_jurnalmengajar/nip_kepsek',
        'NIP',
        '',
        '',
        PARAM_TEXT
    ));
// Upload Logo Sekolah
$settings->add(new admin_setting_configstoredfile(
    'local_jurnalmengajar/logo',
    'Logo Sekolah',
    'Upload logo sekolah (PNG/JPG)',
    'logo'
));

// Upload Stempel
$settings->add(new admin_setting_configstoredfile(
    'local_jurnalmengajar/stempel',
    'Stempel',
    'Upload stempel (PNG transparan)',
    'stempel'
));
// ==============================
// TTD Kepala Sekolah
// ==============================
$settings->add(new admin_setting_configstoredfile(
    'local_jurnalmengajar/ttd',
    'Tanda Tangan',
    'Upload tanda tangan kepala sekolah (PNG)',
    'ttd'
));
$settings->add(new admin_setting_configtext(
    'local_jurnalmengajar/nomor_kepsek',
    'Nomor Kepala Sekolah',
    'Nomor WhatsApp Kepala Sekolah',
    ''
));
    // =========================
    // HARI SEKOLAH & LIBUR
    // =========================
    $settings->add(new admin_setting_heading(
        'local_jurnalmengajar/harisekolah_heading',
        'Hari Sekolah & Libur',
        'Pengaturan hari sekolah dan tanggal libur'
    ));

$settings->add(new admin_setting_configtext(
    'local_jurnalmengajar/harisekolah',
    'Hari Sekolah',
    'Isi hari sekolah dipisahkan koma. Contoh: Senin,Selasa,Rabu,Kamis,Jumat',
    'Senin,Selasa,Rabu,Kamis,Jumat',
    PARAM_TEXT
));

    $settings->add(new admin_setting_configtextarea(
    'local_jurnalmengajar/tanggallibur',
    'Tanggal Libur',
    'Isi tanggal libur, satu baris satu tanggal atau rentang tanggal.
Contoh:
2026-03-21
2026-03-23 s/d 2026-03-25',
    ''
));

    // =========================
    // KONFIGURASI WABLAS
    // =========================
    $settings->add(new admin_setting_heading(
        'local_jurnalmengajar/wablas',
        'Konfigurasi Wablas',
        'Digunakan untuk notifikasi WhatsApp'
    ));

    $settings->add(new admin_setting_configtext(
        'local_jurnalmengajar/apikey',
        'API Key',
        '',
        'cek dashboard wablas',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configtext(
        'local_jurnalmengajar/secretkey',
        'Secret Key',
        '',
        'cek dashboard wablas',
        PARAM_RAW_TRIMMED
    ));
    
 $settings->add(new admin_setting_configtext(
        'local_jurnalmengajar/wablas_url',
        'Wablas URL',
        '',
        'cek dashboard wablas'
    ));

    $settings->add(new admin_setting_configtext(
        'local_jurnalmengajar/wablas_group',
        'Group WhatsApp',
        'Group ID atau JID (120xxx@g.us)',
        'cek dashboard wablas'
    ));

    // =========================
    // HALAMAN WALI KELAS
    // =========================
    $ADMIN->add('local_jurnalmengajar_cat', new admin_externalpage(
        'local_jurnalmengajar_walikelas',
        'Manajemen Wali Kelas',
        new moodle_url('/local/jurnalmengajar/wali_kelas.php'),
        'moodle/site:config'
    ));
}
