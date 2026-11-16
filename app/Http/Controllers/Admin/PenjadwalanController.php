<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\SlotWaktu;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\VersiJadwal;
use App\Services\PenjadwalanSolver;
use Illuminate\Http\Request;

/** Jadwal otomatis: buat versi dengan solver, lihat grid jadwal, pindahkan/kunci blok, aktifkan versi */
class PenjadwalanController extends Controller
{
    public function __construct(private PenjadwalanSolver $solver)
    {
    }

    public function index()
    {
        $ta = TahunAjaran::aktif();
        $versi = $ta ? VersiJadwal::with('pembuat')->withCount('jadwal')->where('tahun_ajaran_id', $ta->id)->orderByDesc('id')->get() : collect();
        $data = $ta ? $this->solver->ringkasanData($ta->id) : null;
        return view('admin.penjadwalan.versi', compact('ta', 'versi', 'data'));
    }

    public function buat(Request $r)
    {
        $ta = TahunAjaran::aktifId();
        abort_unless($ta, 422, 'Belum ada tahun ajaran aktif.');
        $d = $r->validate(['nama' => 'nullable|string|max:120', 'waktu' => 'required|integer|min:10|max:1200']);

        $macet = now()->subMinutes(45);
        if (VersiJadwal::where('tahun_ajaran_id', $ta)->whereIn('status_solver', ['menunggu', 'berjalan'])->where('updated_at', '>', $macet)->exists()) {
            return back()->with('error', 'Masih ada perhitungan yang berjalan. Tunggu sampai selesai atau hapus draf yang macet.');
        }
        $data = $this->solver->ringkasanData($ta);
        if ($data['masalah']) {
            return back()->with('error', 'Data penjadwalan belum siap: ' . $data['masalah'][0]);
        }
        $versi = $this->solver->buatDraf($ta, $d['nama'] ?: 'Draf ' . now()->format('d M H:i'), (int) $d['waktu'], $r->user()->id);
        $this->solver->mulai($versi->id);
        return redirect()->route('admin.penjadwalan')->with('ok', 'Perhitungan dimulai. Halaman ini diperbarui otomatis sampai selesai.');
    }

    public function lihat(Request $r, VersiJadwal $versi)
    {
        $mode = in_array($r->query('mode'), ['guru', 'hari'], true) ? $r->query('mode') : 'kelas';
        $kelasList = Kelas::orderBy('tingkat')->orderBy('nama')->get();
        $guruList = User::where('role', 'guru')->where('aktif', true)->orderBy('kode_guru')->orderBy('name')->get();
        $id = (int) $r->query('id');

        $semua = Jadwal::with('ruang.mapel', 'ruang.kelas', 'ruang.guru', 'guru')->where('versi_jadwal_id', $versi->id)->get();
        $kelas = $guru = null;
        if ($mode === 'kelas') {
            $kelas = $kelasList->firstWhere('id', $id) ?? $kelasList->first();
            $blok = $semua->filter(fn ($b) => $kelas && $b->ruang->kelas_id == $kelas->id)->values();
        } elseif ($mode === 'hari') {
            $blok = $semua;
        } else {
            $guru = $guruList->firstWhere('id', $id) ?? $guruList->first();
            $blok = $semua->filter(fn ($b) => $guru && ($b->guru_id ?? $b->ruang->guru_id) == $guru->id)->values();
        }

        $slots = SlotWaktu::where('tahun_ajaran_id', $versi->tahun_ajaran_id)->orderBy('hari')->orderBy('jam_mulai')->get()->groupBy('hari');
        [$baris, $grid] = $mode === 'hari' ? [[], []] : $this->susunGrid($slots, $blok);
        [$harian, $hariList] = $mode === 'hari' ? $this->susunHarian($slots, $semua, $kelasList, $id) : [[], $slots->keys()->sort()->values()];
        $bentrok = $this->solver->bentrok($versi);
        $jamAda = $semua->sum(fn ($b) => $b->jumlahJam());

        return view('admin.penjadwalan.lihat', compact('versi', 'mode', 'kelasList', 'guruList', 'kelas', 'guru', 'slots', 'baris', 'grid', 'harian', 'hariList', 'id', 'bentrok', 'jamAda'));
    }

