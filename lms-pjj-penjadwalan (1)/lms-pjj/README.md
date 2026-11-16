# LMS PJJ Sekolah — Pembelajaran Jarak Jauh SD, SMP, SMA, SMK (Laravel 12)

Learning Management System untuk pembelajaran jarak jauh (PJJ) di sekolah, dibangun dengan Laravel 12 dan MySQL. Satu aplikasi bisa dipakai untuk jenjang SD, SMP, SMA, maupun SMK: cukup pilih jenjang di pengaturan, maka pilihan tingkat kelas menyesuaikan (SD 1–6, SMP 7–9, SMA/SMK 10–12) dan menu jurusan muncul untuk SMA/SMK.

Ada tiga peran: **admin sekolah**, **guru**, dan **siswa**. Login memakai NIP (guru) atau NISN (siswa).

## Cara kerja

Admin mengisi data kelas, mata pelajaran, guru, dan siswa (bisa impor dari CSV/Excel), lalu membagi tugas mengajar: setiap mapel di satu kelas menjadi satu **ruang belajar** yang diampu seorang guru, lengkap dengan jadwal dan link kelas daring (Google Meet/Zoom).

Di ruang belajar, guru membuat **pertemuan** berisi materi teks, video YouTube, dan lampiran (PDF, PowerPoint, Word, dll). Setiap pertemuan bisa membuka **presensi daring** pada jam tertentu: siswa menekan tombol *Saya hadir*, siswa yang tidak presensi sampai jam tutup otomatis tercatat alpa, dan guru bisa mengubah status menjadi izin/sakit.

Guru memberi **tugas** dengan tenggat; siswa mengumpulkan file atau jawaban teks, pengumpulan terlambat ditandai otomatis, dan guru menilai satu per satu lengkap dengan komentar. **Kuis/ulangan online** mendukung soal pilihan ganda (dinilai otomatis) dan esai (dikoreksi guru), acak soal dan opsi, batas waktu, jawaban tersimpan otomatis, dan pengumpulan otomatis saat waktu habis.

Nilai akhir tiap mapel dihitung dari rata-rata tugas, kuis, dan persentase kehadiran sesuai bobot yang diatur guru, lalu dibandingkan dengan KKM. Admin melihat leger nilai satu kelas, rekap presensi bulanan, dan pantauan keaktifan siswa untuk menindaklanjuti siswa yang tidak mengikuti PJJ.

## Fitur

**Siswa**
- Beranda: presensi yang sedang dibuka, tugas yang belum dikumpulkan beserta sisa waktu, kuis aktif, jadwal hari ini, pengumuman
- Progres belajar per mapel (materi yang sudah ditandai selesai)
- Materi per pertemuan: teks, video YouTube, lampiran yang bisa dibuka/diunduh
- Presensi daring satu klik selama jam presensi dibuka
- Kumpulkan tugas (file/teks), lihat nilai dan komentar guru
- Kerjakan kuis berwaktu, navigasi nomor soal, simpan otomatis, tetap aman bila sinyal sempat putus
- Nilai sementara per mapel, diskusi tanya jawab dengan guru

**Guru**
- Beranda: tugas menunggu penilaian, kuis aktif, jadwal mengajar hari ini
- Kelola pertemuan (draf/terbit), jam presensi, tugas dan lampiran soal
- Penilaian tugas cepat: daftar siswa di samping, simpan nilai lalu lanjut ke siswa berikutnya
- Kuis: pilihan ganda & esai, bobot per soal, impor banyak soal sekaligus dari teks (salin dari Word), koreksi esai, reset percobaan siswa
- Analisis butir soal: persentase jawaban benar dan sebaran pilihan tiap soal
- Rekap presensi (matriks siswa x pertemuan), rekap nilai lengkap + unduh CSV
- Atur KKM, bobot nilai (tugas/kuis/kehadiran), link kelas daring per jadwal
- Pengumuman untuk kelas yang diajar

**Admin sekolah**
- Dashboard: jumlah siswa aktif hari ini, kehadiran PJJ per kelas hari ini, ruang belajar yang belum mengunggah materi seminggu terakhir
- Data sekolah, jenjang, tahun ajaran & semester (salin pembagian mengajar ke semester baru)
- Jurusan, kelas & wali kelas, mata pelajaran (Umum, Kejuruan, Muatan Lokal)
- Data guru & siswa: tambah, ubah, nonaktifkan, reset password, impor CSV
- Pembagian mengajar & jadwal mingguan dengan pencegahan jadwal bentrok
- Rekap presensi bulanan per kelas, leger nilai semua mapel (CSV/cetak), pantauan keaktifan siswa
- Pengumuman untuk semua, guru saja, siswa saja, atau kelas tertentu (bisa disematkan)

