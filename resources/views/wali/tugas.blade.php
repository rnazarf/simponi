@extends('layouts.app')
@section('title', 'Monitoring Tugas & Kuis · ' . $kelas->nama)
@section('content')
@include('partials.wali-kepala')

<div class="kepala mt">
  <div>
    <h1><span class="sorot">Monitoring Tugas &amp; Tagihan Siswa</span></h1>
    <p>Pantau ketercapaian pengumpulan tugas kelas {{ $kelas->nama }} dari seluruh guru mata pelajaran.</p>
  </div>
  @if (!empty($tunggakanPerSiswa))
    <div class="aksi">
      <button type="button" class="btn" onclick="salinFormatWA()">
        {!! icon('message', 16) !!}Salin Format Laporan WhatsApp
      </button>
    </div>
  @endif
</div>

@if (!empty($tunggakanPerSiswa))
  <div class="kartu" style="background:var(--garis-lemah);border-left:4px solid var(--margin);margin-bottom:20px">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px">
      <div>
        <h3 style="margin:0 0 6px 0;display:flex;align-items:center;gap:8px">
          {!! icon('flag', 16) !!}Rekap Siswa Belum Mengumpulkan Tugas (Lewat Tenggat)
        </h3>
        <p class="kecil-redup" style="margin:0">
          Terdapat <b>{{ count($tunggakanPerSiswa) }}</b> siswa yang memiliki tugas terbengkalai. Anda dapat menyalin teks berikut untuk diumumkan di grup wali murid.
        </p>
      </div>
      <button type="button" class="btn kecil garis" onclick="salinFormatWA()">
        {!! icon('clipboard', 14) !!}Salin Teks
      </button>
    </div>
    
    <div style="margin-top:14px;background:var(--latar);padding:14px;border-radius:8px;font-family:monospace;font-size:12.5px;max-height:200px;overflow-y:auto;white-space:pre-wrap;border:1px solid var(--garis)" id="kotak-wa">@php
$teksWa = "*LAPORAN TUNGGAKAN TUGAS KELAS " . strtoupper($kelas->nama) . "*\n";
$teksWa .= "Tanggal: " . now()->locale('id')->isoFormat('D MMMM Y') . "\n";
$teksWa .= "-------------------------------------\n";
$teksWa .= "Yth. Bapak/Ibu Orang Tua/Wali Murid,\n";
$teksWa .= "Berikut kami sampaikan daftar putra/putri yang masih memiliki tagihan tugas di SIMPONI:\n\n";
$idx = 1;
foreach ($tunggakanPerSiswa as $namaSiswa => $daftar) {
    $teksWa .= "{$idx}. *" . $namaSiswa . "*\n";
    foreach ($daftar as $d) {
        $teksWa .= "   • " . $d . "\n";
    }
    $idx++;
}
$teksWa .= "\nMohon bantuan Bapak/Ibu untuk mendampingi dan mengingatkan putra/putrinya agar segera menyelesaikan tugas tersebut.\nTerima kasih.\n\nWali Kelas: " . ($kelas->wali?->name ?? 'Wali Kelas ' . $kelas->nama);
echo $teksWa;
@endphp</div>
  </div>
@endif

<div class="kartu rapat">
  <div class="tabel-wrap">
    <table>
      <thead>
        <tr>
          <th>Mata Pelajaran</th>
          <th>Judul Tugas</th>
          <th>Guru Pengampu</th>
          <th>Tenggat</th>
          <th class="angka">Terkumpul</th>
          <th>Siswa Belum Mengumpulkan</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($tugasStats as $ts)
          @php($t = $ts['tugas'])
          <tr>
            <td class="tebal">{{ $t->ruang->mapel->nama }}</td>
            <td>
              <a href="{{ route('tugas.show', [$t->ruang, $t]) }}" class="tebal">
                {{ $t->judul }}
              </a>
              @if ($ts['lewat_tenggat'])
                <span class="lencana merah" style="margin-left:4px">Lewat Tenggat</span>
              @else
                <span class="lencana hijau" style="margin-left:4px">Masih Dibuka</span>
              @endif
            </td>
            <td>{{ $t->ruang->guru?->name ?? '-' }}</td>
            <td style="white-space:nowrap">{{ $t->tenggat ? tgl_panjang($t->tenggat) : 'Tidak ada tenggat' }}</td>
            <td class="angka">
              <span class="lencana {{ $ts['jumlah_belum'] === 0 ? 'hijau' : ($ts['lewat_tenggat'] ? 'merah' : 'biru') }}">
                {{ $ts['jumlah_kumpul'] }} / {{ $ts['total_siswa'] }}
              </span>
            </td>
            <td>
              @if ($ts['belum_kumpul']->isEmpty())
                <span class="lencana hijau">{!! icon('check', 12) !!} Semua siswa sudah mengumpulkan</span>
              @else
                <details style="cursor:pointer">
                  <summary style="font-size:12.5px;color:var(--tinta);font-weight:600">
                    Lihat {{ $ts['belum_kumpul']->count() }} siswa belum kumpul &darr;
                  </summary>
                  <div style="margin-top:6px;padding:8px;background:var(--garis-lemah);border-radius:6px;font-size:12px;line-height:1.6">
                    @foreach ($ts['belum_kumpul'] as $bs)
                      <span style="display:inline-block;background:var(--latar);padding:2px 6px;margin:2px;border-radius:4px;border:1px solid var(--garis)">
                        <a href="{{ route('wali.siswa', [$bs, 'kelas' => $kelas->id]) }}">{{ $bs->name }}</a>
                      </span>
                    @endforeach
                  </div>
                </details>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="6">
              @include('partials.kosong', ['ikon' => 'clipboard', 'judul' => 'Belum Ada Tugas di Rombel Ini'])
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<script>
function salinFormatWA() {
  const el = document.getElementById('kotak-wa');
  if (!el) return;
  navigator.clipboard.writeText(el.innerText).then(() => {
    alert('Format laporan WhatsApp berhasil disalin ke clipboard!');
  }).catch(() => {
    alert('Gagal menyalin. Silakan pilih dan salin teks secara manual.');
  });
}
</script>
@endsection
