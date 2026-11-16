<?php

namespace Database\Seeders;

use App\Models\Diskusi;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Kuis;
use App\Models\Mapel;
use App\Models\Pengaturan;
use App\Models\Pengumpulan;
use App\Models\Pengumuman;
use App\Models\Percobaan;
use App\Models\Pertemuan;
use App\Models\Presensi;
use App\Models\Ruang;
use App\Models\TahunAjaran;
use App\Models\Tugas;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Data SMK PP Negeri Banjarbaru, Semester Ganjil 2026/2027.
 * Sumber: jadwal_terstruktur.xlsx (Jadwal PBM Rev 31 Juli): 3 jurusan, 8 rombel (kelas X dan XI),
 * 36 kode guru, 35 mapel, 128 ruang belajar, 141 beban ajar, 144 blok jadwal, 408 jam pelajaran/minggu.
 *
 * Yang di-seed:
 *  - Master LMS    : tahun ajaran, jurusan, kelas, mapel, guru (+kode_guru), siswa, ruang.
 *  - Penjadwalan   : slot_waktu, beban_ajar, versi_jadwal (impor, aktif), jadwal (blok per guru), ketersediaan_guru kosong.
 *  - Isi LMS       : materi/presensi beberapa minggu terakhir; ruang demo Matematika X ATP A lengkap (tugas, kuis, diskusi).
 *
 * Catatan data: nama guru belum ada di jadwal, jadi dibuat "Guru <KODE>" dan username = kode huruf kecil
 * (ganti dengan NIP/nama asli lewat impor atau edit di bawah). Tanggal LMS dibuat relatif terhadap hari ini.
 */
class DatabaseSeeder extends Seeder
{
    private string $hash;

    /** Nama mapel di jadwal (kolom pertama) => dipakai sebagai kunci. Format: [nama di jadwal, kode, nama lengkap, kelompok] */
    private function dataMapel(): array
    {
        return [
            ['AT. Buah', 'ATBUAH', 'AT. Buah', 'Kejuruan'],
            ['AT. Hias', 'ATHIAS', 'AT. Hias', 'Kejuruan'],
            ['AT. Sayur', 'ATSAYUR', 'AT. Sayur', 'Kejuruan'],
            ['Alsintan', 'ALSINTAN', 'Alat dan Mesin Pertanian', 'Kejuruan'],
            ['B. Ind', 'BIND', 'Bahasa Indonesia', 'Umum'],
            ['B. Ing', 'BING', 'Bahasa Inggris', 'Umum'],
            ['BK', 'BK', 'Bimbingan Konseling', 'Umum'],
            ['BTA', 'BTA', 'Baca Tulis Al-Qur\'an', 'Muatan Lokal'],
            ['DPBHP', 'DPBHP', 'DPBHP', 'Kejuruan'],
            ['DPMHP', 'DPMHP', 'DPMHP', 'Kejuruan'],
            ['DPPHP', 'DPPHP', 'DPPHP', 'Kejuruan'],
            ['Das. AT', 'DASAT', 'Das. AT', 'Kejuruan'],
            ['Hewani', 'HEWANI', 'Hewani', 'Kejuruan'],
            ['Hidro', 'HIDRO', 'Hidro', 'Kejuruan'],
            ['IPAS', 'IPAS', 'Ilmu Pengetahuan Alam dan Sosial', 'Kejuruan'],
            ['Kempang', 'KEMPANG', 'Kempang', 'Kejuruan'],
            ['Kuljar', 'KULJAR', 'Kuljar', 'Kejuruan'],
            ['Literasi', 'LITERASI', 'Literasi', 'Umum'],
            ['MTK', 'MTK', 'Matematika', 'Umum'],
            ['Nabati', 'NABATI', 'Nabati', 'Kejuruan'],
            ['P. Bibit', 'PBIBIT', 'P. Bibit', 'Kejuruan'],
            ['P. Siap Lahan', 'PSIAPLAHAN', 'P. Siap Lahan', 'Kejuruan'],
            ['PAI', 'PAI', 'Pendidikan Agama Islam', 'Umum'],
            ['PH. Tan Bun', 'PHTANBUN', 'PH. Tan Bun', 'Kejuruan'],
            ['PKN', 'PKN', 'Pendidikan Pancasila', 'Umum'],
            ['PKWU', 'PKWU', 'Projek Kreatif dan Kewirausahaan', 'Umum'],
            ['Panen', 'PANEN', 'Panen', 'Kejuruan'],
            ['Pangan', 'PANGAN', 'Pangan', 'Kejuruan'],
            ['Pemeliharaan', 'PEMELIHARAAN', 'Pemeliharaan', 'Kejuruan'],
            ['Pemeliharaan 2', 'PEMELIHARAAN2', 'Pemeliharaan 2', 'Kejuruan'],
            ['Penjas', 'PENJAS', 'Pendidikan Jasmani', 'Umum'],
            ['Penyegar', 'PENYEGAR', 'Penyegar', 'Kejuruan'],
            ['Sejarah', 'SEJARAH', 'Sejarah', 'Umum'],
            ['Senbud', 'SENBUD', 'Seni Budaya', 'Umum'],
            ['Simdig', 'SIMDIG', 'Simulasi dan Komunikasi Digital', 'Kejuruan'],
        ];
    }

    /** Struktur jam per hari: [hari, jam_ke|null, mulai, selesai, jenis]. 1 = Senin ... 5 = Jumat */
    private function dataSlot(): array
    {
        return [
            [1, 0, '06:45', '07:30', 'upacara'],
            [1, 1, '07:30', '08:15', 'upacara'],
            [1, 2, '08:15', '09:00', 'kbm'],
            [1, 3, '09:00', '09:45', 'kbm'],
            [1, null, '09:45', '10:00', 'istirahat'],
            [1, 4, '10:00', '10:45', 'kbm'],
            [1, 5, '10:45', '11:30', 'kbm'],
            [1, 6, '11:30', '12:15', 'kbm'],
            [1, null, '12:15', '12:45', 'istirahat'],
            [1, 7, '12:45', '13:30', 'kbm'],
            [1, 8, '13:30', '14:15', 'kbm'],
            [1, 9, '14:15', '15:00', 'kbm'],
            [1, 10, '15:00', '15:45', 'kbm'],
            [1, 11, '15:45', '16:30', 'kbm'],
            [1, 12, '16:30', '17:15', 'kbm'],
            [2, 0, '07:00', '07:45', 'kbm'],
            [2, 1, '07:45', '08:30', 'kbm'],
            [2, 2, '08:30', '09:15', 'kbm'],
            [2, 3, '09:15', '10:00', 'kbm'],
            [2, null, '10:00', '10:15', 'istirahat'],
            [2, 4, '10:15', '11:00', 'kbm'],
            [2, 5, '11:00', '11:45', 'kbm'],
            [2, 6, '11:45', '12:30', 'kbm'],
            [2, null, '12:30', '13:00', 'istirahat'],
            [2, 7, '13:00', '13:45', 'kbm'],
            [2, 8, '13:45', '14:30', 'kbm'],
            [2, 9, '14:30', '15:15', 'kbm'],
            [2, 10, '15:15', '16:00', 'kbm'],
            [2, 11, '16:00', '16:45', 'kbm'],
            [3, 0, '07:00', '07:45', 'kbm'],
            [3, 1, '07:45', '08:30', 'kbm'],
            [3, 2, '08:30', '09:15', 'kbm'],
            [3, 3, '09:15', '10:00', 'kbm'],
            [3, null, '10:00', '10:15', 'istirahat'],
            [3, 4, '10:15', '11:00', 'kbm'],
            [3, 5, '11:00', '11:45', 'kbm'],
            [3, 6, '11:45', '12:30', 'kbm'],
            [3, null, '12:30', '13:00', 'istirahat'],
            [3, 7, '13:00', '13:45', 'kbm'],
            [3, 8, '13:45', '14:30', 'kbm'],
            [3, 9, '14:30', '15:15', 'kbm'],
            [3, 10, '15:15', '16:00', 'kbm'],
            [3, 11, '16:00', '16:45', 'kbm'],
            [3, 12, '16:45', '17:30', 'kbm'],
            [4, 0, '07:00', '07:45', 'kbm'],
            [4, 1, '07:45', '08:30', 'kbm'],
            [4, 2, '08:30', '09:15', 'kbm'],
            [4, 3, '09:15', '10:00', 'kbm'],
            [4, null, '10:00', '10:15', 'istirahat'],
            [4, 4, '10:15', '11:00', 'kbm'],
            [4, 5, '11:00', '11:45', 'kbm'],
            [4, 6, '11:45', '12:30', 'kbm'],
            [4, null, '12:30', '13:00', 'istirahat'],
            [4, 7, '13:00', '13:45', 'kbm'],
            [4, 8, '13:45', '14:30', 'kbm'],
            [4, 9, '14:30', '15:15', 'kbm'],
            [4, 10, '15:15', '16:00', 'kbm'],
            [4, 11, '16:00', '16:45', 'kbm'],
            [4, 12, '16:45', '17:30', 'kbm'],
            [5, 0, '07:00', '08:00', 'senam'],
            [5, 1, '08:15', '09:00', 'kbm'],
            [5, 2, '09:00', '09:45', 'kbm'],
            [5, 3, '09:45', '10:30', 'kbm'],
            [5, 4, '10:30', '11:15', 'kbm'],
            [5, null, '11:15', '14:00', 'istirahat'],
            [5, 5, '14:00', '14:45', 'kbm'],
            [5, 6, '14:45', '15:30', 'kbm'],
            [5, 7, '15:30', '16:15', 'kbm'],
            [5, 8, '16:15', '17:00', 'kbm'],
        ];
    }

