#!/bin/bash

# Path output harus ditentukan di awal
OUTPUT="$(dirname "$0")/jurnal_21_25juli.txt"

# Konfigurasi database
DB_NAME="moodle"            # Ganti sesuai config.php
DB_USER="usermoodle"     # Ganti sesuai config.php
DB_PASS="secret2"     # Ganti sesuai config.php

# Query MySQL & format dengan AWK
mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -N -e "
SELECT 
  FROM_UNIXTIME(j.timecreated, '%Y-%m-%d') AS tanggal,
  DAYNAME(FROM_UNIXTIME(j.timecreated)) AS hari,
  u.lastname,
  c.name AS kelas,
  j.jamke,
  j.keterangan
FROM mdl_local_jurnalmengajar j
JOIN mdl_user u ON u.id = j.userid
JOIN mdl_cohort c ON c.id = j.kelas
WHERE j.timecreated >= UNIX_TIMESTAMP('2025-07-21 00:00:00')
  AND j.timecreated <  UNIX_TIMESTAMP('2025-07-26 00:00:00')
ORDER BY j.timecreated, u.lastname, kelas, j.jamke;" \
| awk '
BEGIN {
    hari_id[1]="Monday"; nama_hari["Monday"]="Senin";
    hari_id[2]="Tuesday"; nama_hari["Tuesday"]="Selasa";
    hari_id[3]="Wednesday"; nama_hari["Wednesday"]="Rabu";
    hari_id[4]="Thursday"; nama_hari["Thursday"]="Kamis";
    hari_id[5]="Friday"; nama_hari["Friday"]="Jumat";
}
{
    data[$2]=data[$2] sprintf("%s\t%s\t%s\t%s\t%s\n", $1,$3,$4,$5,$6)
}
END {
    for(i=1;i<=5;i++){
        h=hari_id[i]
        if(data[h]!=""){
            printf("%d) %s\n%s\n", i, nama_hari[h], data[h])
        }
    }
}' > "$OUTPUT"

echo "File teks berhasil dibuat: $OUTPUT"
