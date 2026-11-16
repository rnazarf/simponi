<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Struktur jam per hari (jam pelajaran, istirahat, upacara, senam) untuk satu tahun ajaran */
class SlotWaktu extends Model
{
    protected $table = 'slot_waktu';
    protected $fillable = ['tahun_ajaran_id', 'hari', 'jam_ke', 'jam_mulai', 'jam_selesai', 'jenis', 'keterangan'];

    public const JENIS = ['kbm' => 'Jam pelajaran', 'istirahat' => 'Istirahat', 'upacara' => 'Upacara', 'senam' => 'Senam'];

    public function scopeKbm($q)
    {
        return $q->where('jenis', 'kbm')->whereNotNull('jam_ke');
    }

    public function mulai(): string
    {
        return substr($this->jam_mulai, 0, 5);
    }

    public function selesai(): string
    {
        return substr($this->jam_selesai, 0, 5);
    }
}
