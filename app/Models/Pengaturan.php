<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Pengaturan extends Model
{
    protected $table = 'pengaturan';
    protected $primaryKey = 'kunci';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $fillable = ['kunci', 'nilai'];

    public const BAWAAN = [
        'nama_sekolah' => 'SMK PPN Banjarbaru',
        'jenjang' => 'SMK',
        'npsn' => '30314295',
        'alamat' => 'Jl. Puteri Junjung Buih No. 15, Banjarbaru, Kalimantan Selatan 70711',
        'kepala_sekolah' => 'Angga Tri Aditia P, S.Pd., M.Pd.',
        'kkm' => '75',
        'presensi_daring' => '1',
        'gemini_api_key' => '',
        'gemini_model' => 'gemini-2.5-flash',
    ];

    public static function presensiDaringAktif(): bool
    {
        return static::ambil('presensi_daring') !== '0';
    }

    public static function geminiApiKey(): string
    {
        return (string) (config('services.gemini.api_key') ?: static::ambil('gemini_api_key'));
    }

    public static function geminiModel(): string
    {
        return (string) (config('services.gemini.model') ?: (static::ambil('gemini_model') ?: 'gemini-2.5-flash'));
    }

    public static function geminiTersedia(): bool
    {
        return !empty(static::geminiApiKey());
    }

    public static function ambil(string $kunci): string
    {
        $semua = Cache::rememberForever('pengaturan', fn () => static::pluck('nilai', 'kunci')->all());
        return (string) ($semua[$kunci] ?? self::BAWAAN[$kunci] ?? '');
    }

    public static function simpan(array $data): void
    {
        foreach ($data as $k => $v) {
            static::updateOrCreate(['kunci' => $k], ['nilai' => $v]);
        }
        Cache::forget('pengaturan');
    }
}
