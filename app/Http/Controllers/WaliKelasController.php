<?php

namespace App\Http\Controllers;

use App\Models\CatatanWali;
use App\Models\Kelas;
use App\Models\Kuis;
use App\Models\Pengumpulan;
use App\Models\Percobaan;
use App\Models\Pertemuan;
use App\Models\Presensi;
use App\Models\Ruang;
use App\Models\TahunAjaran;
use App\Models\Tugas;
use App\Models\User;
use App\Support\Rapor;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WaliKelasController extends Controller
{
    /** Mendapatkan daftar rombel yang diampu sebagai wali kelas dan rombel aktif terpilih */
    private function resolveKelas(Request $request): array
    {
        $u = auth()->user();
        if ($u->role === 'guru') {
            $kelasList = $u->kelasWali()->with('jurusan', 'wali')->orderBy('nama')->get();
            if ($kelasList->isEmpty()) {
                abort(403, 'Menu ini hanya dapat diakses oleh guru yang ditugaskan sebagai wali kelas.');
            }
        } else {
            // Admin dapat melihat seluruh kelas
            $kelasList = Kelas::with('jurusan', 'wali')->orderBy('tingkat')->orderBy('nama')->get();
            if ($kelasList->isEmpty()) {
                abort(404, 'Belum ada data kelas di sistem.');
            }
        }

        $id = (int) $request->query('kelas');
        $kelas = $kelasList->firstWhere('id', $id) ?? $kelasList->first();

        return [$kelasList, $kelas];
    }

    /** Dashboard & Ringkasan Kelas Perwalian */
    public function index(Request $request)
    {
        [$kelasList, $kelas] = $this->resolveKelas($request);

        $siswa = $kelas->siswa()->where('aktif', true)->get();
        $ruang = Ruang::with('mapel', 'guru')
            ->where('kelas_id', $kelas->id)
            ->where('tahun_ajaran_id', TahunAjaran::aktifId())
            ->get();
        $ruangIds = $ruang->pluck('id');

        // Pertemuan bertanggal hari ini di semua mapel kelas ini
        $pertemuanHariIni = Pertemuan::with('ruang.mapel')
            ->whereIn('ruang_id', $ruangIds)
            ->whereDate('tanggal', today())
            ->get();
        $presensiHariIni = Presensi::whereIn('pertemuan_id', $pertemuanHariIni->pluck('id'))->get();
        $statHariIni = $presensiHariIni->countBy('status');

        // Pertemuan semester untuk menghitung akumulasi presensi & alpa
        $pertemuanSemester = Pertemuan::whereIn('ruang_id', $ruangIds)
            ->where(function ($q) {
                $q->where(function ($qd) {
                    $qd->where(fn ($sub) => $sub->where('jenis_presensi', 'daring')->orWhereNull('jenis_presensi'))
                       ->whereNotNull('presensi_tutup')
                       ->where('presensi_tutup', '<', now());
                })->orWhere(function ($qt) {
                    $qt->where('jenis_presensi', 'tatap_muka')
                       ->whereDate('tanggal', '<=', today());
                });
            })->pluck('id');

        $dataPresensi = Presensi::whereIn('pertemuan_id', $pertemuanSemester)->get()->groupBy('siswa_id');

        // Statistik per siswa (Hadir, Izin, Sakit, Alpa, Persen)
        $rekapSiswa = $siswa->mapWithKeys(function ($s) use ($dataPresensi, $pertemuanSemester) {
            $c = ($dataPresensi[$s->id] ?? collect())->countBy('status');
            $hadir = $c['hadir'] ?? 0;
            $izin = $c['izin'] ?? 0;
            $sakit = $c['sakit'] ?? 0;
            $tercatat = $c->sum();
            $alpa = ($c['alpa'] ?? 0) + max(0, $pertemuanSemester->count() - $tercatat);
            $wajib = $hadir + $alpa;
            $persen = $wajib > 0 ? round($hadir / $wajib * 100) : 100;
            return [$s->id => [
                'hadir' => $hadir,
                'izin' => $izin,
                'sakit' => $sakit,
                'alpa' => $alpa,
                'persen' => $persen,
            ]];
        });

        // Deteksi Tugas & Kuis Lewat Tenggat tapi Belum Diserahkan
        $tugasLewat = Tugas::whereIn('ruang_id', $ruangIds)->where('tenggat', '<', now())->get();
        $pengumpulan = Pengumpulan::whereIn('tugas_id', $tugasLewat->pluck('id'))->get()->groupBy('siswa_id');

        $kuisTutup = Kuis::whereIn('ruang_id', $ruangIds)->where('selesai', '<', now())->where('terbit', true)->get();
        $percobaan = Percobaan::whereIn('kuis_id', $kuisTutup->pluck('id'))->whereNotNull('selesai_at')->get()->groupBy('siswa_id');

        $tunggakanSiswa = $siswa->mapWithKeys(function ($s) use ($tugasLewat, $pengumpulan, $kuisTutup, $percobaan) {
            $sudahTugas = ($pengumpulan[$s->id] ?? collect())->pluck('tugas_id')->all();
            $belumTugas = $tugasLewat->reject(fn ($t) => in_array($t->id, $sudahTugas))->count();

            $sudahKuis = ($percobaan[$s->id] ?? collect())->pluck('kuis_id')->all();
            $belumKuis = $kuisTutup->reject(fn ($k) => in_array($k->id, $sudahKuis))->count();

            return [$s->id => [
                'tugas' => $belumTugas,
                'kuis' => $belumKuis,
                'total' => $belumTugas + $belumKuis,
            ]];
        });

        // Siswa Perlu Perhatian Khusus:
        // - Alpa >= 3, ATAU
        // - Tunggakan tugas/kuis >= 2, ATAU
        // - Belum aktif SIMPONI > 3 hari
        $perluPerhatian = $siswa->filter(function ($s) use ($rekapSiswa, $tunggakanSiswa) {
            $alpa = $rekapSiswa[$s->id]['alpa'] ?? 0;
            $tunggak = $tunggakanSiswa[$s->id]['total'] ?? 0;
            $lamaTidakLogin = !$s->terakhir_aktif_at || $s->terakhir_aktif_at->lt(now()->subDays(3));
            return $alpa >= 3 || $tunggak >= 2 || $lamaTidakLogin;
        });

        // 5 Catatan Wali Kelas Terbaru
        $catatanTerbaru = CatatanWali::with('siswa', 'guru')
            ->where('kelas_id', $kelas->id)
            ->latest('tanggal')
            ->limit(5)
            ->get();

        return view('wali.index', compact(
            'kelasList',
            'kelas',
            'siswa',
            'ruang',
            'pertemuanHariIni',
            'statHariIni',
            'rekapSiswa',
            'tunggakanSiswa',
            'perluPerhatian',
            'catatanTerbaru'
        ));
    }

    /** Rekap Presensi Lintas Seluruh Mata Pelajaran */
    public function presensi(Request $request)
    {
        [$kelasList, $kelas] = $this->resolveKelas($request);

        $bulanStr = $request->query('bulan') ?: now()->format('Y-m');
        $bulan = Carbon::parse($bulanStr . '-01');

        $siswa = $kelas->siswa()->where('aktif', true)->get();
        $ruangIds = Ruang::where('kelas_id', $kelas->id)
            ->where('tahun_ajaran_id', TahunAjaran::aktifId())
            ->pluck('id');

        $pertemuan = Pertemuan::with('ruang.mapel')
            ->whereIn('ruang_id', $ruangIds)
            ->where(function ($q) {
                $q->where(function ($qd) {
                    $qd->where(fn ($sub) => $sub->where('jenis_presensi', 'daring')->orWhereNull('jenis_presensi'))
                       ->whereNotNull('presensi_tutup')
                       ->where('presensi_tutup', '<', now());
                })->orWhere(function ($qt) {
                    $qt->where('jenis_presensi', 'tatap_muka')
                       ->whereDate('tanggal', '<=', today());
                });
            })
            ->whereBetween('tanggal', [$bulan->copy()->startOfMonth(), $bulan->copy()->endOfMonth()])
            ->get();

        $dataPresensi = Presensi::whereIn('pertemuan_id', $pertemuan->pluck('id'))->get()->groupBy('siswa_id');

        $rekap = $siswa->map(function ($s) use ($dataPresensi, $pertemuan) {
            $c = ($dataPresensi[$s->id] ?? collect())->countBy('status');
            $hadir = $c['hadir'] ?? 0;
            $izin = $c['izin'] ?? 0;
            $sakit = $c['sakit'] ?? 0;
            $tercatat = $c->sum();
            $alpa = ($c['alpa'] ?? 0) + max(0, $pertemuan->count() - $tercatat);
            $wajib = $hadir + $alpa;
            $persen = $wajib > 0 ? round($hadir / $wajib * 100) : null;
            return [
                'siswa' => $s,
                'hadir' => $hadir,
                'izin' => $izin,
                'sakit' => $sakit,
                'alpa' => $alpa,
                'persen' => $persen,
            ];
        });

        return view('wali.presensi', compact('kelasList', 'kelas', 'bulan', 'pertemuan', 'rekap'));
    }

    /** Monitoring Tugas & Kuis Lintas Mapel (Deteksi Tunggakan Siswa) */
    public function tugas(Request $request)
    {
        [$kelasList, $kelas] = $this->resolveKelas($request);

        $siswa = $kelas->siswa()->where('aktif', true)->get();
        $ruang = Ruang::with('mapel', 'guru')
            ->where('kelas_id', $kelas->id)
            ->where('tahun_ajaran_id', TahunAjaran::aktifId())
            ->get();
        $ruangIds = $ruang->pluck('id');

        // Seluruh Tugas
        $daftarTugas = Tugas::with('ruang.mapel', 'ruang.guru')
            ->whereIn('ruang_id', $ruangIds)
            ->orderByDesc('tenggat')
            ->get();

        $kumpulGrouped = Pengumpulan::whereIn('tugas_id', $daftarTugas->pluck('id'))
            ->get()
            ->groupBy('tugas_id');

        $tugasStats = $daftarTugas->map(function ($t) use ($siswa, $kumpulGrouped) {
            $kumpulan = $kumpulGrouped[$t->id] ?? collect();
            $dikumpulkanIds = $kumpulan->pluck('siswa_id')->all();
            $belumKumpul = $siswa->reject(fn ($s) => in_array($s->id, $dikumpulkanIds));

            return [
                'tugas' => $t,
                'total_siswa' => $siswa->count(),
                'jumlah_kumpul' => $kumpulan->count(),
                'jumlah_belum' => $belumKumpul->count(),
                'belum_kumpul' => $belumKumpul,
                'lewat_tenggat' => $t->lewatTenggat(),
            ];
        });

        // Rekap Siswa dengan Tagihan Tugas Belum Selesai (format siap broadcast WA)
        $tunggakanPerSiswa = [];
        foreach ($siswa as $s) {
            $tagihan = [];
            foreach ($daftarTugas as $t) {
                $kumpulan = $kumpulGrouped[$t->id] ?? collect();
                if (!$kumpulan->contains('siswa_id', $s->id) && $t->lewatTenggat()) {
                    $tagihan[] = $t->ruang->mapel->nama . ' (' . $t->judul . ')';
                }
            }
            if (!empty($tagihan)) {
                $tunggakanPerSiswa[$s->name] = $tagihan;
            }
        }

        return view('wali.tugas', compact('kelasList', 'kelas', 'siswa', 'tugasStats', 'tunggakanPerSiswa'));
    }

    /** Leger Nilai Kelas (Rata-rata Nilai Tugas, Kuis, Akhir Semua Mapel) */
    public function nilai(Request $request)
    {
        [$kelasList, $kelas] = $this->resolveKelas($request);

        $ruang = Ruang::with('mapel', 'kelas')
            ->where('kelas_id', $kelas->id)
            ->where('tahun_ajaran_id', TahunAjaran::aktifId())
            ->get()
            ->sortBy('mapel.nama')
            ->values();

        $siswa = $kelas->siswa()->where('aktif', true)->get();

        $nilai = [];
        foreach ($ruang as $ru) {
            $rekapRapor = Rapor::ruang($ru);
            foreach ($rekapRapor['baris'] as $b) {
                $nilai[$b['siswa']->id][$ru->id] = $b['akhir'];
            }
        }

        // Hitung rata-rata tiap siswa dan peringkat
        $siswaRekap = $siswa->map(function ($s) use ($ruang, $nilai) {
            $daftarNilai = [];
            foreach ($ruang as $ru) {
                if (isset($nilai[$s->id][$ru->id]) && $nilai[$s->id][$ru->id] !== null) {
                    $daftarNilai[] = (float) $nilai[$s->id][$ru->id];
                }
            }
            $rata = count($daftarNilai) > 0 ? round(array_sum($daftarNilai) / count($daftarNilai), 1) : null;
            return [
                'siswa' => $s,
                'rata' => $rata,
                'tuntas' => count($daftarNilai),
            ];
        })->sortByDesc('rata')->values();

        // Peringkat (ranking)
        $ranking = [];
        foreach ($siswaRekap as $idx => $sr) {
            $ranking[$sr['siswa']->id] = $idx + 1;
        }

        if ($request->query('csv')) {
            return $this->exportCsvLeger($kelas, $ruang, $siswaRekap, $nilai, $ranking);
        }

        return view('wali.nilai', compact('kelasList', 'kelas', 'ruang', 'siswa', 'nilai', 'siswaRekap', 'ranking'));
    }

    /** Export Leger Nilai ke CSV */
    private function exportCsvLeger($kelas, $ruang, $siswaRekap, $nilai, $ranking)
    {
        $namaFile = 'Leger_Nilai_' . str_replace(' ', '_', $kelas->nama) . '_' . date('Ymd_His') . '.csv';

        return new StreamedResponse(function () use ($ruang, $siswaRekap, $nilai, $ranking) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 agar terbaca dengan baik di Microsoft Excel
            fputs($handle, "\xEF\xBB\xBF");

            $header = array_merge(['Ranking', 'Nama Siswa', 'NISN'], $ruang->map(fn ($r) => $r->mapel->nama)->all(), ['Rata-Rata']);
            fputcsv($handle, $header);

            foreach ($siswaRekap as $sr) {
                $s = $sr['siswa'];
                $baris = [
                    $ranking[$s->id] ?? '-',
                    $s->name,
                    $s->username,
                ];
                foreach ($ruang as $ru) {
                    $baris[] = $nilai[$s->id][$ru->id] ?? '-';
                }
                $baris[] = $sr['rata'] ?? '-';
                fputcsv($handle, $baris);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$namaFile}\"",
        ]);
    }

    /** Catatan Pembinaan & Konseling Siswa */
    public function catatan(Request $request)
    {
        [$kelasList, $kelas] = $this->resolveKelas($request);

        $siswa = $kelas->siswa()->where('aktif', true)->get();

        $query = CatatanWali::with('siswa', 'guru')
            ->where('kelas_id', $kelas->id);

        if ($request->filled('siswa_id')) {
            $query->where('siswa_id', (int) $request->siswa_id);
        }
        if ($request->filled('jenis')) {
            $query->where('jenis', $request->jenis);
        }

        $daftarCatatan = $query->latest('tanggal')->latest('id')->paginate(15)->withQueryString();

        return view('wali.catatan', compact('kelasList', 'kelas', 'siswa', 'daftarCatatan'));
    }

    /** Simpan Catatan Pembinaan Baru */
    public function simpanCatatan(Request $request)
    {
        [$kelasList, $kelas] = $this->resolveKelas($request);

        $data = $request->validate([
            'siswa_id' => ['required', 'exists:users,id'],
            'jenis' => ['required', 'in:pembinaan,prestasi,pelanggaran,komunikasi_ortu,umum'],
            'tanggal' => ['required', 'date'],
            'catatan' => ['required', 'string', 'max:2000'],
            'tindak_lanjut' => ['nullable', 'string', 'max:2000'],
        ]);

        // Pastikan siswa benar-benar anggota kelas perwalian ini
        $siswa = User::where('id', $data['siswa_id'])->where('kelas_id', $kelas->id)->firstOrFail();

        CatatanWali::create([
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'guru_id' => auth()->id(),
            'jenis' => $data['jenis'],
            'tanggal' => $data['tanggal'],
            'catatan' => $data['catatan'],
            'tindak_lanjut' => $data['tindak_lanjut'] ?? null,
        ]);

        return back()->with('sukses', 'Catatan pembinaan siswa ' . $siswa->name . ' berhasil disimpan.');
    }

    /** Hapus Catatan Pembinaan */
    public function hapusCatatan(Request $request, CatatanWali $catatan)
    {
        [$kelasList, $kelas] = $this->resolveKelas($request);

        if ($catatan->kelas_id !== $kelas->id) {
            abort(403);
        }

        $catatan->delete();

        return back()->with('sukses', 'Catatan pembinaan berhasil dihapus.');
    }

    /** Profil 360° Satu Siswa Perwalian */
    public function siswa(Request $request, User $siswa)
    {
        [$kelasList, $kelas] = $this->resolveKelas($request);

        if ($siswa->kelas_id !== $kelas->id || $siswa->role !== 'siswa') {
            abort(404, 'Siswa tidak ditemukan dalam kelas ini.');
        }

        $ruang = Ruang::with('mapel', 'guru')
            ->where('kelas_id', $kelas->id)
            ->where('tahun_ajaran_id', TahunAjaran::aktifId())
            ->get();
        $ruangIds = $ruang->pluck('id');

        // Presensi Siswa
        $pertemuanTutup = Pertemuan::with('ruang.mapel')
            ->whereIn('ruang_id', $ruangIds)
            ->where(function ($q) {
                $q->where(function ($qd) {
                    $qd->where(fn ($sub) => $sub->where('jenis_presensi', 'daring')->orWhereNull('jenis_presensi'))
                       ->whereNotNull('presensi_tutup')
                       ->where('presensi_tutup', '<', now());
                })->orWhere(function ($qt) {
                    $qt->where('jenis_presensi', 'tatap_muka')
                       ->whereDate('tanggal', '<=', today());
                });
            })
            ->orderByDesc('tanggal')
            ->get();

        $presensiList = Presensi::whereIn('pertemuan_id', $pertemuanTutup->pluck('id'))
            ->where('siswa_id', $siswa->id)
            ->get()
            ->keyBy('pertemuan_id');

        $cPresensi = $presensiList->countBy('status');
        $hadir = $cPresensi['hadir'] ?? 0;
        $izin = $cPresensi['izin'] ?? 0;
        $sakit = $cPresensi['sakit'] ?? 0;
        $alpa = ($cPresensi['alpa'] ?? 0) + max(0, $pertemuanTutup->count() - $presensiList->count());

        // Riwayat absensi tidak hadir (Izin, Sakit, Alpa)
        $riwayatAbsen = $pertemuanTutup->filter(function ($p) use ($presensiList) {
            $st = $presensiList[$p->id]->status ?? 'alpa';
            return $st !== 'hadir';
        })->map(function ($p) use ($presensiList) {
            return [
                'pertemuan' => $p,
                'status' => $presensiList[$p->id]->status ?? 'alpa',
                'keterangan' => $presensiList[$p->id]->keterangan ?? '-',
            ];
        });

        // Tugas & Kuis Siswa
        $daftarTugas = Tugas::with('ruang.mapel')->whereIn('ruang_id', $ruangIds)->orderByDesc('tenggat')->get();
        $kumpulGrouped = Pengumpulan::whereIn('tugas_id', $daftarTugas->pluck('id'))
            ->where('siswa_id', $siswa->id)
            ->get()
            ->keyBy('tugas_id');

        // Nilai Rapor Siswa per Mapel
        $nilaiMapel = [];
        foreach ($ruang as $ru) {
            $rapor = Rapor::ruang($ru);
            $barisSiswa = collect($rapor['baris'])->firstWhere('siswa.id', $siswa->id);
            $nilaiMapel[$ru->id] = [
                'ruang' => $ru,
                'akhir' => $barisSiswa['akhir'] ?? null,
                'rata_tugas' => $barisSiswa['rata_tugas'] ?? null,
                'rata_kuis' => $barisSiswa['rata_kuis'] ?? null,
                'kehadiran' => $barisSiswa['kehadiran'] ?? null,
            ];
        }

        // Catatan Khusus Siswa Ini
        $catatanSiswa = CatatanWali::with('guru')
            ->where('siswa_id', $siswa->id)
            ->latest('tanggal')
            ->get();

        return view('wali.siswa', compact(
            'kelasList',
            'kelas',
            'siswa',
            'hadir',
            'izin',
            'sakit',
            'alpa',
            'pertemuanTutup',
            'riwayatAbsen',
            'daftarTugas',
            'kumpulGrouped',
            'nilaiMapel',
            'catatanSiswa'
        ));
    }
}
