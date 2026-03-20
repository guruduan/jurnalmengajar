<?php
namespace local_jurnalmengajar\form;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

class layananbk_form extends \moodleform {

    public function definition() {
        global $DB;

        $mform = $this->_form;

        // === Pilih Kelas (cohort) ===
        $kelasoptions = [];
        $cohorts = $DB->get_records('cohort', null, 'name ASC');
        foreach ($cohorts as $c) {
            $kelasoptions[$c->id] = format_string($c->name);
        }

        $mform->addElement('select', 'kelas', 'Kelas', $kelasoptions);
        $mform->setType('kelas', PARAM_INT);
        $mform->addRule('kelas', null, 'required');

        // === Jenis Layanan ===
        $options = [
            'Individu' => 'Individu',
            'Kelompok' => 'Kelompok',
            'Klasikal' => 'Klasikal'
        ];

        $mform->addElement('select', 'jenislayanan', 'Jenis Layanan', $options);
        $mform->setType('jenislayanan', PARAM_TEXT);
        $mform->addRule('jenislayanan', null, 'required');

        // === Topik ===
        $mform->addElement('text', 'topik', 'Topik');
        $mform->setType('topik', PARAM_TEXT);
        $mform->addRule('topik', null, 'required');

        // === Peserta (Hidden JSON) ===
        $mform->addElement('hidden', 'peserta', '');
        $mform->setType('peserta', PARAM_RAW);

        // === Area daftar siswa (AJAX) ===
        $mform->addElement('html', '<div id="siswa-area" style="margin:10px 0;"></div>');

        // === Tindak lanjut ===
        $mform->addElement('textarea', 'tindaklanjut', 'Tindak Lanjut', [
            'rows' => 5,
            'cols' => 80
        ]);
        $mform->setType('tindaklanjut', PARAM_RAW);

        // === Catatan ===
        $mform->addElement('textarea', 'catatan', 'Catatan', [
            'rows' => 5,
            'cols' => 80
        ]);
        $mform->setType('catatan', PARAM_RAW);

        // Tombol
        $this->add_action_buttons(true, 'Simpan');
    }
}
