<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/rekap_kehadiran.php'));
$PAGE->set_title('Rekap Kehadiran Murid');
$PAGE->set_heading('Rekap Kehadiran Murid');
$PAGE->requires->jquery();
// load css sticky header
//$PAGE->requires->css('/local/jurnalmengajar/css/stickyheader.css');

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
echo $OUTPUT->heading('Rekap Kehadiran Murid Per Kelas');

// Ambil daftar kelas
$kelaslist = $DB->get_records_menu('cohort', null, 'name ASC', 'id, name');

// Ambil parameter (mode default HARI)
$kelasid     = optional_param('kelas', 0, PARAM_INT);
$dari_raw    = optional_param('dari', '', PARAM_RAW);
$sampai_raw  = optional_param('sampai', '', PARAM_RAW);
$mode        = optional_param('mode', 'hari', PARAM_ALPHA); // 'hari' | 'jam'  ← default HARI
$onlymine    = optional_param('onlymine', 0, PARAM_BOOL);
$matpel      = optional_param('matpel', '', PARAM_TEXT);

$dari   = $dari_raw   ? strtotime($dari_raw) : 0;
$sampai = $sampai_raw ? (strtotime($sampai_raw) + 86399) : 0;

// Fungsi format tanggal Indonesia
function format_tanggal_indo($timestamp) {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $tgl = date('j', $timestamp);
    $bln = $bulan[(int)date('n', $timestamp)];
    $thn = date('Y', $timestamp);
    return "{$tgl} {$bln} {$thn}";
}

// Normalisasi status
function normalize_status($s) {
    $s = strtolower(trim($s));
    $map = [
        'ijin' => 'ijin', 'izin' => 'ijin',
        'sakit' => 'sakit', 'skt' => 'sakit',
        'alpha' => 'alpa', 'alpa' => 'alpa', 'absen' => 'alpa',
        'disp' => 'dispensasi', 'dispen' => 'dispensasi', 'dispensasi' => 'dispensasi',
        'hadir' => 'hadir'
    ];
    return $map[$s] ?? $s;
}

// Prioritas status untuk mode "hari" (semakin besar => makin dominan)
$priority = [
    'hadir'       => 0,
    'dispensasi'  => 1,
    'sakit'       => 2,
    'ijin'        => 3,
    'alpa'        => 4,
];

// ===== Form filter (vertikal) =====
echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'mb-3']);

// Pilih Kelas & Mode Hitung (1 baris)
echo html_writer::start_div('mb-2 d-flex gap-3 align-items-end');

// Pilih Kelas
echo html_writer::start_div('flex-fill');
echo html_writer::tag('label', 'Pilih Kelas:', ['for' => 'kelas', 'class' => 'form-label']);
echo html_writer::select(
    $kelaslist,
    'kelas',
    $kelasid ?: '',
    ['' => '-- Pilih Kelas --'],
    ['class' => 'form-select', 'id' => 'kelas']
);
echo html_writer::end_div();

// Mode Hitung
echo html_writer::start_div('flex-fill');
echo html_writer::tag('label', 'Mode Hitung:', ['for' => 'mode', 'class' => 'form-label']);
$optionsmode = ['hari' => 'Per Hari', 'jam' => 'Per Jam'];
echo html_writer::select(
    $optionsmode,
    'mode',
    in_array($mode, ['hari','jam']) ? $mode : 'hari',
    false,
    ['class' => 'form-select', 'id' => 'mode']
);
echo html_writer::end_div();

echo html_writer::end_div();

// Dari tanggal & Sampai tanggal (1 baris)
echo html_writer::start_div('mb-2 d-flex gap-3 align-items-end');

// Dari tanggal
echo html_writer::start_div('flex-fill');
echo html_writer::tag('label', 'Dari tanggal:', ['for' => 'dari', 'class' => 'form-label']);
echo html_writer::empty_tag('input', [
    'type'     => 'date',
    'name'     => 'dari',
    'id'       => 'dari',
    'value'    => s($dari_raw),
    'class'    => 'form-control',
    'required' => 'required'
]);
echo html_writer::end_div();

