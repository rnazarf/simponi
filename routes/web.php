<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BerkasController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiskusiController;
use App\Http\Controllers\KuisController;
use App\Http\Controllers\PengumumanController;
use App\Http\Controllers\PertemuanController;
use App\Http\Controllers\RuangController;
use App\Http\Controllers\TugasController;
use App\Http\Controllers\WaliKelasController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'form'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware(['guest', 'throttle:10,1']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth', 'role'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::get('/jadwal', [DashboardController::class, 'jadwal'])->name('jadwal');
    Route::get('/profil', [AuthController::class, 'profil'])->name('profil');
    Route::post('/profil', [AuthController::class, 'gantiPassword']);

    Route::get('/pengumuman', [PengumumanController::class, 'index'])->name('pengumuman');
    Route::post('/pengumuman', [PengumumanController::class, 'store'])->middleware('role:admin,guru');
    Route::delete('/pengumuman/{pengumuman}', [PengumumanController::class, 'destroy'])->name('pengumuman.hapus')->middleware('role:admin,guru');

    // Ruang belajar (mapel di kelas). Hak akses dicek per ruang: guru pengampu, siswa kelas tsb, atau admin.
    Route::get('/ruang', [RuangController::class, 'index'])->name('ruang');
    Route::prefix('ruang/{ruang}')->middleware('can:akses,ruang')->group(function () {
        Route::get('/', [RuangController::class, 'show'])->name('ruang.show');
        Route::get('/anggota', [RuangController::class, 'anggota'])->name('ruang.anggota');
        Route::get('/presensi', [RuangController::class, 'presensi'])->name('ruang.presensi');
        Route::get('/nilai', [RuangController::class, 'nilai'])->name('ruang.nilai');
        Route::get('/nilai/csv', [RuangController::class, 'nilaiCsv'])->name('ruang.nilai.csv')->middleware('role:admin,guru');
        Route::get('/diskusi', [DiskusiController::class, 'index'])->name('ruang.diskusi');
        Route::post('/diskusi', [DiskusiController::class, 'store']);
        Route::delete('/diskusi/{diskusi}', [DiskusiController::class, 'destroy'])->name('diskusi.hapus');

        Route::middleware('role:admin,guru')->group(function () {
            Route::get('/pengaturan', [RuangController::class, 'pengaturan'])->name('ruang.pengaturan');
            Route::post('/pengaturan', [RuangController::class, 'simpanPengaturan']);

            Route::get('/pertemuan/baru', [PertemuanController::class, 'create'])->name('pertemuan.create');
            Route::post('/pertemuan', [PertemuanController::class, 'store'])->name('pertemuan.store');
            Route::get('/pertemuan/{pertemuan}/ubah', [PertemuanController::class, 'edit'])->name('pertemuan.edit');
            Route::put('/pertemuan/{pertemuan}', [PertemuanController::class, 'update'])->name('pertemuan.update');
            Route::delete('/pertemuan/{pertemuan}', [PertemuanController::class, 'destroy'])->name('pertemuan.destroy');
            Route::post('/pertemuan/{pertemuan}/presensi', [PertemuanController::class, 'aturPresensi'])->name('pertemuan.presensi');

            Route::get('/tugas/baru', [TugasController::class, 'create'])->name('tugas.create');
            Route::post('/tugas', [TugasController::class, 'store'])->name('tugas.store');
            Route::get('/tugas/{tugas}/ubah', [TugasController::class, 'edit'])->name('tugas.edit');
            Route::put('/tugas/{tugas}', [TugasController::class, 'update'])->name('tugas.update');
            Route::delete('/tugas/{tugas}', [TugasController::class, 'destroy'])->name('tugas.destroy');
            Route::post('/tugas/{tugas}/nilai/{pengumpulan}', [TugasController::class, 'nilai'])->name('tugas.nilai');

            Route::get('/kuis/baru', [KuisController::class, 'create'])->name('kuis.create');
            Route::post('/kuis', [KuisController::class, 'store'])->name('kuis.store');
            Route::get('/kuis/{kuis}/ubah', [KuisController::class, 'edit'])->name('kuis.edit');
            Route::put('/kuis/{kuis}', [KuisController::class, 'update'])->name('kuis.update');
            Route::delete('/kuis/{kuis}', [KuisController::class, 'destroy'])->name('kuis.destroy');
            Route::post('/kuis/{kuis}/soal', [KuisController::class, 'simpanSoal'])->name('kuis.soal');
            Route::post('/kuis/{kuis}/impor', [KuisController::class, 'imporSoal'])->name('kuis.impor');
            Route::post('/kuis/{kuis}/ai-generate', [KuisController::class, 'generateAiSoal'])->name('kuis.ai.generate');
            Route::post('/kuis/{kuis}/ai-simpan', [KuisController::class, 'simpanAiSoal'])->name('kuis.ai.simpan');
            Route::delete('/kuis/{kuis}/soal/{soal}', [KuisController::class, 'hapusSoal'])->name('kuis.soal.hapus');
            Route::get('/kuis/{kuis}/koreksi/{percobaan}', [KuisController::class, 'koreksi'])->name('kuis.koreksi');
            Route::post('/kuis/{kuis}/koreksi/{percobaan}', [KuisController::class, 'simpanKoreksi']);
            Route::delete('/kuis/{kuis}/percobaan/{percobaan}', [KuisController::class, 'resetPercobaan'])->name('kuis.reset');
        });

        Route::get('/pertemuan/{pertemuan}', [PertemuanController::class, 'show'])->name('pertemuan.show');
        Route::get('/tugas', [TugasController::class, 'index'])->name('tugas.index');
        Route::get('/tugas/{tugas}', [TugasController::class, 'show'])->name('tugas.show');
        Route::get('/kuis', [KuisController::class, 'index'])->name('kuis.index');
        Route::get('/kuis/{kuis}', [KuisController::class, 'show'])->name('kuis.show');

        Route::middleware('role:siswa')->group(function () {
            Route::post('/pertemuan/{pertemuan}/hadir', [PertemuanController::class, 'hadir'])->name('pertemuan.hadir');
            Route::post('/pertemuan/{pertemuan}/selesai', [PertemuanController::class, 'selesai'])->name('pertemuan.selesai');
            Route::post('/tugas/{tugas}/kumpul', [TugasController::class, 'kumpul'])->name('tugas.kumpul');
            Route::post('/kuis/{kuis}/mulai', [KuisController::class, 'mulai'])->name('kuis.mulai');
            Route::get('/kuis/{kuis}/kerjakan', [KuisController::class, 'kerjakan'])->name('kuis.kerjakan');
            Route::post('/kuis/{kuis}/jawab', [KuisController::class, 'jawab'])->name('kuis.jawab');
            Route::post('/kuis/{kuis}/pelanggaran', [KuisController::class, 'catatPelanggaran'])->name('kuis.pelanggaran');
            Route::post('/kuis/{kuis}/kumpul', [KuisController::class, 'kumpul'])->name('kuis.kumpul');
        });

        Route::get('/berkas/pertemuan/{pertemuan}', [BerkasController::class, 'pertemuan'])->name('berkas.pertemuan');
        Route::get('/berkas/tugas/{tugas}', [BerkasController::class, 'tugas'])->name('berkas.tugas');
        Route::get('/berkas/kumpulan/{pengumpulan}', [BerkasController::class, 'pengumpulan'])->name('berkas.pengumpulan');
    });

    // Modul Wali Kelas (Khusus Guru Wali Kelas & Admin)
    Route::middleware('role:guru,admin')->prefix('wali')->name('wali.')->group(function () {
        Route::get('/', [WaliKelasController::class, 'index'])->name('index');
        Route::get('/presensi', [WaliKelasController::class, 'presensi'])->name('presensi');
        Route::get('/tugas', [WaliKelasController::class, 'tugas'])->name('tugas');
        Route::get('/nilai', [WaliKelasController::class, 'nilai'])->name('nilai');
        Route::get('/catatan', [WaliKelasController::class, 'catatan'])->name('catatan');
        Route::post('/catatan', [WaliKelasController::class, 'simpanCatatan'])->name('catatan.simpan');
        Route::delete('/catatan/{catatan}', [WaliKelasController::class, 'hapusCatatan'])->name('catatan.hapus');
        Route::get('/siswa/{siswa}', [WaliKelasController::class, 'siswa'])->name('siswa');
    });

    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::get('/sekolah', [Admin\SekolahController::class, 'index'])->name('sekolah');
        Route::post('/sekolah', [Admin\SekolahController::class, 'simpan']);
        Route::post('/tahun-ajaran', [Admin\SekolahController::class, 'tambahTahun'])->name('tahun.store');
        Route::post('/tahun-ajaran/{tahun}/aktif', [Admin\SekolahController::class, 'aktifkanTahun'])->name('tahun.aktif');

        Route::get('/jurusan', [Admin\MasterController::class, 'jurusan'])->name('jurusan');
        Route::post('/jurusan', [Admin\MasterController::class, 'simpanJurusan']);
        Route::delete('/jurusan/{jurusan}', [Admin\MasterController::class, 'hapusJurusan'])->name('jurusan.hapus');
        Route::get('/mapel', [Admin\MasterController::class, 'mapel'])->name('mapel');
        Route::post('/mapel', [Admin\MasterController::class, 'simpanMapel']);
        Route::delete('/mapel/{mapel}', [Admin\MasterController::class, 'hapusMapel'])->name('mapel.hapus');
        Route::get('/kelas', [Admin\MasterController::class, 'kelas'])->name('kelas');
        Route::post('/kelas', [Admin\MasterController::class, 'simpanKelas']);
        Route::delete('/kelas/{kelas}', [Admin\MasterController::class, 'hapusKelas'])->name('kelas.hapus');

        Route::get('/pengguna/{role}', [Admin\PenggunaController::class, 'index'])->name('pengguna')->whereIn('role', ['guru', 'siswa']);
        Route::post('/pengguna/{role}', [Admin\PenggunaController::class, 'simpan'])->whereIn('role', ['guru', 'siswa']);
        Route::post('/pengguna/{role}/impor', [Admin\PenggunaController::class, 'impor'])->name('pengguna.impor')->whereIn('role', ['guru', 'siswa']);
        Route::get('/pengguna/{role}/contoh-csv', [Admin\PenggunaController::class, 'contohCsv'])->name('pengguna.contoh')->whereIn('role', ['guru', 'siswa']);
        Route::post('/pengguna/{user}/reset', [Admin\PenggunaController::class, 'resetPassword'])->name('pengguna.reset');
        Route::delete('/pengguna/{user}', [Admin\PenggunaController::class, 'hapus'])->name('pengguna.hapus');

        Route::get('/ruang', [Admin\RuangController::class, 'index'])->name('ruang');
        Route::post('/ruang', [Admin\RuangController::class, 'simpan']);
        Route::delete('/ruang/{ruang}', [Admin\RuangController::class, 'hapus'])->name('ruang.hapus');

        // Penjadwalan mata pelajaran (solver OR-Tools)
        Route::get('/penjadwalan', [Admin\PenjadwalanController::class, 'index'])->name('penjadwalan');
        Route::post('/penjadwalan', [Admin\PenjadwalanController::class, 'buat'])->name('penjadwalan.buat');
        Route::get('/penjadwalan/versi/{versi}', [Admin\PenjadwalanController::class, 'lihat'])->name('penjadwalan.lihat');
        Route::post('/penjadwalan/versi/{versi}/aktifkan', [Admin\PenjadwalanController::class, 'aktifkan'])->name('penjadwalan.aktifkan');
        Route::delete('/penjadwalan/versi/{versi}', [Admin\PenjadwalanController::class, 'hapus'])->name('penjadwalan.hapus');
        Route::post('/penjadwalan/blok/{jadwal}/pindah', [Admin\PenjadwalanController::class, 'pindah'])->name('penjadwalan.pindah');
        Route::post('/penjadwalan/blok/{jadwal}/kunci', [Admin\PenjadwalanController::class, 'kunci'])->name('penjadwalan.kunci');

        Route::get('/penjadwalan/jam', [Admin\JamController::class, 'index'])->name('penjadwalan.jam');
        Route::post('/penjadwalan/jam', [Admin\JamController::class, 'simpan'])->name('penjadwalan.jam.simpan');
        Route::post('/penjadwalan/jam/salin', [Admin\JamController::class, 'salin'])->name('penjadwalan.jam.salin');
        Route::delete('/penjadwalan/jam/{slot}', [Admin\JamController::class, 'hapus'])->name('penjadwalan.jam.hapus');

        Route::get('/penjadwalan/beban', [Admin\BebanController::class, 'index'])->name('penjadwalan.beban');
        Route::post('/penjadwalan/beban', [Admin\BebanController::class, 'simpan'])->name('penjadwalan.beban.simpan');
        Route::delete('/penjadwalan/beban/{beban}', [Admin\BebanController::class, 'hapus'])->name('penjadwalan.beban.hapus');

        Route::get('/penjadwalan/ketersediaan', [Admin\KetersediaanController::class, 'index'])->name('penjadwalan.ketersediaan');
        Route::post('/penjadwalan/ketersediaan/{guru}', [Admin\KetersediaanController::class, 'simpan'])->name('penjadwalan.ketersediaan.simpan');

        Route::get('/rekap/presensi', [Admin\RekapController::class, 'presensi'])->name('rekap.presensi');
        Route::get('/rekap/nilai', [Admin\RekapController::class, 'nilai'])->name('rekap.nilai');
        Route::get('/rekap/keaktifan', [Admin\RekapController::class, 'keaktifan'])->name('rekap.keaktifan');
    });
});
