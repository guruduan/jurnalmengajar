# Sistem Informasi Murid Guru - Moodle Local Plugin

Plugin Moodle untuk administrasi sekolah.

## Fitur Utama

### 1. Input E-Jurnal KBM
Guru pilih kelas, input jam pelajaran ke (sesuai jam masuk), pilih mata pelajaran, input materi pembelajaran, input aktivitas KBM, pilih Murid tidak hadir - dan beri pilihan sakit, ijin, alpa atau dispensasi dan input keterangan tambahan (misal murid A tidur atau apapun kejadian di kelas). **Guru klik Simpan Jurnal: Jurnal tersimpan di database sistem dan notif wa terkirim ke Wali Kelasnya dan guru sendiri**.

Jurnal mengajar tersimpan di sistem bisa diakses melalui tombol Riwayat Jurnal. Setiap bulan, riwayat jurnal mengajar ini bisa diekspor ke format xlsx, untuk ditandatangi kepsek sebagai laporan.

Notif wa terkirim ke wali kelas, berfungsi ganda; sebagai laporan bahwa Guru mata pelajaran sudah mengajar di kelasnya. Isi pesan wa sesuai yang diinput Guru, sebagai informasi aktivitas pembelajaran yang dilaksanakan dan bagaimana keadaan kelas atau muridnya. Notif wa ini bisa diteruskan ke wa orangtua/wali murid. Misal murid B tidak hadir alpa, padahal dari rumah berangkat sekolah, orangtua/wali muridnya cepat dapat informasi dari kelas langsung; akan memudahkan pembinaan murid dari guru, wali murid dan orangtua sendiri.

### 2. Jadwal Mengajar
Saat dibuka menampilkan jadwal mengajar guru yang bersangkutan. Juga terdapat tombol filter nama guru untuk menampilkan jadwal mengajar dari guru lain.

### 3. Guru Mengajar hari ini
Menu ini menampilkan daftar guru yang mengajar hari ini dan sudah mengisi E-jurnal KBM, data ditampilkan dari E-jurnal KBM yang diinput para guru pada hari ini urut berdasarkan waktu input E-Jurnal KBM. Terdapat fitur Ke Tanggal; yang akan memudahkan guru melihat data tanggal berapa yang diinginkan (bukan tanggal hari ini)

### 4. Jam Pelajaran
Menampilkan waktu tiap jam pelajaran dari jam pelajaran pertama sampai terakhir. Juga ditampilkan waktu istirahat

### 5. Jadwal Per Kelas
Menampilkan jadwal guru mengajar dari kelas yang dipilih, mulai hari pertama sampai hari terakhir sekolah setiap minggunya. Dilengkapi jam pelajaran dan waktu awal sampai akhir jadwal mata pelajarannya.

### 6. Murid Tidak Hadir hari ini
Menampilkan data seluruh Murid yang tidak hadir hari ini dari semua kelas yang sudah diisi E-Jurnal KBM nya oleh para guru. Ada keterangan jam pelajaran ke berapa tidak hadirnya dan waktu inputnya. Juga terdapat fitur Ke Tanggal; yang akan memudahkan guru melihat data tanggal berapa yang diinginkan (bukan tanggal hari ini)

### 7. Rekap Kehadiran Murid
Menampilkan data rekap kehadiran murid per kelas yang dipilih. Ada filter dari tanggal sampai tanggal tertentu. Ada dua mode hitung: rekap per hari dan rekap per jam . Ada filter untuk Guru yang bersangkutan saja dan ada untuk semua guru. Sangat memudahkan proses rekapitulasi kehadiran murid sesuai kepentingan bagi guru sendiri, wali kelas atau orangtua/wali murid.

### 8. Rekap Mengajar Mingguan Guru
Menampilkan data semua guru yang mengajar pada minggu ini (atau pada minggu tertentu yang dipilih), nama guru diurut sesuai abjad, ditampilkan jumlah jam mengajar, beban jam mengajar, dan persentasi pelaksanaan mengajarnya (% mingguan). Dilengkapi dengan tombol detail untuk melihat aktivitas mengajar guru pada minggu itu.

### 9. Rekap KBM di Kelas perminggu
Menampilkan data aktivitas semua guru yang mengajar pada kelas yang dipilih, mulai hari pertama sekolah sampai hari terakhir sekolah setiap minggunya.

### 10. Jurnal Guru wali
Guru memilih siapa murid yang dibina sesuai pembagian murid binaan. Guru sebagai guru wali input jurnal guru wali, terdiri dari isian topik pembinaan/pertemuan, tindaklanjutnya dan keterangan. **Ketika jurnal disimpan, notif wa terkirim ke wali kelas murid yang bersangkutan.**


### 11. Data Murid Binaan Guru Wali
Menampilkan daftar nama murid binaan dari guru wali berdasarkan nama Guru yang dipilih. Sebagai informasi dari guru lain.


### 12. Rekap Kehadiran Murid Binaan Guru Wali
Menampilkan data rekap kehadiran murid binaan masing-masing guru. Ada filter dari tanggal sampai tanggal tertentu. Ada dua mode hitung: rekap per hari dan rekap per jam.

### 13. Input Nilai Harian
Guru yang bersangkutan pilih mata pelajaran, pilih kelas, tentukan tanggal, isi nilai harian per siswa, simpan Nilai. Ada notif wa ke guru yang bersangkutan berisi hanya murid yang diisi nilainya, untuk diteruskan ke grup kelas yang dinilai.

### 14. Rekap Nilai Harian Murid
Menampilkan data nilai yang diberikan pada murid kelas yang sudah diisi nilai hariannya, ada nilai rata-rata. 

### 15. Input Izin Murid (Masuk, Keluar atau Pulang)
Guru piket dapat input Surat Izin untuk murid: Murid datang terlambat mau masuk kelas harus ada surat izin masuk; Murid mau keluar sekolah untuk suatu keperluan misalnya ke puskesmas harus ada surat izin keluar, atau murid mau pulang untuk suatu keperluan harus ada surat izin pulang. **Setelah Guru piket menekan tombol cetak surat atau simpan, data tersimpan di database sistem dan notif wa terkirim ke wali kelas dari murid yang bersangkutan.**

### 16. Rekap Surat Izin Murid
Semua surat izin murid ada rekapitulasinya, bisa dipilih kelas, bisa dipilih murid tertentu, dari tanggal tertentu sampai tanggal tertentu.

### 17. Cetak Banyak Surat Izin
Kadangkala banyak murid meminta surat izin, Guru Piket input dan simpan dahulu lalu bisa melakukan pencetakan surat izin banyak sekaligus, bukan satu satu surat dicetak.

### 18. Layanan Guru BK
Guru BK dapat meninput layanan yang sudah dilakukan, baik layanan individu, kelompok atau klasikal. **Ketika tombol simpan ditekan, data layanan tersimpan di database sistem dan notif wa terkirim ke wali kelas dari murid yang diberikan layanan**. Tersedia ekspor ke xlsx untuk layanan bulan yang dipilih.

### 19. Pembinaan Guru BK
Guru BK dapat meninput pembinaan yang sudah dilakukan untuk murid tertentu. **Ketika tombol simpan ditekan, data pembinaan tersimpan di database sistem dan notif wa terkirim ke wali kelas dari murid yang diberikan pembinaan**. Tersedia ekspor ke xlsx untuk data pembinaan bulan yang dipilih.

### 20. Pengawas Harian
### 21. Pengawas Harian


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
