<?php

namespace App\Services;

use App\Models\BebanAjar;
use App\Models\Jadwal;
use App\Models\KetersediaanGuru;
use App\Models\Ruang;
use App\Models\SlotWaktu;
use App\Models\VersiJadwal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Jembatan Laravel <-> solver OR-Tools (folder /solver di root proyek).
 *
 *   ekspor tabel -> input.json -> python solver.py -> output.json -> impor ke tabel jadwal
 *
 * Juga berisi pemeriksaan data sebelum hitung, pemeriksaan bentrok, pemindahan blok manual, dan penyalinan
 * data penjadwalan saat membuat tahun ajaran baru.
 */
class PenjadwalanSolver
{
    // ------------------------------------------------------------------ memulai

    /** Buat satu versi draf yang siap dihitung solver */
    public function buatDraf(int $taId, string $nama, int $batasWaktu, ?int $userId): VersiJadwal
    {
        return VersiJadwal::create([
            'tahun_ajaran_id' => $taId, 'nama' => $nama, 'status' => 'draf', 'sumber' => 'solver',
            'status_solver' => 'menunggu', 'dibuat_oleh' => $userId,
            'hasil_solver' => ['parameter' => ['waktu' => $batasWaktu]],
        ]);
    }

    /**
     * Mulai perhitungan. Mode 'queue' (bawaan) memakai antrean Laravel dan butuh `php artisan queue:work`.
     * Mode 'proses' menjalankan `php artisan penjadwalan:solve` sebagai proses latar belakang tanpa worker.
     */
    public function mulai(int $versiId): void
    {
        if (config('services.solver.mode', 'queue') === 'proses') {
            try {
                $this->mulaiLatar($versiId);
            } catch (\Throwable $e) {
                $this->tandai($versiId, 'gagal', ['status' => 'GAGAL_MULAI', 'galat' => mb_substr($e->getMessage(), 0, 500)]);
            }
            return;
        }
        \App\Jobs\JalankanSolverPenjadwalan::dispatch($versiId);
    }

