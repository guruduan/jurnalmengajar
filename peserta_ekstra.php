<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/jurnalmengajar/peserta_ekstra.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Peserta Ekstrakurikuler');
$PAGE->set_heading('Peserta Ekstrakurikuler');

global $DB;

// =======================
// SIMPAN PESERTA
// =======================
if (isset($_POST['ekstraid']) && isset($_POST['cohortid'])) {

    $ekstraid = $_POST['ekstraid'];
    $cohortid = $_POST['cohortid'];

    // Hapus peserta lama hanya untuk ekstra + cohort ini
    $DB->delete_records('local_jm_ekstra_peserta', [
        'ekstraid' => $ekstraid,
        'cohortid' => $cohortid
    ]);

    if (!empty($_POST['peserta'])) {
        foreach ($_POST['peserta'] as $userid) {
            $data = new stdClass();
            $data->ekstraid = $ekstraid;
            $data->userid = $userid;
            $data->cohortid = $cohortid;
            $DB->insert_record('local_jm_ekstra_peserta', $data);
        }
    }

    redirect(
        new moodle_url('/local/jurnalmengajar/peserta_ekstra.php?ekstraid='.$ekstraid.'&cohortid='.$cohortid),
        'Peserta ekstrakurikuler berhasil disimpan',
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();

// =======================
// AMBIL DATA
// =======================
$ekstra_list = $DB->get_records('local_jm_ekstra');
$cohort_list = $DB->get_records('cohort');

$selected_ekstra = $_GET['ekstraid'] ?? 0;
$selected_cohort = $_GET['cohortid'] ?? 0;

// Ambil siswa dari cohort
$siswa_list = [];
if ($selected_cohort) {
    $sql = "SELECT u.id, u.firstname, u.lastname
            FROM {cohort_members} cm
            JOIN {user} u ON u.id = cm.userid
            WHERE cm.cohortid = ?
            ORDER BY u.lastname";
    $siswa_list = $DB->get_records_sql($sql, [$selected_cohort]);
}

// Ambil peserta yang sudah dipilih
$peserta_terpilih = [];
if ($selected_ekstra && $selected_cohort) {
    $records = $DB->get_records('local_jm_ekstra_peserta', [
        'ekstraid' => $selected_ekstra,
        'cohortid' => $selected_cohort
    ]);
    foreach ($records as $r) {
        $peserta_terpilih[] = $r->userid;
    }
}

// =======================
// FORM
// =======================
echo '<form method="post">';

echo '<h3>Pilih Ekstrakurikuler</h3>';
echo '<select name="ekstraid" onchange="location.href=\'?ekstraid=\'+this.value">';
echo '<option value="">-- Pilih Ekstra --</option>';
foreach ($ekstra_list as $e) {
    $sel = ($selected_ekstra == $e->id) ? 'selected' : '';
    echo '<option value="'.$e->id.'" '.$sel.'>'.$e->namaekstra.'</option>';
}
echo '</select>';

echo '<h3>Pilih Kelas / Cohort</h3>';
echo '<select name="cohortid" onchange="location.href=\'?ekstraid='.$selected_ekstra.'&cohortid=\'+this.value">';
echo '<option value="">-- Pilih Cohort --</option>';
foreach ($cohort_list as $c) {
    $sel = ($selected_cohort == $c->id) ? 'selected' : '';
    echo '<option value="'.$c->id.'" '.$sel.'>'.$c->name.'</option>';
}
echo '</select>';

if ($selected_cohort && $selected_ekstra) {

    echo '<h3>Daftar Siswa</h3>';

    echo '<table>';
    $i = 0;

    foreach ($siswa_list as $s) {

        if ($i % 3 == 0) echo '<tr>';

        $checked = in_array($s->id, $peserta_terpilih) ? 'checked' : '';

        echo '<td style="padding:5px 25px;">';
        echo '<input type="checkbox" name="peserta[]" value="'.$s->id.'" '.$checked.'> ';
        echo $s->lastname;
        echo '</td>';

        if ($i % 3 == 2) echo '</tr>';

        $i++;
    }

    echo '</table>';

    echo '<br><button type="submit">Simpan Peserta</button>';
}

echo '</form>';

echo $OUTPUT->footer();
