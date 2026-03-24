<?php
require_once(__DIR__ . '/../../config.php');
require_login();

require_once(__DIR__ . '/lib.php');

$context = context_system::instance();
require_capability('moodle/site:config', $context); // hanya admin

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/admin_jurnalguruwali.php'));
$PAGE->set_title('Admin - Jurnal Guru Wali');
$PAGE->set_heading('Semua Jurnal Guru Wali');

// ================= HAPUS DATA =================
$deleteid = optional_param('deleteid', 0, PARAM_INT);

if ($deleteid) {
    require_sesskey();
    $DB->delete_records('local_jurnalguruwali', ['id' => $deleteid]);
    redirect($PAGE->url, 'Data berhasil dihapus');
}

// ================= AMBIL DATA =================
$rows = $DB->get_records_sql("
    SELECT j.*, 
           u1.firstname AS gurufirst, u1.lastname AS gurulast,
           u2.firstname AS muridfirst, u2.lastname AS muridlast
    FROM {local_jurnalguruwali} j
    JOIN {user} u1 ON u1.id = j.guruid
    JOIN {user} u2 ON u2.id = j.muridid
    ORDER BY j.timecreated DESC
");

// ================= TAMPILAN =================
echo $OUTPUT->header();
echo $OUTPUT->heading('Semua Jurnal Guru Wali');

// Tabel
$table = new html_table();
$table->head = ['No', 'Tanggal', 'Guru', 'Murid', 'Topik', 'Tindak Lanjut', 'Keterangan', 'Aksi'];

$no = 1;

foreach ($rows as $r) {

    $tanggal = userdate($r->timecreated, '%d %B %Y %H:%M');

    $guru = trim($r->gurufirst . ' ' . $r->gurulast);
    $murid = trim($r->muridfirst . ' ' . $r->muridlast);

    $deleteurl = new moodle_url('/local/jurnalmengajar/admin_jurnalguruwali.php', [
        'deleteid' => $r->id,
        'sesskey' => sesskey()
    ]);

    $hapus = html_writer::link(
        $deleteurl,
        '🗑 Hapus',
        ['onclick' => "return confirm('Hapus data ini?')"]
    );

    $table->data[] = [
        $no++,
        $tanggal,
        $guru,
        $murid,
        $r->topik,
        $r->tindaklanjut,
        $r->keterangan,
        $hapus
    ];
}

echo html_writer::table($table);

echo $OUTPUT->footer();
