@extends('layouts.app')
@section('title', 'Leger Nilai Perwalian · ' . $kelas->nama)
@section('content')
@include('partials.wali-kepala')

<div class="kepala mt">
  <div>
    <h1><span class="sorot">Leger Nilai Kelas</span></h1>
    <p>Nilai akhir komprehensif seluruh mata pelajaran kelas {{ $kelas->nama }} tahun ajaran {{ \App\Models\TahunAjaran::aktif()?->label() }}.</p>
  </div>
  <div class="aksi">
    <a href="{{ route('wali.nilai', ['kelas' => $kelas->id, 'csv' => 1]) }}" class="btn garis">
      {!! icon('download', 16) !!}Export Excel/CSV
    </a>
    <button class="btn garis" onclick="window.print()">
      {!! icon('printer', 16) !!}Cetak Leger
    </button>
  </div>
</div>

<div class="kartu rapat">
  <div class="tabel-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:40px;text-align:center">Rank</th>
          <th>Nama Siswa</th>
          <th>NISN</th>
          @foreach ($ruang as $ru)
            <th class="angka" title="{{ $ru->mapel->nama }} (KKM {{ $ru->kkm }})">
              {{ $ru->mapel->kode ?? \Illuminate\Support\Str::limit($ru->mapel->nama, 8) }}
            </th>
          @endforeach
          <th class="angka" style="background:var(--garis-lemah);font-weight:700">Rata-Rata</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($siswaRekap as $sr)
          @php($s = $sr['siswa'])
          <tr>
            <td style="text-align:center;font-weight:700">
              @if ($ranking[$s->id] <= 3)
                <span class="lencana kuning" style="font-weight:800">#{{ $ranking[$s->id] }}</span>
              @else
                <span class="kecil-redup">#{{ $ranking[$s->id] }}</span>
              @endif
            </td>
            <td class="tebal">
              <a href="{{ route('wali.siswa', [$s, 'kelas' => $kelas->id]) }}">
                {{ $s->name }}
              </a>
            </td>
            <td>{{ $s->username }}</td>
            @foreach ($ruang as $ru)
              @php($n = $nilai[$s->id][$ru->id] ?? null)
              <td class="angka {{ $n !== null && $n < $ru->kkm ? 'n-rendah tebal' : '' }}">
                {{ $n !== null ? $n : '-' }}
              </td>
            @endforeach
            <td class="angka tebal" style="background:var(--garis-lemah)">
              {{ $sr['rata'] !== null ? number_format($sr['rata'], 1) : '-' }}
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="{{ 4 + $ruang->count() }}">
              @include('partials.kosong', ['ikon' => 'award', 'judul' => 'Belum Ada Data Nilai'])
            </td>
          </tr>
        @endforelse
      </tbody>
      @if ($siswaRekap->isNotEmpty() && $ruang->isNotEmpty())
        <tfoot>
          <tr style="font-weight:700;background:var(--garis-lemah)">
            <td colspan="3" style="text-align:right">Rata-Rata Kelas:</td>
            @foreach ($ruang as $ru)
              @php($vals = [])
              @foreach ($siswa as $s)
                @if (isset($nilai[$s->id][$ru->id]) && $nilai[$s->id][$ru->id] !== null)
                  @php($vals[] = (float) $nilai[$s->id][$ru->id])
                @endif
              @endforeach
              @php($rataMapel = count($vals) > 0 ? round(array_sum($vals) / count($vals), 1) : null)
              <td class="angka {{ $rataMapel !== null && $rataMapel < $ru->kkm ? 'n-rendah' : '' }}">
                {{ $rataMapel !== null ? number_format($rataMapel, 1) : '-' }}
              </td>
            @endforeach
            <td class="angka">
              @php($semuaRata = $siswaRekap->pluck('rata')->filter()->all())
              {{ count($semuaRata) > 0 ? number_format(array_sum($semuaRata) / count($semuaRata), 1) : '-' }}
            </td>
          </tr>
        </tfoot>
      @endif
    </table>
  </div>
</div>

<p class="kecil-redup mt">
  Keterangan: Nilai berwarna merah menunjukkan nilai berada di bawah KKM mata pelajaran terkait. Nilai akhir dihitung otomatis secara berbobot berdasarkan tugas, kuis, dan keaktifan presensi.
</p>
@endsection