    private function mulaiLatar(int $versiId): void
    {
        $php = config('services.solver.php');
        if (! $php) {
            $php = preg_match('/^php/i', basename(PHP_BINARY)) ? PHP_BINARY : null;
        }
        if (! $php) { // XAMPP: PHP_BINARY menunjuk httpd.exe, php.exe ada di C:\xampp\php
            $kandidat = dirname(PHP_BINARY, 3) . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'php.exe';
            $php = is_file($kandidat) ? $kandidat : 'php';
        }
        $perintah = escapeshellarg($php) . ' artisan penjadwalan:solve ' . $versiId;
        chdir(base_path());
        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen('start /B "" ' . $perintah . ' > NUL 2>&1', 'r'));
        } else {
            exec($perintah . ' > /dev/null 2>&1 &');
        }
    }

    // ------------------------------------------------------------------ menjalankan solver

    public function jalankan(int $versiId): void
    {
        @set_time_limit(0);
        $versi = VersiJadwal::findOrFail($versiId);
        $this->tandai($versiId, 'berjalan');

        $dir = storage_path("app/solver/$versiId");
        File::ensureDirectoryExists($dir);
        $in = "$dir/input.json";
        $out = "$dir/output.json";
        @unlink($out);
        file_put_contents($in, json_encode($this->ekspor($versi), JSON_UNESCAPED_UNICODE));

        $proses = Process::timeout(1800)->path(base_path('solver'))
            ->run([config('services.solver.python') ?: (PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3'), 'solver.py', $in, $out]);

        $hasil = is_file($out) ? json_decode(file_get_contents($out), true) : null;
        if (! $hasil || empty($hasil['jadwal'])) {
            $this->tandai($versiId, 'gagal', [
                'status' => $hasil['status'] ?? 'TIDAK_ADA_OUTPUT',
                'pelanggaran_keras' => array_slice($hasil['pelanggaran_keras'] ?? [], 0, 20),
                'galat' => mb_substr($proses->errorOutput(), -1500),
            ]);
            return;
        }
        $this->impor($versi, $hasil);
    }

    /** Data masukan solver: hanya jam KBM, beban ajar tahun ajaran ini, ketersediaan guru, dan blok terkunci versi aktif. */
    private function ekspor(VersiJadwal $versi): array
    {
        $ta = $versi->tahun_ajaran_id;

        $slots = SlotWaktu::where('tahun_ajaran_id', $ta)->kbm()->get(['hari', 'jam_ke'])
            ->map(fn ($s) => [(int) $s->hari, (int) $s->jam_ke])->all();

        $beban = DB::table('beban_ajar as b')->join('ruang as r', 'r.id', '=', 'b.ruang_id')
            ->where('r.tahun_ajaran_id', $ta)
            ->get(['b.ruang_id', 'r.kelas_id', 'b.guru_id', 'b.jam_per_minggu', 'b.pola_blok'])
            ->map(fn ($b) => [
                'ruang_id' => (int) $b->ruang_id, 'kelas_id' => (int) $b->kelas_id, 'guru_id' => (int) $b->guru_id,
                'jam_per_minggu' => (int) $b->jam_per_minggu, 'pola_blok' => json_decode($b->pola_blok ?? 'null', true),
            ])->all();

        $ketersediaan = DB::table('ketersediaan_guru as k')->join('slot_waktu as s', 's.id', '=', 'k.slot_waktu_id')
            ->where('s.tahun_ajaran_id', $ta)->whereNotNull('s.jam_ke')
            ->get(['k.guru_id', 's.hari', 's.jam_ke', 'k.status'])
            ->map(fn ($k) => ['guru_id' => (int) $k->guru_id, 'hari' => (int) $k->hari, 'jam_ke' => (int) $k->jam_ke, 'status' => $k->status])->all();

        // Blok dikunci diambil dari jadwal aktif (guru_id null = pengampu utama ruang)
        $aktif = DB::table('versi_jadwal')->where('tahun_ajaran_id', $ta)->where('status', 'aktif')->where('id', '!=', $versi->id)->value('id');
        $terkunci = $aktif ? DB::table('jadwal as j')->join('ruang as r', 'r.id', '=', 'j.ruang_id')
            ->where('j.versi_jadwal_id', $aktif)->where('j.dikunci', true)->whereNotNull('j.jam_ke_mulai')
            ->get(['j.ruang_id', DB::raw('COALESCE(j.guru_id, r.guru_id) as guru_id'), 'j.hari', 'j.jam_ke_mulai', 'j.jam_ke_selesai'])
            ->map(fn ($j) => array_map('intval', (array) $j))->all() : [];

        return ['slots' => $slots, 'beban' => $beban, 'ketersediaan' => $ketersediaan, 'terkunci' => $terkunci,
            'pengaturan' => ['batas_waktu_detik' => (int) ($versi->hasil_solver['parameter']['waktu'] ?? 120), 'workers' => 8]];
    }

    private function impor(VersiJadwal $versi, array $hasil): void
    {
        $slot = SlotWaktu::where('tahun_ajaran_id', $versi->tahun_ajaran_id)->kbm()->get()->groupBy('hari')->map(fn ($g) => $g->keyBy('jam_ke'));

        // Link kelas daring dibawa dari jadwal aktif (kunci: ruang + guru)
        $link = [];
        $aktif = VersiJadwal::aktif($versi->tahun_ajaran_id);
        if ($aktif && $aktif->id !== $versi->id) {
            foreach (DB::table('jadwal as j')->join('ruang as r', 'r.id', '=', 'j.ruang_id')->where('j.versi_jadwal_id', $aktif->id)
                ->whereNotNull('j.link_meet')->get(['j.ruang_id', DB::raw('COALESCE(j.guru_id, r.guru_id) as guru_id'), 'j.link_meet']) as $l) {
                $link[$l->ruang_id . '|' . $l->guru_id] ??= $l->link_meet;
            }
        }

        DB::transaction(function () use ($versi, $hasil, $slot, $link) {
            DB::table('jadwal')->where('versi_jadwal_id', $versi->id)->delete();
            $baris = [];
            foreach ($hasil['jadwal'] as $b) {
                $baris[] = [
                    'versi_jadwal_id' => $versi->id, 'ruang_id' => $b['ruang_id'], 'guru_id' => $b['guru_id'], 'hari' => $b['hari'],
                    'jam_ke_mulai' => $b['jam_ke_mulai'], 'jam_ke_selesai' => $b['jam_ke_selesai'],
                    'jam_mulai' => $slot[$b['hari']][$b['jam_ke_mulai']]->jam_mulai,
                    'jam_selesai' => $slot[$b['hari']][$b['jam_ke_selesai']]->jam_selesai,
                    'dikunci' => (bool) ($b['dikunci'] ?? false), 'link_meet' => $link[$b['ruang_id'] . '|' . $b['guru_id']] ?? null,
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
            foreach (array_chunk($baris, 200) as $potong) {
                DB::table('jadwal')->insert($potong);
            }
            $this->tandai($versi->id, 'selesai', [
                'status' => $hasil['status'], 'waktu_detik' => $hasil['waktu_detik'], 'objektif' => $hasil['objektif'] ?? null,
                'batas_bawah' => $hasil['batas_bawah'] ?? null, 'metrik' => $hasil['metrik'] ?? [],
                'pelanggaran_keras' => $hasil['pelanggaran_keras'] ?? [], 'peringatan' => $hasil['peringatan'] ?? [],
            ]);
        });
    }

    // ------------------------------------------------------------------ pemeriksaan data sebelum hitung

    /** Ringkasan dan masalah data penjadwalan satu tahun ajaran (dipakai halaman Jadwal Otomatis) */
    public function ringkasanData(int $taId): array
    {
        $slot = SlotWaktu::where('tahun_ajaran_id', $taId)->kbm()->get();
        $ruang = Ruang::where('tahun_ajaran_id', $taId)->get();
        $beban = BebanAjar::with('ruang.mapel', 'ruang.kelas', 'guru')->whereIn('ruang_id', $ruang->pluck('id'))->get();
        $masalah = [];
        $peringatan = [];

        if ($slot->isEmpty()) $masalah[] = 'Struktur jam belum diisi (tab Struktur Jam).';
        if ($beban->isEmpty()) $masalah[] = 'Beban ajar belum diisi (tab Beban Ajar).';

        // blok berurutan terpanjang di salah satu hari
        $runTerpanjang = 0;
        foreach ($slot->groupBy('hari') as $daftar) {
            $jam = $daftar->pluck('jam_ke')->map(fn ($x) => (int) $x)->sort()->values();
            $run = 0;
            foreach ($jam as $i => $j) {
                $run = ($i > 0 && $j === $jam[$i - 1] + 1) ? $run + 1 : 1;
                $runTerpanjang = max($runTerpanjang, $run);
            }
        }

        $totalSlot = $slot->count();
        foreach ($beban->groupBy(fn ($b) => $b->ruang->kelas_id) as $bs) {
            $jam = $bs->sum('jam_per_minggu');
            if ($totalSlot && $jam > $totalSlot) {
                $masalah[] = 'Kelas ' . $bs->first()->ruang->kelas->nama . ' punya ' . $jam . ' jam per minggu, padahal hanya ada ' . $totalSlot . ' jam pelajaran.';
            }
        }

        $tidakBisa = KetersediaanGuru::where('status', 'tidak_bisa')->whereIn('slot_waktu_id', $slot->pluck('id'))->get()->groupBy('guru_id');
        foreach ($beban->groupBy('guru_id') as $gid => $bs) {
            $tersedia = $totalSlot - ($tidakBisa->get($gid)?->count() ?? 0);
            if ($totalSlot && $bs->sum('jam_per_minggu') > $tersedia) {
                $masalah[] = 'Guru ' . ($bs->first()->guru->kode_guru ?: $bs->first()->guru->name) . ' mengajar ' . $bs->sum('jam_per_minggu') . ' jam, padahal hanya tersedia ' . $tersedia . ' jam.';
            }
        }
        foreach ($beban as $b) {
            $label = $b->ruang->mapel->nama . ' ' . $b->ruang->kelas->nama . ' (' . ($b->guru->kode_guru ?: $b->guru->name) . ')';
            if ($b->pola_blok && array_sum($b->pola_blok) !== (int) $b->jam_per_minggu) {
                $masalah[] = "Pola blok $label berjumlah " . array_sum($b->pola_blok) . ' jam, tidak sama dengan ' . $b->jam_per_minggu . ' jam per minggu.';
            }
            if ($runTerpanjang && max($b->ukuranBlok()) > $runTerpanjang) {
                $masalah[] = "Blok terpanjang $label ({$b->polaTeks()}) melebihi jam berurutan terpanjang dalam sehari ($runTerpanjang jam).";
            }
        }

        $tanpaBeban = $ruang->whereNotIn('id', $beban->pluck('ruang_id'));
        if ($tanpaBeban->isNotEmpty() && $beban->isNotEmpty()) {
            $peringatan[] = $tanpaBeban->count() . ' ruang belajar belum punya beban ajar, jadi tidak ikut dijadwalkan.';
        }

        return ['slot' => $totalSlot, 'ruang' => $ruang->count(), 'beban' => $beban->count(), 'jam' => (int) $beban->sum('jam_per_minggu'),
            'guru' => $beban->pluck('guru_id')->unique()->count(), 'masalah' => $masalah, 'peringatan' => $peringatan];
    }

    // ------------------------------------------------------------------ pemeriksaan & pengeditan jadwal

    /** Daftar masalah pada satu versi: bentrok guru/kelas dan beban yang tidak terpenuhi */
    public function bentrok(VersiJadwal $versi): array
    {
        $baris = Jadwal::with('ruang.mapel', 'ruang.kelas', 'ruang.guru', 'guru')->where('versi_jadwal_id', $versi->id)->whereNotNull('jam_ke_mulai')->get();
        $guru = [];
        $kelas = [];
        $jam = [];
        foreach ($baris as $b) {
            $gid = $b->guru_id ?? $b->ruang->guru_id;
            $jam[$b->ruang_id . '|' . $gid] = ($jam[$b->ruang_id . '|' . $gid] ?? 0) + $b->jumlahJam();
            for ($j = $b->jam_ke_mulai; $j <= $b->jam_ke_selesai; $j++) {
                $guru[$gid . '|' . $b->hari . '|' . $j][] = $b;
                $kelas[$b->ruang->kelas_id . '|' . $b->hari . '|' . $j][] = $b;
            }
        }
        $hasil = [];
        foreach ($guru as $kunci => $daftar) {
            if (count($daftar) > 1) {
                [, $h, $j] = explode('|', $kunci);
                $hasil[] = 'Guru ' . ($daftar[0]->pengajar()?->name ?? '?') . ' mengajar di dua tempat: ' . Jadwal::HARI[$h] . ' jam ke-' . $j . '.';
            }
        }
        foreach ($kelas as $kunci => $daftar) {
            if (count($daftar) > 1) {
                [, $h, $j] = explode('|', $kunci);
                $hasil[] = 'Kelas ' . $daftar[0]->ruang->kelas->nama . ' punya dua pelajaran sekaligus: ' . Jadwal::HARI[$h] . ' jam ke-' . $j . '.';
            }
        }
        $beban = BebanAjar::with('ruang.mapel', 'ruang.kelas', 'guru')
            ->whereHas('ruang', fn ($q) => $q->where('tahun_ajaran_id', $versi->tahun_ajaran_id))->get();
        foreach ($beban as $b) {
            $ada = $jam[$b->ruang_id . '|' . $b->guru_id] ?? 0;
            if ($ada !== (int) $b->jam_per_minggu) {
                $hasil[] = $b->ruang->mapel->nama . ' ' . $b->ruang->kelas->nama . ' (' . ($b->guru->kode_guru ?: $b->guru->name) . '): terjadwal ' . $ada . ' dari ' . $b->jam_per_minggu . ' jam.';
            }
        }
        return $hasil;
    }

    /** Pindahkan satu blok ke hari dan jam lain. Mengembalikan pesan galat, atau null bila berhasil. */
    public function pindah(Jadwal $blok, int $hari, int $jamMulai): ?string
    {
        if (! $blok->versi_jadwal_id || $blok->jam_ke_mulai === null) {
            return 'Hanya blok di dalam versi jadwal yang bisa dipindahkan.';
        }
        $ruang = $blok->ruang;
        $akhir = $jamMulai + $blok->jumlahJam() - 1;
        $slot = SlotWaktu::where('tahun_ajaran_id', $ruang->tahun_ajaran_id)->where('hari', $hari)->kbm()->get()->keyBy('jam_ke');
        for ($k = $jamMulai; $k <= $akhir; $k++) {
            if (! $slot->has($k)) return Jadwal::HARI[$hari] . ' tidak punya jam pelajaran ke-' . $k . '.';
        }
        $guruId = (int) ($blok->guru_id ?? $ruang->guru_id);
        $slotId = collect(range($jamMulai, $akhir))->map(fn ($k) => $slot[$k]->id);
        if (KetersediaanGuru::where('guru_id', $guruId)->where('status', 'tidak_bisa')->whereIn('slot_waktu_id', $slotId)->exists()) {
            return 'Guru tersebut ditandai tidak bisa mengajar pada jam itu.';
        }
        $lain = Jadwal::with('ruang.mapel', 'ruang.kelas', 'ruang.guru', 'guru')->where('versi_jadwal_id', $blok->versi_jadwal_id)
            ->where('hari', $hari)->where('id', '!=', $blok->id)
            ->where('jam_ke_mulai', '<=', $akhir)->where('jam_ke_selesai', '>=', $jamMulai)->get();
        foreach ($lain as $x) {
            if ((int) $x->ruang->kelas_id === (int) $ruang->kelas_id) {
                return 'Bentrok: kelas ' . $ruang->kelas->nama . ' sudah ada ' . $x->ruang->mapel->nama . ' pada jam itu.';
            }
            if ((int) ($x->guru_id ?? $x->ruang->guru_id) === $guruId) {
                return 'Bentrok: ' . ($x->pengajar()?->name ?? 'guru') . ' sudah mengajar ' . $x->ruang->mapel->nama . ' di ' . $x->ruang->kelas->nama . ' pada jam itu.';
            }
        }
        $blok->update(['hari' => $hari, 'jam_ke_mulai' => $jamMulai, 'jam_ke_selesai' => $akhir,
            'jam_mulai' => $slot[$jamMulai]->jam_mulai, 'jam_selesai' => $slot[$akhir]->jam_selesai]);
        return null;
    }

    /** Jadikan satu versi sebagai jadwal aktif; yang aktif sebelumnya diarsipkan (aturan: satu aktif per tahun ajaran). */
    public function aktifkan(VersiJadwal $versi): void
    {
        DB::transaction(function () use ($versi) {
            VersiJadwal::where('tahun_ajaran_id', $versi->tahun_ajaran_id)->where('status', 'aktif')->update(['status' => 'arsip']);
            $versi->update(['status' => 'aktif']);
        });
    }

    /** Salin struktur jam, beban ajar, ketersediaan guru, dan jadwal aktif ke tahun ajaran baru. $petaRuang = [id ruang lama => id ruang baru] */
    public function salinKeTahunBaru(int $dariTa, int $keTa, array $petaRuang): void
    {
        $petaSlot = [];
        foreach (SlotWaktu::where('tahun_ajaran_id', $dariTa)->get() as $s) {
            $baru = $s->replicate();
            $baru->tahun_ajaran_id = $keTa;
            $baru->save();
            $petaSlot[$s->id] = $baru->id;
        }
        foreach (BebanAjar::whereIn('ruang_id', array_keys($petaRuang))->get() as $b) {
            $baru = $b->replicate();
            $baru->ruang_id = $petaRuang[$b->ruang_id];
            $baru->save();
        }
        foreach (KetersediaanGuru::whereIn('slot_waktu_id', array_keys($petaSlot))->get() as $k) {
            $baru = $k->replicate();
            $baru->slot_waktu_id = $petaSlot[$k->slot_waktu_id];
            $baru->save();
        }
        $aktif = VersiJadwal::aktif($dariTa);
        if ($aktif) {
            $versi = VersiJadwal::create(['tahun_ajaran_id' => $keTa, 'nama' => 'Salinan: ' . $aktif->nama, 'status' => 'aktif', 'sumber' => 'manual']);
            foreach (Jadwal::where('versi_jadwal_id', $aktif->id)->whereIn('ruang_id', array_keys($petaRuang))->get() as $j) {
                $baru = $j->replicate();
                $baru->versi_jadwal_id = $versi->id;
                $baru->ruang_id = $petaRuang[$j->ruang_id];
                $baru->save();
            }
        }
    }

    // ------------------------------------------------------------------ util

    private function tandai(int $id, string $status, ?array $ringkasan = null): void
    {
        $data = ['status_solver' => $status, 'updated_at' => now()];
        if ($ringkasan !== null) {
            $lama = json_decode(DB::table('versi_jadwal')->where('id', $id)->value('hasil_solver') ?? 'null', true) ?: [];
            $data['hasil_solver'] = json_encode((isset($lama['parameter']) ? ['parameter' => $lama['parameter']] : []) + $ringkasan, JSON_UNESCAPED_UNICODE);
        }
        DB::table('versi_jadwal')->where('id', $id)->update($data);
    }
}
