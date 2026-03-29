<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/jurnalmengajar/lib.php');
require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/riwayat_pramuka.php'));
$PAGE->set_title('Riwayat Pramuka');
$PAGE->set_heading('Riwayat Kegiatan Pramuka');

echo $OUTPUT->header();
echo $OUTPUT->heading('Riwayat Kegiatan Pramuka');

global $DB;

$records = $DB->get_records('local_jurnalpramuka', null, 'timecreated DESC');

if ($records) {

    $table = new html_table();
    $table->head = ['No','Waktu','Guru','Kelas','Materi','Catatan','Tidak hadir','Aksi'];

    $no = 1;

    foreach ($records as $r) {

        // 🔥 support data lama & baru
        if (is_numeric($r->kelas)) {
            $kelas = get_nama_kelas($r->kelas);
        } else {
            $kelas = $r->kelas ?: '-';
        }

        $guru = $DB->get_field('user', 'lastname', ['id' => $r->userid]) ?? '-';

        $absen_data = json_decode($r->absen, true);
        $daftarabsen = '-';

        if (!empty($absen_data)) {
            $list = [];
            foreach ($absen_data as $nama => $alasan) {
                $list[] = "$nama ($alasan)";
            }
            $daftarabsen = implode(", ", $list);
        }

        // tombol hapus
        $deleteurl = new moodle_url('/local/jurnalmengajar/delete_pramuka.php', [
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
    tanggal_indo($r->timecreated),
            $guru,
            $kelas,
            format_string($r->materi),
            format_string($r->catatan),
            $daftarabsen,
            $hapus
        ];
    }

    echo html_writer::table($table);

} else {
    echo $OUTPUT->notification('Belum ada data pramuka.', 'notifymessage');
}

echo $OUTPUT->footer();
