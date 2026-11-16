@extends('layouts.app')
@section('title', 'Profil Siswa · ' . $siswa->name)
@section('content')
<div class="ruang-kepala">
  <div class="isi">
    <span class="pita" style="background: var(--tinta)"></span>
    <div style="flex:1;min-width:220px">
      <div class="remah">
        <a href="{{ route('wali.index', ['kelas' => $kelas->id]) }}">Kelas Perwalian ({{ $kelas->nama }})</a> / 
        <span>Profil Siswa</span>
      </div>
      <h1 style="display:flex;align-items:center;gap:10px">
        {{ $siswa->name }}
        <span class="lencana {{ $siswa->aktif ? 'hijau' : 'merah' }}" style="font-size:12px">
          {{ $siswa->aktif ? 'Aktif' : 'Nonaktif' }}
        </span>
      </h1>
      <div class="meta">
        <span>{!! icon('user', 15) !!}<b>NISN:</b> {{ $siswa->username }}</span>
        <span>{!! icon('grid', 15) !!}<b>Kelas:</b> {{ $kelas->nama }}</span>
        <span>{!! icon('calendar', 15) !!}<b>Terakhir Aktif:</b> {{ $siswa->terakhir_aktif_at ? $siswa->terakhir_aktif_at->diffForHumans() : 'Belum pernah login' }}</span>
        @if ($siswa->email)
          <span>{!! icon('message', 15) !!}{{ $siswa->email }}</span>
        @endif
      </div>
    </div>
    <div class="aksi">
      <a href="{{ route('wali.index', ['kelas' => $kelas->id]) }}" class="btn garis">
        &larr; Kembali ke Kelas
      </a>
    </div>
  </div>
</div>

@include('partials.flash')

@php
$wajib = $hadir + $alpa;
$persenHadir = $wajib > 0 ? round($hadir / $wajib * 100) : 100;
@endphp

<div class="grid g4 mt">
  <div class="stat {{ $persenHadir < 75 ? 'merah' : 'tinta' }}">
    <small>{!! icon('check-square', 16) !!}Kehadiran</small>
    <b>{{ $persenHadir }}%</b>
    <div class="sub">{{ $hadir }} hadir dari {{ $pertemuanTutup->count() }} sesi</div>
  </div>

  <div class="stat {{ $alpa >= 3 ? 'merah' : '' }}">
    <small>{!! icon('flag', 16) !!}Alpa</small>
    <b>{{ $alpa }} kali</b>
    <div class="sub">{{ $izin }} Izin &middot; {{ $sakit }} Sakit</div>
  </div>

  <div class="stat">
    <small>{!! icon('clipboard', 16) !!}Tugas Terkumpul</small>
    <b>{{ $kumpulGrouped->count() }} / {{ $daftarTugas->count() }}</b>
    <div class="sub">{{ max(0, $daftarTugas->count() - $kumpulGrouped->count()) }} tugas belum diserahkan</div>
  </div>

  <div class="stat">
    <small>{!! icon('award', 16) !!}Catatan Pembinaan</small>
    <b>{{ $catatanSiswa->count() }}</b>
    <div class="sub">Rekam jejak konseling</div>
  </div>
</div>

