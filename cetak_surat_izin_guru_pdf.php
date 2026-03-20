<?php
require('../../config.php');
require_once($CFG->libdir . '/pdflib.php');

require_login();
$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

$id = required_param('id', PARAM_INT);

$record = $DB->get_record('local_jurnalmengajar_suratizinguru', ['id' => $id], '*', MUST_EXIST);
$user = $DB->get_record('user', ['id' => $record->userid], 'lastname, firstname, username');

$niprecord = $DB->get_record('user_info_data', [
    'userid' => $record->userid,
    'fieldid' => $DB->get_field('user_info_field', 'id', ['shortname' => 'nip'])
]);

$nip = $niprecord ? $niprecord->data : '';

class PDF_SuratIzin extends \core\tcpdf\tcpdf {
    public function Header() {
        // kosongkan header default
    }
    public function Footer() {
        // kosongkan footer default
    }
}

$pdf = new PDF_SuratIzin(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('SMAN 2 Kandangan');
$pdf->SetTitle('Surat Izin Guru');
$pdf->SetMargins(20, 20, 20);
$pdf->SetAutoPageBreak(TRUE, 20);
$pdf->AddPage();

$bulan = ['','Januari','Februari','Maret','April','Mei','Juni',
          'Juli','Agustus','September','Oktober','November','Desember'];

function tanggal_indo($tanggal) {
    global $bulan;
    $tgl = date_create($tanggal);
    return $tgl->format('d').' '.$bulan[(int)$tgl->format('m')].' '.$tgl->format('Y');
}

$tanggal_ttd = tanggal_indo(date('Y-m-d'));

$html = '
<h2 style="text-align:center;">
SURAT IZIN KELUAR<br>
TENAGA PENDIDIK DAN TENAGA KEPENDIDIKAN<br>
SMA NEGERI 2 KANDANGAN
</h2>

<table cellpadding="5" style="font-size:14px;">
<tr><td width="100"><b>Nama</b></td><td>: '.fullname($user).'</td></tr>
<tr><td><b>NIP</b></td><td>: '.htmlspecialchars($nip).'</td></tr>
<tr><td valign="top"><b>Alasan</b></td><td>: '.nl2br(htmlspecialchars($record->alasan)).'</td></tr>
<tr><td valign="top"><b>Keperluan</b></td><td>: '.nl2br(htmlspecialchars($record->keperluan)).'</td></tr>
</table>

<br><br><br>

<table width="100%" style="font-size:14px;">
<tr><td width="50%"></td>
<td width="50%" style="text-align:left;">
Kandangan, '.$tanggal_ttd.'<br>
Kepala SMAN 2 Kandangan,<br><br><br><br>
<strong>Jainuddin, S.Ag, M.Pd.I</strong><br>
NIP. 19771005 200904 1 002
</td></tr>
</table>

<br><br><br>
<div style="font-size:10px;">
Dicetak oleh: '.fullname($USER).' ('.$USER->username.')
</div>
';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('surat_izin_guru_'.$id.'.pdf', 'I'); // 'I' untuk inline di browser
