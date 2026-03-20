<?php

defined('MOODLE_INTERNAL') || die();

function xmldb_local_jurnalmengajar_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // =====================================================
    // 2026030200 - Schema Lock Final v1.0
    // =====================================================
    if ($oldversion < 2026030200) {

        // =========================
        // local_jurnalmengajar
        // =========================
        $table = new xmldb_table('local_jurnalmengajar');

        // Rename kegiatan -> aktivitas (if exists)
        $oldfield = new xmldb_field('kegiatan');
        if ($dbman->field_exists($table, $oldfield)) {
            $dbman->rename_field($table, $oldfield, 'aktivitas');
        }

        // Change jamke to char(10)
        $field = new xmldb_field('jamke', XMLDB_TYPE_CHAR, '10', null, null, null, null, 'kelas');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }

        // Add userid index if not exists
        $index = new xmldb_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // =========================
        // local_jurnalguruwali
        // =========================
        $table = new xmldb_table('local_jurnalguruwali');

        $index1 = new xmldb_index('guruid_idx', XMLDB_INDEX_NOTUNIQUE, ['guruid']);
        if (!$dbman->index_exists($table, $index1)) {
            $dbman->add_index($table, $index1);
        }

        $index2 = new xmldb_index('muridid_idx', XMLDB_INDEX_NOTUNIQUE, ['muridid']);
        if (!$dbman->index_exists($table, $index2)) {
            $dbman->add_index($table, $index2);
        }

        $index3 = new xmldb_index('timecreated_idx', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
        if (!$dbman->index_exists($table, $index3)) {
            $dbman->add_index($table, $index3);
        }

        // =========================
        // local_jurnallayananbk
        // =========================
        $table = new xmldb_table('local_jurnallayananbk');

        $index = new xmldb_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // =========================
        // local_jurnalpembinaan
        // =========================
        $table = new xmldb_table('local_jurnalpembinaan');

        $index = new xmldb_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026030200, 'local', 'jurnalmengajar');
    }

    return true;
}
