@extends('layouts.app')
@section('title', $versi->nama)
@push('head')
<style>
  .gj { border-collapse: separate; border-spacing: 4px; width: 100%; min-width: 780px; table-layout: fixed; }
  .gj > thead > tr > th { font-size: 12.5px; color: var(--redup); font-weight: 700; padding: 6px 8px; text-align: left; }
  .gj > tbody > tr > th { width: 44px; font-size: 12.5px; color: var(--redup); font-weight: 600; text-align: center; }
  .gj td { vertical-align: top; border-radius: 8px; padding: 0; }
  .gj-kosong { background: var(--kertas-2); height: 46px; }
  .gj-tiada { background: transparent; }
  .gj tr.rh td, .gj tr.rh th { height: 26px; }
  .gj-rehat { background: repeating-linear-gradient(135deg, var(--kertas-2), var(--kertas-2) 6px, transparent 6px, transparent 12px); color: var(--redup); font-size: 11.5px; text-align: center; vertical-align: middle !important; padding: 2px 6px !important; }
  .gj-lewat { display: block; color: var(--redup); font-size: 11px; margin-top: 2px; }
  .gj-non { background: var(--kertas-2); color: var(--redup); font-size: 11.5px; text-align: center; vertical-align: middle !important; padding: 6px !important; }
  .gj-blok { background: var(--kertas-2); border: 1px solid var(--garis); border-left-width: 4px; padding: 7px 9px !important; font-size: 12.5px; }
  .gj-blok b { display: block; font-size: 12.5px; line-height: 1.25; }
  .gj-blok small { color: var(--redup); font-size: 11.5px; }
  .gj-blok summary { cursor: pointer; list-style: none; }
  .gj-blok summary::-webkit-details-marker { display: none; }
  .gj-aksi { margin-top: 8px; padding-top: 8px; border-top: 1px dashed var(--garis-2); display: grid; gap: 6px; }
  .gj-aksi form { display: flex; gap: 4px; align-items: center; flex-wrap: wrap; }
  .gj-aksi select, .gj-aksi input[type=number] { padding: 4px 6px; font-size: 12px; width: auto; min-width: 0; }
</style>
@endpush
@section('content')
@php
  $metrik = $versi->hasil_solver['metrik'] ?? null;
  $namaHari = \App\Models\Jadwal::HARI;
  $hariAda = $slots->keys()->sort()->values();
@endphp
<div class="kepala">
  <div>
    <div class="remah"><a href="{{ route('admin.penjadwalan') }}">Penjadwalan</a> / Versi jadwal</div>
    <h1><span class="sorot">{{ $versi->nama }}</span></h1>
    <p>
      @if ($versi->status === 'aktif')<span class="lencana hijau">Aktif di LMS</span>@elseif ($versi->status === 'draf')<span class="lencana kuning">Draf, belum tampil di LMS</span>@else<span class="lencana">Arsip</span>@endif
      {{ $jamAda }} jam pelajaran per minggu terjadwal
    </p>
  </div>
  @if ($versi->status !== 'aktif' && !$versi->sedangDihitung())
    <form method="post" action="{{ route('admin.penjadwalan.aktifkan', $versi) }}" data-konfirmasi="Jadikan versi ini jadwal aktif di LMS? Jadwal aktif sekarang akan diarsipkan.">@csrf
      <button class="btn hijau">{!! icon('check', 16) !!}Aktifkan versi ini</button></form>
  @endif
</div>
@include('admin.penjadwalan._tab')

@if ($bentrok)
  <div class="flash error"><span class="ic">{!! icon('alert', 18) !!}</span><div><b>{{ count($bentrok) }} masalah pada jadwal ini</b>
    <ul>@foreach (array_slice($bentrok, 0, 8) as $b)<li>{{ $b }}</li>@endforeach @if (count($bentrok) > 8)<li>… dan {{ count($bentrok) - 8 }} lainnya.</li>@endif</ul></div></div>
@else
  <div class="flash ok"><span class="ic">{!! icon('check', 18) !!}</span><div>Tidak ada bentrok guru atau kelas, dan semua beban ajar terpenuhi.</div></div>
@endif
@if ($metrik)
  <p class="kecil-redup">Mutu hasil solver: jam kosong guru <b>{{ $metrik['jam_kosong_guru'] ?? '-' }}</b> · jam kosong kelas <b>{{ $metrik['jam_kosong_kelas'] ?? '-' }}</b> · slot dihindari terpakai <b>{{ $metrik['slot_hindari_terpakai'] ?? '-' }}</b> · blok mapel di hari yang sama <b>{{ $metrik['blok_di_hari_sama'] ?? '-' }}</b>. Angka makin kecil makin baik.</p>
