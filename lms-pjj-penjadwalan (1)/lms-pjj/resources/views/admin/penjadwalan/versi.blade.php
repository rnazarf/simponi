@extends('layouts.app')
@section('title', 'Penjadwalan')
@php $dihitung = $versi->contains(fn ($v) => $v->sedangDihitung()); @endphp
@if ($dihitung)
  @push('head')<meta http-equiv="refresh" content="6">@endpush
@endif
@section('content')
<div class="kepala">
  <div><h1><span class="sorot">Penjadwalan</span></h1><p>Susun jadwal pelajaran otomatis dengan solver OR-Tools · {{ $ta?->label() ?? 'tahun ajaran belum aktif' }}</p></div>
</div>
@include('admin.penjadwalan._tab')
@if (!$ta)
  <div class="flash error">Belum ada tahun ajaran aktif. Atur di menu Pengaturan.</div>
@else
<div class="grid g4">
  <div class="stat"><small>{!! icon('clock', 16) !!}Jam pelajaran / minggu</small><b>{{ $data['slot'] }}</b><div class="sub">slot yang bisa dipakai</div></div>
  <div class="stat"><small>{!! icon('book', 16) !!}Ruang belajar</small><b>{{ $data['ruang'] }}</b></div>
  <div class="stat"><small>{!! icon('clipboard', 16) !!}Beban ajar</small><b>{{ $data['beban'] }}</b><div class="sub">{{ $data['jam'] }} jam per minggu</div></div>
  <div class="stat"><small>{!! icon('user', 16) !!}Guru mengajar</small><b>{{ $data['guru'] }}</b></div>
</div>

@if ($data['masalah'] || $data['peringatan'])
  <div class="kartu mt">
    <h2>{!! icon('alert', 18) !!}Periksa data dulu</h2>
    @if ($data['masalah'])
      <p class="kecil-redup">Masalah ini harus diperbaiki sebelum jadwal otomatis bisa dibuat:</p>
      <ul>@foreach (array_slice($data['masalah'], 0, 8) as $m)<li>{{ $m }}</li>@endforeach
        @if (count($data['masalah']) > 8)<li>… dan {{ count($data['masalah']) - 8 }} masalah lain.</li>@endif</ul>
    @endif
    @foreach ($data['peringatan'] as $p)<p class="kecil-redup">{{ $p }}</p>@endforeach
  </div>
@endif

<div class="grid g-utama mt">
  <div class="kartu rapat">
    <h2>{!! icon('calendar', 18) !!}Versi jadwal <span class="kanan kecil-redup">{{ $versi->count() }} versi</span></h2>
    <div class="tabel-wrap mt"><table>
      <thead><tr><th>Nama</th><th>Status</th><th>Hasil</th><th>Blok</th><th></th></tr></thead>
      <tbody>
        @forelse ($versi as $v)
          @php $m = $v->hasil_solver['metrik'] ?? null; @endphp
          <tr>
            <td class="tebal">{{ $v->nama }}<div class="kecil-redup" style="font-weight:400">{{ ['manual' => 'Manual', 'solver' => 'Solver', 'impor' => 'Impor'][$v->sumber] }} · {{ $v->created_at?->locale('id')->diffForHumans() }}</div></td>
            <td>
              @if ($v->status === 'aktif')<span class="lencana hijau">Aktif di LMS</span>
              @elseif ($v->status === 'draf')<span class="lencana kuning">Draf</span>
              @else<span class="lencana">Arsip</span>@endif
              @if ($v->status_solver === 'menunggu')<div class="kecil-redup">Menunggu antrean…</div>
              @elseif ($v->status_solver === 'berjalan')<div class="kecil-redup">Sedang dihitung…</div>
              @elseif ($v->status_solver === 'gagal')<div><span class="lencana merah">Gagal</span></div>@endif
            </td>
            <td style="font-size:12.5px">
              @if ($m)
                Jam kosong guru <b>{{ $m['jam_kosong_guru'] ?? '-' }}</b><br>Jam kosong kelas <b>{{ $m['jam_kosong_kelas'] ?? '-' }}</b>
                @if (($v->hasil_solver['waktu_detik'] ?? null) !== null)<div class="kecil-redup">{{ $v->hasil_solver['waktu_detik'] }} detik · {{ $v->hasil_solver['status'] ?? '' }}</div>@endif
              @elseif ($v->status_solver === 'gagal')
                <span class="kecil-redup">{{ $v->hasil_solver['status'] ?? 'GAGAL' }}</span>
                @foreach (array_slice($v->hasil_solver['pelanggaran_keras'] ?? [], 0, 2) as $p)<div class="kecil-redup">{{ $p }}</div>@endforeach
                @if (!empty($v->hasil_solver['galat']))<div class="kecil-redup">{{ \Illuminate\Support\Str::limit($v->hasil_solver['galat'], 160) }}</div>@endif
              @else - @endif
            </td>
            <td class="angka">{{ $v->jadwal_count }}</td>
            <td class="angka" style="white-space:nowrap">
              @if ($v->jadwal_count)<a class="ikon-btn" href="{{ route('admin.penjadwalan.lihat', $v) }}" title="Lihat jadwal">{!! icon('eye', 16) !!}</a>@endif
              @if ($v->status !== 'aktif' && $v->jadwal_count && !$v->sedangDihitung())
                <form method="post" action="{{ route('admin.penjadwalan.aktifkan', $v) }}" style="display:inline" data-konfirmasi="Jadikan &quot;{{ $v->nama }}&quot; sebagai jadwal aktif? Jadwal aktif sekarang akan diarsipkan.">@csrf<button class="ikon-btn" title="Aktifkan">{!! icon('check', 16) !!}</button></form>
              @endif
              @if ($v->status !== 'aktif')@include('partials.hapus', ['url' => route('admin.penjadwalan.hapus', $v), 'pesan' => 'Hapus versi "' . $v->nama . '"?'])@endif
            </td>
          </tr>
        @empty
          <tr><td colspan="5">@include('partials.kosong', ['ikon' => 'calendar', 'judul' => 'Belum ada versi jadwal', 'teks' => 'Isi struktur jam dan beban ajar, lalu buat jadwal otomatis.'])</td></tr>
        @endforelse
      </tbody>
    </table></div>
  </div>

  <form method="post" action="{{ route('admin.penjadwalan.buat') }}" class="kartu">
    @csrf
    <h2>{!! icon('plus', 18) !!}Buat jadwal otomatis</h2>
    <div class="field"><label>Nama versi</label><input type="text" name="nama" value="{{ old('nama') }}" placeholder="Draf {{ now()->format('d M') }}" maxlength="120"></div>
    <div class="field"><label>Batas waktu hitung (detik)</label><input type="number" name="waktu" value="{{ old('waktu', 120) }}" min="10" max="1200" required><small>Makin lama, makin besar peluang jadwal lebih rapi.</small></div>
    <button class="btn" {{ $data['masalah'] || $dihitung ? 'disabled' : '' }}>{!! icon('clock', 16) !!}Mulai hitung</button>
    <p class="kecil-redup mt">Hasilnya masuk sebagai <b>draf</b> dan belum mengubah jadwal di LMS. Periksa dulu, lalu aktifkan. Blok yang <b>dikunci</b> di jadwal aktif tetap di tempatnya.</p>
    @if (config('services.solver.mode', 'queue') === 'queue')<p class="kecil-redup">Mode antrean: pastikan <code>php artisan queue:work</code> sedang berjalan.</p>@endif
  </form>
</div>
@endif
@endsection