    /**
     * Jadwal per hari seperti sheet "grid" di Excel: baris = jam (istirahat/upacara/senam ikut tampil), kolom = semua kelas.
     * $pilih = nomor hari, atau 0 untuk semua hari. Mengembalikan [$harian, $hariList];
     * $harian[hari] = [['slot' => SlotWaktu, 'sel' => [kelas_id => [blok, ...]]], ...]
     */
    private function susunHarian($slots, $semua, $kelasList, int $pilih): array
    {
        $peta = [];
        foreach ($semua as $b) {
            if ($b->jam_ke_mulai === null) continue;
            for ($j = $b->jam_ke_mulai; $j <= $b->jam_ke_selesai; $j++) {
                $peta[$b->ruang->kelas_id][$b->hari][$j][] = $b;
            }
        }
        $hariList = $slots->keys()->sort()->values();
        $tampil = $pilih && $hariList->contains($pilih) ? collect([$pilih]) : $hariList;
        $harian = [];
        foreach ($tampil as $h) {
            $baris = [];
            foreach ($slots[$h] as $s) {
                $sel = [];
                if ($s->jenis === 'kbm' && $s->jam_ke !== null) {
                    foreach ($kelasList as $k) $sel[$k->id] = $peta[$k->id][$h][$s->jam_ke] ?? [];
                }
                $baris[] = ['slot' => $s, 'sel' => $sel];
            }
            $harian[$h] = $baris;
        }
        return [$harian, $hariList];
    }

