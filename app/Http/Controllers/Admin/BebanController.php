<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BebanAjar;
use App\Models\Kelas;
use App\Models\Ruang;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Beban mengajar: berapa jam per minggu dan bagaimana dipecah menjadi blok, per guru per ruang */
class BebanController extends Controller
{
    public function index(Request $r)
    {
        $ta = TahunAjaran::aktif();
        $kelasList = Kelas::orderBy('tingkat')->orderBy('nama')->get();
        $kelas = $kelasList->firstWhere('id', (int) $r->query('kelas')) ?? $kelasList->first();
        $ruang = $kelas && $ta ? Ruang::with('mapel', 'beban.guru')->where('kelas_id', $kelas->id)->where('tahun_ajaran_id', $ta->id)->get()->sortBy('mapel.nama')->values() : collect();
        $guru = User::where('role', 'guru')->where('aktif', true)->orderBy('kode_guru')->orderBy('name')->get();
        return view('admin.penjadwalan.beban', compact('ta', 'kelasList', 'kelas', 'ruang', 'guru'));
    }

    public function simpan(Request $r)
    {
        $ta = TahunAjaran::aktifId();
        $d = $r->validate([
            'ruang_id' => ['required', Rule::exists('ruang', 'id')->where('tahun_ajaran_id', $ta)],
            'guru_id' => ['required', Rule::exists('users', 'id')->where('role', 'guru')],
            'jam_per_minggu' => 'required|integer|min:1|max:30',
            'pola' => ['nullable', 'regex:/^\s*\d+(\s*[,+]\s*\d+)*\s*$/'],
        ], ['pola.regex' => 'Pola blok ditulis seperti 4+2 atau 3,3.']);

        $pola = null;
        if (! empty($d['pola'])) {
            $pola = array_map('intval', preg_split('/\s*[,+]\s*/', trim($d['pola'])));
            if (min($pola) < 1 || array_sum($pola) !== (int) $d['jam_per_minggu']) {
                return back()->withInput()->with('error', 'Jumlah pola blok (' . implode('+', $pola) . ') harus sama dengan jam per minggu (' . $d['jam_per_minggu'] . ').');
            }
        }
        BebanAjar::updateOrCreate(
            ['ruang_id' => $d['ruang_id'], 'guru_id' => $d['guru_id']],
            ['jam_per_minggu' => $d['jam_per_minggu'], 'pola_blok' => $pola],
        );
        $ruang = Ruang::find($d['ruang_id']);
        if (! $ruang->guru_id) $ruang->update(['guru_id' => $d['guru_id']]); // guru pertama menjadi pengampu utama di LMS
        return redirect()->route('admin.penjadwalan.beban', ['kelas' => $ruang->kelas_id])->with('ok', 'Beban ajar disimpan.');
    }

    public function hapus(BebanAjar $beban)
    {
        $beban->delete();
        return back()->with('ok', 'Beban ajar dihapus.');
    }
}
