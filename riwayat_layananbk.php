<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/jurnalmengajar/lib.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/riwayat_layananbk.php'));
$PAGE->set_title('Riwayat Layanan BK');
$PAGE->set_heading('Riwayat Layanan BK');

echo $OUTPUT->header();
echo $OUTPUT->heading('Riwayat Layanan BK');

// ================= DATA =================
global $DB;

$records = $DB->get_records('local_jurnallayananbk', null, 'timecreated DESC');

if ($records) {

    $table = new html_table();
    $table->head = ['No','Waktu','Kelas','Jenis','Topik','Peserta','Guru BK','Aksi'];

    $no = 1;

    foreach ($records as $r) {

        $kelas = get_nama_kelas($r->kelas);
        $waktu = format_waktu_indo($r->timecreated);

        $peserta = json_decode($r->peserta, true);
        $peserta_str = is_array($peserta) ? implode(', ', $peserta) : '-';

        $guru = $DB->get_field('user','lastname',['id'=>$r->userid]) ?? '-';

        // tombol hapus
        $deleteurl = new moodle_url('/local/jurnalmengajar/delete_layananbk.php', [
            'id' => $r->id,
            'sesskey' => sesskey()
        ]);

        $hapus = html_writer::link(
            $deleteurl,
            '🗑 Hapus',
            [
                'class' => 'btn btn-danger btn-sm',
                'onclick' => "return confirm('Yakin ingin menghapus data ini?')"
            ]
        );

        $table->data[] = [
            $no++,
            $waktu,
            $kelas,
            $r->jenislayanan,
            $r->topik,
            shorten_text($peserta_str, 40),
            $guru,
            $hapus
        ];
    }

    echo html_writer::table($table);

//} else {
//    echo html_writer::notification('Belum ada data.', 'notifymessage');
//}
} else {
    \core\notification::add('Belum ada data Jurnal Layanan BK.', \core\output\notification::NOTIFY_INFO);
}
echo $OUTPUT->footer();
