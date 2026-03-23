<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/rekap_kelas_perminggu.php'));
$PAGE->set_title('Rekap Pekanan KBM Kelas');
$PAGE->set_heading('Rekap Pekanan KBM Kelas');

global $DB, $OUTPUT;
// === Ambil setting tanggal awal minggu ===
$tanggalawalminggu = get_config('local_jurnalmengajar', 'tanggalawalminggu'); // format: YYYY-MM-DD
if (empty($tanggalawalminggu)) {
    throw new moodle_exception('Tanggal awal minggu belum diset di pengaturan plugin.');
}

// === Hitung rentang minggu ke-1 s.d. ke-20 ===
$mingguoptions = [];
for ($i = 0; $i < 20; $i++) {
    $start = strtotime($tanggalawalminggu . " +{$i} week");
    $end   = strtotime("+6 day", $start);
    $label = 'Minggu ' . ($i+1) . ' (' . date('d M Y', $start) . ' s/d ' . date('d M Y', $end) . ')';
    $mingguoptions[$i+1] = $label;
}

// === Ambil cohort (kelas) ===
$kelasrecords = $DB->get_records('cohort', null, 'name ASC', 'id, name');
$kelasoptions = [];
foreach ($kelasrecords as $k) {
    $kelasoptions[$k->id] = $k->name;
}

// === Hitung minggu berjalan (default) ===
$hariini = strtotime('today');
$diff = floor(($hariini - strtotime($tanggalawalminggu)) / (7 * 24 * 60 * 60));
$minggu_berjalan = ($diff >= 0 && $diff < 20) ? $diff + 1 : 1;

// === Ambil input dengan default kelas pertama & minggu berjalan ===
$kelas = optional_param('kelas', key($kelasoptions), PARAM_INT); // default kelas pertama
$minggu = optional_param('minggu', $minggu_berjalan, PARAM_INT);

// === Hitung tanggal filter ===
$startdate = strtotime($tanggalawalminggu . " +" . ($minggu-1) . " week");
$enddate   = strtotime("+6 day", $startdate);

// range waktu dalam timestamp
$starttime = $startdate;
$endtime   = strtotime("+1 day", $enddate) - 1;

// === Ambil data jurnal ===
$jurnalrecords = [];
if ($kelas) {
    $jurnalrecords = $DB->get_records_select('local_jurnalmengajar',
        "kelas = :kelas AND timecreated >= :start AND timecreated <= :end",
        ['kelas' => $kelas, 'start' => $starttime, 'end' => $endtime],
        "timecreated ASC, jamke ASC"
    );
}

// === Cetak form filter ===
echo $OUTPUT->header();
// Tombol kembali
echo html_writer::div(
    html_writer::link(
        '#',
        '⬅ Kembali',
        [
            'class' => 'btn btn-secondary',
            'onclick' => 'history.back(); return false;'
        ]
    ),
    'mb-3'
);
echo $OUTPUT->heading('Rekap KBM di kelas per minggu');
echo html_writer::start_tag('form', ['method' => 'get']);
echo html_writer::tag('label', 'Pilih Kelas: ', ['for' => 'kelas']);
echo html_writer::select($kelasoptions, 'kelas', $kelas);
echo ' ';
echo html_writer::tag('label', 'Pilih Minggu: ', ['for' => 'minggu']);
echo html_writer::select($mingguoptions, 'minggu', $minggu);
echo ' ';
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => 'Tampilkan']);
echo html_writer::end_tag('form');

// === Tampilkan data per hari (Senin-Jumat) ===
$hari = [
    'Monday'    => 'Senin',
    'Tuesday'   => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday'  => 'Kamis',
    'Friday'    => 'Jumat'
];

foreach ($hari as $eng => $indo) {
    $rows = [];
    $tanggalhari = '';
    foreach ($jurnalrecords as $r) {
        $haridata = date('l', $r->timecreated);
        if ($haridata == $eng) {
            $tanggalhari = date('d-m-Y', $r->timecreated);

            // --- Ambil lastname pengajar ---
            $lastname = $DB->get_field('user', 'lastname', ['id' => $r->userid]);

            $rows[] = [
                $r->jamke,
                format_string($r->matapelajaran),
                $lastname,
                format_text($r->materi),
                // format_text($r->keterangan),  // kolom keterangan dihapus
                date('d-m-Y H:i', $r->timecreated)
            ];
        }
    }

    echo html_writer::tag('h3', $indo . ($tanggalhari ? ", tanggal $tanggalhari" : ''));
    if (empty($rows)) {
        echo html_writer::tag('p', 'Tidak ada data.');
    } else {
        $table = new html_table();
        // $table->head = ['Jamke', 'Mata Pelajaran', 'Pengajar', 'Materi', 'Keterangan', 'Tanggal Input'];
        $table->head = ['Jamke', 'Mata Pelajaran', 'Pengajar', 'Materi', 'Waktu Input'];
        $table->data = $rows;
        echo html_writer::table($table);
    }
}

echo $OUTPUT->footer();
