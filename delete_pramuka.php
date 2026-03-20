<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$id = required_param('id', PARAM_INT);
require_sesskey();

$context = context_system::instance();
require_capability('local/jurnalmengajar:view', $context);

global $DB;

$DB->delete_records('local_jurnalpramuka', ['id' => $id]);

redirect(
    new moodle_url('/local/jurnalmengajar/riwayat_pramuka.php'),
    'Data berhasil dihapus',
    2
);
