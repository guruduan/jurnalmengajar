# cat /var/www/html/moodle/local/jurnalmengajar/cetak_surat_izin.php
<?php

require_once('../../config.php');
require_once($CFG->libdir.'/pdflib.php');
require_once($CFG->dirroot.'/local/jurnalmengajar/lib.php');

require_login();

$id = required_param('id', PARAM_INT);
global $DB;

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

// Ambil NIP (aman jika field belum ada)
$fieldid_nip = $DB->get_field('user_info_field', 'id', ['shortname' => 'nip']);

$nip_guru = '-';
$nip_penginput = '-';

if ($fieldid_nip) {
    $nip_guru = $DB->get_field('user_info_data', 'data', [
        'userid' => $guru->id,
        'fieldid' => $fieldid_nip
    ]) ?: '-';

    $nip_penginput = $DB->get_field('user_info_data', 'data', [
        'userid' => $penginput->id,
        'fieldid' => $fieldid_nip
    ]) ?: '-';
}

$tanggal = tanggal_indo($data->timecreated, 'judul');

// ==========================
// UNTUK FILE PDF
// ==========================
$tanggalfile = tanggal_indo($data->timecreated, 'tanggal');
$alasan = htmlspecialchars($data->alasan);
$keperluan = htmlspecialchars($data->keperluan);
$namasiswa = htmlspecialchars($siswa->lastname);
$namaguru  = htmlspecialchars($guru->lastname);
$namapenginput = htmlspecialchars($penginput->lastname);
$namakelas = htmlspecialchars($kelas->name);

// ==========================
// SIAPKAN PDF
// ==========================
$pdf = new pdf();
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetAuthor($sekolah);
$pdf->SetTitle('Surat Izin Murid - ' . $sekolah . ' - ' . $tanggalfile);
$pdf->SetSubject('Surat Izin Murid');

$pdf->AddPage();
$pdf->SetFont('helvetica', '', 10);

// Isi surat
$sekolah_upper = mb_strtoupper($sekolah);
$html = <<<HTML
<h3 style="text-align: center;">
SURAT IZIN KELUAR/MASUK MURID<br>
{$sekolah_upper}
</h3>

<br><br>

<table>
<tr><td width="120">Nama</td><td>: {$namasiswa}</td></tr>
<tr><td>Kelas</td><td>: {$namakelas}</td></tr>
<tr><td>Alasan</td><td>: {$alasan}</td></tr>
<tr><td>Keperluan</td><td>: {$keperluan}</td></tr>
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
    <td><u>{$namaguru}</u></td>
    <td><u>{$namapenginput}</u></td>
</tr>
<tr>
    <td>NIP {$nip_guru}</td>
    <td>NIP {$nip_penginput}</td>
</tr>
</table>
HTML;

// Garis potong
$separator = <<<HTML
<br>
<hr style="border-top: 1px dashed #000;">
<br><br>
HTML;

// Tulis HTML
$htmloutput = $html . $separator;
$pdf->writeHTML($htmloutput);

// Ambil stempel dari settings
$stempel_path = jurnalmengajar_get_stempel_path();

// Tambahkan stempel setelah HTML
if (!empty($stempel_path) && file_exists($stempel_path)) {
    // Posisi stempel (atur sesuai kebutuhan)
    $pdf->Image($stempel_path, 70, 42, 38, 38, 'PNG');
}

// Output PDF
if (ob_get_length()) {
    ob_clean();
}
$namafile = 'surat_izin_' . str_replace(' ', '_', $tanggalfile) . '.pdf';
$pdf->Output($namafile, 'I');
