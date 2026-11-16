<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BebanAjar;
use App\Models\KetersediaanGuru;
use App\Models\SlotWaktu;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Waktu yang tidak bisa (wajib dihindari) atau sebaiknya dihindari oleh tiap guru */
class KetersediaanController extends Controller
{
    public function index(Request $r)
    {
        $ta = TahunAjaran::aktif();
        $guruList = User::where('role', 'guru')->where('aktif', true)->orderBy('kode_guru')->orderBy('name')->get();
        $guru = $guruList->firstWhere('id', (int) $r->query('guru')) ?? $guruList->first();
        $slots = $ta ? SlotWaktu::where('tahun_ajaran_id', $ta->id)->kbm()->orderBy('hari')->orderBy('jam_ke')->get()->groupBy('hari') : collect();
        $jamKe = $slots->flatten()->pluck('jam_ke')->unique()->sort()->values();
        $status = $guru ? KetersediaanGuru::where('guru_id', $guru->id)->pluck('status', 'slot_waktu_id') : collect();
        $jamMengajar = $guru ? (int) BebanAjar::where('guru_id', $guru->id)->whereHas('ruang', fn ($q) => $q->where('tahun_ajaran_id', $ta?->id))->sum('jam_per_minggu') : 0;
        return view('admin.penjadwalan.ketersediaan', compact('ta', 'guruList', 'guru', 'slots', 'jamKe', 'status', 'jamMengajar'));
    }

    public function simpan(Request $r, User $guru)
    {
        abort_unless($guru->role === 'guru', 404);
        $ta = TahunAjaran::aktifId();
        $d = $r->validate(['s' => 'nullable|array', 's.*' => 'nullable|in:,tidak_bisa,hindari']);
        $idSlot = SlotWaktu::where('tahun_ajaran_id', $ta)->kbm()->pluck('id');
        DB::transaction(function () use ($guru, $idSlot, $d) {
            KetersediaanGuru::where('guru_id', $guru->id)->whereIn('slot_waktu_id', $idSlot)->delete();
            foreach ($d['s'] ?? [] as $slotId => $st) {
                if ($st && $idSlot->contains((int) $slotId)) {
                    KetersediaanGuru::create(['guru_id' => $guru->id, 'slot_waktu_id' => (int) $slotId, 'status' => $st]);
                }
            }
        });
        return redirect()->route('admin.penjadwalan.ketersediaan', ['guru' => $guru->id])->with('ok', 'Ketersediaan ' . $guru->name . ' disimpan.');
    }
}
