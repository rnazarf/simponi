<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\Ruang;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Pembagian mengajar: mapel apa diajar siapa di kelas mana, plus jadwal & link kelas daring */
class RuangController extends Controller
{
    public function index(Request $r)
    {
        $kelasList = Kelas::orderBy('tingkat')->orderBy('nama')->get();
        $kelas = $kelasList->firstWhere('id', (int) $r->query('kelas')) ?? $kelasList->first();
        $ta = TahunAjaran::aktif();
        $ruang = $kelas ? Ruang::with('mapel', 'guru', 'jadwal')->where('kelas_id', $kelas->id)
            ->where('tahun_ajaran_id', $ta?->id)->get()->sortBy('mapel.nama')->values() : collect();
        $mapel = Mapel::orderBy('nama')->get();
        $guru = User::where('role', 'guru')->where('aktif', true)->orderBy('name')->get();
        return view('admin.ruang', compact('kelasList', 'kelas', 'ta', 'ruang', 'mapel', 'guru'));
    }

    public function simpan(Request $r)
    {
        $ta = TahunAjaran::aktifId();
        abort_unless($ta, 422, 'Belum ada tahun ajaran aktif.');
        $data = $r->validate([
            'kelas_id' => 'required|exists:kelas,id',
            'mapel_id' => 'required|exists:mapel,id',
            'guru_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'guru')],
        ]);
        $ruang = Ruang::firstOrNew(['tahun_ajaran_id' => $ta, 'kelas_id' => $data['kelas_id'], 'mapel_id' => $data['mapel_id']]);
        if (!$ruang->exists) $ruang->kkm = (int) setting('kkm');
        $ruang->guru_id = $data['guru_id'] ?? null;
        $ruang->save();
        return redirect()->route('admin.ruang', ['kelas' => $data['kelas_id']])->with('ok', 'Pembagian mengajar disimpan.');
    }

    public function hapus(Ruang $ruang)
    {
        if ($ruang->pertemuan()->exists() || $ruang->tugas()->exists()) {
            return back()->with('error', 'Ruang belajar sudah berisi materi/tugas. Ganti gurunya saja, jangan dihapus.');
        }
        $ruang->delete();
        return back()->with('ok', 'Ruang belajar dihapus.');
    }
}
