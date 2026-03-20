<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/rekap_pramuka.php'));
$PAGE->set_title('Rekap Kegiatan Pramuka');
$PAGE->set_heading('Rekap Kegiatan Pramuka');
$PAGE->requires->jquery();
$PAGE->requires->css('/local/jurnalmengajar/css/stickyheader.css');

echo $OUTPUT->header();
echo $OUTPUT->heading('Rekap Kehadiran Kegiatan Pramuka Per Kelas');

$kelaslist = $DB->get_records_menu('cohort', null, 'name ASC', 'id, name');

$kelasid = optional_param('kelas', 0, PARAM_INT);
$dari_str = optional_param('dari', '', PARAM_RAW);
$sampai_str = optional_param('sampai', '', PARAM_RAW);

echo html_writer::start_tag('form', ['method' => 'get', 'style' => 'display:flex; gap:10px; align-items:center;']);
echo html_writer::label('Pilih Kelas: ', 'kelas');
echo html_writer::select($kelaslist, 'kelas', $kelasid, 'Pilih...', ['required' => true]);

echo html_writer::label('Dari tanggal: ', 'dari');
echo '<input type="date" name="dari" required value="' . s($dari_str) . '">';
echo html_writer::label('Sampai tanggal: ', 'sampai');
echo '<input type="date" name="sampai" required value="' . s($sampai_str) . '">';
echo '<input type="submit" value="Tampilkan" class="btn btn-primary">';

if (!empty($kelasid) && !empty($dari_str) && !empty($sampai_str)) {
    $exporturl = new moodle_url('/local/jurnalmengajar/rekap_pramuka_export.php', [
        'kelas' => $kelasid,
        'dari' => $dari_str,
        'sampai' => $sampai_str,
        'format' => 'xlsx'
    ]);
    echo html_writer::link($exporturl, '📤 Ekspor ke XLSX', ['class' => 'btn btn-success']);
}
echo html_writer::end_tag('form');
echo '<hr>';

$dari = strtotime($dari_str);
$sampai = strtotime($sampai_str) + 86399;

function format_tanggal_indo($timestamp) {
    $bulan = [
        1 => 'Januari','Februari','Maret','April','Mei','Juni',
        'Juli','Agustus','September','Oktober','November','Desember'
    ];
    return date('j', $timestamp) . ' ' . $bulan[(int)date('n', $timestamp)] . ' ' . date('Y', $timestamp);
}

if ($dari && $sampai) {
    echo '<p><strong>Rentang Tanggal:</strong> ' . format_tanggal_indo($dari) . ' sampai ' . format_tanggal_indo($sampai) . '</p>';
}

if ($kelasid && $dari && $sampai) {
    $members = $DB->get_records('cohort_members', ['cohortid' => $kelasid]);
    $userids = array_map(fn($m) => $m->userid, $members);

    if (empty($userids)) {
        echo 'Tidak ada murid dalam kelas ini.';
        echo $OUTPUT->footer();
        exit;
    }

    list($in_sql, $params) = $DB->get_in_or_equal($userids);
    $users = $DB->get_records_sql("
        SELECT id, firstname, lastname
        FROM {user}
        WHERE id $in_sql
        ORDER BY lastname ASC, firstname ASC
    ", $params);

    $cohort = $DB->get_record('cohort', ['id' => $kelasid], 'name', MUST_EXIST);

    // Ambil data jurnal dari tabel baru
    $jurnals = $DB->get_records_select('local_jurnalpramuka',
        'kelas = :kelas AND timecreated BETWEEN :dari AND :sampai',
        ['kelas' => $cohort->name, 'dari' => $dari, 'sampai' => $sampai]
    );

    // Inisialisasi data kehadiran
    $data = [];
    foreach ($users as $u) {
        $data[$u->id] = ['hadir' => 0, 'sakit' => 0, 'ijin' => 0, 'alpa' => 0, 'dispensasi' => 0];
    }

    // Hitung kehadiran
    foreach ($jurnals as $jurnal) {
        $absen = json_decode($jurnal->absen, true) ?? [];
        foreach ($users as $uid => $u) {
            $namasiswa = trim($u->lastname);
            $found = false;
            foreach ($absen as $nama => $alasan) {
                if (strcasecmp(trim($nama), $namasiswa) == 0) {
                    $alasan = strtolower(trim($alasan));
                    if (isset($data[$uid][$alasan])) {
                        $data[$uid][$alasan]++;
                    }
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $data[$uid]['hadir']++;
            }
        }
    }

    // Tampilkan tabel hasil
    echo html_writer::start_div('table-wrapper');
    echo html_writer::start_tag('table', ['class' => 'generaltable']);
    echo html_writer::tag('thead',
        html_writer::tag('tr',
            html_writer::tag('th', 'No') .
            html_writer::tag('th', 'Nama Murid') .
            html_writer::tag('th', 'Hadir') .
            html_writer::tag('th', 'Sakit') .
            html_writer::tag('th', 'Ijin') .
            html_writer::tag('th', 'Alpa') .
            html_writer::tag('th', 'Dispensasi') .
            html_writer::tag('th', 'Persentase')
        )
    );
    echo html_writer::start_tag('tbody');

    $no = 1;
    foreach ($data as $uid => $d) {
        $total = array_sum($d);
        $persen = $total > 0 ? round(($d['hadir'] / $total) * 100, 1) . '%' : '-';
        $namasiswa = ucwords(strtolower($users[$uid]->lastname));

        echo html_writer::tag('tr',
            html_writer::tag('td', $no++) .
            html_writer::tag('td', $namasiswa) .
            html_writer::tag('td', $d['hadir']) .
            html_writer::tag('td', $d['sakit']) .
            html_writer::tag('td', $d['ijin']) .
            html_writer::tag('td', $d['alpa']) .
            html_writer::tag('td', $d['dispensasi']) .
            html_writer::tag('td', $persen)
        );
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_div();
}

echo $OUTPUT->footer();
