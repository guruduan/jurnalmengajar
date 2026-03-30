<?php
require('../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/jurnalmengajar:submitsuratizin', $context);

$id = required_param('id', PARAM_INT);

$record = $DB->get_record('local_jurnalmengajar_suratizinguru', ['id' => $id], '*', MUST_EXIST);

// Hapus
$DB->delete_records('local_jurnalmengajar_suratizinguru', ['id' => $id]);

redirect(new moodle_url('/local/jurnalmengajar/izin_guru.php'), 'Data berhasil dihapus', 2);
