@extends('layouts.app')
@section('title', 'Struktur jam')
@section('content')
<div class="kepala">
  <div><h1><span class="sorot">Struktur jam</span></h1><p>Jam pelajaran, istirahat, upacara, dan senam per hari · {{ $ta?->label() ?? 'tahun ajaran belum aktif' }}</p></div>
</div>
@include('admin.penjadwalan._tab')
@if (!$ta)
  <div class="flash error">Belum ada tahun ajaran aktif.</div>
@else
<div class="grid g-utama">
  <div>
    @foreach (\App\Models\Jadwal::HARI as $no => $namaHari)
      @php $daftar = $slot[$no] ?? collect(); @endphp
      @continue($daftar->isEmpty() && $no === 6)
      <div class="kartu rapat">
        <h2>{{ $namaHari }} <span class="kanan kecil-redup">{{ $daftar->where('jenis', 'kbm')->count() }} jam pelajaran</span></h2>
        <div class="tabel-wrap mt"><table>
          <thead><tr><th>Jam ke</th><th>Waktu</th><th>Jenis</th><th>Keterangan</th><th></th></tr></thead>
          <tbody>
            @forelse ($daftar as $s)
              <tr>
                <td class="tebal">{{ $s->jam_ke ?? '-' }}</td>
                <td>{{ $s->mulai() }}–{{ $s->selesai() }}</td>
                <td>@if ($s->jenis === 'kbm')<span class="lencana biru">Pelajaran</span>@else<span class="lencana">{{ \App\Models\SlotWaktu::JENIS[$s->jenis] }}</span>@endif</td>
                <td class="kecil-redup">{{ $s->keterangan }}</td>
                <td class="angka" style="white-space:nowrap"><a class="ikon-btn" href="{{ route('admin.penjadwalan.jam', ['ubah' => $s->id]) }}" title="Ubah">{!! icon('edit', 16) !!}</a>
                  @include('partials.hapus', ['url' => route('admin.penjadwalan.jam.hapus', $s), 'pesan' => 'Hapus baris jam ini?'])</td>
              </tr>
            @empty
              <tr><td colspan="5" class="kecil-redup">Belum ada baris jam di hari ini.</td></tr>
            @endforelse
          </tbody>
        </table></div>
      </div>
    @endforeach
  </div>
  <div>
    <form method="post" action="{{ route('admin.penjadwalan.jam.simpan') }}" class="kartu">
      @csrf <input type="hidden" name="id" value="{{ $ubah?->id }}">
      <h2>{!! icon($ubah ? 'edit' : 'plus', 18) !!}{{ $ubah ? 'Ubah baris jam' : 'Tambah baris jam' }}</h2>
      <div class="baris-form">
        <div class="field"><label>Hari</label><select name="hari">@foreach (\App\Models\Jadwal::HARI as $no => $h)<option value="{{ $no }}" @selected((int) old('hari', $ubah?->hari ?? 1) === $no)>{{ $h }}</option>@endforeach</select></div>
        <div class="field"><label>Jenis</label><select name="jenis">@foreach (\App\Models\SlotWaktu::JENIS as $k => $n)<option value="{{ $k }}" @selected(old('jenis', $ubah?->jenis ?? 'kbm') === $k)>{{ $n }}</option>@endforeach</select></div>
      </div>
      <div class="field"><label>Jam ke- (hanya jam pelajaran)</label><input type="number" name="jam_ke" min="0" max="30" value="{{ old('jam_ke', $ubah?->jam_ke) }}"></div>
      <div class="baris-form">
        <div class="field"><label>Mulai</label><input type="time" name="jam_mulai" value="{{ old('jam_mulai', $ubah?->mulai()) }}" required></div>
        <div class="field"><label>Selesai</label><input type="time" name="jam_selesai" value="{{ old('jam_selesai', $ubah?->selesai()) }}" required></div>
      </div>
      <div class="field"><label>Keterangan (opsional)</label><input type="text" name="keterangan" maxlength="120" value="{{ old('keterangan', $ubah?->keterangan) }}"></div>
      <button class="btn">{!! icon('check', 16) !!}Simpan</button> @if ($ubah)<a class="btn garis" href="{{ route('admin.penjadwalan.jam') }}">Batal</a>@endif
      <p class="kecil-redup mt">Nomor jam ke- dipakai solver untuk blok berurutan; istirahat tidak diberi nomor dan boleh dilewati blok.</p>
    </form>
    <form method="post" action="{{ route('admin.penjadwalan.jam.salin') }}" class="kartu" data-konfirmasi="Isi hari tujuan akan diganti dengan struktur hari asal. Lanjutkan?">
      @csrf
      <h2>{!! icon('layers', 18) !!}Salin struktur satu hari</h2>
      <div class="field"><label>Dari</label><select name="dari">@foreach (\App\Models\Jadwal::HARI as $no => $h)<option value="{{ $no }}">{{ $h }}</option>@endforeach</select></div>
      <div class="field"><label>Ke hari</label>
        @foreach (\App\Models\Jadwal::HARI as $no => $h)<label class="cek"><input type="checkbox" name="ke[]" value="{{ $no }}"> {{ $h }}</label>@endforeach</div>
      <button class="btn garis">{!! icon('layers', 16) !!}Salin</button>
    </form>
  </div>
</div>
@endif
@endsection
