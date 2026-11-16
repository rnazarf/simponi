@extends('layouts.app')
@section('title', 'Hasil · ' . $kuis->judul)
@section('content')
@include('partials.ruang-kepala')
@php
  $selesai = $percobaan->whereNotNull('selesai_at');
  $dinilai = $selesai->whereNotNull('nilai');
  $st = $kuis->status();
@endphp
<div class="kepala">
  <div><div class="remah">Kuis · {{ tgl($kuis->mulai, true) }} – {{ tgl($kuis->selesai, true) }}</div><h1><span class="sorot">{{ $kuis->judul }}</span></h1></div>
  <div class="aksi"><a class="btn garis" href="{{ route('kuis.edit', [$ruang, $kuis]) }}">{!! icon('edit', 16) !!}Soal &amp; pengaturan</a></div>
</div>
<div class="grid g4">
  <div class="stat tinta"><small>Status</small><b style="font-size:20px">{{ ['draf' => 'Draf', 'belum' => 'Belum dibuka', 'berlangsung' => 'Sedang dibuka', 'selesai' => 'Ditutup'][$st] }}</b></div>
  <div class="stat"><small>Mengumpulkan</small><b>{{ $selesai->count() }}/{{ $siswa->count() }}</b><div class="sub">{{ $percobaan->count() - $selesai->count() }} sedang mengerjakan</div></div>
  <div class="stat"><small>Rata-rata</small><b>{{ $dinilai->count() ? round($dinilai->avg('nilai'), 1) : '-' }}</b><div class="sub">tertinggi {{ $dinilai->count() ? round($dinilai->max('nilai'), 1) : '-' }} · terendah {{ $dinilai->count() ? round($dinilai->min('nilai'), 1) : '-' }}</div></div>
  <div class="stat"><small>Tuntas (KKM {{ $ruang->kkm }})</small><b>{{ $dinilai->where('nilai', '>=', $ruang->kkm)->count() }}</b><div class="sub">{{ $selesai->whereNull('nilai')->count() }} menunggu koreksi esai</div></div>
</div>
<div class="grid g-utama mt">
  <div class="kartu rapat">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px 0;flex-wrap:wrap;gap:8px">
      <h2 style="margin:0">{!! icon('users', 18) !!}Hasil per siswa</h2>
      @if ($kuis->anti_curang)
        <span class="lencana biru" style="font-size:12px">🛡️ Anti-Curang Aktif @if ($kuis->maks_pelanggaran > 0)(Toleransi: {{ $kuis->maks_pelanggaran }}x)@else(Mode Pantau Log)@endif</span>
      @endif
    </div>
    <div class="tabel-wrap mt"><table>
      <thead><tr><th>No</th><th>Nama</th><th>Status</th><th>Waktu</th>@if ($kuis->anti_curang)<th>Integritas</th>@endif<th class="angka">Nilai</th><th></th></tr></thead>
      <tbody>
        @foreach ($siswa as $i => $s)
          @php($p = $percobaan[$s->id] ?? null)
          @php($jmlCurang = $p?->jumlah_pelanggaran ?? 0)
          <tr><td>{{ $i + 1 }}</td><td class="tebal">{{ $s->name }}</td>
            <td>@if (!$p)<span class="lencana {{ $st === 'selesai' ? 'merah' : '' }}">{{ $st === 'selesai' ? 'Tidak mengerjakan' : 'Belum' }}</span>
              @elseif (!$p->selesai_at)<span class="lencana biru">Mengerjakan</span>
              @elseif ($p->nilai === null)<span class="lencana kuning">Perlu koreksi</span>
              @else<span class="lencana hijau">Selesai</span>@endif</td>
            <td class="kecil-redup">{{ $p?->selesai_at ? $p->mulai_at->diffInMinutes($p->selesai_at) . ' menit' : '-' }}</td>
            @if ($kuis->anti_curang)
              <td>
                @if (!$p)
                  <span class="kecil-redup">-</span>
                @elseif ($jmlCurang === 0)
                  <span class="lencana hijau" title="Tidak ada aktivitas mencurigakan yang tercatat">✓ Bersih (0)</span>
                @else
                  <button type="button" class="btn kecil {{ $jmlCurang >= ($kuis->maks_pelanggaran > 0 ? $kuis->maks_pelanggaran : 3) ? 'bahaya' : 'garis' }}" onclick="tampilkanLogCurang('{{ addslashes($s->name) }}', {{ $jmlCurang }}, {{ json_encode($p->pelanggaran ?? []) }})" title="Lihat detail log pelanggaran">
                    ⚠️ {{ $jmlCurang }}x Pelanggaran
                  </button>
                @endif
              </td>
            @endif
            <td class="angka tebal {{ $p && $p->nilai !== null && $p->nilai < $ruang->kkm ? 'n-rendah' : '' }}">{{ $p && $p->nilai !== null ? round($p->nilai, 1) : '-' }}</td>
            <td style="white-space:nowrap">@if ($p?->selesai_at)<a class="btn kecil garis" href="{{ route('kuis.koreksi', [$ruang, $kuis, $p]) }}">{{ $p->nilai === null ? 'Koreksi' : 'Lihat' }}</a>@endif
              @if ($p)@include('partials.hapus', ['url' => route('kuis.reset', [$ruang, $kuis, $p]), 'pesan' => 'Hapus jawaban ' . $s->name . ' agar bisa mengerjakan ulang?'])@endif</td></tr>
        @endforeach
      </tbody>
    </table></div>
  </div>
  <div class="kartu">
    <h2>{!! icon('chart', 18) !!}Analisis butir soal</h2>
    <p class="kecil-redup">Persentase siswa yang menjawab benar. Di bawah 40% = soal sulit / perlu dibahas ulang.</p>
    @forelse ($kuis->soal->where('jenis', 'pg') as $s)
      @php($b = $butir[$s->id] ?? ['persen' => null, 'sebaran' => collect()])
      <div style="margin-bottom:12px" title="{{ $s->pertanyaan }}">
        <div style="display:flex;justify-content:space-between;font-size:13px"><span class="potong" style="max-width:75%">{{ $s->urutan }}. {{ \Illuminate\Support\Str::limit($s->pertanyaan, 50) }}</span>
          <b class="{{ $b['persen'] !== null && $b['persen'] < 40 ? 'n-rendah' : '' }}">{{ $b['persen'] ?? '-' }}{{ $b['persen'] !== null ? '%' : '' }}</b></div>
        <div class="butir"><i style="width: {{ $b['persen'] ?? 0 }}%; background: {{ ($b['persen'] ?? 0) < 40 ? 'var(--margin)' : 'var(--hijau)' }}"></i></div>
        @if ($b['sebaran']->isNotEmpty())<div class="kecil-redup" style="margin-top:2px">@foreach ($b['sebaran']->sortKeys() as $h => $n){{ $h }}: {{ $n }}{{ $h === $s->kunci ? ' (kunci)' : '' }}@if (!$loop->last) · @endif @endforeach</div>@endif
      </div>
    @empty
      <p class="kecil-redup">Tidak ada soal pilihan ganda.</p>
    @endforelse
  </div>
