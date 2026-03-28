# Jurnal Mengajar - Moodle Local Plugin

Plugin Moodle untuk administrasi sekolah yang digunakan untuk mencatat aktivitas guru dan administrasi siswa seperti jurnal mengajar, jurnal wali kelas, surat izin siswa, rekap kehadiran, layanan BK, dan notifikasi WhatsApp.

## Fitur Utama

Plugin ini menyediakan beberapa fitur administrasi sekolah:

### 1. Jurnal Mengajar Guru

* Input jurnal setiap mengajar
* Materi pembelajaran
* Kegiatan pembelajaran
* Jam pelajaran
* Siswa tidak hadir
* Keterangan
* Ekspor ke xlsx

### 2. Jurnal Guru Wali

* Jurnal Guru wali
* expor ke xlsx

### 3. Surat Izin Siswa

* Surat izin keluar
* Surat izin masuk
* Surat izin pulang
* Cetak PDF
* Rekap surat izin

### 4. Rekap Kehadiran Siswa

* Rekap per kelas
* Rekap per siswa
* Filter tanggal / bulan / minggu

### 5. Rekap Jam Mengajar Guru

* Jumlah jam mengajar
* Jam terlaksana
* Persentase KBM tiap pekan
* 

### 6. Layanan BK / Pembinaan Siswa

* Jurnal layanan BK
* Jurnal pembinaan murid
* Riwayat layanan bK
* Riwayat pembinaan

### 7. Notifikasi WhatsApp

* Notifikasi otomatis saat jurnal diinput
* Dikirim ke wali kelas
* Menggunakan API WhatsApp (Wablas / sejenis)

---

## Instalasi Plugin

1. Download atau clone repository ini
2. Copy folder ke direktori Moodle:

   ```
   moodle/local/jurnalmengajar
   ```
3. Login sebagai Admin Moodle
4. Buka:

   ```
   Site Administration → Notifications
   ```
5. Ikuti proses instalasi sampai selesai

---

## Struktur Plugin

```
local/jurnalmengajar/
 ├── classes/
 ├── db/
 │    ├── install.xml
 │    └── access.php
 ├── lang/
 │    └── en/
 ├── version.php
 ├── lib.php
 ├── index.php
 └── README.md
```

---

## Kebutuhan Sistem

* Moodle 4
* PHP 8.2
* MariaDB / MySQL
* Web server Nginx

---

## Penggunaan

Plugin digunakan untuk administrasi sekolah tanpa menggunakan Course Moodle, sehingga Moodle dapat digunakan sebagai Sistem Informasi Sekolah seperti:

* Jurnal Mengajar
* Absensi Siswa
* Surat Izin
* Rekap Guru
* Layanan BK
* Pembinaan Siswa

---

## Author

**Noor Ridhwan**
SMAN 2 Kandangan
Kalimantan Selatan
Indonesia

---

## License

Plugin ini menggunakan lisensi MIT dan bebas digunakan serta dimodifikasi untuk kebutuhan sekolah.