// Sampai tanggal
echo html_writer::start_div('flex-fill');
echo html_writer::tag('label', 'Sampai tanggal:', ['for' => 'sampai', 'class' => 'form-label']);
echo html_writer::empty_tag('input', [
    'type'     => 'date',
    'name'     => 'sampai',
    'id'       => 'sampai',
    'value'    => s($sampai_raw),
    'class'    => 'form-control',
    'required' => 'required'
]);
echo html_writer::end_div();

echo html_writer::end_div();

// Mata Pelajaran + Hanya Jurnal Saya + Tombol Tampilkan (1 baris)
echo html_writer::start_div('mb-3 d-flex align-items-end gap-3');

// Mata pelajaran
echo html_writer::start_div('flex-fill');
echo html_writer::tag('label', 'Mata Pelajaran:', ['for' => 'matpel', 'class' => 'form-label']);
echo html_writer::empty_tag('input', [
    'type'        => 'text',
    'name'        => 'matpel',
    'id'          => 'matpel',
    'value'       => s($matpel),
    'class'       => 'form-control',
    'placeholder' => 'Kosongkan jika semua mapel'
]);
echo html_writer::end_div();

// Checkbox hanya jurnal saya
echo html_writer::start_div('form-check mb-0 ms-2');
echo html_writer::empty_tag('input', [
    'type'    => 'checkbox',
    'name'    => 'onlymine',
    'id'      => 'onlymine',
    'value'   => 1,
    'class'   => 'form-check-input',
    'checked' => $onlymine ? 'checked' : null
]);
echo html_writer::tag('label', 'Hanya Jurnal Saya', ['for' => 'onlymine', 'class' => 'form-check-label']);
echo html_writer::end_div();

// Tombol tampilkan
echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => 'Tampilkan',
    'class' => 'btn btn-primary ms-2'
]);

echo html_writer::end_div(); // end flex baris
echo html_writer::end_tag('form');


// ===== Ringkasan filter =====
if ($dari && $sampai) {
    echo '<p><strong>Rentang Tanggal:</strong> ' . format_tanggal_indo($dari) . ' sampai ' . format_tanggal_indo($sampai) . '</p>';
}
echo '<p><strong>Mode:</strong> ' . ($mode === 'hari' ? 'Per Hari' : 'Per Jam') . '</p>';
if ($onlymine || $matpel !== '') {
    $badge = [];
    if ($onlymine) { $badge[] = 'Hanya jurnal saya'; }
    if ($matpel !== '') { $badge[] = 'Matpel: '.s($matpel); }
    echo '<p><em>Filter: ' . implode(' | ', $badge) . '</em></p>';
}

