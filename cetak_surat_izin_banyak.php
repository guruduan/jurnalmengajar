<?php
require_once('../../config.php');
require_once($CFG->libdir . '/pdflib.php');

require_login();
$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);
/**
 * Parse input ids yang bisa berupa:
 *  - satu angka: "1526"
 *  - daftar koma: "1526,1528,1530"
 *  - rentang: "1526-1530"
 *  - gabungan: "1526-1528,1530,1532-1533"
 * Mengembalikan array unik terurut ASC.
 */
 
 // ==========================
// SETTINGS SEKOLAH
// ==========================
$sekolah = get_config('local_jurnalmengajar', 'nama_sekolah');
$tempat  = get_config('local_jurnalmengajar', 'tempat_ttd');

function jm_parse_ids(string $raw, int $maxexpand = 5000): array {
    $raw = trim($raw);
    if ($raw === '') return [];

    // Normalisasi spasi di sekitar tanda minus dan koma.
    $norm = preg_replace('/\s*-\s*/', '-', $raw);
    $norm = preg_replace('/\s*,\s*/', ',', $norm);
    $norm = preg_replace('/\s+/', ',', $norm);

    $out = [];
    foreach (preg_split('/,/', $norm, -1, PREG_SPLIT_NO_EMPTY) as $tok) {
        if (preg_match('/^\d+$/', $tok)) {
            $out[] = (int)$tok;
        } elseif (preg_match('/^(\d+)-(\d+)$/', $tok, $m)) {
            $start = (int)$m[1];
            $end   = (int)$m[2];
            if ($start > 0 && $end >= $start && ($end - $start) <= $maxexpand) {
                for ($i = $start; $i <= $end; $i++) {
                    $out[] = $i;
                }
            }
        }
    }
    $out = array_values(array_unique(array_filter($out)));
    sort($out, SORT_NUMERIC);
    return $out;
}

// --- Param: tetap dukung ?id=123 (legacy) dan ?ids=1,2,3 atau rentang 1-5 ---
$id_single = optional_param('id', 0, PARAM_INT);
$ids_raw   = optional_param('ids', '', PARAM_RAW_TRIMMED);

// Kumpulkan daftar ID final
$ids = [];
if ($id_single > 0) {
    $ids[] = $id_single;
}
if ($ids_raw !== '') {
    $ids = array_merge($ids, jm_parse_ids($ids_raw));
}
$ids = array_values(array_unique(array_filter($ids)));

if (empty($ids)) {
    // Pakai exception Moodle, bukan print_error()
    throw new moodle_exception(
        'generalexceptionmessage',
        'error',
        '',
        'Parameter "id" atau "ids" wajib diisi. Contoh: ?ids=1526-1528,1531'
    );
}

// Formatter tanggal Indonesia (zona WITA)
$fmt = new IntlDateFormatter('id_ID', IntlDateFormatter::FULL, IntlDateFormatter::NONE, 'Asia/Makassar', null, 'EEEE, dd MMMM yyyy');

// Ambil fieldid NIP sekali saja
$fieldid_nip = $DB->get_field('user_info_field', 'id', ['shortname' => 'nip']);
$nip_for = function (int $userid) use ($DB, $fieldid_nip): string {
    if (empty($fieldid_nip)) return '';
    return (string)($DB->get_field('user_info_data', 'data', [
        'userid' => $userid,
        'fieldid' => $fieldid_nip
    ]) ?? '');
};


// Siapkan PDF (TCPDF via moodlelib)
$pdf = new pdf();
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetAuthor('SIM SMAN 2 Kandangan');
$pdf->SetTitle('Surat Izin Siswa (Massal)');
$pdf->SetSubject('Cetak Massal Surat Izin');

// Lokasi stempel (opsional)
$stempel_path = $CFG->dirroot . '/local/jurnalmengajar/assets/stempel.png';

// Fungsi render satu surat (1 halaman)
$render_surat = function (pdf $pdf, stdClass $record) use ($DB, $fmt, $nip_for, $stempel_path, $sekolah, $tempat) {
    // Lookup entitas terkait (gunakan IGNORE_MISSING untuk aman)
    $siswa     = $DB->get_record('user',   ['id' => $record->userid],        'id, lastname', IGNORE_MISSING);
    $kelas     = $DB->get_record('cohort', ['id' => $record->kelasid],       'id, name',     IGNORE_MISSING);
    $penginput = $DB->get_record('user',   ['id' => $record->penginput],     'id, lastname', IGNORE_MISSING);
    $guru      = $DB->get_record('user',   ['id' => $record->guru_pengajar], 'id, lastname', IGNORE_MISSING);

    $nama_siswa = $siswa->lastname ?? '-';
    $nama_kelas = $kelas->name     ?? '-';
    $nama_peng  = $penginput->lastname ?? '-';
    $nama_guru  = $guru->lastname  ?? '-';

    $nip_guru = !empty($guru->id) ? $nip_for($guru->id) : '';
    $nip_peng = !empty($penginput->id) ? $nip_for($penginput->id) : '';

    $tanggal = $fmt->format($record->timecreated);

    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 10);

$sekolah_upper = mb_strtoupper($sekolah);
$html = <<<HTML
<h3 style="text-align: center;">
SURAT IZIN KELUAR/MASUK SISWA<br>
{$sekolah_upper}
</h3>

<br><br>

<table>
<tr><td width="120">Nama</td><td>: {$siswa->lastname}</td></tr>
<tr><td>Kelas</td><td>: {$kelas->name}</td></tr>
<tr><td>Alasan</td><td>: {$record->alasan}</td></tr>
<tr><td>Keperluan</td><td>: {$record->keperluan}</td></tr>
</table>

<br><br>

<table width="100%">
<tr>
    <td width="50%" style="text-align:left;">Guru Pengajar</td>
    <td width="50%" style="text-align:left;">{$tempat}, {$tanggal}</td>
</tr>
<tr>
    <td></td>
    <td style="text-align:left;">Pengawas Harian</td>
</tr>
<tr><td colspan="2"><br><br><br></td></tr>
<tr>
    <td><u>{$guru->lastname}</u></td>
    <td><u>{$penginput->lastname}</u></td>
</tr>
<tr>
    <td>NIP: {$nip_guru}</td>
    <td>NIP: {$nip_peng}</td>
</tr>
</table>
HTML;

    $pdf->writeHTML($html, true, false, true, false, '');

    // Stempel opsional (posisi dapat disesuaikan)
    if (is_readable($stempel_path)) {
        // x, y, width, height (mm), format
        $pdf->Image($stempel_path, 70, 42, 38, 38, 'PNG');
    }
};

// Loop semua ID: 1 halaman per ID
$printed = 0;
foreach ($ids as $sid) {
    $record = $DB->get_record('local_jurnalmengajar_suratizin', ['id' => $sid]);
    if (!$record) {
        // Lewati ID yang tidak ditemukan (silent skip)
        continue;
    }
    $render_surat($pdf, $record);
    $printed++;
}

// Jika semua ID tidak ada data
if ($printed < 1 || $pdf->getNumPages() < 1) {
    throw new moodle_exception(
        'generalexceptionmessage',
        'error',
        '',
        'Tidak ada data surat izin yang valid untuk dicetak.'
    );
}

// Keluarkan PDF ke browser
$pdf->Output('surat_izin_massal.pdf', 'I');
