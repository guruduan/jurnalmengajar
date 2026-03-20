<?php

require_once('../../config.php');
require_once($CFG->libdir.'/pdflib.php');

$id = required_param('id', PARAM_INT);
global $DB;
setlocale(LC_TIME, 'id_ID.UTF-8');

// Ambil data surat
$data = $DB->get_record('local_jurnalmengajar_suratizin', ['id' => $id], '*', MUST_EXIST);
$siswa = $DB->get_record('user', ['id' => $data->userid], 'id, lastname');
$kelas = $DB->get_record('cohort', ['id' => $data->kelasid], 'id, name');
$penginput = $DB->get_record('user', ['id' => $data->penginput], 'id, lastname');
$guru = $DB->get_record('user', ['id' => $data->guru_pengajar], 'id, lastname');

// ==========================
// SETTINGS SEKOLAH
// ==========================
$sekolah = get_config('local_jurnalmengajar', 'nama_sekolah');
$tempat  = get_config('local_jurnalmengajar', 'tempat_ttd');

// Ambil NIP dari field profil khusus (misalnya: profile_field_nip)
$nip_guru = $DB->get_field('user_info_data', 'data', [
    'userid' => $guru->id,
    'fieldid' => $DB->get_field('user_info_field', 'id', ['shortname' => 'nip'])
]);
$nip_penginput = $DB->get_field('user_info_data', 'data', [
    'userid' => $penginput->id,
    'fieldid' => $DB->get_field('user_info_field', 'id', ['shortname' => 'nip'])
]);

// Format tanggal dalam Bahasa Indonesia
//$tanggal = strftime('%A, %d %B %Y', $data->timecreated); // Pastikan locale ID diaktifkan
$fmt = new IntlDateFormatter('id_ID', IntlDateFormatter::FULL, IntlDateFormatter::NONE, 'Asia/Makassar', null, 'EEEE, dd MMMM yyyy');
$tanggal = $fmt->format($data->timecreated);


// Siapkan PDF
$pdf = new pdf();
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 10);

// Isi surat
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
<tr><td>Alasan</td><td>: {$data->alasan}</td></tr>
<tr><td>Keperluan</td><td>: {$data->keperluan}</td></tr>
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
    <td>NIP: {$nip_penginput}</td>
</tr>
</table>
HTML;

// Cetak PDF
//$pdf->writeHTML($html);
// Tambahkan simbol gunting dan garis putus-putus
$separator = <<<HTML
<br>
<hr style="border-top: 1px dashed #000;">
<br><br>
HTML;

$stempel_path = $CFG->dirroot . '/local/jurnalmengajar/assets/stempel.png';
//kena nama pengaawas $pdf->Image($stempel_path, 90, 50, 30, 30, 'PNG'); // stempel pertama
//$pdf->Image($stempel_path, 90, 120, 30, 30, 'PNG'); // stempel kedua (jika dua surat per halaman)

$pdf->Image($stempel_path, 70, 42, 38, 38, 'PNG'); // stempel pertama
//$pdf->Image($stempel_path, 70, 120, 30, 30, 'PNG'); // stempel kedua (jika dua surat per halaman)

// Gabungkan dua surat izin dalam satu halaman
//$htmloutput = $html . $separator . $html;
//$pdf->writeHTML($htmloutput);
//$pdf->Output('surat_izin.pdf', 'I');

// Cetak hanya satu surat saja
$htmloutput = $html . $separator;
$pdf->writeHTML($htmloutput);
$pdf->Output('surat_izin.pdf', 'I');
