<?php
// local/jurnalmengajar/kartu_ujian.php
// Generate Kartu Peserta Ujian + Generate Daftar Hadir (A4 Portrait per-hari)
// Revisi: Daftar hadir per hari, halaman portrait, header menunjukkan tanggal hari, Ruang

require(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/jurnalmengajar/kartu_ujian.php'));
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

    $ordered_students = [];
    $seen = [];
    for ($i = 0; $i < $maxcount; $i++) {
        foreach ($allcohorts as $c) {
            $cid = $c->id;
            if (isset($students_by_cohort[$cid][$i])) {
                $stu = $students_by_cohort[$cid][$i];
                if (!isset($seen[$stu->id])) {
                    $ordered_students[] = $stu;
                    $seen[$stu->id] = true;
                }
            }
        }
    }

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

// ---------------- ACTION: GENERATE KARTU (tidak diubah) ----------------
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
        $pdf->Cell(($cardw/2)-$padx, 6, 'Nama Murid : ' . $stu->lastname, 0, 0, 'L');
        $pdf->SetXY($rightX, $pdf->GetY());
        $pdf->Cell(($cardw/2)-$padx, 6, 'Kelas : ' . $stu->cohortname, 0, 1, 'R');
        $pdf->Ln(1);

        $pdf->SetFont('helvetica','',9);
        $pdf->SetX($leftX);
        $pdf->Cell(($cardw/2)-$padx, 4, 'Nama Ruang : ' . $roomname, 0, 0, 'L');
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

    $pdf->Output('kartu_peserta_ujian_' . userdate(time(), '%Y%m%d_%H%M') . '.pdf', 'I');
    exit;
}

