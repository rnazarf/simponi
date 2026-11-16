<?php

namespace App\Http\Controllers;

use App\Models\Kuis;
use App\Models\Percobaan;
use App\Models\Ruang;
use App\Models\Soal;
use App\Services\GeminiQuizService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KuisController extends Controller
{
    public function index(Request $r, Ruang $ruang)
    {
        $u = $r->user();
        $ruang->load('mapel', 'kelas');
        $kuis = Kuis::where('ruang_id', $ruang->id)
            ->when($u->role === 'siswa', fn ($q) => $q->where('terbit', true))
            ->withCount(['soal', 'percobaan as selesai_count' => fn ($q) => $q->whereNotNull('selesai_at')])
            ->orderByDesc('mulai')->get();
        $milikSaya = $u->role === 'siswa'
            ? Percobaan::where('siswa_id', $u->id)->whereIn('kuis_id', $kuis->pluck('id'))->get()->keyBy('kuis_id')
            : collect();
        return view('kuis.index', compact('ruang', 'kuis', 'milikSaya'));
    }

    public function create(Ruang $ruang)
    {
        $ruang->load('mapel', 'kelas');
        $kuis = new Kuis([
            'mulai' => today()->addDay()->setTime(7, 0),
            'selesai' => today()->addDay()->setTime(21, 0),
            'durasi_menit' => 60, 'acak_soal' => true, 'acak_opsi' => true, 'tampilkan_nilai' => true,
            'anti_curang' => true, 'maks_pelanggaran' => 0,
        ]);
        return view('kuis.form', compact('ruang', 'kuis'));
    }

    public function store(Request $r, Ruang $ruang)
    {
        $kuis = Kuis::create($this->validasi($r) + ['ruang_id' => $ruang->id, 'terbit' => false]);
        return redirect()->route('kuis.edit', [$ruang, $kuis])->with('ok', 'Kuis dibuat. Tambahkan soal, lalu terbitkan.');
    }

    public function edit(Ruang $ruang, Kuis $kuis)
    {
        $this->milik($kuis, $ruang);
        $ruang->load('mapel', 'kelas');
        $kuis->load('soal');
        $sudahDikerjakan = $kuis->percobaan()->exists();
        $ubahSoal = $kuis->soal->firstWhere('id', (int) request('soal'));
        return view('kuis.form', compact('ruang', 'kuis', 'sudahDikerjakan', 'ubahSoal'));
    }

    public function update(Request $r, Ruang $ruang, Kuis $kuis)
    {
        $this->milik($kuis, $ruang);
        $data = $this->validasi($r);
        $data['terbit'] = $r->boolean('terbit');
        if ($data['terbit'] && !$kuis->soal()->exists()) {
            return back()->with('error', 'Tambahkan minimal satu soal sebelum kuis diterbitkan.');
        }
        $kuis->update($data);
        return back()->with('ok', $data['terbit'] ? 'Kuis disimpan dan terbit untuk siswa.' : 'Kuis disimpan sebagai draf.');
    }

    public function destroy(Ruang $ruang, Kuis $kuis)
    {
        $this->milik($kuis, $ruang);
        $kuis->delete();
        return redirect()->route('kuis.index', $ruang)->with('ok', 'Kuis dihapus.');
    }

    public function simpanSoal(Request $r, Ruang $ruang, Kuis $kuis)
    {
        $this->milik($kuis, $ruang);
        $data = $r->validate([
            'soal_id' => 'nullable|integer',
            'jenis' => 'required|in:pg,esai',
            'pertanyaan' => 'required|string|max:5000',
            'opsi' => 'array',
            'opsi.*' => 'nullable|string|max:1000',
            'kunci' => 'nullable|required_if:jenis,pg|in:A,B,C,D,E',
            'bobot' => 'required|integer|min:1|max:100',
        ], ['kunci.required_if' => 'Pilih kunci jawaban untuk soal pilihan ganda.']);

        $opsi = null;
        if ($data['jenis'] === 'pg') {
            $opsi = array_filter(array_map('trim', $data['opsi'] ?? []), fn ($v) => $v !== '');
            if (count($opsi) < 2) return back()->withInput()->with('error', 'Soal pilihan ganda butuh minimal 2 opsi.');
            if (!isset($opsi[$data['kunci']])) return back()->withInput()->with('error', 'Kunci jawaban harus salah satu opsi yang diisi.');
        }
        $nilai = [
            'jenis' => $data['jenis'], 'pertanyaan' => $data['pertanyaan'], 'opsi' => $opsi,
            'kunci' => $data['jenis'] === 'pg' ? $data['kunci'] : null, 'bobot' => $data['bobot'],
        ];
        if (!empty($data['soal_id'])) {
            $kuis->soal()->whereKey($data['soal_id'])->firstOrFail()->update($nilai);
            $this->hitungUlang($kuis);
            return redirect()->route('kuis.edit', [$ruang, $kuis])->with('ok', 'Soal diperbarui.');
        }
        $kuis->soal()->create($nilai + ['urutan' => (int) $kuis->soal()->max('urutan') + 1]);
        return back()->with('ok', 'Soal ditambahkan.');
    }

    /**
     * Impor banyak soal dari teks, format:
     *   1. Pertanyaan
     *   A. opsi   B. opsi ...
     *   Kunci: B
     * Soal tanpa opsi A-E dianggap esai.
     */
    public function imporSoal(Request $r, Ruang $ruang, Kuis $kuis)
    {
        $this->milik($kuis, $ruang);
        $r->validate(['teks' => 'required|string|max:100000']);
        $blok = preg_split('/\R(?=\s*\d+[\.\)]\s)/', "\n" . trim($r->teks));
        $urutan = (int) $kuis->soal()->max('urutan');
        $masuk = 0;
        $gagal = [];
        foreach ($blok as $b) {
            $b = trim($b);
            if ($b === '') continue;
            if (!preg_match('/^\s*(\d+)[\.\)]\s*(.*)$/s', $b, $m)) continue;
            $nomor = $m[1];
            $baris = preg_split('/\R/', $m[2]);
            $tanya = [];
            $opsi = [];
            $kunci = null;
            foreach ($baris as $l) {
                if (preg_match('/^\s*([A-Ea-e])[\.\)]\s*(.+)$/', $l, $o)) $opsi[strtoupper($o[1])] = trim($o[2]);
                elseif (preg_match('/^\s*kunci\s*[:=]\s*([A-Ea-e])/i', $l, $k)) $kunci = strtoupper($k[1]);
                elseif (!$opsi) $tanya[] = $l;
            }
            $tanya = trim(implode("\n", $tanya));
            if ($tanya === '') { $gagal[] = $nomor; continue; }
            if ($opsi && (!$kunci || !isset($opsi[$kunci]) || count($opsi) < 2)) { $gagal[] = $nomor; continue; }
            $kuis->soal()->create([
                'urutan' => ++$urutan, 'jenis' => $opsi ? 'pg' : 'esai', 'pertanyaan' => $tanya,
                'opsi' => $opsi ?: null, 'kunci' => $opsi ? $kunci : null, 'bobot' => $opsi ? 1 : 5,
            ]);
            $masuk++;
        }
        $pesan = "$masuk soal berhasil diimpor.";
        if ($gagal) $pesan .= ' Soal nomor ' . implode(', ', $gagal) . ' dilewati (kunci tidak ada / format salah).';
        return back()->with($masuk ? 'ok' : 'error', $pesan);
    }

    public function generateAiSoal(Request $r, Ruang $ruang, Kuis $kuis, GeminiQuizService $gemini)
    {
        $this->milik($kuis, $ruang);
        $ruang->load('mapel', 'kelas');

        $data = $r->validate([
            'prompt' => 'required|string|max:15000',
            'jumlah' => 'nullable|integer|min:1|max:30',
            'jenis' => 'nullable|in:pg,esai,campuran',
            'tingkat' => 'nullable|in:mudah,sedang,sulit,campuran',
            'opsi_count' => 'nullable|in:4,5',
            'api_key' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:60',
        ]);

        $params = [
            'prompt' => $data['prompt'],
            'jumlah' => (int) ($data['jumlah'] ?? 5),
            'jenis' => $data['jenis'] ?? 'pg',
            'tingkat' => $data['tingkat'] ?? 'sedang',
            'opsi_count' => (int) ($data['opsi_count'] ?? 5),
            'mapel' => $ruang->mapel?->nama,
            'jenjang' => \App\Models\Pengaturan::ambil('jenjang'),
            'kelas' => $ruang->kelas?->nama,
            'api_key' => $data['api_key'] ?? null,
            'model' => $data['model'] ?? null,
        ];

        $hasil = $gemini->generate($params);
        return response()->json($hasil, $hasil['ok'] ? 200 : 422);
    }

    public function simpanAiSoal(Request $r, Ruang $ruang, Kuis $kuis)
    {
        $this->milik($kuis, $ruang);
        $data = $r->validate([
            'soal' => 'required|array|min:1',
            'soal.*.pertanyaan' => 'required|string|max:5000',
            'soal.*.jenis' => 'required|in:pg,esai',
            'soal.*.bobot' => 'required|integer|min:1|max:100',
            'soal.*.opsi' => 'nullable|array',
            'soal.*.kunci' => 'nullable|string|max:5',
        ]);

        $urutan = (int) $kuis->soal()->max('urutan');
        $masuk = 0;

        DB::transaction(function () use ($kuis, $data, &$urutan, &$masuk) {
            foreach ($data['soal'] as $item) {
                $isPg = $item['jenis'] === 'pg';
                $opsi = $isPg && !empty($item['opsi']) ? array_filter($item['opsi'], fn ($v) => trim((string) $v) !== '') : null;
                $kunci = $isPg ? strtoupper(trim((string) ($item['kunci'] ?? ''))) : null;

                $kuis->soal()->create([
                    'urutan' => ++$urutan,
                    'jenis' => $item['jenis'],
                    'pertanyaan' => $item['pertanyaan'],
                    'opsi' => $opsi,
                    'kunci' => $kunci,
                    'bobot' => (int) ($item['bobot'] ?? ($isPg ? 1 : 5)),
                ]);
                $masuk++;
            }
            $this->hitungUlang($kuis);
        });

        if ($r->expectsJson() || $r->wantsJson()) {
            return response()->json([
                'ok' => true,
                'pesan' => "{$masuk} butir soal dari AI berhasil ditambahkan ke kuis.",
                'total_soal' => $kuis->soal()->count(),
                'total_bobot' => $kuis->soal()->sum('bobot'),
            ]);
        }

        return back()->with('ok', "{$masuk} butir soal dari AI berhasil ditambahkan ke kuis.");
    }

    public function hapusSoal(Ruang $ruang, Kuis $kuis, Soal $soal)
    {
        $this->milik($kuis, $ruang);
        abort_unless($soal->kuis_id === $kuis->id, 404);
        $soal->delete();
        $kuis->soal()->get()->values()->each(fn ($s, $i) => $s->update(['urutan' => $i + 1]));
        $this->hitungUlang($kuis);
        return back()->with('ok', 'Soal dihapus.');
    }

    public function show(Request $r, Ruang $ruang, Kuis $kuis)
    {
        $this->milik($kuis, $ruang);
        $u = $r->user();
        $ruang->load('mapel', 'kelas');
        $kuis->load('soal');

        if ($u->role === 'siswa') {
            abort_unless($kuis->terbit, 404);
            $p = $this->tutupBilaHabis(Percobaan::where('kuis_id', $kuis->id)->where('siswa_id', $u->id)->first());
            return view('kuis.siswa', compact('ruang', 'kuis', 'p'));
        }

        $siswa = $ruang->siswa()->get();
        $percobaan = Percobaan::where('kuis_id', $kuis->id)->get()->map(fn ($p) => $this->tutupBilaHabis($p))->keyBy('siswa_id');
        $selesai = $percobaan->whereNotNull('selesai_at');
        // Analisis butir: persentase siswa yang menjawab benar tiap soal PG
        $butir = [];
        foreach ($kuis->soal->where('jenis', 'pg') as $s) {
            $benar = $selesai->filter(fn ($p) => ($p->jawaban[$s->id] ?? null) === $s->kunci)->count();
            $sebaran = $selesai->countBy(fn ($p) => $p->jawaban[$s->id] ?? '-');
            $butir[$s->id] = ['persen' => $selesai->count() ? round($benar / $selesai->count() * 100) : null, 'sebaran' => $sebaran];
        }
        return view('kuis.guru', compact('ruang', 'kuis', 'siswa', 'percobaan', 'butir'));
    }

    public function mulai(Request $r, Ruang $ruang, Kuis $kuis)
    {
        $this->milik($kuis, $ruang);
        $u = $r->user();
        if ($kuis->status() !== 'berlangsung') return back()->with('error', 'Kuis tidak sedang dibuka.');
        $p = Percobaan::where('kuis_id', $kuis->id)->where('siswa_id', $u->id)->first();
        if (!$p) {
            $soal = $kuis->soal()->get();
            $urut = $kuis->acak_soal ? $soal->shuffle() : $soal;
            $opsi = [];
            foreach ($urut as $s) {
                if ($s->jenis !== 'pg') continue;
                $huruf = array_keys($s->opsi ?? []);
                if ($kuis->acak_opsi) shuffle($huruf);
                $opsi[$s->id] = $huruf;
            }
            $p = Percobaan::create([
                'kuis_id' => $kuis->id, 'siswa_id' => $u->id, 'mulai_at' => now(),
                'batas_at' => now()->addMinutes($kuis->durasi_menit)->min($kuis->selesai),
                'urutan_soal' => ['soal' => $urut->pluck('id')->all(), 'opsi' => $opsi], 'jawaban' => [],
            ]);
        }
        return redirect()->route('kuis.kerjakan', [$ruang, $kuis]);
    }

    public function kerjakan(Request $r, Ruang $ruang, Kuis $kuis)
    {
        $this->milik($kuis, $ruang);
        $p = $this->tutupBilaHabis(Percobaan::where('kuis_id', $kuis->id)->where('siswa_id', $r->user()->id)->firstOrFail());
        if ($p->selesai_at) return redirect()->route('kuis.show', [$ruang, $kuis]);
        $soal = $kuis->soal()->get()->keyBy('id');
        $daftar = collect($p->urutan_soal['soal'])->map(fn ($id) => $soal[$id] ?? null)->filter()->values();
        $ruang->load('mapel', 'kelas');
        return view('kuis.kerjakan', compact('ruang', 'kuis', 'p', 'daftar'));
    }

    /** Simpan otomatis satu jawaban dan status ragu-ragu */
    public function jawab(Request $r, Ruang $ruang, Kuis $kuis)
    {
        $this->milik($kuis, $ruang);
        $data = $r->validate([
            'soal_id' => 'required|integer',
            'jawaban' => 'nullable|string|max:20000',
            'ragu' => 'nullable|boolean',
        ]);
        return DB::transaction(function () use ($r, $kuis, $data) {
            $p = Percobaan::where('kuis_id', $kuis->id)->where('siswa_id', $r->user()->id)->lockForUpdate()->firstOrFail();
            if ($p->selesai_at || now()->gt($p->batas_at)) return response()->json(['ok' => false, 'pesan' => 'Waktu habis'], 409);
            if (!in_array($data['soal_id'], $p->urutan_soal['soal'], true)) return response()->json(['ok' => false], 422);

            $updates = [];
            if ($r->has('jawaban')) {
                $jawab = $p->jawaban ?? [];
                $jawab[$data['soal_id']] = $data['jawaban'];
                $updates['jawaban'] = $jawab;
            }
            if ($r->has('ragu')) {
                $ragu = $p->ragu ?? [];
                if ($r->boolean('ragu')) {
                    $ragu[$data['soal_id']] = true;
                } else {
                    unset($ragu[$data['soal_id']]);
                }
                $updates['ragu'] = $ragu;
            }
            if ($updates) {
                $p->update($updates);
            }
            return response()->json(['ok' => true, 'tersimpan' => now()->format('H.i.s')]);
        });
    }

    /** Catat aktivitas mencurigakan / pelanggaran selama pengerjaan kuis */
    public function catatPelanggaran(Request $r, Ruang $ruang, Kuis $kuis)
    {
        $this->milik($kuis, $ruang);
        $data = $r->validate([
            'jenis' => 'required|string|in:tab_switch,blur,copy,paste,inspect,shortcut,fullscreen_exit',
            'keterangan' => 'required|string|max:255',
            'durasi_detik' => 'nullable|numeric|min:0|max:86400',
        ]);

        $u = $r->user();
        $p = $this->tutupBilaHabis(Percobaan::where('kuis_id', $kuis->id)->where('siswa_id', $u->id)->firstOrFail());
        if ($p->selesai_at || now()->gt($p->batas_at)) {
            return response()->json(['ok' => false, 'selesai' => true], 409);
        }

        if (!$kuis->anti_curang) {
            return response()->json(['ok' => true, 'anti_curang' => false]);
        }

        $res = $p->catatPelanggaran($data['jenis'], $data['keterangan'], [
            'durasi_detik' => $data['durasi_detik'] ?? null,
            'ip' => $r->ip(),
        ]);

        $forceSubmit = false;
        $pesan = "Peringatan! Pelanggaran ke-{$res['total']} tercatat oleh sistem.";

        if ($kuis->maks_pelanggaran > 0) {
            if ($res['total'] >= $kuis->maks_pelanggaran) {
                $forceSubmit = true;
                $p->selesai_at = now()->min($p->batas_at);
                $p->nilai = $p->hitungNilai();
                $p->save();
                $pesan = "Batas pelanggaran maksimal ({$kuis->maks_pelanggaran}x) tercapai! Kuis otomatis dikumpulkan.";
            } else {
                $sisa = $kuis->maks_pelanggaran - $res['total'];
                $pesan .= " Tersisa {$sisa} kesempatan sebelum kuis otomatis dikumpulkan.";
            }
        }

        return response()->json([
            'ok' => true,
            'total' => $res['total'],
            'maks' => $kuis->maks_pelanggaran,
            'force_submit' => $forceSubmit,
            'pesan' => $pesan,
            'waktu' => $res['entri']['waktu'],
        ]);
    }

    public function kumpul(Request $r, Ruang $ruang, Kuis $kuis)
    {
        $this->milik($kuis, $ruang);
        $p = Percobaan::where('kuis_id', $kuis->id)->where('siswa_id', $r->user()->id)->firstOrFail();
        if (!$p->selesai_at) {
            $p->selesai_at = now()->min($p->batas_at);
            $p->nilai = $p->hitungNilai();
            $p->save();
        }
        return redirect()->route('kuis.show', [$ruang, $kuis])->with('ok', 'Jawaban terkumpul. Terima kasih!');
    }

    public function koreksi(Ruang $ruang, Kuis $kuis, Percobaan $percobaan)
    {
        $this->milik($kuis, $ruang);
        abort_unless($percobaan->kuis_id === $kuis->id, 404);
        $ruang->load('mapel', 'kelas');
        $kuis->load('soal');
        $percobaan->load('siswa');
        return view('kuis.koreksi', compact('ruang', 'kuis', 'percobaan'));
    }

    public function simpanKoreksi(Request $r, Ruang $ruang, Kuis $kuis, Percobaan $percobaan)
    {
        $this->milik($kuis, $ruang);
        abort_unless($percobaan->kuis_id === $kuis->id, 404);
        $skor = [];
        foreach ($kuis->soal->where('jenis', 'esai') as $s) {
            $v = $r->input("skor.{$s->id}");
            if ($v === null || $v === '') continue;
            $skor[$s->id] = max(0, min($s->bobot, (float) $v));
        }
        $percobaan->nilai_esai = $skor;
        $percobaan->nilai = $percobaan->hitungNilai();
        $percobaan->save();
        $berikut = Percobaan::where('kuis_id', $kuis->id)->whereNotNull('selesai_at')->whereNull('nilai')->first();
        return $berikut
            ? redirect()->route('kuis.koreksi', [$ruang, $kuis, $berikut])->with('ok', 'Koreksi disimpan. Lanjut ke siswa berikutnya.')
            : redirect()->route('kuis.show', [$ruang, $kuis])->with('ok', 'Koreksi disimpan.');
    }

    public function resetPercobaan(Ruang $ruang, Kuis $kuis, Percobaan $percobaan)
    {
        $this->milik($kuis, $ruang);
        abort_unless($percobaan->kuis_id === $kuis->id, 404);
        $percobaan->delete();
        return back()->with('ok', 'Percobaan dihapus. Siswa bisa mengerjakan ulang selama kuis masih dibuka.');
    }

    /** Percobaan yang waktunya habis tapi belum dikumpulkan ditutup otomatis */
    private function tutupBilaHabis(?Percobaan $p): ?Percobaan
    {
        if ($p && !$p->selesai_at && now()->gte($p->batas_at)) {
            $p->selesai_at = $p->batas_at;
            $p->nilai = $p->hitungNilai();
            $p->save();
        }
        return $p;
    }

    private function hitungUlang(Kuis $kuis): void
    {
        $kuis->unsetRelation('soal');
        foreach ($kuis->percobaan()->whereNotNull('selesai_at')->get() as $p) {
            $p->setRelation('kuis', $kuis);
            $p->update(['nilai' => $p->hitungNilai()]);
        }
    }

    private function validasi(Request $r): array
    {
        return $r->validate([
            'judul' => 'required|string|max:160',
            'petunjuk' => 'nullable|string|max:5000',
            'mulai' => 'required|date',
            'selesai' => 'required|date|after:mulai',
            'durasi_menit' => 'required|integer|min:5|max:600',
            'maks_pelanggaran' => 'nullable|integer|min:0|max:50',
        ], ['selesai.after' => 'Waktu tutup harus setelah waktu buka.']) + [
            'acak_soal' => $r->boolean('acak_soal'),
            'acak_opsi' => $r->boolean('acak_opsi'),
            'tampilkan_nilai' => $r->boolean('tampilkan_nilai'),
            'anti_curang' => $r->boolean('anti_curang'),
            'maks_pelanggaran' => (int) $r->input('maks_pelanggaran', 0),
        ];
    }
}