// ===== Proses data =====
if ($kelasid && $dari && $sampai) {
    $members = $DB->get_records('cohort_members', ['cohortid' => $kelasid]);
    $userids = array_map(fn($m) => $m->userid, $members);

    if (empty($userids)) {
        echo 'Tidak ada murid dalam kelas ini.';
        echo $OUTPUT->footer();
        exit;
    }

    list($in_sql, $paramsin) = $DB->get_in_or_equal($userids);
    $users = $DB->get_records_sql("
        SELECT id, firstname, lastname
        FROM {user}
        WHERE id $in_sql
        ORDER BY lastname ASC, firstname ASC
    ", $paramsin);

    // Build SELECT jurnal dengan filter tambahan
    $params = ['kelas' => $kelasid, 'dari' => $dari, 'sampai' => $sampai];
    $wheres = ['kelas = :kelas', 'timecreated BETWEEN :dari AND :sampai'];

    if ($onlymine) {
        global $USER;
        $wheres[] = 'userid = :uid';
        $params['uid'] = $USER->id;
    }
    if ($matpel !== '') {
        // exact match; jika ingin LIKE, ganti dua baris di bawah
        $wheres[] = 'matapelajaran = :matpel';
        $params['matpel'] = $matpel;
        // Alternatif LIKE:
        // $wheres[] = $DB->sql_like('matapelajaran', ':matpel', false, false);
        // $params['matpel'] = "%{$matpel}%";
    }

    $selectsql = implode(' AND ', $wheres);
    $jurnals = $DB->get_records_select('local_jurnalmengajar', $selectsql, $params);

    // ==========================
    // CABANG PERHITUNGAN
    // ==========================
    $data = [];
    foreach ($users as $u) {
        $data[$u->id] = ['hadir' => 0, 'sakit' => 0, 'ijin' => 0, 'alpa' => 0, 'dispensasi' => 0];
    }

    if ($mode === 'hari') {
    // ====== MODE PER HARI: HANYA HITUNG KETIDAKHADIRAN PENUH ======

    $perhari = [];     // [userid][tgl] => ['hadir'=>x,'sakit'=>x,'ijin'=>x,'alpa'=>x,'dispensasi'=>x]
    $all_dates = [];   // daftar tanggal unik

    foreach ($jurnals as $jurnal) {
        $tgl = date('Y-m-d', $jurnal->timecreated);
        $all_dates[$tgl] = true;

        // Ambil daftar jam di jurnal ini
        $jamke  = array_filter(array_map('trim', explode(',', (string)($jurnal->jamke ?? ''))));
        $jmljam = count($jamke);
        if ($jmljam == 0) {
            // fallback: kalau jamke kosong, anggap 1 jam
            $jmljam = 1;
        }

        // JSON absen: nama => alasan
        $absen = json_decode($jurnal->absen, true) ?? [];
        $lookup = [];
        foreach ($absen as $nama => $alasan) {
            $namajson = trim($nama);
            $lookup[mb_strtolower($namajson, 'UTF-8')] = normalize_status($alasan);
        }

        // Isi per hari per siswa
        foreach ($users as $uid => $u) {
            $namasiswa = mb_strtolower(trim($u->lastname), 'UTF-8');

            if (!isset($perhari[$uid][$tgl])) {
                $perhari[$uid][$tgl] = [
                    'hadir'       => 0,
                    'sakit'       => 0,
                    'ijin'        => 0,
                    'alpa'        => 0,
                    'dispensasi'  => 0,
                ];
            }

            // Default hadir, kecuali ada di JSON absen
            if (isset($lookup[$namasiswa])) {
                $status = $lookup[$namasiswa];
            } else {
                $status = 'hadir';
            }

            if (!isset($perhari[$uid][$tgl][$status])) {
                $status = 'hadir';
            }

            // Tambahkan sejumlah jam jurnal ini
            $perhari[$uid][$tgl][$status] += $jmljam;
        }
    }

    $uniqdates = array_keys($all_dates);
    sort($uniqdates);

    // Konversi per-jam ke per-hari (full day only)
    foreach ($users as $uid => $u) {
        foreach ($uniqdates as $tgl) {
            if (empty($perhari[$uid][$tgl])) {
                continue;
            }

            $h = $perhari[$uid][$tgl]['hadir'];
            $tot = array_sum($perhari[$uid][$tgl]); // total jam hari itu
            if ($tot == 0) {
                continue;
            }

            $nonhadir = $tot - $h;

            if ($nonhadir == 0) {
                // Semua jam hadir -> 1 hari hadir
                $statushari = 'hadir';
            } else if ($h == 0) {
                // Tidak hadir sama sekali seharian -> pilih status nonhadir prioritas tertinggi
                $statushari = 'hadir';
                $maxprio = -1;
                foreach (['dispensasi','sakit','ijin','alpa'] as $st) {
                    if (!empty($perhari[$uid][$tgl][$st])) {
                        $p = $priority[$st] ?? 0;
                        if ($p > $maxprio) {
                            $maxprio = $p;
                            $statushari = $st;
                        }
                    }
                }
            } else {
                // Campuran hadir + tidak hadir di hari itu
                // → dianggap HADIR di rekap harian (tidak merugikan murid)
                $statushari = 'hadir';
            }

            if (!isset($data[$uid][$statushari])) {
                $statushari = 'hadir';
            }
            $data[$uid][$statushari] += 1;
        }
    }

    $total_unit = count($uniqdates); // total hari
    $unit_label = 'hari';
}
 else {
        // ====== MODE PER JAM (JAMKE) ======
        foreach ($jurnals as $jurnal) {
            $jamke  = array_filter(array_map('trim', explode(',', (string)($jurnal->jamke ?? ''))));
            $jmljam = count($jamke);
            $absen  = json_decode($jurnal->absen, true) ?? [];

            foreach ($users as $uid => $u) {
                $namasiswa = trim($u->lastname);
                $found = false;

                foreach ($absen as $nama => $alasan) {
                    $namajson = trim($nama);
                    $alasan = strtolower(trim($alasan));

                    if (strcasecmp($namajson, $namasiswa) == 0) {
                        $alasan = normalize_status($alasan);
                        if (isset($data[$uid][$alasan])) {
                            $data[$uid][$alasan] += $jmljam;
                        }
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $data[$uid]['hadir'] += $jmljam;
                }
            }
        }

        // total_unit = total jam (ambil maksimum agar konsisten tampilan)
        $total_unit = 0;
        foreach ($data as $d) {
            $total_unit = max($total_unit, $d['hadir'] + $d['sakit'] + $d['ijin'] + $d['alpa'] + $d['dispensasi']);
        }
        $unit_label = 'jam';
    }

    // ====== TABEL HASIL ======
    echo html_writer::start_div('table-wrapper');
    echo html_writer::start_tag('table', ['class' => 'generaltable']);
    echo html_writer::start_tag('thead');
    echo html_writer::tag('tr',
        html_writer::tag('th', 'No') .
        html_writer::tag('th', 'Nama Murid') .
        html_writer::tag('th', 'Hadir') .
        html_writer::tag('th', 'Sakit') .
        html_writer::tag('th', 'Ijin') .
        html_writer::tag('th', 'Alpa') .
        html_writer::tag('th', 'Dispensasi') .
        html_writer::tag('th', 'Persentase') .
        html_writer::tag('th', 'Aksi')
    );
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    $no = 1;
    foreach ($data as $uid => $d) {
        $total = $total_unit; // total hari atau total jam (tergantung mode)
        if ($total > 0) {
            $p = ($d['hadir'] / $total) * 100;
            $p1 = round($p, 1);
            $is_int = abs($p1 - round($p1)) < 0.00001;
            $pstr = $is_int ? (string)round($p1) : number_format($p1, 1, ',', '');
            $persen = $pstr . '% dari ' . $total . ' ' . $unit_label;
        } else {
            $persen = '-';
        }

        $namasiswa = ucwords(strtolower($users[$uid]->lastname));

        $link = new moodle_url('/local/jurnalmengajar/rekap_permurid.php', [
            'siswa'  => $uid,
            'kelas'  => $kelasid,
            'dari'   => date('Y-m-d', $dari),
            'sampai' => date('Y-m-d', $sampai),
            'mode'   => $mode,
            'onlymine' => $onlymine ? 1 : 0,
            'matpel'   => $matpel
        ]);
        $aksi = html_writer::link($link, '🔍 Lihat Rekap Per Murid');

        echo html_writer::tag('tr',
            html_writer::tag('td', $no++) .
            html_writer::tag('td', $namasiswa) .
            html_writer::tag('td', $d['hadir']) .
            html_writer::tag('td', $d['sakit']) .
            html_writer::tag('td', $d['ijin']) .
            html_writer::tag('td', $d['alpa']) .
            html_writer::tag('td', $d['dispensasi']) .
            html_writer::tag('td', $persen) .
            html_writer::tag('td', $aksi)
        );
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_div(); // end wrapper

    // Tombol ekspor
    if (!empty($data)) {
        $exportbase = new moodle_url('/local/jurnalmengajar/rekap_kehadiran_export.php', [
            'kelas'  => $kelasid,
            'dari'   => date('Y-m-d', $dari),
            'sampai' => date('Y-m-d', $sampai),
            'mode'   => $mode,
            'onlymine' => $onlymine ? 1 : 0,
            'matpel'   => $matpel
        ]);
        echo html_writer::start_div('mt-3 mb-3');
        echo html_writer::link(new moodle_url($exportbase, ['format' => 'xlsx']), '📤 Ekspor ke XLSX', [
            'class' => 'btn btn-primary mr-2',
            'style' => 'margin-right: 10px;'
        ]);
        echo html_writer::link(new moodle_url($exportbase, ['format' => 'ods']), '📤 Ekspor ke ODS', [
            'class' => 'btn btn-success'
        ]);
        echo html_writer::end_div();
    }
}

echo $OUTPUT->footer();