// ---------------- ACTION: attendance (PORTRAIT, per-day, simplified columns) ----------------
if ($action === 'attendance' && confirm_sesskey()) {
    global $CFG;
    require_once($CFG->libdir . '/pdflib.php');

    $seating_by_session = $plan->seating_by_session;
    $num_sessions = $plan->num_sessions;
    $exam_dates = $plan->exam_dates;

    // build assignments map: userid -> ['session'=>, 'room'=>, 'meja'=>]
    $assignments = [];
    foreach ($seating_by_session as $sno => $slots) {
        $students_in_s = $plan->sessions[$sno] ?? [];
        for ($i = 0; $i < count($slots); $i++) {
            $slot = $slots[$i];
            $stu = $students_in_s[$i] ?? null;
            if ($stu) {
                $assignments[$stu->id] = ['session' => $sno, 'room' => $slot['room'], 'meja' => $slot['meja'], 'user' => $stu];
            }
        }
    }

    // PDF A4 portrait
    $pdf = new pdf('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Moodle');
    $pdf->SetAuthor(fullname($USER));
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    $leftMargin = 12; $rightMargin = 12; $topMargin = 12; $bottomMargin = 12;
    $pdf->SetMargins($leftMargin,$topMargin,$rightMargin);
    $pdf->SetAutoPageBreak(false);

    // For each date, produce pages per room
    foreach ($exam_dates as $ed) {
        // compute which session numbers occur on this date: for weekdays Mon-Thu -> two waves (s_on_day and s_on_day + num_sessions)
        foreach ($rooms as $rinfo) {
            $rname = $rinfo['name'];

            // collect rows: iterate assignments and pick those assigned to this room and whose session matches the sessions on this date
            $rows = [];

            // for each possible base session (1..num_sessions), compute s_on_day for that base
            // Students who have assignment 'session' equal to s_on_day OR s_on_day + num_sessions (if second wave) attend on this date.
            for ($base = 1; $base <= $num_sessions; $base++) {
                $s_on_day = ((($base - 1) + array_search($ed, $exam_dates)) % $num_sessions) + 1; // not used directly
                // Instead, we will compute for each student whether their assigned session maps to this date by reverse check below
            }

            // We'll determine by checking for each assigned student whether their assigned session occurs on this date.
            foreach ($assignments as $aid => $ainfo) {
                if ($ainfo['room'] !== $rname) continue;
                $assigned_session = $ainfo['session'];
                // check if assigned_session occurs on this date:
                // For a given date index $di, the session numbers that take place that date are:
                // s = ((di) % num_sessions) + 1  (this is s_on_day for base session 1), but actually mapping per original code: for session s, s_on_day = ((s-1)+di)%num_sessions +1
                // To find whether assigned_session maps to date index $di: we search for any base s such that ((s-1)+di)%num_sessions +1 == assigned_session OR + num_sessions
                $di = array_search($ed, $exam_dates);
                $occurs = false;
                // iterate base session s_base (1..num_sessions) and see what session numbers occur that date
                for ($sbase = 1; $sbase <= $num_sessions; $sbase++) {
                    $s_on_day = ((($sbase - 1) + $di) % $num_sessions) + 1;
                    // first wave
                    if ($assigned_session === $s_on_day) { $occurs = true; break; }
                    // second wave (if Mon-Thu) assumed as + num_sessions
                    $w = (int) date('N', strtotime($ed));
                    if ($w >=1 && $w <=4) {
                        if ($assigned_session === ($s_on_day + $num_sessions)) { $occurs = true; break; }
                    }
                }
                if ($occurs) {
                    $rows[] = $ainfo; // includes user object
                }
            }

            // sort rows by meja
            usort($rows, function($a, $b){ return $a['meja'] <=> $b['meja']; });

            // pagination: simple: rows per page depending on font and space
            $pdf->SetFont('helvetica','',10);
            $pageWidth = 210; $pageHeight = 297;
            $printableW = $pageWidth - $leftMargin - $rightMargin;
            $printableH = $pageHeight - $topMargin - $bottomMargin;

            // column widths (mm)
            $colMejaW = 20; // No meja
            $colNamaW = 70;
            $colKelasW = 30;
            $colSesiW = max(15, floor(($printableW - ($colMejaW + $colNamaW + $colKelasW)) / 2));

            $headerH = 14; // title area
            $tableHeaderH = 8;
            $rowH = 8;
            $rows_per_page = floor(($printableH - $headerH - 30) / $rowH);
            if ($rows_per_page < 4) $rows_per_page = 4;

            $chunks = array_chunk($rows, $rows_per_page);
            if (empty($chunks)) $chunks = [[]];

            foreach ($chunks as $pindex => $chunkRows) {
                $pdf->AddPage('P');

                // Title
                $pdf->SetFont('helvetica','B',14);
                $pdf->Cell(0,7, 'Daftar Hadir Ujian - ' . $nama_ujian, 0, 1, 'C');
                $pdf->SetFont('helvetica','',10);
                $subtitle = 'Sekolah: ' . $nama_sekolah . ' | Tanggal: ' . format_tanggal_indonesia($ed) . ' | Ruang: ' . $rname;
                $pdf->Cell(0,6, $subtitle, 0, 1, 'C');
                $pdf->Ln(4);

                // Table header
                $pdf->SetFont('helvetica','B',10);
                $pdf->Cell($colMejaW, $tableHeaderH, 'No Meja', 1, 0, 'C');
                $pdf->Cell($colNamaW, $tableHeaderH, 'Nama', 1, 0, 'C');
                $pdf->Cell($colKelasW, $tableHeaderH, 'Kelas', 1, 0, 'C');
                $pdf->Cell($colSesiW, $tableHeaderH, 'Sesi 1', 1, 0, 'C');
                $pdf->Cell($colSesiW, $tableHeaderH, 'Sesi 2', 1, 0, 'C');
                $pdf->Ln();

                // rows
                $pdf->SetFont('helvetica','',10);
                $no = ($pindex * $rows_per_page) + 1;
                foreach ($chunkRows as $r) {
                    $u = $r['user'];
                    $pdf->Cell($colMejaW, $rowH, $r['meja'], 1, 0, 'C');
                    $nameText = $u ? format_string($u->lastname) : '';
                    $pdf->Cell($colNamaW, $rowH, $nameText, 1, 0, 'L');
                    $kelasText = $u ? format_string($u->cohortname) : '';
                    $pdf->Cell($colKelasW, $rowH, $kelasText, 1, 0, 'C');
                    // empty attendance boxes for sesi columns
                    $pdf->Cell($colSesiW, $rowH, '', 1, 0, 'C');
                    $pdf->Cell($colSesiW, $rowH, '', 1, 0, 'C');
                    $pdf->Ln();
                    $no++;
                }

                // if last page for this room on this date, add signature lines
                if ($pindex === count($chunks)-1) {
                    $pdf->Ln(6);
                    $pdf->Cell(0,6, 'Pengawas: ____________________________     NIP: __________________', 0, 1, 'L');
                }
            }

        }
    }

    // output
    $pdf->Output('daftar_hadir_ujian_' . userdate(time(), '%Y%m%d_%H%M') . '.pdf', 'I');
    exit;
}

// ===== FORM =====
echo $OUTPUT->header();
echo html_writer::tag('h3', 'Generate Kartu Peserta Ujian / Daftar Hadir (Semua Cohort)');

echo html_writer::start_tag('form', ['method'=>'post','action'=>new moodle_url('/local/jurnalmengajar/kartu_ujian.php'), 'id'=>'form-kartu-ujian']);

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

echo html_writer::label('Tanggal Mulai (pertama ujian)','tanggal_mulai');
echo html_writer::empty_tag('input',['type'=>'date','name'=>'tanggal_mulai','id'=>'tanggal_mulai','required'=>'required']);
echo html_writer::empty_tag('br');

echo html_writer::label('Jumlah hari ujian (hari kerja, Senin–Jumat)','jumlah_hari');
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
