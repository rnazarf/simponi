<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu blok pelajaran berurutan (mis. Rabu jam ke-1 s.d. 4) milik satu versi jadwal.
 * jam_mulai/jam_selesai tetap diisi supaya halaman LMS (beranda, jadwal, link kelas daring) tidak berubah.
 */
class Jadwal extends Model
{
    protected $table = 'jadwal';
    protected $fillable = ['versi_jadwal_id', 'ruang_id', 'guru_id', 'hari', 'jam_ke_mulai', 'jam_ke_selesai', 'jam_mulai', 'jam_selesai', 'dikunci', 'link_meet'];
    protected $casts = ['dikunci' => 'boolean'];

    public const HARI = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];

    public function ruang()
    {
        return $this->belongsTo(Ruang::class);
    }

    public function guru()
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function versi()
    {
        return $this->belongsTo(VersiJadwal::class, 'versi_jadwal_id');
    }

    /** Hanya jadwal dari versi yang berstatus aktif (baris lama tanpa versi tetap ikut) */
    public function scopeAktif($q)
    {
        return $q->where(function ($w) {
            $w->whereNull('jadwal.versi_jadwal_id')
                ->orWhereIn('jadwal.versi_jadwal_id', VersiJadwal::where('status', 'aktif')->select('id'));
        });
    }

    /** Blok yang diajar guru tertentu (guru_id kosong berarti pengampu utama ruang) */
    public function scopeUntukGuru($q, int $guruId)
    {
        return $q->where(function ($w) use ($guruId) {
            $w->where('jadwal.guru_id', $guruId)
                ->orWhere(fn ($x) => $x->whereNull('jadwal.guru_id')->whereIn('jadwal.ruang_id', Ruang::where('guru_id', $guruId)->select('id')));
        });
    }

    /** Guru yang mengajar blok ini */
    public function pengajar(): ?User
    {
        return $this->guru ?? $this->ruang?->guru;
    }

    public function jumlahJam(): int
    {
        return $this->jam_ke_mulai !== null ? $this->jam_ke_selesai - $this->jam_ke_mulai + 1 : 0;
    }
}