    /**
     * Matriks baris x hari. Baris = jam ke-N, ditambah baris istirahat tipis setelah jam ke-N bila ada hari yang istirahat di situ.
     * Sel: tiada | non (upacara/senam) | kosong | rehat | blok (rowspan; terpotong di istirahat, bagian sesudahnya 'lanjutan') | lanjut
     * Mengembalikan [$baris, $grid] dengan $baris = [['tipe' => 'jam'|'rehat', 'j' => N], ...]
     */
    private function susunGrid($slots, $blok): array
    {
        $hari = $slots->keys()->sort()->values();
        $perJam = [];   // [hari][jam_ke] => slot
        $rehat = [];    // [hari][N] => slot istirahat setelah jam ke-N
        $semuaJam = [];
        foreach ($hari as $h) {
            foreach ($slots[$h] as $s) {
                if ($s->jam_ke !== null) {
                    $perJam[$h][$s->jam_ke] = $s;
                    $semuaJam[$s->jam_ke] = true;
                }
            }
            foreach ($slots[$h]->where('jenis', 'istirahat') as $s) {
                $sebelum = collect($perJam[$h] ?? [])->filter(fn ($x) => $x->jam_selesai <= $s->jam_mulai)->keys();
                if ($sebelum->isNotEmpty()) $rehat[$h][$sebelum->max()] = $s;
            }
        }
        ksort($semuaJam);

        $baris = [];
        foreach (array_keys($semuaJam) as $j) {
            $baris[] = ['tipe' => 'jam', 'j' => $j];
            foreach ($hari as $h) {
                if (isset($rehat[$h][$j])) { $baris[] = ['tipe' => 'rehat', 'j' => $j]; break; }
            }
        }
        $idxJam = [];
        foreach ($baris as $i => $r) {
            if ($r['tipe'] === 'jam') $idxJam[$r['j']] = $i;
        }

        $mulaiDi = [];
        foreach ($blok as $b) {
            $mulaiDi[$b->hari][$b->jam_ke_mulai][] = $b;
        }

        $grid = [];
        foreach ($hari as $h) {
            foreach ($baris as $i => $r) {
                if (isset($grid[$h][$i])) continue; // sudah diisi oleh blok di atasnya
                $j = $r['j'];
                if ($r['tipe'] === 'rehat') {
                    $grid[$h][$i] = isset($rehat[$h][$j]) ? ['t' => 'rehat', 'slot' => $rehat[$h][$j]] : ['t' => 'tiada'];
                    continue;
                }
                $s = $perJam[$h][$j] ?? null;
                if (! $s) { $grid[$h][$i] = ['t' => 'tiada']; continue; }
                if ($s->jenis !== 'kbm') { $grid[$h][$i] = ['t' => 'non', 'slot' => $s]; continue; }
                $mulai = $mulaiDi[$h][$j] ?? [];
                if (! $mulai) { $grid[$h][$i] = ['t' => 'kosong', 'slot' => $s]; continue; }

                // Blok: dipotong di setiap istirahat yang dilewatinya, lalu disambung lagi sesudahnya
                $akhir = $idxJam[$mulai[0]->jam_ke_selesai] ?? $i;
                $awal = $i;
                $bagian = 0;
                $dari = substr($mulai[0]->jam_mulai, 0, 5);
                for ($k = $i + 1; $k <= $akhir; $k++) {
                    if ($baris[$k]['tipe'] === 'rehat' && isset($rehat[$h][$baris[$k]['j']])) {
                        $br = $rehat[$h][$baris[$k]['j']];
                        $grid[$h][$awal] = ['t' => 'blok', 'blok' => $mulai, 'span' => $k - $awal, 'lanjutan' => $bagian > 0,
                            'waktu' => $dari . '–' . substr($br->jam_mulai, 0, 5)];
                        $grid[$h][$k] = ['t' => 'rehat', 'slot' => $br];
                        $dari = substr($br->jam_selesai, 0, 5);
                        $awal = $k + 1;
                        $bagian++;
                    } else {
                        $grid[$h][$k] = ['t' => 'lanjut'];
                    }
                }
                $grid[$h][$awal] = ['t' => 'blok', 'blok' => $mulai, 'span' => $akhir - $awal + 1, 'lanjutan' => $bagian > 0,
                    'waktu' => $dari . '–' . substr($mulai[0]->jam_selesai, 0, 5)];
            }
        }
        return [$baris, $grid];
    }

    public function pindah(Request $r, Jadwal $jadwal)
    {
        $d = $r->validate(['hari' => 'required|integer|between:1,6', 'jam_ke' => 'required|integer|min:0|max:30']);
        $galat = $this->solver->pindah($jadwal, (int) $d['hari'], (int) $d['jam_ke']);
        return back()->with($galat ? 'error' : 'ok', $galat ?? 'Blok dipindahkan.');
    }

    public function kunci(Jadwal $jadwal)
    {
        $jadwal->update(['dikunci' => ! $jadwal->dikunci]);
        return back()->with('ok', $jadwal->dikunci ? 'Blok dikunci: tidak berubah saat jadwal otomatis dibuat ulang.' : 'Kunci blok dilepas.');
    }

    public function aktifkan(VersiJadwal $versi)
    {
        if ($versi->sedangDihitung()) return back()->with('error', 'Versi ini masih dihitung.');
        if (! $versi->jadwal()->exists()) return back()->with('error', 'Versi ini belum berisi jadwal.');
        $this->solver->aktifkan($versi);
        return back()->with('ok', 'Versi "' . $versi->nama . '" sekarang menjadi jadwal aktif di LMS.');
    }

    public function hapus(VersiJadwal $versi)
    {
        if ($versi->status === 'aktif') return back()->with('error', 'Versi aktif tidak bisa dihapus. Aktifkan versi lain dulu.');
        $versi->delete();
        return redirect()->route('admin.penjadwalan')->with('ok', 'Versi jadwal dihapus.');
    }
}
