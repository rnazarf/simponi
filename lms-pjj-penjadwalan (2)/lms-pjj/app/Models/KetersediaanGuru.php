<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Slot yang tidak bisa (tidak_bisa) atau sebaiknya dihindari (hindari) oleh seorang guru */
class KetersediaanGuru extends Model
{
    protected $table = 'ketersediaan_guru';
    protected $fillable = ['guru_id', 'slot_waktu_id', 'status', 'keterangan'];

    public function guru()
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function slot()
    {
        return $this->belongsTo(SlotWaktu::class, 'slot_waktu_id');
    }
}
