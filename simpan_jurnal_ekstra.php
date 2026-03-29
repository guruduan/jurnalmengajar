<?php
require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $USER;

// Validasi form
if (!isset($_POST['ekstraid']) || !isset($_POST['materi'])) {
    redirect(
        new moodle_url('/local/jurnalmengajar/jurnal_ekstra.php'),
        'Akses tidak valid',
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$ekstraid = $_POST['ekstraid'];
$materi   = trim($_POST['materi']);
$catatan  = $_POST['catatan'];
$status   = $_POST['status'] ?? [];
$tanggal  = !empty($_POST['tanggal']) ? strtotime($_POST['tanggal']) : time();
$time     = time();

if ($materi == '') {
    redirect(
        new moodle_url('/local/jurnalmengajar/jurnal_ekstra.php?ekstraid='.$ekstraid),
        'Materi tidak boleh kosong',
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// =======================
// TRANSACTION START
// =======================
$transaction = $DB->start_delegated_transaction();

// =======================
// SIMPAN JURNAL
// =======================
$jurnal = new stdClass();
$jurnal->ekstraid    = $ekstraid;
$jurnal->tanggal     = $tanggal;
$jurnal->pembinaid   = $USER->id;
$jurnal->materi      = $materi;
$jurnal->catatan     = $catatan;
$jurnal->timecreated = $time;

$jurnalid = $DB->insert_record('local_jm_ekstra_jurnal', $jurnal);

// =======================
// SIMPAN ABSENSI
// =======================
foreach ($status as $userid => $st) {

    // Ambil cohort siswa
    $cohortid = $DB->get_field('local_jm_ekstra_peserta', 'cohortid', [
        'userid' => $userid,
        'ekstraid' => $ekstraid
    ]);

    $absen = new stdClass();
    $absen->jurnalid = $jurnalid;
    $absen->userid   = $userid;
    $absen->status   = $st;
    $absen->cohortid = $cohortid;
    $absen->keterangan = '';

    $DB->insert_record('local_jm_ekstra_absen', $absen);
}

// =======================
// COMMIT
// =======================
$transaction->allow_commit();

// =======================
// REDIRECT
// =======================
redirect(
    new moodle_url('/local/jurnalmengajar/jurnal_ekstra.php?ekstraid='.$ekstraid),
    'Jurnal ekstrakurikuler berhasil disimpan',
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