<div class="grid g-utama mt">
  <div>
    {{-- Card: Status Ketidakhadiran --}}
    <div class="kartu">
      <h2>
        {!! icon('calendar', 18) !!}Riwayat Ketidakhadiran
        <span class="kanan kecil-redup">{{ $riwayatAbsen->count() }} pertemuan tidak hadir</span>
      </h2>
      @if ($riwayatAbsen->isEmpty())
        @include('partials.kosong', [
          'ikon' => 'check',
          'judul' => 'Kehadiran 100% Sempurna',
          'teks' => 'Siswa selalu hadir pada seluruh sesi presensi yang telah ditutup.'
        ])
      @else
        <div class="tabel-wrap">
          <table>
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>Mata Pelajaran</th>
                <th>Status</th>
                <th>Keterangan</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($riwayatAbsen as $ra)
                <tr>
                  <td class="tebal">{{ $ra['pertemuan']->tanggal->locale('id')->isoFormat('D MMMM Y') }}</td>
                  <td>{{ $ra['pertemuan']->ruang->mapel->nama }}</td>
                  <td>
                    <span class="lencana {{ $ra['status'] === 'alpa' ? 'merah' : ($ra['status'] === 'sakit' ? 'kuning' : 'biru') }}">
                      {{ \App\Models\Presensi::STATUS[$ra['status']] ?? $ra['status'] }}
                    </span>
                  </td>
                  <td class="kecil-redup">{{ $ra['keterangan'] ?: '-' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>

    {{-- Card: Status Tugas Siswa --}}
    <div class="kartu mt">
      <h2>{!! icon('clipboard', 18) !!}Status Seluruh Tugas</h2>
      @if ($daftarTugas->isEmpty())
        @include('partials.kosong', ['ikon' => 'clipboard', 'judul' => 'Belum ada tugas di kelas ini'])
      @else
        <div class="tabel-wrap">
          <table>
            <thead>
              <tr>
                <th>Mata Pelajaran</th>
                <th>Tugas</th>
                <th>Tenggat</th>
                <th class="angka">Nilai / Status</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($daftarTugas as $t)
                @php($k = $kumpulGrouped[$t->id] ?? null)
                <tr>
                  <td class="tebal">{{ $t->ruang->mapel->nama }}</td>
                  <td>{{ $t->judul }}</td>
                  <td class="kecil-redup">{{ $t->tenggat ? $t->tenggat->format('d/m/Y H:i') : '-' }}</td>
                  <td class="angka">
                    @if ($k)
                      @if ($k->nilai !== null)
                        <span class="lencana hijau">Nilai {{ $k->nilai }}</span>
                      @else
                        <span class="lencana biru">Sudah Kumpul (Belum Dinilai)</span>
                      @endif
                    @elseif ($t->lewatTenggat())
                      <span class="lencana merah">Belum Mengumpulkan</span>
                    @else
                      <span class="lencana">Belum Mengumpulkan</span>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>

  <div>
    {{-- Card: Nilai Rapor Siswa --}}
    <div class="kartu">
      <h2>{!! icon('award', 18) !!}Nilai Akhir per Mapel</h2>
      <div style="display:flex;flex-direction:column;gap:8px">
        @forelse ($nilaiMapel as $nm)
          @php($r = $nm['ruang'])
          @php($ak = $nm['akhir'])
          <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--garis)">
            <div>
              <b style="font-size:13.5px">{{ $r->mapel->nama }}</b>
              <div class="kecil-redup">KKM {{ $r->kkm }} &middot; Kehadiran {{ $nm['kehadiran'] !== null ? $nm['kehadiran'] . '%' : '-' }}</div>
            </div>
            <div style="text-align:right">
              @if ($ak !== null)
                <span class="lencana {{ $ak < $r->kkm ? 'merah' : 'hijau' }}" style="font-size:14px;font-weight:700">
                  {{ $ak }}
                </span>
              @else
                <span class="kecil-redup">-</span>
              @endif
            </div>
          </div>
        @empty
          <div class="kecil-redup">Belum ada mata pelajaran.</div>
        @endforelse
      </div>
    </div>

    {{-- Card: Catatan Pembinaan Siswa Ini --}}
    <div class="kartu mt">
      <h2>
        {!! icon('edit', 18) !!}Catatan Pembinaan Siswa
        <a href="{{ route('wali.catatan', ['kelas' => $kelas->id, 'siswa_id' => $siswa->id]) }}" class="kanan" style="font-size:13px">+ Tambah</a>
      </h2>
      @forelse ($catatanSiswa as $cs)
        <div class="pt" style="border:1px solid var(--garis);padding:10px;border-radius:8px;margin-bottom:10px">
          <div style="display:flex;justify-content:space-between;margin-bottom:4px">
            <span class="lencana {{ $cs->jenis === 'prestasi' ? 'hijau' : ($cs->jenis === 'pelanggaran' ? 'merah' : 'biru') }}">
              {{ \App\Models\CatatanWali::JENIS[$cs->jenis] ?? $cs->jenis }}
            </span>
            <small class="kecil-redup">{{ $cs->tanggal->format('d/m/Y') }}</small>
          </div>
          <div style="font-size:13.5px;color:var(--teks);margin-top:4px">
            {!! nl2br(e($cs->catatan)) !!}
          </div>
          @if ($cs->tindak_lanjut)
            <div style="font-size:12px;margin-top:6px;color:var(--tinta);font-style:italic">
              Tindak lanjut: {{ $cs->tindak_lanjut }}
            </div>
          @endif
        </div>
      @empty
        @include('partials.kosong', [
          'ikon' => 'edit',
          'judul' => 'Belum Ada Catatan Khusus',
          'teks' => 'Belum ada catatan pembinaan atau konseling untuk siswa ini.'
        ])
      @endforelse
    </div>
  </div>
</div>
@endsection
