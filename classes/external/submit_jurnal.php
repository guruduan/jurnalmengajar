<?php
namespace local_jurnalmengajar\external;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

use external_function_parameters;
use external_value;
use external_single_structure;
use external_api;

class submit_jurnal extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'tanggal'   => new external_value(PARAM_TEXT, 'Tanggal (YYYY-MM-DD)'),
            'jamke'     => new external_value(PARAM_TEXT, 'Jam ke (misal: 1,2)'),
            'kelas'     => new external_value(PARAM_TEXT, 'Nama kelas'),
            'matpel'    => new external_value(PARAM_TEXT, 'Nama mata pelajaran'),
            'kegiatan'  => new external_value(PARAM_TEXT, 'Kegiatan pembelajaran'),
            'absen'     => new external_value(PARAM_RAW, 'Data absen siswa (opsional)', VALUE_OPTIONAL)
        ]);
    }

    public static function execute($tanggal, $jamke, $kelas, $matpel, $kegiatan, $absen = '') {
        global $USER, $DB;

        self::validate_parameters(self::execute_parameters(), [
            'tanggal' => $tanggal,
            'jamke' => $jamke,
            'kelas' => $kelas,
            'matpel' => $matpel,
            'kegiatan' => $kegiatan,
            'absen' => $absen
        ]);

        $record = new \stdClass();
        $record->userid = $USER->id;
        $record->tanggal = $tanggal;
        $record->jamke = $jamke;
        $record->kelas = $kelas;
        $record->matpel = $matpel;
        $record->kegiatan = $kegiatan;
        $record->absen = $absen;
        $record->timecreated = time();

        $DB->insert_record('local_jurnalmengajar', $record);

        return ['status' => 'success', 'message' => 'Jurnal berhasil disimpan'];
    }

    public static function execute_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT),
            'message' => new external_value(PARAM_TEXT)
        ]);
    }
}
