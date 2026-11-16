@extends('layouts.app')
@section('title', 'Ketersediaan guru')
@push('head')
<style>
  .ks-grid { border-collapse: separate; border-spacing: 4px; }
  .ks-grid th { font-size: 12.5px; color: var(--redup); font-weight: 600; padding: 4px 8px; }
  .ks { min-width: 74px; padding: 7px 6px; border-radius: 8px; border: 1px solid var(--garis-2); background: var(--kartu); color: var(--teks-2); font: inherit; font-size: 12px; cursor: pointer; }
  .ks[data-s="tidak_bisa"] { background: var(--merah-lembut); border-color: var(--merah-border); color: var(--merah-tua); font-weight: 700; }
  .ks[data-s="hindari"] { background: var(--oranye-lembut); border-color: var(--oranye-border); color: var(--oranye-tua); font-weight: 700; }
  .ks-kunci { display: flex; gap: 14px; flex-wrap: wrap; font-size: 12.5px; margin: 10px 0 16px; }
</style>
@endpush
@section('content')
<div class="kepala">
  <div><h1><span class="sorot">Ketersediaan guru</span></h1><p>Tandai jam yang tidak bisa atau sebaiknya dihindari tiap guru · {{ $ta?->label() ?? 'tahun ajaran belum aktif' }}</p></div>
  @if ($guruList->isNotEmpty())
    <form class="inline-form" method="get"><div class="field"><select name="guru" onchange="this.form.submit()">
      @foreach ($guruList as $g)<option value="{{ $g->id }}" @selected($guru?->id === $g->id)>{{ $g->kode_guru ? $g->kode_guru . ' · ' : '' }}{{ $g->name }}</option>@endforeach
    </select></div></form>
  @endif
</div>
@include('admin.penjadwalan._tab')
@if (!$ta)
  <div class="flash error">Belum ada tahun ajaran aktif.</div>
@elseif (!$guru)
  <div class="kartu">@include('partials.kosong', ['ikon' => 'user', 'judul' => 'Belum ada guru'])</div>
@elseif ($slots->isEmpty())
  <div class="kartu">@include('partials.kosong', ['ikon' => 'clock', 'judul' => 'Struktur jam belum diisi', 'teks' => 'Isi dulu di tab Struktur Jam.'])</div>
@else
<form method="post" action="{{ route('admin.penjadwalan.ketersediaan.simpan', $guru) }}" class="kartu">
  @csrf
  <h2>{!! icon('user', 18) !!}{{ $guru->name }} <span class="kanan kecil-redup">mengajar {{ $jamMengajar }} jam per minggu</span></h2>
  <div class="ks-kunci">
    <span>Klik sebuah jam untuk mengganti status:</span>
    <span><b>Putih</b> = bisa</span>
    <span style="color:var(--merah-tua)"><b>Merah</b> = tidak bisa (wajib dihindari)</span>
    <span style="color:var(--oranye-tua)"><b>Oranye</b> = sebaiknya dihindari</span>
  </div>
  <div class="tabel-wrap">
    <table class="ks-grid">
      <thead><tr><th>Jam ke</th>@foreach ($slots->keys()->sort() as $h)<th>{{ \App\Models\Jadwal::HARI[$h] }}</th>@endforeach</tr></thead>
      <tbody>
        @foreach ($jamKe as $j)
          <tr><th>{{ $j }}</th>
            @foreach ($slots->keys()->sort() as $h)
              @php $s = $slots[$h]->firstWhere('jam_ke', $j); $st = $s ? ($status[$s->id] ?? '') : ''; @endphp
              <td>@if ($s)
                <button type="button" class="ks" data-s="{{ $st }}">{{ $s->mulai() }}</button>
                <input type="hidden" name="s[{{ $s->id }}]" value="{{ $st }}">
              @endif</td>
            @endforeach
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  <div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap">
    <button class="btn">{!! icon('check', 16) !!}Simpan</button>
    <button type="button" class="btn garis" id="ks-kosongkan">Bersihkan semua</button>
  </div>
</form>
@endif
@endsection
@push('script')
<script>
(function () {
  var urutan = ['', 'tidak_bisa', 'hindari'];
  document.querySelectorAll('.ks').forEach(function (b) {
    b.addEventListener('click', function () {
      var i = (urutan.indexOf(b.dataset.s || '') + 1) % urutan.length;
      b.dataset.s = urutan[i];
      b.parentNode.querySelector('input[type=hidden]').value = urutan[i];
    });
  });
  var k = document.getElementById('ks-kosongkan');
  if (k) k.addEventListener('click', function () {
    document.querySelectorAll('.ks').forEach(function (b) { b.dataset.s = ''; b.parentNode.querySelector('input[type=hidden]').value = ''; });
  });
})();
</script>
@endpush
