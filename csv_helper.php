<?php
// JANGAN require config.php di sini

function jw_load_binaan_csv(): array {
    global $CFG;
    $csvpath = $CFG->dataroot . '/binaan.csv';
    if (!file_exists($csvpath)) return [];

    $content = file_get_contents($csvpath);
    if ($content === false) return [];

    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    $lines = preg_split("/\r\n|\n|\r/", trim($content));
    if (count($lines) < 2) return [];

    $delimiter = (substr_count($lines[0], ';') > substr_count($lines[0], ',')) ? ';' : ',';
    $header = str_getcsv(array_shift($lines), $delimiter);

    $need = ['userid','lastname','nis','murid','kelas'];
    $idx = [];
    foreach ($need as $c) {
        $p = array_search($c, $header);
        if ($p === false) return [];
        $idx[$c] = $p;
    }

    $rows = [];
    foreach ($lines as $line) {
        $r = str_getcsv($line, $delimiter);
        $rows[] = [
            'guruid' => (int)($r[$idx['userid']] ?? 0),
            'nis'    => trim($r[$idx['nis']] ?? ''),
            'murid'  => trim($r[$idx['murid']] ?? ''),
            'kelas'  => trim($r[$idx['kelas']] ?? ''),
        ];
    }
    return $rows;
}

function jw_get_murid_options_from_csv(int $guruid): array {
    global $DB;
    $rows = jw_load_binaan_csv();
    if (!$rows) return [];

    $ids = [];
    foreach ($rows as $r) {
        if ($r['guruid'] != $guruid) continue;
        if ($r['nis'] === '') continue;

        $u = $DB->get_record_sql(
            "SELECT u.id
               FROM {user} u
               JOIN {user_info_data} d ON d.userid=u.id
               JOIN {user_info_field} f ON f.id=d.fieldid
              WHERE f.shortname='nis' AND d.data=:nis",
            ['nis'=>$r['nis']],
            IGNORE_MISSING
        );
        if ($u) $ids[$u->id] = true;
    }

    if (!$ids) return [];

    list($in,$p) = $DB->get_in_or_equal(array_keys($ids));
    $users = $DB->get_records_select('user',"id $in",$p,'lastname,firstname','id,firstname,lastname');

    $out = [];
    foreach ($users as $u) {
        $out[$u->id] = trim($u->firstname.' '.$u->lastname);
    }
    asort($out, SORT_NATURAL | SORT_FLAG_CASE);
    return $out;
}
