<?php
namespace local_jurnalmengajar\form;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

class jurnal_form extends \moodleform {
    public function definition() {
        global $DB;
        $mform = $this->_form;

        // ✅ Tambahkan field hidden 'id' untuk menyimpan parameter saat edit
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $cohorts = $DB->get_records_menu('cohort', null, 'name ASC', 'id, name');
        $kelas_options = ['' => '-- Pilih Kelas --'] + $cohorts;

        $mform->addElement('select', 'kelas', 'Kelas', $kelas_options);
        $mform->addRule('kelas', 'Silakan pilih kelas', 'required');
        $mform->setType('kelas', PARAM_TEXT);

        $mform->addElement('text', 'jamke', 'Jam Pelajaran Ke');
        $mform->setType('jamke', PARAM_TEXT);
        $mform->addRule('jamke', 'Isian hanya boleh angka dan koma (misal: 2,3)', 'regex', '/^\d+(,\d+)*$/', 'client');
        $mform->addRule('jamke', null, 'required', null, 'client');

        $mform->addElement('select', 'matapelajaran', 'Mata Pelajaran', ['' => '- Pilih Mata Pelajaran -'] + [
            'Pendidikan Agama Islam dan Budi Pekerti' => 'Pendidikan Agama Islam dan Budi Pekerti',
            'Pendidikan Agama Kristen dan Budi Pekerti' => 'Pendidikan Agama Kristen dan Budi Pekerti',
            'Pendidikan Pancasila' => 'Pendidikan Pancasila',
            'Bahasa Indonesia' => 'Bahasa Indonesia',
            'Bahasa Inggris' => 'Bahasa Inggris',
            'Fisika' => 'Fisika',
            'Kimia' => 'Kimia',
            'Biologi' => 'Biologi',
            'Sosiologi' => 'Sosiologi',
            'Ekonomi' => 'Ekonomi',
            'Geografi' => 'Geografi',
            'Pendidikan Jasmani, Olahraga dan Kesehatan' => 'Pendidikan Jasmani, Olahraga dan Kesehatan',
            'Seni dan Budaya' => 'Seni dan Budaya',
            'Informatika' => 'Informatika',
            'Matematika' => 'Matematika',
            'Matematika Lanjut' => 'Matematika Lanjut',
            'Sejarah' => 'Sejarah',
            'Prakarya dan Kewirausahaan' => 'Prakarya dan Kewirausahaan',
            'Pendidikan Al Quran' => 'Pendidikan Al Quran',
            'Bimbingan Konseling' => 'Bimbingan Konseling',
            'Pendalaman AlKitab' => 'Pendalaman AlKitab'
        ]);
        $mform->setType('matapelajaran', PARAM_TEXT);
        $mform->addRule('matapelajaran', null, 'required');

        $mform->addElement('textarea', 'materi', 'Materi', 'rows="3" cols="60"');
        $mform->setType('materi', PARAM_RAW);
        $mform->addRule('materi', null, 'required');

        $mform->addElement('textarea', 'aktivitas', 'Aktivitas KBM', 'rows="3" cols="60"');
        $mform->setType('aktivitas', PARAM_RAW);
        $mform->addRule('aktivitas', null, 'required');

        $mform->addElement('html', '<div id="absen-area"><em>Silakan pilih kelas...</em></div>');
        $mform->addElement('textarea', 'absen', 'Murid Tidak Hadir', 'wrap="virtual" rows="2" cols="50" readonly');
        $mform->setType('absen', PARAM_RAW);

        $mform->addElement('textarea', 'keterangan', 'Keterangan Tambahan', 'rows="2" cols="60"');
        $mform->setType('keterangan', PARAM_RAW);

        $this->add_action_buttons(true, 'Simpan Jurnal');

        // ✅ Script AJAX tetap
        $mform->addElement('html', <<<HTML
<script>
require(['jquery'], function($) {
    function updateAbsenField() {
        const data = {};
        $('.absen-item input[type=checkbox]').each(function() {
            if (this.checked) {
                const nama = $(this).data('nama');
                const alasan = $(this).closest('.absen-item').find('select').val();
                if (alasan) data[nama] = alasan;
            }
        });
        $('input[name=absen]').val(JSON.stringify(data));
    }

    function loadSiswa(kelas) {
        if (!kelas) return;
        $.get('/local/jurnalmengajar/get_students.php', {kelas: kelas}, function(html) {
            $('#absen-area').html(html);

            $('.absen-checkbox').on('change', function() {
                const alasan = $(this).closest('.absen-item').find('select');
                alasan.prop('disabled', !this.checked);
                updateAbsenField();
            });

            $('.absen-alasan').on('change', updateAbsenField);
        });
    }

    $(document).ready(function() {
        $('select[name=kelas]').change(function() {
            loadSiswa($(this).val());
        });
        loadSiswa($('select[name=kelas]').val());
    });
});
</script>
HTML);
    }
}
