<?php

namespace App\Jobs;

use App\Services\PenjadwalanSolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

/** Jalankan worker: php artisan queue:work --timeout=1900 */
class JalankanSolverPenjadwalan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 1900;
    public int $tries = 1;

    public function __construct(public int $versiId)
    {
    }

    public function handle(PenjadwalanSolver $solver): void
    {
        $solver->jalankan($this->versiId);
    }

    public function failed(\Throwable $e): void
    {
        $lama = json_decode(DB::table('versi_jadwal')->where('id', $this->versiId)->value('hasil_solver') ?? 'null', true) ?: [];
        DB::table('versi_jadwal')->where('id', $this->versiId)->update([
            'status_solver' => 'gagal', 'updated_at' => now(),
            'hasil_solver' => json_encode(($lama['parameter'] ?? null ? ['parameter' => $lama['parameter']] : []) + ['status' => 'GALAT', 'galat' => mb_substr($e->getMessage(), 0, 800)], JSON_UNESCAPED_UNICODE),
        ]);
    }
}
