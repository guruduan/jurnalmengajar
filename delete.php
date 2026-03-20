
<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$id = required_param('id', PARAM_INT);
$context = context_system::instance();
require_capability('local/jurnalmengajar:submit', $context);

global $DB, $USER;

$record = $DB->get_record('local_jurnalmengajar', [
    'id' => $id
], '*', MUST_EXIST);
$DB->delete_records('local_jurnalmengajar', ['id' => $id]);

redirect(new moodle_url('/local/jurnalmengajar/all.php'), 'Entri berhasil dihapus.', 2);
