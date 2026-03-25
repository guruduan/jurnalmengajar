
<?php
defined('MOODLE_INTERNAL') || die();

function jurnalmengajar_get_jadwal_acuan() {
    global $DB;

    $sql = "SELECT j.id, j.userid, j.hari, j.kelas, j.jamke,
                   u.lastname
            FROM {local_jurnalmengajar_jadwal} j
            JOIN {user} u ON u.id = j.userid
            ORDER BY u.lastname, j.hari, j.jamke";

    $records = $DB->get_records_sql($sql);

    $jadwal = [];

    foreach ($records as $r) {
        $jadwal[] = [
            'userid'   => $r->userid,
            'hari'     => $r->hari,
            'kelas'    => $r->kelas,
            'jamke'    => $r->jamke,
            'lastname' => $r->lastname
        ];
    }

    return $jadwal;
}

