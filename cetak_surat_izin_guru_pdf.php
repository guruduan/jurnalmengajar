
<?php
require('../../config.php');
require_once($CFG->libdir . '/pdflib.php');

require_login();
$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

$id = required_param('id', PARAM_INT);

global $DB, $USER;
$record = $DB->get_record('local_jurnalmengajar_suratizinguru', ['id' => $id], '*', MUST_EXIST);
$user = $DB->get_record('user', ['id' => $record->userid], 'lastname, firstname, username');

$config = get_config('local_jurnalmengajar');

$nama_sekolah = $config->nama_sekolah ?? 'Nama Sekolah';
$tempat       = $config->tempat_ttd ?? 'Tempat';
$nama_kepsek  = $config->nama_kepsek ?? 'Nama Kepala Sekolah';
$nip_kepsek   = $config->nip_kepsek ?? 'NIP';

$fieldid = $DB->get_field('user_info_field', 'id', ['shortname' => 'nip']);

$niprecord = $fieldid ? $DB->get_record('user_info_data', [
    'userid' => $record->userid,
    'fieldid' => $fieldid
]) : null;

$nip = $niprecord ? $niprecord->data : '-';

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
$pdf->SetAuthor(mb_strtoupper($nama_sekolah, 'UTF-8'));
$pdf->SetTitle('Surat Izin Guru');
$pdf->SetMargins(20, 20, 20);
$pdf->SetAutoPageBreak(TRUE, 20);
$pdf->AddPage();

function tanggal_indo($tanggal) {
    $bulan = ['','Januari','Februari','Maret','April','Mei','Juni',
              'Juli','Agustus','September','Oktober','November','Desember'];

    $tgl = date_create($tanggal);
    return $tgl->format('d').' '.$bulan[(int)$tgl->format('m')].' '.$tgl->format('Y');
}

$tanggal_ttd = tanggal_indo(date('Y-m-d'));

$html = '
<h2 style="text-align:center;">
SURAT IZIN KELUAR<br>
TENAGA PENDIDIK DAN TENAGA KEPENDIDIKAN<br>
'.mb_strtoupper($nama_sekolah, 'UTF-8').'
</h2>

<br><br>

<table cellpadding="5" style="font-size:14px;">
<tr><td width="120"><b>Nama</b></td><td>: '.fullname($user).'</td></tr>
<tr><td><b>NIP</b></td><td>: '.htmlspecialchars($nip).'</td></tr>
<tr><td valign="top"><b>Alasan</b></td><td>: '.nl2br(htmlspecialchars($record->alasan)).'</td></tr>
<tr><td valign="top"><b>Keperluan</b></td><td>: '.nl2br(htmlspecialchars($record->keperluan)).'</td></tr>
</table>

<br><br><br>

<table width="100%" style="font-size:14px;">
<tr><td width="50%"></td>
<td width="50%" style="text-align:left;">
'.$tempat.', '.$tanggal_ttd.'<br>
Kepala Sekolah,<br><br><br><br>
<strong>'.$nama_kepsek.'</strong><br>
NIP. '.$nip_kepsek.'
</td></tr>
</table>

<br><br><br>
<div style="font-size:10px;">
Dicetak oleh: '.fullname($USER).' ('.$USER->username.')
</div>
';

$pdf->writeHTML($html, true, false, true, false, '');
$filename = 'surat_izin_guru_'.$id.'.pdf';
$pdf->Output($filename, 'I');
