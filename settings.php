<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    // Tambahkan kategori plugin
    $ADMIN->add('localplugins', new admin_category('local_jurnalmengajar_cat', 'Jurnal Mengajar'));

    // Halaman setting
    $settings = new admin_settingpage('local_jurnalmengajar', 'Jurnal Mengajar');
    $ADMIN->add('local_jurnalmengajar_cat', $settings);

    // Tanggal awal minggu
    $settings->add(new admin_setting_configtext(
        'local_jurnalmengajar/tanggalawalminggu',
        'Tanggal Awal Minggu Pertama',
        'Format: YYYY-MM-DD. Contoh: 2025-06-23',
        '2025-06-23',
        PARAM_RAW
    ));

    // Mapel
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

    // =========================
    // API WABLAS
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
        '',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configtext(
        'local_jurnalmengajar/secretkey',
        'Secret Key',
        '',
        '',
        PARAM_RAW_TRIMMED
    ));
    // =========================
    // MANAJEMAN WALI KELAS
    // =========================

$ADMIN->add('local_jurnalmengajar_cat', new admin_externalpage(
    'local_jurnalmengajar_walikelas',
    'Manajemen Wali Kelas',
    new moodle_url('/local/jurnalmengajar/wali_kelas.php'),
    'moodle/site:config'
));
}
