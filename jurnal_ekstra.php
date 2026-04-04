<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/local/jurnalmengajar/jurnal_ekstra.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Jurnal Ekstrakurikuler');
$PAGE->set_heading('Jurnal Ekstrakurikuler');

global $DB, $USER;

// =======================
// AMBIL EKSTRA YANG DIBINA
// =======================
$sql = "SELECT e.id, e.namaekstra
        FROM {local_jm_ekstra} e
        JOIN {local_jm_ekstra_pembina} p ON p.ekstraid = e.id
        WHERE p.userid = ?";
$ekstra_saya = $DB->get_records_sql($sql, [$USER->id]);

$selected_ekstra = $_GET['ekstraid'] ?? 0;

echo $OUTPUT->header();

echo '<h3>Jurnal Ekstrakurikuler</h3>';
echo '<h4>Ekstrakurikuler yang Anda bina:</h4>';

if (!$ekstra_saya) {
    echo '<div style="color:red">Anda belum ditetapkan sebagai pembina ekstrakurikuler.</div>';
}

// Jika pembina lebih dari 1 ekstra
if (count($ekstra_saya) > 1) {
    echo '<form method="get">';
    echo '<label>Pilih Ekstrakurikuler:</label><br>';

    foreach ($ekstra_saya as $e) {
        $checked = ($selected_ekstra == $e->id) ? 'checked' : '';
        echo '<input type="radio" name="ekstraid" value="'.$e->id.'" '.$checked.'> ';
        echo $e->namaekstra.'<br>';
    }

    echo '<br><button type="submit">Pilih</button>';
    echo '</form>';
}

// Jika hanya 1 ekstra → otomatis pilih
if (count($ekstra_saya) == 1) {
    foreach ($ekstra_saya as $e) {
        $selected_ekstra = $e->id;
    }
}

// Tampilkan nama ekstra yang dipilih
if ($selected_ekstra) {
    $namaekstra = $DB->get_field('local_jm_ekstra', 'namaekstra', ['id' => $selected_ekstra]);
    echo '<br><b>Ekstra: '.$namaekstra.'</b><br>';
}

// =======================
// FORM JURNAL + ABSENSI
// =======================
if ($selected_ekstra) {

    // Ambil peserta ekstra + kelas
    $sqlsiswa = "SELECT DISTINCT u.id, u.firstname, u.lastname, c.name AS kelas
                 FROM {local_jm_ekstra_peserta} p
                 JOIN {user} u ON u.id = p.userid
                 LEFT JOIN {cohort} c ON c.id = p.cohortid
                 WHERE p.ekstraid = ?
                 ORDER BY c.name, u.lastname";
    $siswa = $DB->get_records_sql($sqlsiswa, [$selected_ekstra]);

    echo '<hr>';
    echo '<h3>Form Jurnal</h3>';

    echo '<form method="post" action="simpan_jurnal_ekstra.php">';
echo '<input type="hidden" name="ekstraid" value="'.$selected_ekstra.'">';

echo 'Tanggal:<br>';
echo '<input type="date" name="tanggal" value="'.date('Y-m-d').'"><br><br>';

echo 'Materi :<br>';
echo '<textarea name="materi" rows="4" cols="70" required></textarea><br><br>';

echo 'Kegiatan:<br>';
echo '<textarea name="kegiatan" rows="4" cols="70"></textarea><br><br>';

echo 'Catatan:<br>';
echo '<textarea name="catatan" rows="3" cols="70"></textarea><br><br>';

    echo '<b>Absensi Murid ('.count($siswa).' murid)</b><br>';

    echo '<table border="1" cellpadding="5">';
    echo '<tr>
            <th>No</th>
            <th>Nama Murid</th>
            <th>Kelas</th>
            <th>Status</th>
          </tr>';

    $no = 1;

    foreach ($siswa as $s) {
        echo '<tr>';
        echo '<td>'.$no++.'</td>';
        echo '<td>'.$s->lastname.'</td>';
        echo '<td>'.$s->kelas.'</td>';
        echo '<td>
                <select name="status['.$s->id.']">
                    <option value="Hadir">Hadir</option>
                    <option value="Sakit">Sakit</option>
                    <option value="Izin">Izin</option>
                    <option value="Alpa">Alpa</option>
                    <option value="Dispensasi">Dispensasi</option>
                </select>
              </td>';
        echo '</tr>';
    }

    echo '</table><br>';
    echo '<button type="submit">Simpan Jurnal</button>';
    echo '</form>';
}

echo $OUTPUT->footer();
