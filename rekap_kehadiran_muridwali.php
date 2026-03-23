<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot.'/local/jurnalmengajar/csv_helper.php');

require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

/* ==========================
 * PAGE
 * ========================== */
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/rekap_kehadiran_muridwali.php'));
$PAGE->set_title('Rekap Kehadiran Murid Binaan Guru Wali');
$PAGE->set_heading('Rekap Kehadiran Murid Binaan Guru Wali');

echo $OUTPUT->header();

/* ==========================
 * PARAMETER
 * ========================== */
$dari_raw   = optional_param('dari', '', PARAM_RAW);
$sampai_raw = optional_param('sampai', '', PARAM_RAW);
$mode       = optional_param('mode', 'hari', PARAM_ALPHA);

$dari   = $dari_raw ? strtotime($dari_raw) : 0;
$sampai = $sampai_raw ? (strtotime($sampai_raw) + 86399) : 0;

/* ==========================
 * HELPER
 * ========================== */
function normalize_status(string $s): string {
    $s = strtolower(trim($s));
    $map = [
        'ijin'=>'ijin','izin'=>'ijin',
        'sakit'=>'sakit',
        'alpha'=>'alpa','alpa'=>'alpa',
        'disp'=>'dispensasi','dispensasi'=>'dispensasi',
        'hadir'=>'hadir'
    ];
    return $map[$s] ?? $s;
}

$priority = [
    'hadir'      => 0,
    'dispensasi' => 1,
    'sakit'      => 2,
    'ijin'       => 3,
    'alpa'       => 4,
];

// Ambil kelas utama siswa (cohort)
function jw_get_kelas_siswa(int $userid): ?string {
    global $DB;
    $rows = $DB->get_records_sql(
        "SELECT c.name
           FROM {cohort} c
           JOIN {cohort_members} cm ON cm.cohortid = c.id
          WHERE cm.userid = :uid
       ORDER BY c.name ASC",
        ['uid'=>$userid]
    );
    if (!$rows) return null;
    foreach ($rows as $r) {
        if (preg_match('~^(X|XI|XII)[A-Z0-9\-]*~', $r->name)) {
            return $r->name;
        }
    }
    return reset($rows)->name ?? null;
}

// Ambil id cohort dari nama kelas
function jw_get_kelasid_by_name(string $kelasname): int {
    global $DB;
    $rec = $DB->get_record('cohort', ['name'=>$kelasname], 'id', IGNORE_MISSING);
    return $rec->id ?? 0;
}

/* ==========================
 * AMBIL MURID BINAAN (CSV)
 * ========================== */
$muridopts = jw_get_murid_options_from_csv($USER->id);
$userids   = array_keys($muridopts);

if (empty($userids)) {
    echo $OUTPUT->notification('Anda belum memiliki murid binaan.', 'warning');
    echo $OUTPUT->footer();
    exit;
}

list($in_sql, $paramsin) = $DB->get_in_or_equal($userids);
$users = $DB->get_records_sql(
    "SELECT id, firstname, lastname
       FROM {user}
      WHERE id $in_sql",
    $paramsin
);

// Gabungkan user + kelas
$users_with_class = [];
foreach ($users as $u) {
    $kelas = jw_get_kelas_siswa($u->id) ?? 'ZZZ';
    $users_with_class[] = (object)[
        'id'       => $u->id,
        'firstname'=> $u->firstname,
        'lastname' => $u->lastname,
        'kelas'    => $kelas
    ];
}

// Cache kelasid siswa
$kelasid_siswa = [];
foreach ($users_with_class as $u) {
    $kelasid_siswa[$u->id] = jw_get_kelasid_by_name($u->kelas);
}

// Urutkan: kelas → nama
usort($users_with_class, function($a, $b) {
    $k = strnatcasecmp($a->kelas, $b->kelas);
    return $k !== 0 ? $k : strnatcasecmp($a->lastname, $b->lastname);
});

/* ==========================
 * INFO HEADER
 * ========================== */
echo html_writer::div(
    '<strong>Guru Wali:</strong> '.s($USER->lastname).
    ' | <strong>Jumlah Murid Binaan:</strong> '.count($users_with_class),
    'alert alert-info'
);

/* ==========================
 * FORM FILTER
 * ========================== */
echo html_writer::start_tag('form', ['method'=>'get','class'=>'mb-3']);
echo html_writer::start_div('d-flex gap-3 mb-2');

echo html_writer::div(
    html_writer::label('Dari Tanggal','dari').
    html_writer::empty_tag('input',[
        'type'=>'date','name'=>'dari','value'=>s($dari_raw),
        'class'=>'form-control','required'=>'required'
    ]),
    'flex-fill'
);

echo html_writer::div(
    html_writer::label('Sampai Tanggal','sampai').
    html_writer::empty_tag('input',[
        'type'=>'date','name'=>'sampai','value'=>s($sampai_raw),
        'class'=>'form-control','required'=>'required'
    ]),
    'flex-fill'
);

echo html_writer::end_div();

echo html_writer::div(
    html_writer::label('Mode Hitung','mode').
    html_writer::select(
        ['hari'=>'Per Hari','jam'=>'Per Jam'],
        'mode',
        $mode,
        false,
        ['class'=>'form-select w-auto']
    ),
    'mb-3'
);