</div>

{{-- Modal Rincian Log Pelanggaran Anti-Curang --}}
<div id="modal-log-curang" class="anti-curang-overlay" style="display:none">
  <div class="anti-curang-box" style="max-width:540px;text-align:left">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;border-bottom:1px dashed var(--garis);padding-bottom:10px">
      <div>
        <h3 style="margin:0;font-size:17px;color:var(--teks);display:flex;align-items:center;gap:6px">
          🛡️ Log Integritas: <span id="modal-log-nama">-</span>
        </h3>
        <span class="kecil-redup" id="modal-log-sub">-</span>
      </div>
      <button type="button" class="btn kecil garis" onclick="document.getElementById('modal-log-curang').style.display='none'">✕</button>
    </div>

    <div id="modal-log-isi" style="max-height:360px;overflow-y:auto;padding-right:4px"></div>

    <div style="margin-top:14px;text-align:right">
      <button type="button" class="btn garis" onclick="document.getElementById('modal-log-curang').style.display='none'">Tutup</button>
    </div>
  </div>
</div>

@push('script')
<script>
function tampilkanLogCurang(nama, total, log) {
  document.getElementById('modal-log-nama').textContent = nama;
  document.getElementById('modal-log-sub').textContent = 'Total: ' + total + ' aktivitas mencurigakan terekam';
  var wrap = document.getElementById('modal-log-isi');
  wrap.innerHTML = '';

  if (!log || !log.length) {
    wrap.innerHTML = '<p class="kecil-redup" style="text-align:center;padding:16px 0">Tidak ada rincian pelanggaran.</p>';
  } else {
    log.forEach(function (item, idx) {
      var d = document.createElement('div');
      d.style.cssText = 'padding:10px 12px;border-radius:var(--r-sm);background:var(--kertas-2);margin-bottom:8px;border-left:3px solid var(--merah);font-size:13px';
      var durasiStr = item.durasi_detik ? ' (' + item.durasi_detik + ' detik)' : '';
      var waktuStr = item.waktu || '-';
      var labelJenis = {
        'tab_switch': 'Beralih Tab / Aplikasi',
        'blur': 'Keluar Jendela Kuis',
        'copy': 'Menyalin (Copy) Teks',
        'paste': 'Menempel (Paste) Teks',
        'inspect': 'Klik Kanan / Inspect Element',
        'shortcut': 'Tombol Pintasan Dilarang'
      }[item.jenis] || item.jenis || 'Aktivitas';

      d.innerHTML = '<div style="display:flex;justify-content:space-between;margin-bottom:3px"><b>#' + (idx + 1) + ' · ' + labelJenis + '</b><span class="kecil-redup">' + waktuStr + '</span></div>' +
        '<div style="color:var(--teks-2)">' + (item.keterangan || '-') + durasiStr + '</div>';
      wrap.appendChild(d);
    });
  }

  document.getElementById('modal-log-curang').style.display = 'flex';
}
</script>
@endpush
@endsection
