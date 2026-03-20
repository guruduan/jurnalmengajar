<?php
namespace local_jurnalmengajar\external;

use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use external_value;
use external_api;
use context_system;

class get_jurnal_entries extends external_api {
    public static function execute_parameters() {
        return new external_function_parameters([]);
    }

    public static function execute() {
        global $USER, $DB;

        $context = context_system::instance();
        self::validate_context($context);

        $entries = $DB->get_records('local_jurnalmengajar', ['userid' => $USER->id], 'timecreated DESC');

        $result = [];
        foreach ($entries as $entry) {
            $result[] = [
                'id'            => $entry->id,
                'tanggal'       => date('Y-m-d', $entry->timecreated),
                'kelas'         => $entry->kelas,
                'matapelajaran' => $entry->matapelajaran,
                'jamke'         => $entry->jamke,
                'kegiatan'      => $entry->kegiatan,
            ];
        }

        return $result;
    }

    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id'            => new external_value(PARAM_INT, 'ID entri'),
                'tanggal'       => new external_value(PARAM_TEXT, 'Tanggal'),
                'kelas'         => new external_value(PARAM_TEXT, 'Kelas'),
                'matapelajaran' => new external_value(PARAM_TEXT, 'Mata Pelajaran'),
                'jamke'         => new external_value(PARAM_TEXT, 'Jam Ke'),
                'kegiatan'      => new external_value(PARAM_TEXT, 'Kegiatan'),
            ])
        );
    }
}
