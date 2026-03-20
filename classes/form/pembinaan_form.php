<?php
namespace local_jurnalmengajar\form;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

class pembinaan_form extends \moodleform {

    public function definition() {
        global $DB;

        $mform = $this->_form;

        // ==== Dropdown kelas ====
        $kelasrecords = $DB->get_records_menu('cohort', null, 'name ASC', 'id, name');
        $kelasoptions = ['' => '-Pilih kelas-'] + $kelasrecords;

        $mform->addElement('select', 'kelas', get_string('kelas','local_jurnalmengajar'), $kelasoptions);
        $mform->setType('kelas', PARAM_INT);
        $mform->addRule('kelas', null, 'required', null, 'client');

        // === Permasalahan ===
        $mform->addElement('textarea', 'permasalahan', 'Permasalahan',
            'wrap="virtual" rows="4" cols="50"');
        $mform->setType('permasalahan', PARAM_RAW);
        $mform->addRule('permasalahan', null, 'required');

        // === Upaya yang dilakukan ===
        $mform->addElement('textarea', 'tindakan', 'Upaya yang dilakukan', 
            'wrap="virtual" rows="4" cols="50"');
        $mform->setType('tindakan', PARAM_RAW);
        $mform->addRule('tindakan', null, 'required');

        // === Peserta (Hidden) ===
        $mform->addElement('hidden', 'peserta', '');
        $mform->setType('peserta', PARAM_RAW);

        // === Placeholder daftar siswa (AJAX) ===
        $mform->addElement('html', '<div id="siswa-area" style="margin:10px 0;"></div>');

        $this->add_action_buttons();
    }
}