    /**
     * Beban ajar + posisi jadwal: [rombel, mapel, kode_guru, jam_per_minggu, [[hari, jam_ke_mulai, jam_ke_selesai], ...]]
     * Satu rombel+mapel boleh punya beberapa guru (mis. Das. AT) => satu ruang, beberapa baris beban_ajar.
     * RA-1 dan RA-2 = dua guru berbeda yang sama-sama berkode RA di jadwal kertas.
     */
    private function dataBeban(): array
    {
        return [
            ['X ATP A', 'B. Ind', 'MIA', 4, [[3, 1, 4]]],
            ['X ATP A', 'B. Ing', 'MONA', 4, [[1, 4, 7]]],
            ['X ATP A', 'BK', 'HA', 2, [[4, 10, 11]]],
            ['X ATP A', 'BTA', 'QA', 2, [[5, 1, 2]]],
            ['X ATP A', 'Das. AT', 'AG', 3, [[4, 3, 5]]],
            ['X ATP A', 'Das. AT', 'HW', 3, [[4, 0, 2]]],
            ['X ATP A', 'Das. AT', 'SR', 3, [[2, 0, 2]]],
            ['X ATP A', 'Das. AT', 'SYR', 3, [[2, 3, 5]]],
            ['X ATP A', 'IPAS', 'FPL', 2, [[2, 6, 7]]],
            ['X ATP A', 'IPAS', 'SJ', 4, [[2, 8, 11]]],
            ['X ATP A', 'Literasi', 'ESI', 1, [[4, 6, 6]]],
            ['X ATP A', 'MTK', 'SWP', 4, [[1, 8, 11]]],
            ['X ATP A', 'PAI', 'SS', 3, [[3, 5, 7]]],
            ['X ATP A', 'PKN', 'NH', 2, [[5, 7, 8]]],
            ['X ATP A', 'Penjas', 'SF', 3, [[4, 7, 9]]],
            ['X ATP A', 'Sejarah', 'PAT', 2, [[1, 2, 3]]],
            ['X ATP A', 'Senbud', 'KZ', 2, [[5, 3, 4]]],
            ['X ATP A', 'Simdig', 'RNF', 4, [[3, 8, 11]]],
            ['X ATP B', 'B. Ind', 'MIA', 4, [[3, 5, 8]]],
            ['X ATP B', 'B. Ing', 'MONA', 4, [[3, 1, 4]]],
            ['X ATP B', 'BK', 'HA', 2, [[5, 3, 4]]],
            ['X ATP B', 'BTA', 'QA', 2, [[1, 8, 9]]],
            ['X ATP B', 'Das. AT', 'AG', 3, [[4, 0, 2]]],
            ['X ATP B', 'Das. AT', 'HW', 3, [[4, 3, 5]]],
            ['X ATP B', 'Das. AT', 'RFD', 3, [[2, 0, 2]]],
            ['X ATP B', 'Das. AT', 'SR', 3, [[2, 3, 5]]],
            ['X ATP B', 'IPAS', 'FPL', 2, [[1, 2, 3]]],
            ['X ATP B', 'IPAS', 'SJ', 4, [[1, 4, 7]]],
            ['X ATP B', 'Literasi', 'ESI', 1, [[2, 10, 10]]],
            ['X ATP B', 'MTK', 'SWP', 4, [[4, 6, 9]]],
            ['X ATP B', 'PAI', 'SS', 3, [[3, 9, 11]]],
            ['X ATP B', 'PKN', 'NH', 2, [[5, 5, 6]]],
            ['X ATP B', 'Penjas', 'SF', 3, [[4, 10, 12]]],
            ['X ATP B', 'Sejarah', 'PAT', 2, [[1, 10, 11]]],
            ['X ATP B', 'Senbud', 'KZ', 2, [[5, 1, 2]]],
            ['X ATP B', 'Simdig', 'RNF', 4, [[2, 6, 9]]],
            ['X ATPH', 'B. Ind', 'MIA', 4, [[3, 9, 12]]],
            ['X ATPH', 'B. Ing', 'MONA', 4, [[1, 8, 11]]],
            ['X ATPH', 'BK', 'HA', 2, [[1, 2, 3]]],
            ['X ATPH', 'BTA', 'QA', 2, [[5, 5, 6]]],
            ['X ATPH', 'Das. AT', 'FF', 3, [[3, 0, 2]]],
            ['X ATPH', 'Das. AT', 'IR', 3, [[2, 0, 2]]],
            ['X ATPH', 'Das. AT', 'NT', 3, [[2, 3, 5]]],
            ['X ATPH', 'Das. AT', 'RA-1', 3, [[3, 3, 5]]],
            ['X ATPH', 'IPAS', 'FPL', 2, [[3, 6, 7]]],
            ['X ATPH', 'IPAS', 'SJ', 4, [[5, 1, 4]]],
            ['X ATPH', 'Literasi', 'YA', 1, [[2, 9, 9]]],
            ['X ATPH', 'MTK', 'SWP', 4, [[1, 4, 7]]],
            ['X ATPH', 'PAI', 'SS', 3, [[2, 6, 8]]],
            ['X ATPH', 'PKN', 'NH', 2, [[4, 10, 11]]],
            ['X ATPH', 'Penjas', 'SF', 3, [[4, 1, 3]]],
            ['X ATPH', 'Sejarah', 'PAT', 2, [[4, 8, 9]]],
            ['X ATPH', 'Senbud', 'KZ', 2, [[5, 7, 8]]],
            ['X ATPH', 'Simdig', 'SSS', 4, [[4, 4, 7]]],
            ['X APHP', 'B. Ind', 'MIA', 4, [[4, 9, 12]]],
            ['X APHP', 'B. Ing', 'MONA', 4, [[3, 5, 8]]],
            ['X APHP', 'BK', 'HA', 2, [[5, 1, 2]]],
            ['X APHP', 'BTA', 'QA', 2, [[5, 3, 4]]],
            ['X APHP', 'DPBHP', 'PAU', 4, [[2, 4, 7]]],
            ['X APHP', 'DPMHP', 'NTI', 4, [[2, 8, 11]]],
            ['X APHP', 'DPPHP', 'DAP', 4, [[2, 0, 3]]],
            ['X APHP', 'IPAS', 'FPL', 2, [[1, 6, 7]]],
            ['X APHP', 'IPAS', 'SJ', 4, [[1, 8, 11]]],
            ['X APHP', 'Literasi', 'YA', 1, [[3, 4, 4]]],
            ['X APHP', 'MTK', 'SWP', 4, [[4, 0, 3]]],
            ['X APHP', 'PAI', 'SS', 3, [[3, 1, 3]]],
            ['X APHP', 'PKN', 'NH', 2, [[4, 7, 8]]],
            ['X APHP', 'Penjas', 'SF', 3, [[4, 4, 6]]],
            ['X APHP', 'Sejarah', 'PAT', 2, [[3, 9, 10]]],
            ['X APHP', 'Senbud', 'KZ', 2, [[5, 5, 6]]],
            ['X APHP', 'Simdig', 'SSS', 4, [[1, 2, 5]]],
            ['XI ATP', 'Alsintan', 'DA', 4, [[4, 4, 7]]],
            ['XI ATP', 'B. Ind', 'EDI', 3, [[2, 5, 7]]],
            ['XI ATP', 'B. Ing', 'MONA', 4, [[2, 8, 11]]],
            ['XI ATP', 'BK', 'HA', 2, [[5, 5, 6]]],
            ['XI ATP', 'BTA', 'QA', 2, [[1, 6, 7]]],
            ['XI ATP', 'Literasi', 'ESI', 1, [[2, 1, 1]]],
            ['XI ATP', 'MTK', 'FHK', 3, [[2, 2, 4]]],
            ['XI ATP', 'P. Bibit', 'SR', 4, [[3, 4, 7]]],
            ['XI ATP', 'P. Siap Lahan', 'HW', 4, [[4, 8, 11]]],
            ['XI ATP', 'PAI', 'SS', 3, [[5, 1, 3]]],
            ['XI ATP', 'PKN', 'NH', 2, [[1, 2, 3]]],
            ['XI ATP', 'PKWU', 'SYR', 5, [[1, 8, 10], [3, 8, 9]]],
            ['XI ATP', 'Panen', 'SR', 4, [[4, 0, 3]]],
            ['XI ATP', 'Pemeliharaan', 'AG', 4, [[3, 0, 3]]],
            ['XI ATP', 'Pemeliharaan 2', 'RFD', 2, [[3, 10, 11]]],
            ['XI ATP', 'Penjas', 'SF', 2, [[5, 7, 8]]],
            ['XI ATP', 'Sejarah', 'RFD', 2, [[1, 4, 5]]],
            ['XI ATPH A', 'AT. Buah', 'AW', 3, [[4, 0, 2]]],
            ['XI ATPH A', 'AT. Hias', 'FF', 3, [[3, 8, 10]]],
            ['XI ATPH A', 'AT. Sayur', 'NT', 4, [[3, 0, 3]]],
            ['XI ATPH A', 'Alsintan', 'DA', 4, [[2, 4, 7]]],
            ['XI ATPH A', 'B. Ind', 'EDI', 3, [[4, 10, 12]]],
            ['XI ATPH A', 'B. Ing', 'USM', 4, [[1, 5, 6], [4, 6, 7]]],
            ['XI ATPH A', 'BK', 'HA', 2, [[5, 7, 8]]],
            ['XI ATPH A', 'BTA', 'QA', 2, [[4, 8, 9]]],
            ['XI ATPH A', 'Hidro', 'RA-2', 4, [[3, 4, 7]]],
            ['XI ATPH A', 'Kuljar', 'IR', 3, [[1, 2, 4]]],
            ['XI ATPH A', 'Literasi', 'YA', 1, [[2, 8, 8]]],
            ['XI ATPH A', 'MTK', 'FHK', 3, [[1, 10, 12]]],
            ['XI ATPH A', 'PAI', 'SS', 3, [[2, 9, 11]]],
            ['XI ATPH A', 'PKN', 'NH', 2, [[5, 3, 4]]],
            ['XI ATPH A', 'PKWU', 'RA-2', 2, [[4, 4, 5]]],
            ['XI ATPH A', 'Pangan', 'UMA', 4, [[2, 0, 3]]],
            ['XI ATPH A', 'Penjas', 'SF', 2, [[5, 1, 2]]],
            ['XI ATPH A', 'Sejarah', 'RFD', 2, [[1, 8, 9]]],
            ['XI ATPH B', 'AT. Buah', 'AW', 3, [[3, 0, 2]]],
            ['XI ATPH B', 'AT. Hias', 'UMA', 3, [[4, 4, 6]]],
            ['XI ATPH B', 'AT. Sayur', 'NT', 4, [[4, 0, 3]]],
            ['XI ATPH B', 'Alsintan', 'DA', 4, [[2, 0, 3]]],
            ['XI ATPH B', 'B. Ind', 'EDI', 3, [[1, 10, 12]]],
            ['XI ATPH B', 'B. Ing', 'USM', 4, [[3, 8, 9], [4, 10, 11]]],
            ['XI ATPH B', 'BK', 'HA', 2, [[1, 8, 9]]],
            ['XI ATPH B', 'BTA', 'QA', 2, [[2, 10, 11]]],
            ['XI ATPH B', 'Hidro', 'AW', 4, [[3, 4, 7]]],
            ['XI ATPH B', 'Kuljar', 'FF', 3, [[1, 5, 7]]],
            ['XI ATPH B', 'Literasi', 'YA', 1, [[3, 10, 10]]],
            ['XI ATPH B', 'MTK', 'FHK', 3, [[1, 2, 4]]],
            ['XI ATPH B', 'PAI', 'SS', 3, [[5, 5, 7]]],
            ['XI ATPH B', 'PKN', 'NH', 2, [[5, 1, 2]]],
            ['XI ATPH B', 'PKWU', 'RA-2', 2, [[2, 8, 9]]],
            ['XI ATPH B', 'Pangan', 'UMA', 4, [[2, 4, 7]]],
            ['XI ATPH B', 'Penjas', 'SF', 2, [[5, 3, 4]]],
            ['XI ATPH B', 'Sejarah', 'RFD', 2, [[4, 8, 9]]],
            ['XI APHP', 'Alsintan', 'PAU', 4, [[5, 1, 4]]],
            ['XI APHP', 'B. Ind', 'EDI', 3, [[2, 0, 2]]],
            ['XI APHP', 'B. Ing', 'MONA', 4, [[2, 3, 6]]],
            ['XI APHP', 'BK', 'HA', 2, [[2, 7, 8]]],
            ['XI APHP', 'BTA', 'QA', 2, [[5, 7, 8]]],
            ['XI APHP', 'Hewani', 'NTI', 5, [[4, 1, 5]]],
            ['XI APHP', 'Kempang', 'NTI', 2, [[4, 6, 7]]],
            ['XI APHP', 'Literasi', 'ESI', 1, [[1, 2, 2]]],
            ['XI APHP', 'MTK', 'FHK', 3, [[2, 9, 11]]],
            ['XI APHP', 'Nabati', 'DAP', 5, [[3, 0, 4]]],
            ['XI APHP', 'PAI', 'SS', 3, [[1, 3, 5]]],
            ['XI APHP', 'PH. Tan Bun', 'PAU', 5, [[4, 8, 12]]],
            ['XI APHP', 'PKN', 'NH', 2, [[1, 8, 9]]],
            ['XI APHP', 'PKWU', 'SWP', 2, [[3, 9, 10]]],
            ['XI APHP', 'Penjas', 'SF', 2, [[5, 5, 6]]],
            ['XI APHP', 'Penyegar', 'RYD', 4, [[3, 5, 8]]],
            ['XI APHP', 'Sejarah', 'RFD', 2, [[1, 6, 7]]],
        ];
    }

