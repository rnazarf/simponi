<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatatanWali extends Model
{
    protected $table = 'catatan_wali';

    protected $fillable = [
        'kelas_id',
        'siswa_id',
        'guru_id',
        'jenis',
        'tanggal',
        'catatan',
        'tindak_lanjut',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public const JENIS = [
        'pembinaan' => 'Pembinaan Disiplin',
        'prestasi' => 'Prestasi / Apresiasi',
        'pelanggaran' => 'Pelanggaran / Masalah',
        'komunikasi_ortu' => 'Komunikasi Orang Tua',
        'umum' => 'Catatan Umum',
    ];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }

    public function siswa()
    {
        return $this->belongsTo(User::class, 'siswa_id');
    }

    public function guru()
    {
        return $this->belongsTo(User::class, 'guru_id');
    }
}
