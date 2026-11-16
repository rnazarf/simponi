@extends('layouts.app')
@section('title', 'Beban ajar')
@section('content')
<div class="kepala">
  <div><h1><span class="sorot">Beban ajar</span></h1><p>Berapa jam per minggu tiap guru mengajar di tiap kelas, dan bagaimana dipecah menjadi blok · {{ $ta?->label() ?? 'tahun ajaran belum aktif' }}</p></div>
  @if ($kelasList->isNotEmpty())
    <form class="inline-form" method="get"><div class="field"><select name="kelas" onchange="this.form.submit()">
      @foreach ($kelasList as $k)<option value="{{ $k->id }}" @selected($kelas?->id === $k->id)>{{ $k->nama }}</option>@endforeach
    </select></div></form>
  @endif
</div>
@include('admin.penjadwalan._tab')
@if (!$ta)
  <div class="flash error">Belum ada tahun ajaran aktif.</div>
@elseif (!$kelas)
  <div class="kartu">@include('partials.kosong', ['ikon' => 'grid', 'judul' => 'Belum ada kelas'])</div>
@else
<div class="kartu rapat">
  <h2>{!! icon('clipboard', 18) !!}Kelas {{ $kelas->nama }} <span class="kanan kecil-redup">{{ $ruang->sum(fn ($r) => $r->beban->sum('jam_per_minggu')) }} jam per minggu</span></h2>
  <div class="tabel-wrap mt"><table>
    <thead><tr><th>Mapel</th><th>Beban ajar</th><th>Tambah guru</th></tr></thead>
    <tbody>
      @forelse ($ruang as $r)
        <tr>
          <td class="tebal">{{ $r->mapel->nama }}</td>
          <td style="font-size:13px">
            @forelse ($r->beban as $b)
              <div style="display:flex;gap:6px;align-items:center">
                <span class="lencana biru">{{ $b->guru->kode_guru ?: $b->guru->name }}</span> {{ $b->jam_per_minggu }} jam
                <span class="kecil-redup">blok {{ $b->polaTeks() }}</span>
                @include('partials.hapus', ['url' => route('admin.penjadwalan.beban.hapus', $b), 'pesan' => 'Hapus beban ajar ini?'])
              </div>
            @empty
              <span class="kecil-redup">Belum ada beban ajar, tidak ikut dijadwalkan.</span>
            @endforelse
          </td>
          <td>
            <details><summary class="kecil-redup" style="cursor:pointer">+ guru</summary>
              <form method="post" action="{{ route('admin.penjadwalan.beban.simpan') }}" class="inline-form" style="margin-top:6px">@csrf
                <input type="hidden" name="ruang_id" value="{{ $r->id }}">
                <div class="field"><select name="guru_id" required>@foreach ($guru as $g)<option value="{{ $g->id }}" @selected($r->guru_id === $g->id)>{{ $g->kode_guru ? $g->kode_guru . ' · ' : '' }}{{ $g->name }}</option>@endforeach</select></div>
                <div class="field"><input type="number" name="jam_per_minggu" min="1" max="30" placeholder="Jam" required style="width:80px"></div>
                <div class="field"><input type="text" name="pola" placeholder="Blok, mis. 4+2" style="width:110px"></div>
                <button class="btn kecil">Simpan</button></form>
              <p class="kecil-redup">Guru yang sudah ada diperbarui bila dipilih lagi. Pola kosong = satu blok.</p>
            </details>
          </td>
        </tr>
      @empty
        <tr><td colspan="3">@include('partials.kosong', ['ikon' => 'clipboard', 'judul' => 'Belum ada mapel di kelas ini', 'teks' => 'Tambahkan mapel dulu di menu Pembagian Mengajar.'])</td></tr>
      @endforelse
    </tbody>
  </table></div>
  <p class="kecil-redup mt">Satu mapel boleh diajar beberapa guru (mis. Dasar-dasar AT oleh 4 guru): tambahkan tiap guru dengan jam masing-masing. Guru yang punya beban ajar bisa membuka ruang belajar tersebut di LMS.</p>
</div>
@endif
@endsection
