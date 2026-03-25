<?php

defined('MOODLE_INTERNAL') || die();

function xmldb_local_jurnalmengajar_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // =====================================================
    // 2026032501 - Update schema ke Final Production
    // =====================================================
    if ($oldversion < 2026032501) {

        // =================================================
        // BEBAN MENGAJAR - rename jumlahjam -> jam_perminggu
        // =================================================
        $table = new xmldb_table('local_jurnalmengajar_beban');

        $oldfield = new xmldb_field('jumlahjam');
        $newfield = new xmldb_field('jam_perminggu', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, 0, 'userid');

        if ($dbman->field_exists($table, $oldfield)) {
            $dbman->rename_field($table, $oldfield, 'jam_perminggu');
        } else {
            if (!$dbman->field_exists($table, $newfield)) {
                $dbman->add_field($table, $newfield);
            }
        }

        // =================================================
        // SURAT IZIN SISWA - tambah index
        // =================================================
        $table = new xmldb_table('local_jurnalmengajar_suratizin');

        $indexes = [
            'userid_idx' => 'userid',
            'kelasid_idx' => 'kelasid',
            'guru_pengajar_idx' => 'guru_pengajar',
            'penginput_idx' => 'penginput',
            'timecreated_idx' => 'timecreated'
        ];

        foreach ($indexes as $name => $fieldname) {
            $index = new xmldb_index($name, XMLDB_INDEX_NOTUNIQUE, [$fieldname]);
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        // =================================================
        // SURAT IZIN GURU - tambah index userid
        // =================================================
        $table = new xmldb_table('local_jurnalmengajar_suratizinguru');

        $index = new xmldb_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // =================================================
        // JURNAL PEMBINAAN - tambah field baru
        // =================================================
        $table = new xmldb_table('local_jurnalpembinaan');

        $fields = [
            new xmldb_field('peserta', XMLDB_TYPE_TEXT, null, null, null, null, null, 'kelas'),
            new xmldb_field('permasalahan', XMLDB_TYPE_TEXT, null, null, null, null, null, 'peserta'),
            new xmldb_field('tindakan', XMLDB_TYPE_TEXT, null, null, null, null, null, 'permasalahan'),
            new xmldb_field('tempat', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'tindakan')
        ];

        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // =================================================
        // NILAI HARIAN - tambah field baru
        // =================================================
        $table = new xmldb_table('local_jm_nilaiharian');

        $fields = [
            new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, null, 0, 'timecreated'),
            new xmldb_field('mapel', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null, 'userid'),
            new xmldb_field('cohortid', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, null, null, 'mapel'),
            new xmldb_field('tanggal', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null, 'kelas'),
            new xmldb_field('nilaijson', XMLDB_TYPE_TEXT, null, null, null, null, null, 'tanggal')
        ];

        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // tambah index nilai harian
        $indexes = [
            'userid_idx' => 'userid',
            'cohortid_idx' => 'cohortid',
            'tanggal_idx' => 'tanggal'
        ];

        foreach ($indexes as $name => $fieldname) {
            $index = new xmldb_index($name, XMLDB_INDEX_NOTUNIQUE, [$fieldname]);
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        // SAVEPOINT
        upgrade_plugin_savepoint(true, 2026032501, 'local', 'jurnalmengajar');
    }

    return true;
}
