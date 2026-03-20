<?php
require_once('../../config.php');
require_once($CFG->libdir.'/pdflib.php');
require_once($CFG->dirroot.'/local/jurnalmengajar/libwa.php');

global $DB, $USER;

$id = required_param('id', PARAM_INT);
$printedbyid = optional_param('printedbyid', 0, PARAM_INT);

// Ambil data surat izin guru
$surat = $DB->get_record('local_jurnalmengajar_suratizinguru', ['id' => $id], '*', MUST_EXIST);
$guru = $DB->get_record('user', ['id' => $surat->userid], '*', MUST_EXIST);

// Ambil NIP guru dari custom profile field 'nip'
$fieldid = $DB->get_field('user_info_field', 'id', ['shortname' => 'nip']);
$nip = '';
if ($fieldid) {
    $nip = $DB->get_field('user_info_data', 'data', ['userid' => $guru->id, 'fieldid' => $fieldid]);
}

// Ambil user yang mencetak (role TU), fallback ke user login
if ($printedbyid) {
    $userpencetak = $DB->get_record('user', ['id' => $printedbyid]);
    if (!$userpencetak) {
        $userpencetak = $USER;
    }
} else {
    $userpencetak = $USER;
}

// Setting locale dan timezone
setlocale(LC_TIME, 'id_ID.UTF-8');
date_default_timezone_set('Asia/Makassar');
$fmt = new IntlDateFormatter('id_ID', IntlDateFormatter::FULL, IntlDateFormatter::NONE, 'Asia/Makassar', IntlDateFormatter::GREGORIAN);
$tanggal = $fmt->format($surat->waktuinput);
$jam = date('H:i');
// ⬇️ Kirim WhatsApp real-time ke Kepala Sekolah
kirim_wa_izin_guru($guru, $nip, $surat->alasan, $surat->keperluan, $tanggal, $jam, $userpencetak->lastname);

// Fungsi untuk membentuk HTML surat izin guru
function suratizin_guru_html($guru, $nip, $surat, $tanggal, $userpencetak) {
    $html = <<<HTML
    <div style="page-break-inside: avoid; margin-bottom: 10px; font-size:10px; line-height:1.1;">
    <h3 style="text-align:center; line-height:1.1; font-size:12px; margin-bottom: 5px;">
        SURAT IZIN KELUAR<br>
        TENAGA PENDIDIK DAN TENAGA KEPENDIDIKAN<br>
        SMA NEGERI 2 KANDANGAN
    </h3>
    <table cellpadding="2" style="font-size:10px; width:100%;">
        <tr><td width="90">Nama</td><td>: {$guru->lastname}</td></tr>
        <tr><td>NIP</td><td>: {$nip}</td></tr>
        <tr><td>Alasan</td><td>: {$surat->alasan}</td></tr>
        <tr><td>Keperluan</td><td>: {$surat->keperluan}</td></tr>
    </table>
    <table width="100%" style="font-size:10px;">
        <tr>
            <td width="50%"></td>
            <td width="50%" style="text-align:left; padding-left:5px;">
                Kandangan, {$tanggal}<br>
                Kepala SMAN 2 Kandangan,<br><br><br><br>
                Jainuddin, S.Ag, M.Pd.I<br>
                NIP. 19771005 200904 1 002
            </td>
        </tr>
    </table>
    </div>
HTML;
    return $html;
}

// Siapkan PDF
$pdf = new pdf();
$pdf->AddPage('P', 'F4');
$pdf->SetFont('helvetica', '', 10);

$stempel = __DIR__ . '/assets/ttd.png';

// Cetak 4 surat per halaman
$htmlsurat = suratizin_guru_html($guru, $nip, $surat, $tanggal, $userpencetak);
$separator = '<hr style="border-top: 1px dashed #000; margin:8px 0;">';

$pdf->writeHTML($htmlsurat, true, false, true, false, '');
if (file_exists($stempel)) {
    // Geser ke posisi ttd kepala sekolah (sesuaikan jika perlu)
    $pdf->Image($stempel, 110, $pdf->GetY() - 36, 20);
}
$pdf->writeHTML($separator, true, false, true, false, '');
$pdf->writeHTML($htmlsurat, true, false, true, false, '');
if (file_exists($stempel)) {
    // Geser ke posisi ttd kepala sekolah (sesuaikan jika perlu)
    $pdf->Image($stempel, 110, $pdf->GetY() - 36, 20);
}
$pdf->writeHTML($separator, true, false, true, false, '');

// Output PDF
$pdf->Output('surat_izin_guru.pdf', 'I');
