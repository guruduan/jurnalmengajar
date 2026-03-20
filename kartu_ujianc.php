<?php
// local/jurnalmengajar/kartu_ujian.php
// Generate Kartu Peserta Ujian + Generate Daftar Hadir (A4 Landscape)
// Revisi: untuk Sen-Kam buat 2 subkolom per tanggal (sesi awal | sesi akhir+1).
// File lengkap; ganti isi file di server dengan ini.

require(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/kartu_ujianc.php'));
$PAGE->set_pagelayout('report');
$PAGE->set_title('Generate Kartu Peserta Ujian / Daftar Hadir');
$PAGE->set_heading('Generate Kartu Peserta Ujian / Daftar Hadir');

global $DB, $CFG;

// ambil semua cohort
$allcohorts = $DB->get_records('cohort', null, 'name ASC');

$action = optional_param('action', '', PARAM_ALPHA);

// ===== fungsi build plan (sama) =====
function build_plan($allcohorts, $rooms, $tanggal_mulai, $jumlah_hari) {
    global $DB;
    $students_by_cohort = [];
    $maxcount = 0;
    foreach ($allcohorts as $c) {
        $cid = $c->id;
        $sql = "SELECT u.id, u.username, u.firstname, u.lastname, u.idnumber, u.password, c.name AS cohortname
                  FROM {cohort_members} cm
                  JOIN {user} u ON u.id = cm.userid
                  JOIN {cohort} c ON c.id = cm.cohortid
                 WHERE c.id = :cid
              ORDER BY u.lastname ASC, u.firstname ASC";
        $stus = $DB->get_records_sql($sql, ['cid' => $cid]);
        $students_by_cohort[$cid] = array_values($stus);
        $cnt = count($stus);
        if ($cnt > $maxcount) $maxcount = $cnt;
    }

  // --- mulai penggantian: build ordered_students sesuai pola XA, XI-A, XII-A, XB, ... ---
$ordered_students = [];
$seen = [];

// buat map cohort id->name dan normalized name -> id
$cohortnames_by_id = [];
$norm_to_id = [];
foreach ($allcohorts as $c) {
    $cohortnames_by_id[$c->id] = $c->name;
    // normalisasi: huruf besar, hilangkan spasi, ganti underscore dan multiple dash tetap
    $norm = strtoupper(preg_replace('/[^A-Z0-9\-]/', '', str_replace('_', '', str_replace(' ', '', $c->name))));
    $norm_to_id[$norm] = $c->id;
}

// bangun students_by_cohort_name (normalized => array students)
$students_by_cohort_name = [];
foreach ($students_by_cohort as $cid => $arr) {
    $cname = isset($cohortnames_by_id[$cid]) ? $cohortnames_by_id[$cid] : '';
    $norm = strtoupper(preg_replace('/[^A-Z0-9\-]/', '', str_replace('_', '', str_replace(' ', '', $cname))));
    $students_by_cohort_name[$norm] = array_values($arr);
}

// urutan huruf yang Anda pakai (A..G)
$letters = ['A','B','C','D','E','F','G'];

// build groups per letter: X{L}, XI-{L}, XII-{L}
$sequence_groups = [];
foreach ($letters as $L) {
    $g = [];
    $g[] = strtoupper('X' . $L);       // contoh: XA
    $g[] = strtoupper('XI-' . $L);     // contoh: XI-A
    $g[] = strtoupper('XII-' . $L);    // contoh: XII-A (mungkin tidak ada untuk F/G)
    $sequence_groups[] = $g;
}

// cari panjang maksimal di antara cohort yang ada (untuk indeks iterasi)
$maxcount = 0;
foreach ($sequence_groups as $grp) {
    foreach ($grp as $cohnorm) {
        $cnt = isset($students_by_cohort_name[$cohnorm]) ? count($students_by_cohort_name[$cohnorm]) : 0;
        if ($cnt > $maxcount) $maxcount = $cnt;
    }
}

// interleave per indeks: untuk i = 0..maxcount-1, ambil X?, XI-?, XII-? (jika ada)
for ($i = 0; $i < $maxcount; $i++) {
    foreach ($sequence_groups as $grp) {
        foreach ($grp as $cohnorm) {
            if (!empty($students_by_cohort_name[$cohnorm]) && isset($students_by_cohort_name[$cohnorm][$i])) {
                $stu = $students_by_cohort_name[$cohnorm][$i];
                if (!isset($seen[$stu->id])) {
                    $ordered_students[] = $stu;
                    $seen[$stu->id] = true;
                }
            }
        }
    }
}

// jika ada siswa dari cohort lain yang tidak tercakup, tambahkan di akhir (preserve order by cohort id)
foreach ($students_by_cohort as $cid => $arr) {
    $cname = isset($cohortnames_by_id[$cid]) ? $cohortnames_by_id[$cid] : '';
    $norm = strtoupper(preg_replace('/[^A-Z0-9\-]/', '', str_replace('_', '', str_replace(' ', '', $cname))));
    // cek apakah norm sudah tercakup dalam sequence_groups
    $inSequence = false;
    foreach ($sequence_groups as $g) {
        if (in_array($norm, $g, true)) { $inSequence = true; break; }
    }
    if ($inSequence) continue;
    foreach ($arr as $stu) {
        if (!isset($seen[$stu->id])) {
            $ordered_students[] = $stu;
            $seen[$stu->id] = true;
        }
    }
}
// --- selesai penggantian ---


    $total_students = count($ordered_students);
    $total_capacity = 0;
    foreach ($rooms as $r) $total_capacity += $r['capacity'];
    if ($total_capacity < 1) $total_capacity = 1;

    $num_sessions = (int) ceil($total_students / $total_capacity);
    if ($num_sessions < 1) $num_sessions = 1;

    $sessions = [];
    for ($s = 1; $s <= $num_sessions; $s++) $sessions[$s] = [];
    foreach ($ordered_students as $idx => $stu) {
        $sno = (int) floor($idx / $total_capacity) + 1;
        if ($sno > $num_sessions) $sno = $num_sessions;
        $sessions[$sno][] = $stu;
    }

    $seating_by_session = [];
    foreach ($sessions as $sno => $stulist) {
        $fullslots = [];
        foreach ($rooms as $ri => $rinfo) {
            for ($m = 1; $m <= $rinfo['capacity']; $m++) {
                $fullslots[] = ['room' => $rinfo['name'], 'meja' => $m];
            }
        }
        $seating_by_session[$sno] = $fullslots;
    }

    // exam dates (Mon-Fri)
    $exam_dates = [];
    $d = strtotime($tanggal_mulai);
    while (count($exam_dates) < $jumlah_hari) {
        $w = (int) date('N', $d);
        if ($w >= 1 && $w <= 5) $exam_dates[] = date('Y-m-d', $d);
        $d = strtotime('+1 day', $d);
    }

    return (object)[
        'ordered_students' => $ordered_students,
        'sessions' => $sessions,
        'seating_by_session' => $seating_by_session,
        'num_sessions' => $num_sessions,
        'exam_dates' => $exam_dates,
        'total_capacity' => $total_capacity
    ];
}

// ===== ambil input bila generate/attendance =====
if (($action === 'generate' || $action === 'attendance') && confirm_sesskey()) {
    $nama_ujian    = required_param('nama_ujian', PARAM_TEXT);
    $nama_sekolah  = required_param('nama_sekolah', PARAM_TEXT);
    $tahun_ajaran  = required_param('tahun_ajaran', PARAM_TEXT);
    $tanggal_mulai = required_param('tanggal_mulai', PARAM_RAW_TRIMMED);
    $jumlah_hari   = required_param('jumlah_hari', PARAM_INT);
    $jumlah_ruang  = required_param('jumlah_ruang', PARAM_INT);

    $rooms = [];
    for ($r = 1; $r <= $jumlah_ruang; $r++) {
        $rname = optional_param('ruang_'.$r, '', PARAM_RAW_TRIMMED);
        $rkap  = optional_param('ruang_'.$r.'_kapasitas', 0, PARAM_INT);
        if ($rname !== '' && $rkap > 0) $rooms[] = ['name' => $rname, 'capacity' => $rkap];
    }
    if (empty($rooms)) {
        echo $OUTPUT->header();
        echo $OUTPUT->notification('Isi data ruang terlebih dahulu.', 'notifyproblem');
        echo $OUTPUT->footer();
        exit;
    }

    $plan = build_plan($allcohorts, $rooms, $tanggal_mulai, $jumlah_hari);
}

// ---------------- ACTION: GENERATE KARTU (fix tombol previously) ----------------
if ($action === 'generate' && confirm_sesskey()) {
    $assignments = [];
    for ($s = 1; $s <= $plan->num_sessions; $s++) {
        $students_in_s = $plan->sessions[$s] ?? [];
        $slots = $plan->seating_by_session[$s] ?? [];
        for ($i = 0; $i < count($students_in_s); $i++) {
            $stu = $students_in_s[$i];
            $slot = $slots[$i] ?? ['room' => $rooms[0]['name'], 'meja' => ($i+1)];
            $assignments[] = ['user' => $stu, 'session' => $s, 'room' => $slot['room'], 'meja' => $slot['meja']];
        }
    }

    require_once($CFG->libdir . '/pdflib.php');
    $pdf = new pdf(PDF_PAGE_ORIENTATION, 'mm', [330, 215], true, 'UTF-8', false);
    $pdf->SetCreator('Moodle');
    $pdf->SetAuthor(fullname($USER));
    $pdf->SetTitle('Kartu Peserta Ujian');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(0,0,0);
    $pdf->SetAutoPageBreak(false,0);
    $pdf->AddPage('L');

    $cols = 3; $rows = 3;
    $pagewidth = 330; $pageheight = 215;
    $cardw = $pagewidth / $cols;
    $cardh = $pageheight / $rows;

    $tanggal_ujian_teks = build_tanggal_range($plan->exam_dates[0] ?? '', end($plan->exam_dates) ?? '');

    $i = 0;
    foreach ($assignments as $assign) {
        if ($i > 0 && $i % 9 == 0) $pdf->AddPage('L');
        $idx = $i % 9;
        $row = floor($idx / $cols);
        $col = $idx % $cols;
        $x = $col * $cardw; $y = $row * $cardh;

        $pdf->SetDrawColor(200,200,200);
        $pdf->Rect($x + 2, $y + 2, $cardw - 4, $cardh - 4);

        $padx = 6; $pady = 5;
        $stu = $assign['user'];
        $roomname = $assign['room'];
        $meja = $assign['meja'];
        $sno = $assign['session'];

        // header
        $pdf->SetFont('helvetica','B',11);
        $pdf->SetXY($x, $y + $pady);
        $pdf->Cell($cardw,5,'KARTU PESERTA',0,1,'C');
        $pdf->SetFont('helvetica','',10);
        $pdf->SetX($x); $pdf->Cell($cardw,5,$nama_ujian,0,1,'C');
        $pdf->SetX($x); $pdf->Cell($cardw,5,$nama_sekolah,0,1,'C');
        $pdf->SetX($x); $pdf->Cell($cardw,5,'Tahun Ajaran '.$tahun_ajaran,0,1,'C');
        $pdf->SetX($x); $pdf->Cell($cardw,5,$tanggal_ujian_teks,0,1,'C');
        $pdf->Ln(1);

        // Nama (bold normal) left, Kelas bold normal right
        $leftX = $x + $padx; $rightX = $x + ($cardw/2);
        $pdf->SetFont('helvetica','B',9);
        $pdf->SetXY($leftX, $pdf->GetY());
        $pdf->Cell(($cardw/2)-$padx, 6, 'Nama : ' . $stu->lastname, 0, 0, 'L');
        $pdf->SetXY($rightX, $pdf->GetY());
        $pdf->Cell(($cardw/2)-$padx, 6, 'Kelas : ' . $stu->cohortname, 0, 1, 'R');
        $pdf->Ln(1);

        $pdf->SetFont('helvetica','',9);
        $pdf->SetX($leftX);
        $pdf->Cell(($cardw/2)-$padx, 4, 'Ruang : ' . $roomname, 0, 0, 'L');
        $pdf->SetX($rightX);
        $pdf->Cell(($cardw/2)-$padx, 4, 'Username : ' . $stu->username, 0, 1, 'L');

        $password = get_student_plain_password($stu);
        $pdf->SetX($leftX);
        $pdf->Cell(($cardw/2)-$padx, 4, 'Nomor Meja : ' . $meja, 0, 0, 'L');
        $pdf->SetX($rightX);
        $pdf->Cell(($cardw/2)-$padx, 4, 'Password : ' . $password, 0, 1, 'L');

        // sesi table (Hari / Tanggal / Sesi) -- gunakan s_on_day relatif ke sno
        $pdf->Ln(1);
        $numDates = max(1, count($plan->exam_dates));
        $cellw = ($cardw - 2*$padx) / $numDates;
        if ($numDates <= 6) { $fontDay = 8; $fontSession = 10; }
        elseif ($numDates <= 10) { $fontDay = 7; $fontSession = 9; }
        else { $fontDay = 6; $fontSession = 8; }
        // Hari labels
        $pdf->SetFont('helvetica','B',$fontDay);
        $pdf->SetX($leftX);
        foreach ($plan->exam_dates as $ed) {
            $wday = (int) date('N', strtotime($ed));
            $label = ['','Sen','Sel','Rab','Kam','Jum'][$wday] ?? '';
            $pdf->Cell($cellw,5,$label,0,0,'C');
        }
        $pdf->Ln(5);
        // Tanggal labels
        $pdf->SetFont('helvetica','',$fontDay);
        $pdf->SetX($leftX);
        foreach ($plan->exam_dates as $ed) {
            $dt = date_create_from_format('Y-m-d',$ed);
            $lab = $dt ? intval($dt->format('j')).'/'.$dt->format('n') : $ed;
            $pdf->Cell($cellw,5,$lab,0,0,'C');
        }
        $pdf->Ln(5);
        // Sesi labels (per tanggal): for Sen-Thu two-wave as requested
        $pdf->SetFont('helvetica','B',$fontSession);
        $pdf->SetX($leftX);
        for ($didx=0;$didx<$numDates;$didx++) {
            $ed = $plan->exam_dates[$didx];
            $s_on_day = ((($sno - 1) + $didx) % $plan->num_sessions) + 1;
            $w = (int) date('N', strtotime($ed));
            if ($w >=1 && $w <=4) {
                $second = $s_on_day + $plan->num_sessions;
                $txt = $s_on_day . ' & ' . $second;
            } else {
                $txt = (string)$s_on_day;
            }
            $pdf->Cell($cellw,6,$txt,0,0,'C');
        }
        $pdf->Ln(6);

        $i++;
    }

    $pdf->Output('kartu_peserta_ujian.pdf','I');
    exit;
}

// ---------------- ACTION: attendance (landscape, paginated, two subcols for Sen-Thu) ----------------
if ($action === 'attendance' && confirm_sesskey()) {
    global $CFG;
    require_once($CFG->libdir . '/pdflib.php');

    $seating_by_session = $plan->seating_by_session;
    $num_sessions = $plan->num_sessions;
    $exam_dates = $plan->exam_dates;

    // map user->s0 (initial session)
    $user_session = [];
    foreach ($plan->sessions as $sno => $list) {
        foreach ($list as $stu) $user_session[$stu->id] = $sno;
    }

    // PDF A4 landscape
    $pdf = new pdf(PDF_PAGE_ORIENTATION, 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Moodle');
    $pdf->SetAuthor(fullname($USER));
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // margins
    $leftMargin = 12; $rightMargin = 12; $topMargin = 12; $bottomMargin = 12;
    $pdf->SetMargins($leftMargin,$topMargin,$rightMargin);
    $pdf->SetAutoPageBreak(false);

    // For each initial session s (1..num_sessions)
    for ($s = 1; $s <= $num_sessions; $s++) {
        $slots = $seating_by_session[$s] ?? [];
        $students_in_s = $plan->sessions[$s] ?? [];
        // map slot index -> student assigned in session s
        $slot_student = [];
        for ($i = 0; $i < count($slots); $i++) {
            $slot_student[$i] = $students_in_s[$i] ?? null;
        }

        // group slots by room
        $roomnames = array_map(function($r){ return $r['name']; }, $rooms);
        foreach ($roomnames as $rname) {
            // collect rows: all slots that belong to this room (preserve slot order)
            $all_rows = [];
            foreach ($slots as $idx => $slot) {
                if ($slot['room'] === $rname) {
                    $stu = $slot_student[$idx] ?? null;
                    $all_rows[] = ['slotindex' => $idx, 'student' => $stu];
                }
            }

            // layout calculations (landscape)
            $pageWidthTotal = 297;
            $printableW = $pageWidthTotal - $leftMargin - $rightMargin; // mm

// asumsi: $pdf sudah di-set font yang sama nanti dipakai untuk tabel
// contoh: $pdf->SetFont('helvetica','',10);

$maxNameW = 0; $maxClassW = 0;
foreach ($all_rows as $rr) {
    if (!empty($rr['student'])) {
        $n = trim($rr['student']->lastname);
        $c = trim($rr['student']->cohortname);
        // ukur lebar aktual teks dengan font saat ini
        $wname = $pdf->GetStringWidth($n);
        $wclass = $pdf->GetStringWidth($c);
        if ($wname > $maxNameW) $maxNameW = $wname;
        if ($wclass > $maxClassW) $maxClassW = $wclass;
    }
}

// padding kecil dalam satuan unit PDF
$namePadding = 4; $kelasPadding = 3;
$estNameW = round($maxNameW + $namePadding);
$estKelasW = round($maxClassW + $kelasPadding);

// tetap pakai batas min/max relatif printable area tapi lebih konservatif
$colNoW = round($printableW * 0.06);
$colNamaMin = round($printableW * 0.10); // minimal 10%
$colNamaMax = round($printableW * 0.35); // maksimal 35% (kurangi dari 45%)
$colKelasMin = round($printableW * 0.06);
$colKelasMax = round($printableW * 0.12); // maksimal 12% (lebih kecil)

$colNamaW = min(max($estNameW, $colNamaMin), $colNamaMax);
$colKelasW = min(max($estKelasW, $colKelasMin), $colKelasMax);

// cek jika total melebihi printableW (termasuk kolom lain jika ada)
$totalNeeded = $colNoW + $colNamaW + $colKelasW; // + kolom lain...
if ($totalNeeded > $printableW) {
    $available = $printableW - $colNoW;
    // prioritaskan kelas minimal dulu:
    $colKelasW = min($colKelasW, round($available * 0.12));
    $colNamaW = $available - $colKelasW;
    // pastikan nama tidak di bawah min
    if ($colNamaW < $colNamaMin) {
        // jika tetap overflow, kecilkan font 1pt dan rekalkulasi (sederhana):
        $currentFontSize = 10; // set sesuai
        for ($fs = $currentFontSize - 1; $fs >= 6; $fs--) {
            $pdf->SetFont('helvetica','',$fs);
            // hitung ulang lebar maksimum nama
            $maxNameW = 0;
            foreach ($all_rows as $rr) {
                if (!empty($rr['student'])) {
                    $n = trim($rr['student']->lastname);
                    $w = $pdf->GetStringWidth($n);
                    if ($w > $maxNameW) $maxNameW = $w;
                }
            }
            $estNameW = round($maxNameW + $namePadding);
            $colNamaW = min(max($estNameW, $colNamaMin), $colNamaMax);
            $totalNeeded = $colNoW + $colNamaW + $colKelasW;
            if ($totalNeeded <= $printableW) break;
        }
    }
}


            // Determine number of subcolumns: for each date Sen-Thu => 2 subcols, Fri =>1
            $subcols_count = 0;
            $date_subcounts = []; // per date, value 2 or 1
            foreach ($exam_dates as $ed) {
                $w = (int) date('N', strtotime($ed));
                $sc = ($w >= 1 && $w <= 4) ? 2 : 1;
                $date_subcounts[] = $sc;
                $subcols_count += $sc;
            }

            // remaining width for subcolumns
            $remainingW = $printableW - ($colNoW + $colNamaW + $colKelasW);
            if ($remainingW < 10) $remainingW = 10;
            $subcolW = floor($remainingW / $subcols_count);
            // build array of subcol widths per date (if date has 2 subcols, two entries)
            $subcolWidths = [];
            foreach ($date_subcounts as $sc) {
                for ($k=0;$k<$sc;$k++) $subcolWidths[] = $subcolW;
            }
            // fix leftover pixels to last subcol
            $leftover = $remainingW - ($subcolW * $subcols_count);
            if ($leftover > 0) $subcolWidths[count($subcolWidths)-1] += $leftover;

            // Now compute pagination as before
            $usableH = 210 - $topMargin - $bottomMargin;
            $headerArea = 28; // header includes two header rows + sesi row
            $tableHeaderH = 8;
            $pengawasDoubleH = 12;
            $pengawasSignH = 10;
            $availableForRowsPerPage = $usableH - $headerArea - $tableHeaderH - $pengawasDoubleH - $pengawasSignH - 6;
            $minRowH = 5;
            $rowh = 8;
            $rows_per_page = floor($availableForRowsPerPage / $rowh);
            if ($rows_per_page < 4) {
                $rowh = max($minRowH, floor($availableForRowsPerPage / max(1,6)));
                $rows_per_page = floor($availableForRowsPerPage / $rowh);
            }
            if ($rows_per_page < 1) $rows_per_page = 1;

            if ($rowh >= 12) $fontRow = 11;
            elseif ($rowh >= 9) $fontRow = 10;
            elseif ($rowh >= 7) $fontRow = 9;
            elseif ($rowh >= 6) $fontRow = 8;
            else $fontRow = 7;

            // paginate rows
            $total_rows = count($all_rows);
            $pages = [];
            if ($total_rows === 0) $pages[] = []; else {
                $i = 0;
                while ($i < $total_rows) {
                    $pages[] = array_slice($all_rows, $i, $rows_per_page);
                    $i += $rows_per_page;
                }
            }

            // Render pages. We must print header with date (spanning its subcols) then second header row with subcol labels.
            $pageIndex = 0;
            foreach ($pages as $pageRows) {
                $isLastPage = ($pageIndex === count($pages)-1);
                $pdf->AddPage('L');

                // Title + subtitle
                $pdf->SetFont('helvetica','B',14);
                $pdf->Cell(0,7, 'Daftar Hadir - ' . $nama_ujian, 0, 1, 'C');
                $pdf->SetFont('helvetica','',10);
                $subtitle = '' . $nama_sekolah . ' | Mulai Sesi : ' . $s . ' | Ruang: ' . $rname;
                $pdf->Cell(0,6, $subtitle, 0, 1, 'C');
                $pdf->Ln(3);

                // First header row: No | Nama | Kelas | for each date print a cell with width = sum of its subcols
                $pdf->SetFont('helvetica','B', max(8, $fontRow));
                $pdf->Cell($colNoW, $tableHeaderH, 'No', 1, 0, 'C');
                $pdf->Cell($colNamaW, $tableHeaderH, 'Nama', 1, 0, 'C');
                $pdf->Cell($colKelasW, $tableHeaderH, 'Kelas', 1, 0, 'C');

                // iterate dates and compute total width per date by summing next N subcolWidths
                $subidx = 0;
                foreach ($date_subcounts as $di => $sc) {
                    $totalW = 0;
                    for ($k=0;$k<$sc;$k++) { $totalW += $subcolWidths[$subidx + $k]; }
                    // show date label (e.g. Sen 1/12)
                    $ed = $exam_dates[$di];
                    $wday = (int) date('N', strtotime($ed));
                    $labelDay = ['','Sen','Sel','Rab','Kam','Jum'][$wday] ?? '';
                    $dt = date_create_from_format('Y-m-d', $ed);
                    $labelDate = $dt ? intval($dt->format('j')) . '/' . $dt->format('n') : $ed;
                    $headerLabel = $labelDay . ' ' . $labelDate;
                    $pdf->Cell($totalW, $tableHeaderH, $headerLabel, 1, 0, 'C');
                    $subidx += $sc;
                }
                $pdf->Ln();

                // Second header row: empty under No | Nama put 'Sesi' | empty under Kelas to align
                $pdf->SetFont('helvetica','B', max(8, $fontRow));
                $pdf->Cell($colNoW, $tableHeaderH, 'Meja', 1, 0, 'C');
                $pdf->Cell($colNamaW, $tableHeaderH, 'Sesi', 1, 0, 'C');
                $pdf->Cell($colKelasW, $tableHeaderH, '', 1, 0, 'C');

                // For each date, print its subcols: Sen-Thu => two cells with sesi labels; Fri => one cell
                $subidx = 0;
                foreach ($date_subcounts as $di => $sc) {
                    $ed = $exam_dates[$di];
                    $s_on_day = ((($s - 1) + $di) % $num_sessions) + 1;
                    $w = (int) date('N', strtotime($ed));
                    if ($sc == 2) {
                        // left subcol: s_on_day
                        $pdf->Cell($subcolWidths[$subidx], $tableHeaderH, (string)$s_on_day, 1, 0, 'C');
                        // right subcol: s_on_day + num_sessions
                        $pdf->Cell($subcolWidths[$subidx+1], $tableHeaderH, (string)($s_on_day + $num_sessions), 1, 0, 'C');
                        $subidx += 2;
                    } else {
                        // single subcol (Friday) - put s_on_day centered
                        $pdf->Cell($subcolWidths[$subidx], $tableHeaderH, (string)$s_on_day, 1, 0, 'C');
                        $subidx += 1;
                    }
                }
                $pdf->Ln();

                // rows on this page
                $pdf->SetFont('helvetica','', $fontRow);
                $no = ($pageIndex * $rows_per_page) + 1;
                foreach ($pageRows as $rrow) {
                    $stu = $rrow['student'];
                    $pdf->Cell($colNoW, $rowh, $no, 1, 0, 'C');
                    $nameText = $stu ? format_string($stu->lastname) : '';
                    $pdf->Cell($colNamaW, $rowh, $nameText, 1, 0, 'L');
                    $kelasText = $stu ? format_string($stu->cohortname) : '';
                    $pdf->Cell($colKelasW, $rowh, $kelasText, 1, 0, 'L');

                    // print empty cells for each subcol (these are the signature cells)
                    foreach ($subcolWidths as $sw) {
                        $pdf->Cell($sw, $rowh, '', 1, 0, 'C');
                    }
                    $pdf->Ln();
                    $no++;
                }

                // Last page: add pengawas double row + 'Tanda Tangan Pengawas'
                if ($isLastPage) {
                    $ph = max($rowh * 2, 12);
                    $pdf->SetFont('helvetica','B',$fontRow);
                    $pdf->Cell($colNoW, $ph, '', 1, 0, 'C');
                    $pdf->Cell($colNamaW, $ph, 'NAMA PENGAWAS', 1, 0, 'L');
                    $pdf->Cell($colKelasW, $ph, '', 1, 0, 'L');
                    foreach ($subcolWidths as $sw) $pdf->Cell($sw, $ph, '', 1, 0, 'C');
                    $pdf->Ln();

                    $th = max(10, round($rowh * 1.0));
                    $pdf->SetFont('helvetica','', $fontRow);
                    $pdf->Cell($colNoW, $th, '', 1, 0, 'C');
                    $pdf->Cell($colNamaW, $th, 'Tanda Tangan Pengawas', 1, 0, 'L');
                    $pdf->Cell($colKelasW, $th, '', 1, 0, 'L');
                    foreach ($subcolWidths as $sw) $pdf->Cell($sw, $th, '', 1, 0, 'C');
                    $pdf->Ln();
                }

                $pageIndex++;
            } // pages

        } // rooms
    } // sessions

    // output
    $pdf->Output('daftar_hadir_ujian_' . userdate(time(), '%Y%m%d_%H%M%S') . '.pdf', 'I');
    exit;
}

// ===== FORM =====
echo $OUTPUT->header();
echo html_writer::tag('h3', 'Generate Kartu Peserta Ujian dan Daftar Hadir');

echo html_writer::start_tag('form', ['method'=>'post','action'=>new moodle_url('/local/jurnalmengajar/kartu_ujianc.php'), 'id'=>'form-kartu-ujian']);

// input basic
echo html_writer::label('Nama Ujian','nama_ujian');
echo html_writer::empty_tag('input',['type'=>'text','name'=>'nama_ujian','id'=>'nama_ujian','value'=>'ASESMEN AKHIR SEMESTER','size'=>60,'required'=>'required']);
echo html_writer::empty_tag('br');

echo html_writer::label('Nama Sekolah','nama_sekolah');
echo html_writer::empty_tag('input',['type'=>'text','name'=>'nama_sekolah','id'=>'nama_sekolah','value'=>'SMA NEGERI 2 KANDANGAN','size'=>60,'required'=>'required']);
echo html_writer::empty_tag('br');

echo html_writer::label('Tahun Ajaran','tahun_ajaran');
echo html_writer::empty_tag('input',['type'=>'text','name'=>'tahun_ajaran','id'=>'tahun_ajaran','value'=>'2025/2026','size'=>20,'required'=>'required']);
echo html_writer::empty_tag('br');

echo html_writer::label('Tanggal Mulai Ujian','tanggal_mulai');
echo html_writer::empty_tag('input',['type'=>'date','name'=>'tanggal_mulai','id'=>'tanggal_mulai','required'=>'required']);
echo html_writer::empty_tag('br');

echo html_writer::label('Jumlah Hari Ujian','jumlah_hari');
echo html_writer::empty_tag('input',['type'=>'number','name'=>'jumlah_hari','id'=>'jumlah_hari','value'=>1,'min'=>1,'required'=>'required']);
echo html_writer::empty_tag('br');

echo html_writer::label('Jumlah ruang','jumlah_ruang');
echo html_writer::empty_tag('input',[
    'type'=>'number','name'=>'jumlah_ruang','id'=>'jumlah_ruang',
    'value'=>1,'min'=>1,'required'=>'required','oninput'=>'renderRuangInputs()'
]);
echo html_writer::empty_tag('br');

echo html_writer::tag('div','',['id'=>'ruang-container']);

echo html_writer::empty_tag('input',['type'=>'hidden','name'=>'sesskey','value'=>sesskey()]);

// tombol
echo html_writer::tag('button','🖨️ Generate PDF Kartu',['type'=>'submit','name'=>'action','value'=>'generate','class'=>'btn btn-primary']);
echo ' ';
echo html_writer::tag('button','📋 Generate Daftar Hadir',['type'=>'submit','name'=>'action','value'=>'attendance','class'=>'btn btn-secondary']);

echo html_writer::end_tag('form');
?>
<script>
function renderRuangInputs(){
    const j = parseInt(document.getElementById('jumlah_ruang').value || '0');
    const container = document.getElementById('ruang-container');
    container.innerHTML = '';
    for (let i=1;i<=j;i++){
        const wrap = document.createElement('div');
        wrap.style.marginBottom='6px';
        wrap.innerHTML = `
            <label>Ruang ${i}:</label>
            <input type="text" name="ruang_${i}" required style="min-width:180px;" placeholder="Nama ruang ${i}">
            &nbsp; Jumlah peserta:
            <input type="number" name="ruang_${i}_kapasitas" required min="1" value="30" style="width:80px;">
        `;
        container.appendChild(wrap);
    }
}
renderRuangInputs();
</script>
<?php
echo $OUTPUT->footer();

// ===== helper functions =====
function get_student_plain_password(stdClass $user): string {
    global $DB;
    $fieldshort = ['passwordujian','password_ujian','exam_password','password'];
    foreach ($fieldshort as $short) {
        $field = $DB->get_record('user_info_field', ['shortname'=>$short], 'id', IGNORE_MISSING);
        if ($field) {
            $rec = $DB->get_record('user_info_data', ['fieldid'=>$field->id,'userid'=>$user->id], 'data', IGNORE_MISSING);
            if ($rec && trim($rec->data) !== '') return trim($rec->data);
        }
    }
    if (!empty($user->idnumber) && strlen(trim($user->idnumber))>=3) return trim($user->idnumber);
    if (!empty($user->password)) {
        $pw = trim($user->password);
        if (strpos($pw, '$') === false && strlen($pw) <= 60 && strlen($pw) >= 4) return $pw;
    }
    return '******';
}

function build_tanggal_range(string $mulai, string $sampai): string {
    if (!$mulai) return '';
    if (!$sampai) return format_tanggal_indonesia($mulai);
    return format_tanggal_indonesia($mulai) . ' s.d. ' . format_tanggal_indonesia($sampai);
}

function format_tanggal_indonesia(string $ymd): string {
    if (empty($ymd)) return '';
    $parts = explode('-', $ymd);
    if (count($parts) !== 3) return $ymd;
    [$y,$m,$d] = $parts;
    $bulan = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
              '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
    $nm = $bulan[$m] ?? $m;
    return ltrim($d,'0') . ' ' . $nm . ' ' . $y;
}
