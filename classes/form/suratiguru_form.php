<?php
namespace local_jurnalmengajar\form;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

class suratiguru_form extends \moodleform {
    public function definition() {
        $mform = $this->_form;

        // Ambil data dari customdata
        $choices = $this->_customdata['choices'] ?? [];
        $nipdata = $this->_customdata['nipdata'] ?? [];

        // Dropdown nama guru/pegawai
        $mform->addElement('select', 'userid', 'Nama Guru/Pegawai', $choices);
        $mform->setType('userid', PARAM_INT);
        $mform->addRule('userid', 'Harus dipilih', 'required', null, 'client');

        // Input NIP (otomatis terisi via JS)
        $mform->addElement('text', 'nip', 'NIP', ['readonly' => 'readonly', 'placeholder' => 'Akan terisi otomatis']);
        $mform->setType('nip', PARAM_RAW);
        $mform->addRule('nip', 'NIP tidak boleh kosong', 'required', null, 'client');

        // Alasan izin
        $mform->addElement('textarea', 'alasan', 'Alasan', 'wrap="virtual" rows="4" cols="50"');
        $mform->setType('alasan', PARAM_TEXT);
        $mform->addRule('alasan', 'Tidak boleh kosong', 'required', null, 'client');

        // Keperluan
        $mform->addElement('textarea', 'keperluan', 'Keperluan', 'wrap="virtual" rows="4" cols="50"');
        $mform->setType('keperluan', PARAM_TEXT);
        $mform->addRule('keperluan', 'Tidak boleh kosong', 'required', null, 'client');

        // Tombol
        $this->add_action_buttons(true, 'Simpan dan Cetak');
    }
}
