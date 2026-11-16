<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pertemuan extends Model
{
    protected $table = 'pertemuan';
    protected $fillable = [
        'ruang_id', 'urutan', 'judul', 'isi', 'video_url',
        'lampiran_path', 'lampiran_nama', 'tanggal',
        'jenis_presensi', 'presensi_buka', 'presensi_tutup', 'terbit'
    ];
    protected $casts = [
        'tanggal' => 'date',
        'presensi_buka' => 'datetime',
        'presensi_tutup' => 'datetime',
        'terbit' => 'boolean'
    ];

    public const JENIS_PRESENSI = [
        'daring' => 'Presensi Daring',
        'tatap_muka' => 'Presensi Tatap Muka',
        'tidak_ada' => 'Tanpa Presensi',
    ];

    public function ruang()
    {
        return $this->belongsTo(Ruang::class);
    }

    public function presensi()
    {
        return $this->hasMany(Presensi::class);
    }

    public function tugas()
    {
        return $this->hasMany(Tugas::class);
    }

    public function isDaring(): bool
    {
        return ($this->jenis_presensi ?? 'daring') === 'daring';
    }

    public function isTatapMuka(): bool
    {
        return ($this->jenis_presensi ?? 'daring') === 'tatap_muka';
    }

    public function adaPresensi(): bool
    {
        return ($this->jenis_presensi ?? 'daring') !== 'tidak_ada';
    }

    public function labelJenisPresensi(): string
    {
        return self::JENIS_PRESENSI[$this->jenis_presensi ?? 'daring'] ?? 'Presensi Daring';
    }

    public function presensiTerbuka(): bool
    {
        if (!$this->isDaring()) return false;
        return $this->presensi_buka && $this->presensi_tutup && now()->between($this->presensi_buka, $this->presensi_tutup);
    }

    public function presensiTutup(): bool
    {
        if ($this->isDaring()) {
            return (bool) ($this->presensi_tutup && $this->presensi_tutup->isPast());
        }
        if ($this->isTatapMuka()) {
            return $this->tanggal->isPast() || $this->tanggal->isToday();
        }
        return false;
    }

    /** ID video YouTube dari berbagai bentuk link (watch, youtu.be, shorts, embed) */
    public function youtubeId(): ?string
    {
        if (!$this->video_url) return null;
        preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/))([A-Za-z0-9_-]{11})~', $this->video_url, $m);
        return $m[1] ?? null;
    }
}
