<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Beban mengajar: satu guru pada satu ruang (kelas + mapel). Satu ruang boleh punya beberapa guru. */
class BebanAjar extends Model
{
    protected $table = 'beban_ajar';
    protected $fillable = ['ruang_id', 'guru_id', 'jam_per_minggu', 'pola_blok'];
    protected $casts = ['pola_blok' => 'array'];

    public function ruang()
    {
        return $this->belongsTo(Ruang::class);
    }

    public function guru()
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    /** Ukuran tiap blok; bila pola kosong, seluruh jam menjadi satu blok */
    public function ukuranBlok(): array
    {
        return $this->pola_blok ?: [(int) $this->jam_per_minggu];
    }

    public function polaTeks(): string
    {
        return implode(' + ', $this->ukuranBlok());
    }
}
