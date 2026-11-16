<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Satu versi jadwal pelajaran (draf hasil solver, jadwal aktif, atau arsip). Hanya satu 'aktif' per tahun ajaran. */
class VersiJadwal extends Model
{
    protected $table = 'versi_jadwal';
    protected $fillable = ['tahun_ajaran_id', 'nama', 'status', 'sumber', 'status_solver', 'hasil_solver', 'dibuat_oleh'];
    protected $casts = ['hasil_solver' => 'array'];

    public function tahunAjaran()
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function jadwal()
    {
        return $this->hasMany(Jadwal::class);
    }

    /** Versi aktif untuk tahun ajaran (default: tahun ajaran aktif) */
    public static function aktif(?int $tahunAjaranId = null): ?self
    {
        return static::where('tahun_ajaran_id', $tahunAjaranId ?? TahunAjaran::aktifId())->where('status', 'aktif')->first();
    }

    public function sedangDihitung(): bool
    {
        return in_array($this->status_solver, ['menunggu', 'berjalan'], true);
    }
}