**Teknis**
- Laravel 12 standar: controller, model Eloquent, migration, seeder, Blade (tanpa Node.js/NPM)
- Hak akses per peran dan per ruang belajar; siswa hanya bisa membuka kelasnya sendiri
- Berkas materi/tugas disimpan di storage privat, hanya bisa diunduh oleh yang berhak
- Validasi jenis & ukuran file, perlindungan CSRF, batas percobaan login
- Tampilan responsif, nyaman dibuka di HP siswa
- Data contoh: SMK dengan 5 kelas, 10 guru, 120 siswa, materi, presensi, tugas, dan kuis beberapa minggu

## Instalasi

**Kebutuhan:** PHP 8.2 atau lebih baru (XAMPP 8.2+ / Laragon), MySQL/MariaDB, dan [Composer](https://getcomposer.org).

1. Ekstrak ZIP, pindahkan folder `lms-pjj` ke `C:\xampp\htdocs\lms-pjj` (atau `C:\laragon\www\lms-pjj`).
2. Jalankan Apache dan MySQL, buka `http://localhost/phpmyadmin`, buat database `lms_pjj`.
3. Jalankan setup: Windows klik dua kali `setup.bat`, Linux/macOS `bash setup.sh`.
4. Buka `http://localhost/lms-pjj` dan login:
   - Admin: `admin` / `admin123`
   - Guru: `guru` / `guru123`
   - Siswa: `siswa` / `siswa123`
   - Guru & siswa lain di data contoh: NIP/NISN masing-masing, password `rahasia123`

**Tanpa XAMPP:** `bash setup.sh` lalu `php artisan serve`, buka `http://127.0.0.1:8000`.

**Untuk hosting:** arahkan document root ke folder `public/`, ubah `APP_ENV=production`, `APP_DEBUG=false`, dan `APP_URL` di `.env`. Akun demo di halaman login otomatis disembunyikan saat `APP_ENV=production`; ganti password admin setelah instalasi.

## Mengganti ke jenjang SD / SMP / SMA

Login sebagai admin → **Pengaturan** → ubah nama sekolah dan jenjang. Lalu sesuaikan data kelas, mapel, guru, dan siswa (hapus data contoh atau jalankan `php artisan migrate:fresh --force` untuk mulai dari database kosong, kemudian buat akun admin lewat `php artisan tinker`).

## Penjadwalan otomatis (OR-Tools)

Menu admin **Penjadwalan** menyusun jadwal pelajaran dengan solver OR-Tools CP-SAT (folder `solver/`, Python).

1. **Struktur Jam**: jam pelajaran, istirahat, upacara, dan senam per hari.
2. **Beban Ajar**: jam per minggu tiap guru di tiap kelas dan pola blok (mis. `4+2`). Satu mapel boleh diajar beberapa guru.
3. **Ketersediaan Guru**: tandai jam yang tidak bisa (wajib dihindari) atau sebaiknya dihindari.
4. **Jadwal Otomatis**: tekan *Mulai hitung*. Hasilnya berupa **draf**; periksa grid per kelas atau per guru, pindahkan atau kunci blok bila perlu, lalu **Aktifkan** supaya tampil di LMS.

Kebutuhan: Python 3.9+ dan `pip install -r solver/requirements.txt`. Atur di `.env` (lihat `.env.example`): `SOLVER_PYTHON` (Windows: `python` atau `py`), dan `SOLVER_MODE`:

- `queue` (bawaan): jalankan `php artisan queue:work --timeout=1900` di terminal terpisah.
- `proses`: tanpa worker, solver dijalankan sebagai proses latar. Lebih mudah di XAMPP/Laragon.

Uji lewat terminal: `php artisan penjadwalan:solve` (membuat draf baru lalu menghitung). Pemeriksa jadwal mandiri: `python solver/validasi.py input.json jadwal.json`.

Guru memakai **kode guru** (mis. SWP) seperti di jadwal kertas; isi di menu Guru (impor CSV punya kolom ke-6 `kode_guru`). Guru yang punya beban ajar di sebuah ruang bisa membuka ruang tersebut di LMS.
