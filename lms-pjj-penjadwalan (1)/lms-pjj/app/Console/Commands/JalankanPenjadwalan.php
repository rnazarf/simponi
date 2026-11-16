<?php

namespace App\Console\Commands;

use App\Models\TahunAjaran;
use App\Services\PenjadwalanSolver;
use Illuminate\Console\Command;

/**
 * php artisan penjadwalan:solve 5      -> hitung versi jadwal nomor 5 (dibuat dari halaman Jadwal Otomatis)
 * php artisan penjadwalan:solve        -> buat versi baru untuk tahun ajaran aktif lalu hitung (untuk uji lewat terminal)
 */
class JalankanPenjadwalan extends Command
{
    protected $signature = 'penjadwalan:solve {versi? : ID versi_jadwal} {--waktu=120 : batas waktu hitung (detik)}';
    protected $description = 'Susun jadwal pelajaran dengan solver OR-Tools';

    public function handle(PenjadwalanSolver $solver): int
    {
        $id = $this->argument('versi');
        if (! $id) {
            $ta = TahunAjaran::aktifId();
            if (! $ta) {
                $this->error('Belum ada tahun ajaran aktif.');
                return self::FAILURE;
            }
            $id = $solver->buatDraf($ta, 'Draf terminal ' . now()->format('d M H:i'), (int) $this->option('waktu'), null)->id;
        }
        $this->info("Menghitung versi #$id ...");
        $solver->jalankan((int) $id);
        $versi = \App\Models\VersiJadwal::find($id);
        $this->line('Status: ' . $versi->status_solver);
        $this->line(json_encode($versi->hasil_solver['metrik'] ?? $versi->hasil_solver, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $versi->status_solver === 'selesai' ? self::SUCCESS : self::FAILURE;
    }
}
