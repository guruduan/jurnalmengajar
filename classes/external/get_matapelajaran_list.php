<?php
namespace local_jurnalmengajar\external;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

use external_api;
use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use external_value;

class get_matapelajaran_list extends external_api {
    public static function execute_parameters() {
        return new external_function_parameters([]);
    }

    public static function execute() {
        $daftar = [
            'Matematika',
            'Fisika',
            'Kimia',
            'Biologi',
            'Bahasa Indonesia',
            'Bahasa Inggris',
            'Sejarah',
            'Geografi'
        ];

        $result = [];
        foreach ($daftar as $mapel) {
            $result[] = ['value' => $mapel, 'label' => $mapel];
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