@endif

<div class="kartu">
  <form class="inline-form" method="get" style="margin-bottom:14px">
    <div class="field"><select name="mode" onchange="this.form.elements.id.value=''; this.form.submit()">
      <option value="kelas" @selected($mode === 'kelas')>Per kelas</option><option value="guru" @selected($mode === 'guru')>Per guru</option></select></div>
    <div class="field"><select name="id" onchange="this.form.submit()">
      @if ($mode === 'kelas')
        @foreach ($kelasList as $k)<option value="{{ $k->id }}" @selected($kelas?->id === $k->id)>{{ $k->nama }}</option>@endforeach
      @else
        @foreach ($guruList as $g)<option value="{{ $g->id }}" @selected($guru?->id === $g->id)>{{ $g->kode_guru ? $g->kode_guru . ' · ' : '' }}{{ $g->name }}</option>@endforeach
      @endif
    </select></div>
  </form>

  <div class="tabel-wrap">
    <table class="gj">
      <thead><tr><th></th>@foreach ($hariAda as $h)<th>{{ $namaHari[$h] }}</th>@endforeach</tr></thead>
      <tbody>
        @foreach ($baris as $i => $br)
          <tr class="{{ $br['tipe'] === 'rehat' ? 'rh' : '' }}">
            <th>{{ $br['tipe'] === 'jam' ? $br['j'] : '' }}</th>
            @foreach ($hariAda as $h)
              @php $c = $grid[$h][$i]; @endphp
              @if ($c['t'] === 'lanjut') @continue @endif
              @if ($c['t'] === 'tiada')
                <td class="gj-tiada"></td>
              @elseif ($c['t'] === 'rehat')
                <td class="gj-rehat">Istirahat {{ $c['slot']->mulai() }}–{{ $c['slot']->selesai() }}</td>
              @elseif ($c['t'] === 'non')
                <td class="gj-non">{{ \App\Models\SlotWaktu::JENIS[$c['slot']->jenis] }}<br>{{ $c['slot']->mulai() }}–{{ $c['slot']->selesai() }}</td>
              @elseif ($c['t'] === 'kosong')
                <td class="gj-kosong" title="{{ $c['slot']->mulai() }}–{{ $c['slot']->selesai() }}"></td>
              @else
                @foreach ($c['blok'] as $b)
                  @php $pengajar = $b->pengajar(); @endphp
                  <td rowspan="{{ $c['span'] }}" class="gj-blok" style="border-left-color: {{ $b->ruang->warna() }}">
                    <details>
                      <summary>
                        <b>{{ $b->ruang->mapel->nama }} @if ($b->dikunci){!! icon('pin', 12) !!}@endif</b>
                        <small>{{ $mode === 'kelas' ? ($pengajar?->kode_guru ?: $pengajar?->name) : $b->ruang->kelas->nama }} · {{ substr($b->jam_mulai, 0, 5) }}–{{ substr($b->jam_selesai, 0, 5) }}</small>
                        @if ($c['lewat'])<span class="gj-lewat">melewati istirahat</span>@endif
                      </summary>
                      <div class="gj-aksi">
                        <small>{{ $pengajar?->name }} · {{ $b->jumlahJam() }} jam</small>
                        <form method="post" action="{{ route('admin.penjadwalan.pindah', $b) }}">@csrf
                          <select name="hari">@foreach ($hariAda as $hh)<option value="{{ $hh }}" @selected($hh == $b->hari)>{{ $namaHari[$hh] }}</option>@endforeach</select>
                          jam <input type="number" name="jam_ke" min="0" max="30" value="{{ $b->jam_ke_mulai }}" style="width:58px">
                          <button class="btn kecil garis">Pindah</button></form>
                        <form method="post" action="{{ route('admin.penjadwalan.kunci', $b) }}">@csrf
                          <button class="btn kecil garis">{!! icon('pin', 13) !!}{{ $b->dikunci ? 'Lepas kunci' : 'Kunci blok' }}</button></form>
                      </div>
                    </details>
                  </td>
                @endforeach
              @endif
            @endforeach
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  <p class="kecil-redup mt">Klik sebuah blok untuk memindahkannya (bentrok dan jam "tidak bisa" ditolak otomatis) atau menguncinya. Blok terkunci tidak berubah saat jadwal otomatis dibuat ulang. Mengubah jadwal aktif langsung tampil di LMS.</p>
</div>
@endsection
