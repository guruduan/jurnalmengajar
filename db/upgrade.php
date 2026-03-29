<?php

defined('MOODLE_INTERNAL') || die();

function xmldb_local_jurnalmengajar_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // =====================================================
    // 2026032501 - Update schema ke Final Production
    // =====================================================
    if ($oldversion < 2026032501) {

        // BEBAN MENGAJAR - rename jumlahjam -> jam_perminggu
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

        // SURAT IZIN SISWA - tambah index
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

        upgrade_plugin_savepoint(true, 2026032501, 'local', 'jurnalmengajar');
    }

    // =====================================================
    // 2026032701 - Buat tabel Jadwal Mengajar
    // =====================================================
    if ($oldversion < 2026032701) {

        $table = new xmldb_table('local_jurnalmengajar_jadwal');

        if (!$dbman->table_exists($table)) {

            $table->add_field('id', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, null, null);
            $table->add_field('hari', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('kelas', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('jamke', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null);
            $table->add_field('ruang', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '19', null, null, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '19', null, null, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026032701, 'local', 'jurnalmengajar');
    }

    // =====================================================
// 2026033001 - Tabel Jurnal Ekstrakurikuler
// =====================================================
if ($oldversion < 2026033001) {

    // Tabel Ekstrakurikuler
    $table = new xmldb_table('local_jm_ekstra');

    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('namaekstra', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $dbman->create_table($table);
    }

    // Mapping Pembina
    $table = new xmldb_table('local_jm_ekstra_pembina');

    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('ekstraid', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('ekstraid_idx', XMLDB_INDEX_NOTUNIQUE, ['ekstraid']);
        $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);

        $dbman->create_table($table);
    }

    // Peserta Ekstra
    $table = new xmldb_table('local_jm_ekstra_peserta');

    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('ekstraid', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, null, null);
        $table->add_field('kelas', XMLDB_TYPE_CHAR, '20', null, null, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $dbman->create_table($table);
    }

    // Jurnal Ekstra
    $table = new xmldb_table('local_jm_ekstra_jurnal');

    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('ekstraid', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, null, null);
        $table->add_field('tanggal', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, null, null);
        $table->add_field('pembinaid', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, null, null);
        $table->add_field('materi', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('catatan', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '19', null, null, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $dbman->create_table($table);
    }

    // Absensi Ekstra
    $table = new xmldb_table('local_jm_ekstra_absen');

    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('jurnalid', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '19', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('keterangan', XMLDB_TYPE_TEXT, null, null, null, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $dbman->create_table($table);
    }

    upgrade_plugin_savepoint(true, 2026033001, 'local', 'jurnalmengajar');
}

// =====================================================
// 2026033002 - Tambah cohortid di peserta ekstra
// =====================================================
if ($oldversion < 2026033002) {

    $table = new xmldb_table('local_jm_ekstra_peserta');
    $field = new xmldb_field('cohortid', XMLDB_TYPE_INTEGER, '19', null, null, null, null, 'userid');

    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    upgrade_plugin_savepoint(true, 2026033002, 'local', 'jurnalmengajar');
}

    return true;
}
