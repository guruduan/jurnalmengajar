<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_login();

$context = context_system::instance();

// 🔐 hanya admin
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/admin.php'));
$PAGE->set_title('Manajemen Jurnal Mengajar');
$PAGE->set_heading('Manajemen Jurnal Mengajar');

global $DB;

echo $OUTPUT->header();
echo $OUTPUT->heading('📊 Semua Jurnal Guru');

// 🔎 ambil semua jurnal + lastname saja
$sql = "SELECT j.*, u.lastname
          FROM {local_jurnalmengajar} j
          JOIN {user} u ON u.id = j.userid
      ORDER BY j.id DESC
         LIMIT 50";

$entries = $DB->get_records_sql($sql);

if ($entries) {

    echo html_writer::start_tag('table', ['class' => 'generaltable']);

    echo html_writer::start_tag('thead');
    echo html_writer::tag('tr',
        html_writer::tag('th', 'No') .
        html_writer::tag('th', 'Nama Guru') .
        html_writer::tag('th', 'Kelas') .
        html_writer::tag('th', 'Jam Ke') .
        html_writer::tag('th', 'Mapel') .
        html_writer::tag('th', 'Materi') .
        html_writer::tag('th', 'Absen') .
        html_writer::tag('th', 'Waktu') .
        html_writer::tag('th', 'Aksi')
    );
    echo html_writer::end_tag('thead');

    echo html_writer::start_tag('tbody');

    $no = 1;

    foreach ($entries as $e) {

        // nama guru (lastname saja)
        $namaguru = $e->lastname;

        // nama kelas
        $namakelas = $DB->get_field('cohort', 'name', ['id' => $e->kelas]) ?? '???';

        // parsing absen
        $absendata = json_decode($e->absen, true);
        $absentext = '';
        if (is_array($absendata)) {
            foreach ($absendata as $nama => $alasan) {
                $absentext .= "$nama ($alasan), ";
            }
            $absentext = rtrim($absentext, ', ');
        } else {
            $absentext = $e->absen;
        }

        // URL aksi
        $editurl = new moodle_url('/local/jurnalmengajar/editadmin.php', ['id' => $e->id]);
        $deleteurl = new moodle_url('/local/jurnalmengajar/delete.php', [
            'id' => $e->id,
            'sesskey' => sesskey()
        ]);

        $aksi = html_writer::link($editurl, 'Edit');

        // tombol hapus
        $aksi .= ' | ' . html_writer::link(
            $deleteurl,
            'Hapus',
            [
                'onclick' => "return confirm('Yakin ingin menghapus jurnal ini?')",
                'class' => 'text-danger'
            ]
        );

        echo html_writer::start_tag('tr');

        echo html_writer::tag('td', $no);
        echo html_writer::tag('td', $namaguru); // ✅ lastname saja
        echo html_writer::tag('td', $namakelas);
        echo html_writer::tag('td', $e->jamke);
        echo html_writer::tag('td', $e->matapelajaran);
        echo html_writer::tag('td', shorten_text($e->materi, 30), ['title' => $e->materi]);
        echo html_writer::tag('td', shorten_text($absentext, 25), ['title' => $absentext]);
        echo html_writer::tag('td', tanggal_indo($e->timecreated));
        echo html_writer::tag('td', $aksi);

        echo html_writer::end_tag('tr');

        $no++;
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');

} else {
    echo 'Belum ada data jurnal.';
}

echo $OUTPUT->footer();
