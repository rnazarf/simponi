<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SlotWaktu;
use App\Models\TahunAjaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Struktur jam per hari: jam pelajaran, istirahat, upacara, senam */
class JamController extends Controller
{
    public function index(Request $r)
    {
        $ta = TahunAjaran::aktif();
        $slot = $ta ? SlotWaktu::where('tahun_ajaran_id', $ta->id)->orderBy('hari')->orderBy('jam_mulai')->get()->groupBy('hari') : collect();
        $ubah = $r->filled('ubah') && $ta ? SlotWaktu::where('tahun_ajaran_id', $ta->id)->find($r->query('ubah')) : null;
        return view('admin.penjadwalan.jam', compact('ta', 'slot', 'ubah'));
    }

    public function simpan(Request $r)
    {
        $ta = TahunAjaran::aktifId();
        abort_unless($ta, 422, 'Belum ada tahun ajaran aktif.');
        $id = $r->integer('id') ?: null;
        $d = $r->validate([
            'hari' => 'required|integer|between:1,6',
            'jenis' => 'required|in:kbm,istirahat,upacara,senam',
            'jam_ke' => 'nullable|integer|min:0|max:30',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
            'keterangan' => 'nullable|string|max:120',
        ], ['jam_selesai.after' => 'Jam selesai harus setelah jam mulai.']);

        if ($d['jenis'] === 'kbm' && ! isset($d['jam_ke'])) {
            return back()->withInput()->with('error', 'Jam pelajaran harus diberi nomor jam ke-.');
        }
        if ($d['jenis'] !== 'kbm') $d['jam_ke'] = null;

        $lain = SlotWaktu::where('tahun_ajaran_id', $ta)->where('hari', $d['hari'])->when($id, fn ($q) => $q->where('id', '!=', $id));
        if ((clone $lain)->where('jam_mulai', '<', $d['jam_selesai'] . ':00')->where('jam_selesai', '>', $d['jam_mulai'] . ':00')->exists()) {
            return back()->withInput()->with('error', 'Waktu bertabrakan dengan baris lain di hari yang sama.');
        }
        if ($d['jam_ke'] !== null && (clone $lain)->where('jam_ke', $d['jam_ke'])->exists()) {
            return back()->withInput()->with('error', 'Nomor jam ke-' . $d['jam_ke'] . ' sudah dipakai di hari itu.');
        }
        $slot = $id ? SlotWaktu::where('tahun_ajaran_id', $ta)->findOrFail($id) : new SlotWaktu();
        $slot->fill($d + ['tahun_ajaran_id' => $ta])->save();
        return redirect()->route('admin.penjadwalan.jam')->with('ok', 'Baris jam disimpan.');
    }

    public function hapus(SlotWaktu $slot)
    {
        $slot->delete();
        return back()->with('ok', 'Baris jam dihapus (beserta pembatasan guru pada jam itu).');
    }

    /** Salin struktur jam satu hari ke hari lain (menggantikan isi hari tujuan) */
    public function salin(Request $r)
    {
        $ta = TahunAjaran::aktifId();
        $d = $r->validate(['dari' => 'required|integer|between:1,6', 'ke' => 'required|array|min:1', 'ke.*' => 'integer|between:1,6']);
        $sumber = SlotWaktu::where('tahun_ajaran_id', $ta)->where('hari', $d['dari'])->get();
        if ($sumber->isEmpty()) return back()->with('error', 'Hari asal belum punya baris jam.');
        DB::transaction(function () use ($ta, $d, $sumber) {
            foreach (array_diff($d['ke'], [$d['dari']]) as $tujuan) {
                SlotWaktu::where('tahun_ajaran_id', $ta)->where('hari', $tujuan)->delete();
                foreach ($sumber as $s) {
                    $baru = $s->replicate();
                    $baru->hari = $tujuan;
                    $baru->save();
                }
            }
        });
        return back()->with('ok', 'Struktur jam disalin.');
    }
}
