@extends('layouts.app')
@section('title', 'Koreksi · ' . $percobaan->siswa->name)
@section('content')
@include('partials.ruang-kepala')
<div class="kepala">
  <div><div class="remah"><a href="{{ route('kuis.show', [$ruang, $kuis]) }}">{{ $kuis->judul }}</a> / koreksi</div><h1><span class="sorot">{{ $percobaan->siswa->name }}</span></h1>
    <p>Dikumpulkan {{ tgl($percobaan->selesai_at, true) }} · nilai saat ini {{ $percobaan->nilai !== null ? round($percobaan->nilai, 1) : 'menunggu koreksi esai' }}</p></div>
</div>

@if ($kuis->anti_curang)
  <div class="kartu" style="margin-bottom:16px;border-left:4px solid {{ ($percobaan->jumlah_pelanggaran ?? 0) > 0 ? 'var(--merah)' : 'var(--hijau)' }}">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
      <div style="display:flex;align-items:center;gap:8px">
        <span style="font-size:20px">{{ ($percobaan->jumlah_pelanggaran ?? 0) > 0 ? '⚠️' : '🛡️' }}</span>
        <div>
          <b>Laporan Integritas Kuis (Anti-Curang)</b>
          <div class="kecil-redup">
            @if (($percobaan->jumlah_pelanggaran ?? 0) === 0)
              Siswa mengerjakan dengan tertib tanpa ada aktivitas mencurigakan.
            @else
              Terdeteksi {{ $percobaan->jumlah_pelanggaran }} aktivitas mencurigakan selama kuis.
            @endif
          </div>
        </div>
      </div>
      <span class="lencana {{ ($percobaan->jumlah_pelanggaran ?? 0) > 0 ? 'merah' : 'hijau' }}">
        {{ $percobaan->jumlah_pelanggaran ?? 0 }} Pelanggaran
      </span>
    </div>

    @if (!empty($percobaan->pelanggaran))
      <details style="margin-top:12px;border-top:1px dashed var(--garis);padding-top:10px">
        <summary style="cursor:pointer;font-size:13px;color:var(--tinta);font-weight:600">
          Lihat rincian log aktivitas mencurigakan ({{ count($percobaan->pelanggaran) }} catatan)
        </summary>
        <div style="margin-top:10px;display:grid;gap:6px">
          @foreach ($percobaan->pelanggaran as $idx => $plg)
            <div style="font-size:12.5px;padding:8px 12px;background:var(--kertas-2);border-radius:var(--r-sm);border-left:3px solid var(--merah)">
              <div style="display:flex;justify-content:space-between">
                <b>#{{ $idx + 1 }} · {{ match($plg['jenis'] ?? '') { 'tab_switch' => 'Beralih Tab / Aplikasi', 'blur' => 'Keluar Jendela Kuis', 'copy' => 'Menyalin (Copy) Teks', 'paste' => 'Menempel (Paste) Teks', 'inspect' => 'Klik Kanan / Inspect', 'shortcut' => 'Tombol Pintasan Dilarang', default => $plg['jenis'] ?? 'Aktivitas' } }}</b>
                <span class="kecil-redup">{{ $plg['waktu'] ?? '-' }}</span>
              </div>
              <div style="color:var(--teks-2);margin-top:2px">{{ $plg['keterangan'] ?? '-' }}</div>
            </div>
          @endforeach
        </div>
      </details>
    @endif
  </div>
@endif

<form method="post" class="kartu">
  @csrf
  @foreach ($kuis->soal as $s)
    @php($j = $percobaan->jawaban[$s->id] ?? null)
    <div style="padding:16px 0;border-bottom:1px dashed var(--garis-2)">
      <div class="tebal" style="white-space:pre-line">{{ $s->urutan }}. {{ $s->pertanyaan }}</div>
      @if ($s->jenis === 'pg')
        @php($benar = $j === $s->kunci)
        <div style="margin-top:6px;font-size:14px">Jawaban: <b>{{ $j ? $j . '. ' . ($s->opsi[$j] ?? '') : 'Tidak dijawab' }}</b>
          <span class="lencana {{ $benar ? 'hijau' : 'merah' }}">{{ $benar ? 'Benar' : 'Salah · kunci ' . $s->kunci }}</span> <span class="kecil-redup">bobot {{ $s->bobot }}</span></div>
      @else
        <div class="catatan" style="border-left-color:var(--tinta);margin:8px 0;font-size:14.5px">{!! filled($j) ? teks($j) : '<em class="kecil-redup">Tidak dijawab</em>' !!}</div>
        <div class="inline-form"><div class="field"><label>Skor (0–{{ $s->bobot }})</label>
          <input type="number" name="skor[{{ $s->id }}]" min="0" max="{{ $s->bobot }}" step="0.5" value="{{ $percobaan->nilai_esai[$s->id] ?? '' }}" style="max-width:120px" required></div></div>
      @endif
    </div>
  @endforeach
  @if ($kuis->soal->where('jenis', 'esai')->isNotEmpty())
    <button class="btn mt">{!! icon('check', 16) !!}Simpan koreksi</button>
  @endif
</form>
@endsection
