<?php
namespace local_jurnalmengajar\external;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

use external_api;
use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use external_value;

class get_kelas_list extends external_api {
    public static function execute_parameters() {
        return new external_function_parameters([]);
    }

    public static function execute() {
        global $DB;
        $records = $DB->get_records('cohort', null, 'name ASC', 'id, name');
        $result = [];
        foreach ($records as $r) {
            $result[] = ['value' => $r->name, 'label' => $r->name];
        }
        return $result;
    }

    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'value' => new external_value(PARAM_TEXT, 'Value'),
                'label' => new external_value(PARAM_TEXT, 'Label')
            ])
        );
    }
}
