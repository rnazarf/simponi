@extends('layouts.app')
@section('title', 'Rekap Presensi Perwalian · ' . $kelas->nama)
@section('content')
@include('partials.wali-kepala')

<div class="kepala mt">
  <div>
    <h1><span class="sorot">Presensi Lintas Mata Pelajaran</span></h1>
    <p>Rekap kehadiran gabungan semua mapel kelas {{ $kelas->nama }} bulan {{ $bulan->locale('id')->translatedFormat('F Y') }} &middot; {{ $pertemuan->count() }} pertemuan telah selesai.</p>
  </div>
  <div class="aksi">
    <form class="inline-form" method="get">
      <input type="hidden" name="kelas" value="{{ $kelas->id }}">
      <div class="field">
        <input type="month" name="bulan" value="{{ $bulan->format('Y-m') }}">
      </div>
      <button class="btn garis">Tampilkan</button>
    </form>
    <button class="btn garis" onclick="window.print()">{!! icon('printer', 16) !!}Cetak Rekap</button>
  </div>
</div>

<div class="kartu rapat">
  <div class="tabel-wrap">
    <table>
      <thead>
        <tr>
          <th>No</th>
          <th>Nama Siswa</th>
          <th>NISN</th>
          <th class="angka">Hadir</th>
          <th class="angka">Izin</th>
          <th class="angka">Sakit</th>
          <th class="angka">Alpa</th>
          <th class="angka">% Kehadiran</th>
          <th style="text-align:center">Status</th>
          <th style="text-align:right">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($rekap as $i => $r)
          @php($p = $r['persen'])
          <tr>
            <td>{{ $i + 1 }}</td>
            <td class="tebal">
              <a href="{{ route('wali.siswa', [$r['siswa'], 'kelas' => $kelas->id]) }}">
                {{ $r['siswa']->name }}
              </a>
            </td>
            <td>{{ $r['siswa']->username }}</td>
            <td class="angka">{{ $r['hadir'] }}</td>
            <td class="angka">{{ $r['izin'] }}</td>
            <td class="angka">{{ $r['sakit'] }}</td>
            <td class="angka {{ $r['alpa'] >= 3 ? 'n-rendah tebal' : '' }}">{{ $r['alpa'] }}</td>
            <td class="angka tebal {{ $p !== null && $p < 75 ? 'n-rendah' : ($p !== null && $p < 85 ? 'n-sedang' : 'n-tinggi') }}">
              {{ $p !== null ? $p . '%' : '-' }}
            </td>
            <td style="text-align:center">
              @if ($r['alpa'] >= 3 || ($p !== null && $p < 70))
                <span class="lencana merah">Perhatian Khusus</span>
              @elseif ($r['alpa'] > 0 || ($p !== null && $p < 85))
                <span class="lencana kuning">Perlu Dipantau</span>
              @else
                <span class="lencana hijau">Disiplin</span>
              @endif
            </td>
            <td style="text-align:right">
              <a href="{{ route('wali.siswa', [$r['siswa'], 'kelas' => $kelas->id]) }}" class="btn kecil garis">
                {!! icon('user', 14) !!}Detail
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="10">@include('partials.kosong', ['ikon' => 'check-square', 'judul' => 'Tidak ada data siswa'])</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<p class="kecil-redup mt">
  Keterangan: Menghitung pertemuan daring yang presensinya sudah ditutup dan sesi tatap muka hingga hari ini. Warna merah menandakan alpa 3 kali atau lebih / persentase kehadiran di bawah 75%.
</p>
@endsection