echo html_writer::empty_tag('input',[
    'type'=>'submit','value'=>'Tampilkan','class'=>'btn btn-primary'
]);

echo html_writer::end_tag('form');

/* ==========================
 * PROSES DATA
 * ========================== */
$data = [];
foreach ($users_with_class as $u) {
    $data[$u->id] = ['hadir'=>0,'sakit'=>0,'ijin'=>0,'alpa'=>0,'dispensasi'=>0];
}

if ($dari && $sampai) {

    $jurnals = $DB->get_records_select(
        'local_jurnalmengajar',
        'timecreated BETWEEN :dari AND :sampai',
        ['dari'=>$dari,'sampai'=>$sampai]
    );

    /* ===== MODE PER HARI ===== */
    if ($mode === 'hari') {

        $perhari   = [];
        $all_dates = [];

        foreach ($jurnals as $j) {

            $jamke  = array_filter(array_map('trim', explode(',', (string)$j->jamke)));
            $jmljam = count($jamke) ?: 1;

            $absen = json_decode($j->absen, true) ?? [];
            $lookup = [];
            foreach ($absen as $nama => $alasan) {
                $lookup[mb_strtolower(trim($nama),'UTF-8')] = normalize_status($alasan);
            }

            foreach ($users_with_class as $u) {

                if (
                    empty($kelasid_siswa[$u->id]) ||
                    (int)$j->kelas !== (int)$kelasid_siswa[$u->id]
                ) {
                    continue;
                }

                $tgl = date('Y-m-d', $j->timecreated);
                $all_dates[$u->id][$tgl] = true;

                $key = mb_strtolower(trim($u->lastname),'UTF-8');
                $status = $lookup[$key] ?? 'hadir';

                $perhari[$u->id][$tgl][$status] =
                    ($perhari[$u->id][$tgl][$status] ?? 0) + $jmljam;
            }
        }

        foreach ($users_with_class as $u) {
            foreach (array_keys($all_dates[$u->id] ?? []) as $tgl) {

                $tot   = array_sum($perhari[$u->id][$tgl]);
                $hadir = $perhari[$u->id][$tgl]['hadir'] ?? 0;

                if ($hadir == $tot) {
                    $data[$u->id]['hadir']++;
                } elseif ($hadir == 0) {
                    $pick = 'hadir'; $max = -1;
                    foreach (['dispensasi','sakit','ijin','alpa'] as $st) {
                        if (!empty($perhari[$u->id][$tgl][$st]) && $priority[$st] > $max) {
                            $pick = $st; $max = $priority[$st];
                        }
                    }
                    $data[$u->id][$pick]++;
                } else {
                    $data[$u->id]['hadir']++;
                }
            }
        }

    /* ===== MODE PER JAM ===== */
    } else {

        foreach ($jurnals as $j) {

            $jamke  = array_filter(array_map('trim', explode(',', (string)$j->jamke)));
            $jmljam = count($jamke);
            if (!$jmljam) continue;

            $absen = json_decode($j->absen, true) ?? [];

            foreach ($users_with_class as $u) {

                if (
                    empty($kelasid_siswa[$u->id]) ||
                    (int)$j->kelas !== (int)$kelasid_siswa[$u->id]
                ) {
                    continue;
                }

                $found = false;
                foreach ($absen as $nama => $alasan) {
                    if (strcasecmp(trim($nama), trim($u->lastname)) === 0) {
                        $data[$u->id][normalize_status($alasan)] += $jmljam;
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $data[$u->id]['hadir'] += $jmljam;
                }
            }
        }
    }

    /* ==========================
     * TABEL OUTPUT
     * ========================== */
    echo html_writer::start_tag('table',['class'=>'generaltable']);
    echo html_writer::tag('tr',
        '<th>No</th><th>Nama Murid</th><th>Kelas</th>
         <th>Hadir</th><th>Sakit</th><th>Ijin</th><th>Alpa</th><th>Disp</th>
         <th>Detail Kehadiran</th>'
    );

    $no = 1;
    foreach ($users_with_class as $u) {
        $d = $data[$u->id];

        $detailurl = new moodle_url('/local/jurnalmengajar/rekap_permurid.php', [
            'kelas'  => $kelasid_siswa[$u->id],
            'siswa'  => $u->id,
            'dari'   => date('Y-m-d',$dari),
            'sampai' => date('Y-m-d',$sampai),
            'mode'   => $mode
        ]);

        echo html_writer::tag('tr',
            '<td>'.$no++.'</td>'.
            '<td>'.s(ucwords(strtolower($u->lastname))).'</td>'.
            '<td>'.s($u->kelas).'</td>'.
            '<td>'.$d['hadir'].'</td>'.
            '<td>'.$d['sakit'].'</td>'.
            '<td>'.$d['ijin'].'</td>'.
            '<td>'.$d['alpa'].'</td>'.
            '<td>'.$d['dispensasi'].'</td>'.
            '<td>'.html_writer::link($detailurl,'🔍 Detail',['class'=>'btn btn-sm btn-outline-primary']).'</td>'
        );
    }

    echo html_writer::end_tag('table');
}
echo html_writer::link(
    '#',
    '⬅ Kembali',
    [
        'class' => 'btn btn-secondary',
        'onclick' => 'history.back(); return false;',
        'title' => 'Kembali ke halaman sebelumnya'
    ]
);
echo $OUTPUT->footer();