    public function run(): void
    {
        mt_srand(2026);
        $this->hash = Hash::make('rahasia123');

        Pengaturan::simpan(Pengaturan::BAWAAN);
        $ta = TahunAjaran::create(['nama' => '2026/2027', 'semester' => 'Ganjil', 'aktif' => true]);

        // ---------- Pengguna & data master ----------
        User::create(['name' => 'Admin Sekolah', 'username' => 'admin', 'role' => 'admin', 'password' => 'admin123', 'aktif' => true, 'terakhir_aktif_at' => now()]);

        $jurusan = [];
        foreach ([
            ['ATP', 'Agribisnis Tanaman Perkebunan'],
            ['ATPH', 'Agribisnis Tanaman Pangan dan Hortikultura'],
            ['APHP', 'Agribisnis Pengolahan Hasil Pertanian'],
        ] as [$kode, $nama]) {
            $jurusan[$kode] = Jurusan::create(['kode' => $kode, 'nama' => $nama]);
        }

        // Mapel: kunci = nama persis seperti di jadwal
        $mapel = [];
        foreach ($this->dataMapel() as [$kunci, $kode, $nama, $kelompok]) {
            $mapel[$kunci] = Mapel::create(['kode' => $kode, 'nama' => $nama, 'kelompok' => $kelompok]);
        }

        // Guru: satu akun per kode guru. Akun demo "guru" = SWP (guru Matematika kelas X).
        $kodeGuru = collect($this->dataBeban())->pluck(2)->unique()->sort()->values();
        $guru = [];
        User::unguarded(function () use ($kodeGuru, &$guru) {
            foreach ($kodeGuru as $kode) {
                $demo = $kode === 'SWP';
                $guru[$kode] = User::create([
                    'name' => "Guru $kode", 'username' => $demo ? 'guru' : Str::lower($kode), 'kode_guru' => $kode,
                    'role' => 'guru', 'password' => $demo ? 'guru123' : $this->hash, 'aktif' => true,
                    'terakhir_aktif_at' => now()->subHours(mt_rand(1, 40)),
                ]);
            }
        });

        // Rombel: [nama, tingkat, jurusan]. Wali kelas dipetakan ke guru. Akun demo "guru" (SWP) = wali kelas X ATP A.
        $waliMap = [
            'X ATP A' => 'SWP',
            'X ATP B' => 'AG',
            'X ATPH' => 'AW',
            'X APHP' => 'DA',
            'XI ATP' => 'DAP',
            'XI ATPH A' => 'EDI',
            'XI ATPH B' => 'ESI',
            'XI APHP' => 'FF',
        ];
        $kelasData = [
            ['X ATP A', 10, 'ATP'], ['X ATP B', 10, 'ATP'], ['X ATPH', 10, 'ATPH'], ['X APHP', 10, 'APHP'],
            ['XI ATP', 11, 'ATP'], ['XI ATPH A', 11, 'ATPH'], ['XI ATPH B', 11, 'ATPH'], ['XI APHP', 11, 'APHP'],
        ];
        $kelas = [];
        $siswaKelas = [];
        $nisn = 81230000;
        foreach ($kelasData as [$nama, $tingkat, $jur]) {
            $waliKode = $waliMap[$nama] ?? null;
            $k = Kelas::create([
                'nama' => $nama,
                'tingkat' => $tingkat,
                'jurusan_id' => $jurusan[$jur]->id,
                'wali_id' => $waliKode && isset($guru[$waliKode]) ? $guru[$waliKode]->id : null,
            ]);
            $kelas[$nama] = $k;
            // 24 siswa per kelas. Akun demo "siswa" ada di X ATP A.
            $daftar = [];
            for ($i = 0; $i < 24; $i++) {
                $jk = $i % 2 ? 'P' : 'L';
                $demo = $nama === 'X ATP A' && $i === 0;
                $daftar[] = User::create([
                    'name' => $demo ? 'Raka Aditya Pratama' : $this->namaAcak($jk),
                    'username' => $demo ? 'siswa' : '00' . ($nisn++), 'jk' => $jk, 'role' => 'siswa', 'kelas_id' => $k->id,
                    'password' => $demo ? 'siswa123' : $this->hash, 'aktif' => true,
                    'terakhir_aktif_at' => $demo ? now() : (mt_rand(1, 100) <= 7 ? null : now()->subMinutes(mt_rand(1, 100) <= 15 ? mt_rand(4400, 12000) : mt_rand(5, 1500))),
                ]);
            }
            $siswaKelas[$k->id] = collect($daftar)->sortBy('name')->values();
        }

        // ---------- Penjadwalan: slot waktu ----------
        $slot = []; // [hari][jam_ke] => [mulai, selesai]
        $slotRows = [];
        foreach ($this->dataSlot() as [$hari, $jamKe, $mulai, $selesai, $jenis]) {
            $slotRows[] = ['tahun_ajaran_id' => $ta->id, 'hari' => $hari, 'jam_ke' => $jamKe, 'jam_mulai' => $mulai, 'jam_selesai' => $selesai,
                'jenis' => $jenis, 'keterangan' => null, 'created_at' => now(), 'updated_at' => now()];
            if ($jamKe !== null && $jenis === 'kbm') $slot[$hari][$jamKe] = [$mulai, $selesai];
        }
        DB::table('slot_waktu')->insert($slotRows);

        // ---------- Penjadwalan: versi jadwal (hasil impor dari jadwal kertas) ----------
        $versiId = DB::table('versi_jadwal')->insertGetId([
            'tahun_ajaran_id' => $ta->id, 'nama' => 'Jadwal Ganjil 2026 Rev 31 Juli', 'status' => 'aktif', 'sumber' => 'impor',
            'status_solver' => null, 'hasil_solver' => null, 'dibuat_oleh' => User::where('username', 'admin')->value('id'),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // ---------- Ruang, beban ajar, dan jadwal ----------
        // Kelompokkan per (rombel, mapel) supaya satu ruang dipakai bersama oleh semua guru pengampunya.
        $grup = [];
        foreach ($this->dataBeban() as $b) {
            $grup[$b[0] . '|' . $b[1]][] = $b;
        }
        $semuaRuang = [];
        foreach ($grup as $baris) {
            [$namaKelas, $namaMapel] = $baris[0];
            // Pengampu utama LMS = guru dengan jam terbanyak (seri: yang pertama)
            $utama = collect($baris)->sortByDesc(fn ($x) => $x[3])->first()[2];
            $r = Ruang::create(['tahun_ajaran_id' => $ta->id, 'kelas_id' => $kelas[$namaKelas]->id, 'mapel_id' => $mapel[$namaMapel]->id,
                'guru_id' => $guru[$utama]->id, 'kkm' => 75]);
            $semuaRuang[] = $r;

            foreach ($baris as [, , $kode, $jam, $blokBlok]) {
                DB::table('beban_ajar')->insert(['ruang_id' => $r->id, 'guru_id' => $guru[$kode]->id, 'jam_per_minggu' => $jam,
                    'pola_blok' => json_encode(array_map(fn ($x) => $x[2] - $x[1] + 1, $blokBlok)), 'created_at' => now(), 'updated_at' => now()]);
                foreach ($blokBlok as [$hari, $a, $z]) {
                    DB::table('jadwal')->insert([
                        'versi_jadwal_id' => $versiId, 'ruang_id' => $r->id, 'guru_id' => $guru[$kode]->id, 'hari' => $hari,
                        'jam_ke_mulai' => $a, 'jam_ke_selesai' => $z, 'jam_mulai' => $slot[$hari][$a][0], 'jam_selesai' => $slot[$hari][$z][1],
                        'dikunci' => false,
                        'link_meet' => 'https://meet.google.com/' . substr(md5("a$r->id-$kode"), 0, 3) . '-' . substr(md5("b$r->id-$kode"), 0, 4) . '-' . substr(md5("c$r->id-$kode"), 0, 3),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }

        // ---------- Ruang demo: Matematika X ATP A ----------
        $mtk = Ruang::where('kelas_id', $kelas['X ATP A']->id)->where('mapel_id', $mapel['MTK']->id)->first();
        $mtk->update(['deskripsi' => "Selamat datang di kelas Matematika X ATP A.\n\nSetiap pertemuan: baca materi, tekan **Saya hadir** saat presensi dibuka, lalu kerjakan latihan. Tugas dikumpulkan paling lambat pukul 23.59 di hari tenggat.\n\nBuku pegangan: Matematika SMK Kelas X (Kurikulum Merdeka)."]);
        $this->isiMatematika($mtk, $siswaKelas[$mtk->kelas_id], $guru['SWP']);

        // ---------- Ruang lain: pertemuan ringan + presensi ----------
        $topikKhusus = [
            'PAI' => ['Iman kepada Allah SWT', 'Asmaul Husna', 'Perilaku jujur dalam kehidupan', 'Adab menuntut ilmu'],
            'PKN' => ['Nilai-nilai Pancasila', 'Hak dan kewajiban warga negara', 'Bhinneka Tunggal Ika'],
            'B. Ind' => ['Teks laporan hasil observasi', 'Teks eksposisi', 'Menulis surat lamaran kerja', 'Teks prosedur'],
            'B. Ing' => ['Self introduction', 'Describing people', 'Asking and giving opinion', 'Job interview'],
            'Sejarah' => ['Konsep dasar sejarah', 'Kerajaan Hindu-Buddha di Indonesia', 'Masuknya Islam ke Nusantara'],
            'Penjas' => ['Kebugaran jasmani', 'Teknik dasar bola voli', 'Pola hidup sehat'],
            'MTK' => ['Eksponen dan logaritma', 'Barisan dan deret', 'Persamaan kuadrat'],
        ];
        foreach ($semuaRuang as $r) {
            if ($r->id === $mtk->id) continue;
            $namaMapel = $r->mapel->nama;
            $topik = $topikKhusus[array_search($r->mapel_id, array_map(fn ($m) => $m->id, $mapel))]
                ?? ["Pengantar $namaMapel", "Konsep dasar $namaMapel", "Penerapan $namaMapel", "Latihan dan refleksi $namaMapel"];
            $sepi = $r->id % 7 === 0; // sebagian sengaja belum isi materi minggu ini (contoh pantauan admin)
            $jumlah = $sepi ? 1 : mt_rand(2, count($topik));
            foreach (array_slice($topik, 0, $jumlah) as $i => $judul) {
                $mundur = $sepi ? 15 : ($jumlah - 1 - $i) * 7 + ($r->id % 3 === 0 ? 0 : mt_rand(1, 3));
                $tgl = today()->subDays($mundur);
                $p = Pertemuan::create([
                    'ruang_id' => $r->id, 'urutan' => $i + 1, 'judul' => $judul, 'tanggal' => $tgl, 'terbit' => true,
                    'isi' => "Pada pertemuan ini kita mempelajari **$judul**.\n\nBacalah ringkasan materi berikut dengan saksama, lalu catat hal penting di buku tulis masing-masing. Jika ada yang belum jelas, tanyakan di tab Diskusi.\n\nTujuan pembelajaran:\n1. Memahami konsep $judul.\n2. Menerapkan konsep tersebut dalam contoh sehari-hari.\n3. Menyelesaikan latihan yang diberikan guru.",
                    'presensi_buka' => $tgl->copy()->setTime($mundur === 0 ? 0 : 7, 0), 'presensi_tutup' => $tgl->copy()->setTime($mundur === 0 ? 23 : 15, $mundur === 0 ? 59 : 0),
                ]);
                $this->isiPresensi($p, $siswaKelas[$r->kelas_id], mt_rand(78, 94));
                foreach ($siswaKelas[$r->kelas_id] as $s) {
                    if (mt_rand(1, 100) <= ($s->username === 'siswa' ? 75 : 60)) {
                        DB::table('materi_selesai')->insert(['pertemuan_id' => $p->id, 'siswa_id' => $s->id, 'selesai_at' => $tgl->copy()->setTime(mt_rand(8, 20), mt_rand(0, 59))]);
                    }
                }
            }
        }

        // ---------- Pengumuman ----------
        $admin = User::where('username', 'admin')->first();
        $umum = [
            [$admin, 'Jadwal Penilaian Tengah Semester (PTS) daring', 'semua', null, true, 2,
                "PTS semester ini dilaksanakan secara daring melalui menu Kuis di masing-masing ruang belajar.\n\nPastikan kuota internet cukup dan perangkat sudah dicoba sebelum hari pelaksanaan. Jadwal lengkap dibagikan oleh wali kelas."],
            [$admin, 'Rapat koordinasi evaluasi pembelajaran', 'guru', null, false, 4,
                'Rapat evaluasi pelaksanaan pembelajaran dan asesmen hari Jumat pukul 13.30 melalui Google Meet. Mohon setiap guru membawa rekap kehadiran siswa.'],
            [$guru['SWP'], 'Kuis SPLDV dibuka hari ini', 'siswa', $kelas['X ATP A']->id, false, 0,
                'Anak-anak, kuis Sistem Persamaan Linear Dua Variabel sudah dibuka di ruang Matematika. Waktu mengerjakan 30 menit. Selamat mengerjakan!'],
            [$admin, 'Libur memperingati hari besar nasional', 'semua', null, false, 9,
                'Kegiatan belajar daring diliburkan pada tanggal merah. Tugas yang tenggatnya jatuh pada hari libur berlaku sampai hari berikutnya.'],
        ];
        foreach ($umum as [$u, $judul, $sasaran, $kelasId, $pin, $hari, $isi]) {
            $p = Pengumuman::create(['user_id' => $u->id, 'judul' => $judul, 'sasaran' => $sasaran, 'kelas_id' => $kelasId, 'disematkan' => $pin, 'isi' => $isi]);
            $p->forceFill(['created_at' => now()->subDays($hari)->subHours($hari ? 0 : 3)])->save();
        }
    }

    /** Ruang demo: 6 pertemuan SPLDV + 3 tugas + 2 kuis + diskusi */
    private function isiMatematika(Ruang $r, $siswa, User $guru): void
    {
        $materi = [
            ['Pengertian Persamaan Linear Dua Variabel', "Persamaan linear dua variabel (PLDV) adalah persamaan yang memiliki dua variabel dengan pangkat tertinggi satu. Bentuk umumnya:\n\n**ax + by = c**, dengan a dan b tidak sama dengan nol.\n\nContoh: 2x + 3y = 12. Pasangan (3, 2) adalah salah satu penyelesaiannya karena 2(3) + 3(2) = 12.\n\nSatu PLDV memiliki tak hingga banyak penyelesaian. Jika digambar pada bidang Kartesius, himpunan penyelesaiannya membentuk garis lurus."],
            ['Sistem Persamaan Linear Dua Variabel (SPLDV)', "SPLDV adalah dua buah PLDV yang saling berkaitan dan memiliki satu penyelesaian yang sama.\n\nContoh:\nx + y = 7\nx - y = 1\n\nPenyelesaiannya adalah x = 4 dan y = 3, karena pasangan ini memenuhi kedua persamaan sekaligus.\n\nAda tiga kemungkinan banyaknya penyelesaian SPLDV: tepat satu (garis berpotongan), tidak ada (garis sejajar), atau tak hingga (garis berimpit)."],
            ['Metode Grafik', "Langkah menyelesaikan SPLDV dengan metode grafik:\n\n1. Tentukan titik potong tiap persamaan dengan sumbu X (y = 0) dan sumbu Y (x = 0).\n2. Gambar kedua garis pada bidang Kartesius yang sama.\n3. Titik potong kedua garis adalah penyelesaiannya.\n\nMetode ini cocok untuk memahami konsep, tetapi kurang teliti bila penyelesaiannya pecahan."],
            ['Metode Substitusi', "Langkah metode substitusi:\n\n1. Ubah salah satu persamaan menjadi bentuk x = ... atau y = ...\n2. Substitusikan ke persamaan yang lain sehingga tersisa satu variabel.\n3. Selesaikan, lalu substitusikan kembali untuk mencari variabel kedua.\n\nContoh: x + y = 10 dan 2x - y = 5. Dari persamaan pertama, y = 10 - x. Substitusi: 2x - (10 - x) = 5, sehingga 3x = 15 dan x = 5. Maka y = 5."],
            ['Metode Eliminasi dan Gabungan', "Metode eliminasi menghilangkan salah satu variabel dengan menjumlahkan atau mengurangkan kedua persamaan setelah koefisiennya disamakan.\n\nMetode gabungan (eliminasi-substitusi) paling sering dipakai: eliminasi untuk mencari variabel pertama, lalu substitusi untuk variabel kedua.\n\nContoh: 3x + 2y = 16 dan x + 2y = 8. Kurangkan: 2x = 8, x = 4. Substitusi ke x + 2y = 8: 2y = 4, y = 2."],
            ['Penerapan SPLDV dalam Kehidupan Sehari-hari', "Banyak masalah sehari-hari bisa dimodelkan dengan SPLDV, misalnya harga barang, umur, dan keuangan usaha.\n\nContoh: harga 2 bibit cabai dan 3 polybag adalah Rp41.000, sedangkan 1 bibit dan 5 polybag Rp34.500. Misalkan bibit = x dan polybag = y, maka:\n2x + 3y = 41.000\nx + 5y = 34.500\n\nSelesaikan dengan metode gabungan untuk mendapatkan harga masing-masing.\n\nHari ini presensi dibuka sampai pukul 23.59, dan Kuis 2 juga sudah dibuka."],
        ];
        foreach ($materi as $i => [$judul, $isi]) {
            $tgl = today()->subDays((5 - $i) * 7);
            $hariIni = $i === 5;
            $p = Pertemuan::create([
                'ruang_id' => $r->id, 'urutan' => $i + 1, 'judul' => $judul, 'isi' => $isi, 'tanggal' => $tgl, 'terbit' => true,
                'presensi_buka' => $tgl->copy()->setTime($hariIni ? 0 : 6, 0), 'presensi_tutup' => $tgl->copy()->setTime($hariIni ? 23 : 15, $hariIni ? 59 : 0),
            ]);
            if ($i === 0) {
                $path = "materi/ruang-{$r->id}/ringkasan-pldv.pdf";
                Storage::put($path, $this->pdfSederhana(['Ringkasan Materi: Persamaan Linear Dua Variabel', '', 'Bentuk umum: ax + by = c', 'Contoh: 2x + 3y = 12, salah satu penyelesaiannya (3, 2).', 'Satu PLDV memiliki tak hingga banyak penyelesaian.', 'Grafiknya berupa garis lurus pada bidang Kartesius.']));
                $p->update(['lampiran_path' => $path, 'lampiran_nama' => 'Ringkasan PLDV.pdf']);
            }
            // Pertemuan hari ini masih berjalan: baru sebagian presensi, demo siswa belum
            $this->isiPresensi($p, $siswa, $hariIni ? 45 : 90, $hariIni ? ['siswa'] : []);
            foreach ($siswa as $s) {
                if ($s->username === 'siswa' && $i >= 4) continue;
                if ($s->username === 'siswa' || mt_rand(1, 100) <= ($hariIni ? 20 : 80)) {
                    DB::table('materi_selesai')->insert(['pertemuan_id' => $p->id, 'siswa_id' => $s->id, 'selesai_at' => $tgl->copy()->setTime(mt_rand(8, 20), mt_rand(0, 59))]);
                }
            }
        }

        // ---------- Tugas ----------
        $tugas = [
            ['Latihan 1: Menentukan penyelesaian PLDV', 22, 2, 'Kerjakan latihan halaman 34 nomor 1-5. Tulis langkah pengerjaan dengan jelas, foto hasil tulisan tangan, lalu unggah dalam satu file PDF atau JPG.', 'lalu'],
            ['Latihan 2: Metode substitusi', 8, 4, "Selesaikan 5 soal SPLDV berikut dengan metode substitusi:\n1. x + y = 9 dan x - y = 3\n2. 2x + y = 11 dan x - y = 1\n3. x + 2y = 8 dan 3x - y = 3\n4. 4x + y = 14 dan x + y = 5\n5. 2x - 3y = 1 dan x + y = 8", 'lalu'],
            ['Proyek mini: Soal cerita SPLDV dari lingkungan sekitar', -5, 6, "Buat 2 soal cerita SPLDV dari kehidupan sehari-hari (misal harga barang di toko pertanian), lalu selesaikan dengan metode gabungan.\n\nBoleh dikumpulkan berupa teks atau file.", 'jalan'],
        ];
        foreach ($tugas as $n => [$judul, $hariLalu, $urutPertemuan, $instruksi, $status]) {
            $tenggat = today()->subDays($hariLalu)->setTime(23, 59);
            $t = Tugas::create(['ruang_id' => $r->id, 'pertemuan_id' => Pertemuan::where('ruang_id', $r->id)->where('urutan', $urutPertemuan)->value('id'),
                'judul' => $judul, 'instruksi' => $instruksi, 'tenggat' => $tenggat, 'jenis_kumpul' => $status === 'jalan' ? 'keduanya' : 'file', 'boleh_terlambat' => true]);
            foreach ($siswa as $s) {
                $demo = $s->username === 'siswa';
                if ($status === 'jalan') {
                    if (!$demo && mt_rand(1, 100) <= 35) {
                        Pengumpulan::create(['tugas_id' => $t->id, 'siswa_id' => $s->id, 'dikumpulkan_at' => now()->subHours(mt_rand(2, 60)), 'terlambat' => false,
                            'teks' => "Soal 1: Harga 3 cangkul dan 2 sabit Rp450.000, sedangkan 1 cangkul dan 1 sabit Rp175.000.\n3x + 2y = 450.000 dan x + y = 175.000\nDiperoleh cangkul Rp100.000 dan sabit Rp75.000."]);
                    }
                    continue;
                }
                if (!$demo && mt_rand(1, 100) <= 8) continue; // tidak mengumpulkan
                $terlambat = !$demo && mt_rand(1, 100) <= 10;
                $waktu = $terlambat ? $tenggat->copy()->addHours(mt_rand(2, 30)) : $tenggat->copy()->subHours(mt_rand(1, 96));
                $path = "tugas/{$t->id}/jawaban-{$s->id}.pdf";
                Storage::put($path, $this->pdfSederhana(['Jawaban ' . $judul, 'Nama: ' . $s->name, '', '1. x = 6, y = 3', '2. x = 4, y = 3', '3. x = 2, y = 3', '4. x = 3, y = 2', '5. x = 5, y = 3']));
                $dinilai = $n === 0 || $demo || mt_rand(1, 100) <= 70;
                Pengumpulan::create([
                    'tugas_id' => $t->id, 'siswa_id' => $s->id, 'file_path' => $path, 'file_nama' => 'jawaban-' . Str::slug($s->name) . '.pdf',
                    'dikumpulkan_at' => $waktu, 'terlambat' => $terlambat,
                    'nilai' => $dinilai ? ($demo ? [88, 92][$n] : mt_rand($terlambat ? 60 : 68, 98)) : null,
                    'komentar' => $dinilai && ($demo || mt_rand(1, 3) === 1) ? ['Langkah pengerjaan sudah runtut, pertahankan.', 'Bagus. Perhatikan penulisan tanda negatif.', 'Jawaban benar, tulisan lebih rapi lagi ya.'][mt_rand(0, 2)] : null,
                    'dinilai_at' => $dinilai ? $tenggat->copy()->addDays(2) : null,
                ]);
            }
        }

        // ---------- Kuis 1 (sudah selesai) ----------
        $k1 = Kuis::create(['ruang_id' => $r->id, 'judul' => 'Kuis 1: Konsep PLDV dan SPLDV', 'petunjuk' => 'Pilih satu jawaban yang paling tepat.',
            'mulai' => today()->subDays(13)->setTime(7, 0), 'selesai' => today()->subDays(13)->setTime(20, 0), 'durasi_menit' => 20, 'terbit' => true]);
        foreach ([
            ['Bentuk umum persamaan linear dua variabel adalah ...', ['A' => 'ax + by = c', 'B' => 'ax² + bx + c = 0', 'C' => 'y = mx²', 'D' => 'ax + b = 0'], 'A'],
            ['Pasangan berikut yang merupakan penyelesaian 2x + 3y = 12 adalah ...', ['A' => '(2, 3)', 'B' => '(3, 2)', 'C' => '(4, 2)', 'D' => '(1, 4)'], 'B'],
            ['Grafik himpunan penyelesaian PLDV berbentuk ...', ['A' => 'parabola', 'B' => 'lingkaran', 'C' => 'garis lurus', 'D' => 'titik'], 'C'],
            ['Jika x + y = 7 dan x - y = 1, nilai x adalah ...', ['A' => '3', 'B' => '4', 'C' => '5', 'D' => '6'], 'B'],
            ['SPLDV yang kedua garisnya sejajar memiliki ... penyelesaian.', ['A' => 'satu', 'B' => 'dua', 'C' => 'tak hingga', 'D' => 'tidak ada'], 'D'],
        ] as $i => [$tanya, $opsi, $kunci]) {
            $k1->soal()->create(['urutan' => $i + 1, 'jenis' => 'pg', 'pertanyaan' => $tanya, 'opsi' => $opsi, 'kunci' => $kunci, 'bobot' => 2]);
        }
        $k1->load('soal');
        foreach ($siswa as $s) {
            $demo = $s->username === 'siswa';
            if (!$demo && mt_rand(1, 100) <= 6) continue;
            $jawab = [];
            foreach ($k1->soal as $soal) {
                $benar = $demo ? $soal->urutan !== 5 : mt_rand(1, 100) <= ($soal->urutan === 5 ? 38 : 82);
                $salah = array_values(array_diff(array_keys($soal->opsi), [$soal->kunci]));
                $jawab[$soal->id] = $benar ? $soal->kunci : $salah[array_rand($salah)];
            }
            $mulai = $k1->mulai->copy()->addMinutes(mt_rand(30, 600));
            $p = Percobaan::create(['kuis_id' => $k1->id, 'siswa_id' => $s->id, 'mulai_at' => $mulai, 'batas_at' => $mulai->copy()->addMinutes(20),
                'selesai_at' => $mulai->copy()->addMinutes(mt_rand(7, 19)), 'urutan_soal' => ['soal' => $k1->soal->pluck('id')->shuffle()->all(), 'opsi' => []], 'jawaban' => $jawab]);
            $p->update(['nilai' => $p->hitungNilai()]);
        }

        // ---------- Kuis 2 (dibuka hari ini, ada esai) ----------
        $k2 = Kuis::create(['ruang_id' => $r->id, 'judul' => 'Kuis 2: Menyelesaikan SPLDV', 'petunjuk' => "Kerjakan 6 soal pilihan ganda dan 1 soal esai.\nGunakan kertas buram untuk menghitung.",
            'mulai' => today()->setTime(0, 0), 'selesai' => today()->setTime(23, 59), 'durasi_menit' => 30, 'terbit' => true]);
        foreach ([
            ['Penyelesaian dari x + y = 10 dan 2x - y = 5 adalah ...', ['A' => 'x = 5, y = 5', 'B' => 'x = 4, y = 6', 'C' => 'x = 6, y = 4', 'D' => 'x = 3, y = 7'], 'A'],
            ['Dari 3x + 2y = 16 dan x + 2y = 8, nilai x adalah ...', ['A' => '2', 'B' => '3', 'C' => '4', 'D' => '6'], 'C'],
            ['Metode yang menghilangkan salah satu variabel dengan menyamakan koefisien disebut ...', ['A' => 'grafik', 'B' => 'substitusi', 'C' => 'eliminasi', 'D' => 'determinan'], 'C'],
            ['Jika 2x + y = 11 dan x - y = 1, maka nilai x + y adalah ...', ['A' => '5', 'B' => '7', 'C' => '9', 'D' => '11'], 'B'],
            ['Harga 2 buku dan 1 pensil Rp13.000, harga 1 buku dan 1 pensil Rp8.000. Harga 1 buku adalah ...', ['A' => 'Rp3.000', 'B' => 'Rp4.000', 'C' => 'Rp5.000', 'D' => 'Rp6.000'], 'C'],
            ['Langkah pertama metode substitusi adalah ...', ['A' => 'menggambar grafik', 'B' => 'menyatakan satu variabel dalam variabel lain', 'C' => 'menjumlahkan kedua persamaan', 'D' => 'mengalikan kedua persamaan'], 'B'],
        ] as $i => [$tanya, $opsi, $kunci]) {
            $k2->soal()->create(['urutan' => $i + 1, 'jenis' => 'pg', 'pertanyaan' => $tanya, 'opsi' => $opsi, 'kunci' => $kunci, 'bobot' => 2]);
        }
        $esai = $k2->soal()->create(['urutan' => 7, 'jenis' => 'esai', 'bobot' => 8,
            'pertanyaan' => "Harga 2 bibit cabai dan 3 polybag adalah Rp41.000, sedangkan 1 bibit dan 5 polybag Rp34.500.\nTentukan harga 1 bibit dan 1 polybag. Tuliskan langkah penyelesaiannya!"]);
        $k2->load('soal');
        $nomorCoba = 0;
        foreach ($siswa->where("username", "!=", "siswa")->take(11) as $s) {
            $jawab = [];
            foreach ($k2->soal as $soal) {
                if ($soal->jenis === 'esai') {
                    $jawab[$soal->id] = "Misal bibit = x, polybag = y.\n2x + 3y = 41.000\nx + 5y = 34.500, dikali 2: 2x + 10y = 69.000\nEliminasi: 7y = 28.000, y = 4.000\nx = 34.500 - 20.000 = 14.500\nJadi bibit Rp14.500 dan polybag Rp4.000.";
                    continue;
                }
                $salah = array_values(array_diff(array_keys($soal->opsi), [$soal->kunci]));
                $jawab[$soal->id] = mt_rand(1, 100) <= 78 ? $soal->kunci : $salah[array_rand($salah)];
            }
            $mulai = now()->subMinutes(mt_rand(40, 200))->max($k2->mulai);
            $p = Percobaan::create(['kuis_id' => $k2->id, 'siswa_id' => $s->id, 'mulai_at' => $mulai, 'batas_at' => $mulai->copy()->addMinutes(30),
                'selesai_at' => $mulai->copy()->addMinutes(mt_rand(12, 28))->min(now()), 'urutan_soal' => ['soal' => $k2->soal->pluck('id')->all(), 'opsi' => []], 'jawaban' => $jawab]);
            // 8 percobaan pertama sudah dikoreksi guru, sisanya menunggu koreksi esai
            if ($nomorCoba++ < 8) $p->nilai_esai = [$esai->id => mt_rand(4, 8)];
            $p->nilai = $p->hitungNilai();
            $p->save();
        }

        // ---------- Diskusi ----------
        $tanya = [
            [$siswa[3], 'Bu, untuk metode eliminasi, kalau koefisiennya tidak ada yang sama boleh dikali dua-duanya?', [[$guru, 'Boleh. Kalikan kedua persamaan dengan bilangan yang membuat koefisien salah satu variabel sama, misalnya memakai KPK-nya.'], [$siswa[7], 'Terima kasih Bu, jadi lebih paham.']]],
            [$siswa[0], 'Bu, tugas latihan 2 boleh dikumpulkan dalam bentuk foto JPG?', [[$guru, 'Boleh, asalkan tulisannya terbaca jelas. Kalau lebih dari satu foto, gabungkan jadi PDF ya.']]],
            [$siswa[10], 'Untuk proyek mini, soal ceritanya boleh dari harga di warung dekat rumah?', []],
        ];
        foreach ($tanya as $j => [$penanya, $isi, $balas]) {
            $waktu = now()->subDays(6 - $j * 2);
            $d = Diskusi::create(['ruang_id' => $r->id, 'user_id' => $penanya->id, 'isi' => $isi]);
            $d->forceFill(['created_at' => $waktu])->save();
            foreach ($balas as $b => [$u, $teks]) {
                $x = Diskusi::create(['ruang_id' => $r->id, 'user_id' => $u->id, 'parent_id' => $d->id, 'isi' => $teks]);
                $x->forceFill(['created_at' => $waktu->copy()->addHours(2 + $b * 3)])->save();
            }
        }
    }

    /** Nama siswa contoh (tanpa library Faker agar seeder jalan dengan composer install --no-dev) */
    private function namaAcak(string $jk): string
    {
        $depanL = ['Aditya', 'Bagas', 'Dimas', 'Fajar', 'Galih', 'Hafiz', 'Ilham', 'Joko', 'Kevin', 'Lutfi', 'Muhammad', 'Naufal', 'Rizky', 'Satria', 'Taufik', 'Wahyu', 'Yusuf', 'Zaki', 'Arif', 'Bima', 'Dafa', 'Eko', 'Farhan', 'Gilang', 'Hendra', 'Irfan', 'Rangga', 'Rafi', 'Andika', 'Putra'];
        $depanP = ['Aisyah', 'Bunga', 'Citra', 'Dewi', 'Elsa', 'Fitri', 'Gita', 'Hana', 'Indah', 'Kirana', 'Laila', 'Maya', 'Nabila', 'Putri', 'Rahma', 'Salsabila', 'Tiara', 'Wulan', 'Yuni', 'Zahra', 'Anisa', 'Dinda', 'Intan', 'Nadia', 'Ratna', 'Sekar', 'Vina', 'Amelia', 'Kartika', 'Syifa'];
        $belakang = ['Pratama', 'Saputra', 'Wijaya', 'Nugroho', 'Setiawan', 'Hidayat', 'Kurniawan', 'Ramadhan', 'Santoso', 'Firmansyah', 'Lestari', 'Rahmawati', 'Anggraini', 'Wulandari', 'Permata', 'Sari', 'Maharani', 'Puspitasari', 'Hakim', 'Susanto', 'Purnama', 'Utami', 'Siregar', 'Nasution', 'Simanjuntak', 'Harahap', 'Gunawan', 'Prasetyo', 'Handayani', 'Kusuma'];
        $depan = $jk === 'L' ? $depanL : $depanP;
        return $depan[mt_rand(0, count($depan) - 1)] . ' ' . $belakang[mt_rand(0, count($belakang) - 1)];
    }

    /** Isi presensi acak; $kecuali = username yang sengaja dikosongkan */
    private function isiPresensi(Pertemuan $p, $siswa, int $persenHadir, array $kecuali = []): void
    {
        $tutup = $p->presensi_tutup->isPast();
        $berjalan = max(6, (int) $p->presensi_buka->diffInMinutes(now(), true));
        foreach ($siswa as $s) {
            if (in_array($s->username, $kecuali, true)) continue;
            $acak = mt_rand(1, 100);
            if ($acak <= $persenHadir) {
                Presensi::create(['pertemuan_id' => $p->id, 'siswa_id' => $s->id, 'status' => 'hadir',
                    'waktu' => $p->presensi_buka->copy()->addMinutes(mt_rand(5, $tutup ? 300 : min(300, $berjalan)))]);
            } elseif ($tutup && $acak <= $persenHadir + 5) {
                Presensi::create(['pertemuan_id' => $p->id, 'siswa_id' => $s->id, 'status' => $acak % 2 ? 'izin' : 'sakit',
                    'keterangan' => $acak % 2 ? 'Acara keluarga' : 'Demam, surat dari orang tua', 'waktu' => $p->presensi_buka, 'oleh_guru' => true]);
            }
        }
    }

    /** PDF satu halaman berisi baris teks (untuk lampiran contoh, tanpa library tambahan) */
    private function pdfSederhana(array $baris): string
    {
        $teks = "BT /F1 13 Tf 60 780 Td 18 TL\n";
        foreach ($baris as $l) $teks .= '(' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $l) . ") Tj T*\n";
        $teks .= 'ET';
        $obj = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length ' . strlen($teks) . " >>\nstream\n$teks\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $posisi = [];
        foreach ($obj as $i => $o) {
            $posisi[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n$o\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($obj) + 1) . "\n0000000000 65535 f \n";
        foreach ($posisi as $o) $pdf .= sprintf("%010d 00000 n \n", $o);
        return $pdf . "trailer\n<< /Size " . (count($obj) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    }
}
