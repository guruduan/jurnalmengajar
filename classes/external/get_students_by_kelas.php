<?php
namespace local_jurnalmengajar\external;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

use external_function_parameters;
use external_value;
use external_multiple_structure;
use external_single_structure;
use external_api;
use context_system;

class get_students_by_kelas extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'kelas' => new external_value(PARAM_TEXT, 'Nama kelas / cohort')
        ]);
    }

    public static function execute($kelas) {
        global $DB;

        self::validate_parameters(self::execute_parameters(), ['kelas' => $kelas]);

        $context = context_system::instance();
        self::validate_context($context);

        // Ambil cohort ID
        $cohort = $DB->get_record('cohort', ['idnumber' => $kelas], '*', IGNORE_MISSING);
        if (!$cohort) {
            throw new \moodle_exception('Cohort not found');
        }

        // Ambil user dari cohort
        $sql = "SELECT u.id, u.firstname, u.lastname
                FROM {cohort_members} cm
                JOIN {user} u ON u.id = cm.userid
                WHERE cm.cohortid = :cohortid
                ORDER BY u.lastname ASC";
        $students = $DB->get_records_sql($sql, ['cohortid' => $cohort->id]);

        $results = [];
        foreach ($students as $s) {
            $results[] = [
                'id' => $s->id,
                'fullname' => fullname($s)
            ];
        }

        return $results;
    }

    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT),
                'fullname' => new external_value(PARAM_TEXT),
            ])
        );
    }
}
