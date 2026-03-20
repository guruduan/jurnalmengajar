<?php
namespace local_jurnalmengajar\form;

require_once($CFG->libdir.'/formslib.php');

class guruwali_form extends \moodleform {
    public function definition() {
        global $USER;

        $m = $this->_form;

        // dropdown murid langsung dari binaan.csv
        $muridopts = jw_get_murid_options_from_csv($USER->id);

        $m->addElement('static','no','No.','(otomatis)');
        $m->addElement('static','waktu','Waktu Pertemuan', userdate(time()));
        $m->addElement('select','muridid','Nama Murid',$muridopts);
        $m->addRule('muridid','Wajib dipilih','required',null,'client');

        $m->addElement('text','topik','Topik',['size'=>80]);
        $m->setType('topik', PARAM_TEXT);

        $m->addElement('textarea','tindaklanjut','Tindak Lanjut',['rows'=>3,'cols'=>80]);
        $m->setType('tindaklanjut', PARAM_TEXT);

        $m->addElement('textarea','keterangan','Keterangan',['rows'=>3,'cols'=>80]);
        $m->setType('keterangan', PARAM_TEXT);

        $this->add_action_buttons(true,'Simpan');
    }
}
