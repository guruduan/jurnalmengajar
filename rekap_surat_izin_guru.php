<?php
require_once('../../config.php');
require_once($CFG->libdir.'/pdflib.php');

require_login();
$context = context_system::instance();
require_capability('local/jurnalmengajar:viewallsuratizin', $context);

$bulan = required_param('bulan', PARAM_INT); // 1-12
$tahun = date('Y'); // ✅ Ambil tahun saat ini

if ($bulan < 1 || $bulan > 12) {
    throw new moodle_exception('Bulan tidak valid.');
}

$starttime = strtotime("{$tahun}-{$bulan}-01 00:00:00"); // ✅ Awal bulan
$endtime = strtotime('+1 month', $starttime); // ✅ Awal bulan berikutnya

// 🔧 SQL perbaikan: ganti LIMIT 1 dengan subquery agar aman dan efisien
$sql = "SELECT s.*, u.firstname, u.lastname, d.data as nip
        FROM {local_jurnalmengajar_suratizinguru} s
        JOIN {user} u ON u.id = s.userid
        LEFT JOIN {user_info_data} d ON d.userid = u.id
        WHERE d.fieldid = (SELECT id FROM {user_info_field} WHERE shortname = 'nip')
          AND s.waktuinput >= :start AND s.waktuinput < :end
        ORDER BY s.waktuinput DESC";

$params = ['start' => $starttime, 'end' => $endtime];
$records = $DB->get_records_sql($sql, $params);

// ✅ Set bahasa lokal Indonesia dan zona waktu WITA
setlocale(LC_TIME, 'id_ID.UTF-8');
date_default_timezone_set('Asia/Makassar');

// ✅ Formatter tanggal
$fmt = new IntlDateFormatter(
    'id_ID',
    IntlDateFormatter::FULL,
    IntlDateFormatter::NONE,
    'Asia/Makassar',
    IntlDateFormatter::GREGORIAN
);

// 🔧 Format nama bulan lebih aman
$fmt_bulan = new IntlDateFormatter('id_ID', IntlDateFormatter::LONG, IntlDateFormatter::NONE, 'Asia/Makassar');
$namabulan = $fmt_bulan->format($starttime);

// ✅ Mulai PDF
$pdf = new pdf();
$pdf->AddPage('P', 'F4');
$pdf->SetFont('helvetica', '', 10);

// ✅ Konten HTML untuk tabel
$html = "<h3 style='text-align:center; margin-top:10px; margin-bottom:10px;'>Rekap Surat Izin Guru/Pegawai<br>Bulan {$namabulan}</h3>";

$html .= "<table cellpadding='4' cellspacing='0' style='font-size:9px; width:100%; border-collapse: collapse;' border='1'>";
$html .= "<thead>
<tr style='font-weight:bold; text-align:center;'>
    <th style='width:4%; border:1px solid black;'>No</th>
    <th style='width:20%; border:1px solid black;'>Hari, Tanggal</th>
    <th style='width:20%; border:1px solid black;'>Nama</th>
    <th style='width:16%; border:1px solid black;'>NIP</th>
    <th style='width:20%; border:1px solid black;'>Alasan</th>
    <th style='width:20%; border:1px solid black;'>Keperluan</th>
</tr>
</thead><tbody>";

$no = 1;
foreach ($records as $r) {
    $tanggal = $fmt->format($r->waktuinput);
    // 🔧 Tampilkan nama lengkap atau hanya lastname sesuai kebutuhan
    $namaguru = $r->lastname;

    $html .= "<tr>
        <td style='border:1px solid black; text-align:center;'>{$no}</td>
        <td style='border:1px solid black;'>{$tanggal}</td>
        <td style='border:1px solid black;'>{$namaguru}</td>
        <td style='border:1px solid black;'>{$r->nip}</td>
        <td style='border:1px solid black;'>{$r->alasan}</td>
        <td style='border:1px solid black;'>{$r->keperluan}</td>
    </tr>";
    $no++;
}

// 🔧 Tambahkan "Tidak ada data" jika kosong
if ($no === 1) {
    $html .= "<tr><td colspan='6' style='text-align:center; border:1px solid black;'>Tidak ada data</td></tr>";
}

$html .= "</tbody></table>";

// ✅ Tulis ke PDF
$pdf->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);

// ✅ Output PDF ke browser
$pdf->Output("rekap_surat_izin_guru_{$bulan}_{$tahun}.pdf", 'I');
