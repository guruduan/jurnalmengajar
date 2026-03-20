<?php
namespace local_jurnalmengajar\external;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

use external_function_parameters;
use external_value;
use external_single_structure;
use external_api;
use core_user\fields;

class get_students extends external_api {
    public static function execute_parameters() {
        return new external_function_parameters([
            'kelas' => new external_value(PARAM_TEXT, 'Nama kelas (cohort)')
        ]);
    }

    public static function execute($kelas) {
        global $DB, $OUTPUT;

        self::validate_parameters(self::execute_parameters(), ['kelas' => $kelas]);

        $cohort = $DB->get_record('cohort', ['name' => $kelas], '*', IGNORE_MISSING);
        if (!$cohort) {
            return '<ion-item><ion-label>Cohort tidak ditemukan</ion-label></ion-item>';
        }

        $context = \context_system::instance();
        $userfieldsapi = fields::for_userpic();
        $fields = $userfieldsapi->get_sql('u', false, '', '', false)->selects;

        $sql = "SELECT $fields
                  FROM {cohort_members} cm
                  JOIN {user} u ON u.id = cm.userid
                 WHERE cm.cohortid = :cohortid
              ORDER BY u.lastname ASC";
        $params = ['cohortid' => $cohort->id];
        $students = $DB->get_records_sql($sql, $params);

        $html = '';
        foreach ($students as $s) {
            $name = fullname($s);
            $html .= '
            <ion-item class="absen-item">
                <ion-label>
                    <ion-checkbox class="absen-checkbox" data-nama="'.htmlspecialchars($name).'" slot="start"></ion-checkbox>
                    ' . htmlspecialchars($name) . '
                </ion-label>
                <ion-select class="absen-alasan" interface="popover" placeholder="Alasan" disabled>
                    <ion-select-option value="Sakit">Sakit</ion-select-option>
                    <ion-select-option value="Izin">Izin</ion-select-option>
                    <ion-select-option value="Alpa">Alpa</ion-select-option>
                    <ion-select-option value="Dispensasi">Dispensasi</ion-select-option>
                </ion-select>
            </ion-item>';
        }

        return $html ?: '<ion-item><ion-label>Tidak ada siswa</ion-label></ion-item>';
    }

    public static function execute_returns() {
        return new external_value(PARAM_RAW, 'HTML tampilan daftar siswa');
    }
}
