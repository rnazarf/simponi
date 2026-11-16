@extends('layouts.app')
@section('title', 'Perwalian · ' . $kelas->nama)
@section('content')
@include('partials.wali-kepala')

<div class="grid g4 mt">
  <div class="stat tinta">
    <small>{!! icon('users', 16) !!}Total Siswa</small>
    <b>{{ $siswa->count() }}</b>
    <div class="sub">Siswa aktif di rombel ini</div>
  </div>

  <div class="stat">
    <small>{!! icon('check-square', 16) !!}Presensi Hari Ini</small>
    <b>{{ $statHariIni['hadir'] ?? 0 }} <small style="font-size:14px;font-weight:normal;color:var(--redup)">hadir</small></b>
    <div class="sub">
      @if ($pertemuanHariIni->isEmpty())
        Tidak ada sesi hari ini
      @else
        {{ $statHariIni['izin'] ?? 0 }} Izin · {{ $statHariIni['sakit'] ?? 0 }} Sakit · {{ $statHariIni['alpa'] ?? 0 }} Alpa
      @endif
    </div>
  </div>

  <div class="stat {{ $perluPerhatian->isNotEmpty() ? 'merah' : '' }}">
    <small>{!! icon('flag', 16) !!}Perlu Perhatian</small>
    <b>{{ $perluPerhatian->count() }}</b>
    <div class="sub">Alpa &ge; 3, tugas bolong, atau jarang login</div>
  </div>

  <div class="stat">
    <small>{!! icon('book', 16) !!}Mata Pelajaran</small>
    <b>{{ $ruang->count() }}</b>
    <div class="sub">Semester aktif</div>
  </div>
</div>

<div class="grid g-utama mt">
  <div>
    {{-- Card: Deteksi Dini / Siswa Perlu Perhatian --}}
    <div class="kartu">
      <h2>
        {!! icon('flag', 18) !!}Deteksi Dini Siswa Perlu Perhatian
        <span class="lencana {{ $perluPerhatian->isNotEmpty() ? 'merah' : 'hijau' }} kanan">
          {{ $perluPerhatian->count() }} siswa
        </span>
      </h2>
      <p class="kecil-redup" style="margin-bottom:12px">
        Daftar siswa yang memerlukan perhatian atau pembinaan wali kelas akibat akumulasi ketidakhadiran, tugas belum dikumpulkan, atau tidak aktif di SIMPONI.
      </p>

      @if ($perluPerhatian->isEmpty())
        @include('partials.kosong', [
          'ikon' => 'check',
          'judul' => 'Kondisi Kelas Sangat Baik',
          'teks' => 'Tidak ada siswa yang terdeteksi memiliki akumulasi alpa tinggi atau kendala keaktifan saat ini.'
        ])
      @else
        <div class="tabel-wrap">
          <table>
            <thead>
              <tr>
                <th>Nama Siswa</th>
                <th class="angka">Alpa</th>
                <th class="angka">Tugas/Kuis Nunggak</th>
                <th>Terakhir Aktif</th>
                <th style="text-align:right">Aksi</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($perluPerhatian as $s)
                @php($alpa = $rekapSiswa[$s->id]['alpa'] ?? 0)
                @php($tunggak = $tunggakanSiswa[$s->id]['total'] ?? 0)
                <tr>
                  <td>
                    <a href="{{ route('wali.siswa', [$s, 'kelas' => $kelas->id]) }}" class="tebal">
                      {{ $s->name }}
                    </a>
                    <div class="kecil-redup">NISN: {{ $s->username }}</div>
                  </td>
                  <td class="angka">
                    @if ($alpa >= 3)
                      <span class="lencana merah" title="{{ $alpa }} kali alpa">{{ $alpa }} Alpa</span>
                    @else
                      <span class="lencana">{{ $alpa }}</span>
                    @endif
                  </td>
                  <td class="angka">
                    @if ($tunggak > 0)
                      <span class="lencana merah" title="{{ $tunggak }} belum dikumpulkan">{{ $tunggak }} tertunggak</span>
                    @else
                      <span class="lencana hijau">Lengkap</span>
                    @endif
                  </td>
                  <td>
                    @if ($s->terakhir_aktif_at)
                      <span style="font-size:12.5px" title="{{ $s->terakhir_aktif_at->isoFormat('D MMMM Y, HH:mm') }}">
                        {{ $s->terakhir_aktif_at->diffForHumans() }}
                      </span>
                    @else
                      <span class="lencana merah">Belum pernah</span>
                    @endif
                  </td>
                  <td style="text-align:right;white-space:nowrap">
                    <a href="{{ route('wali.siswa', [$s, 'kelas' => $kelas->id]) }}" class="btn kecil garis">
                      {!! icon('user', 14) !!}Detail
                    </a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>

    {{-- Card: Sesi Pembelajaran Hari Ini --}}
    <div class="kartu mt">
      <h2>
        {!! icon('calendar', 18) !!}Sesi Pembelajaran Hari Ini
        <span class="kanan kecil-redup">{{ today()->locale('id')->isoFormat('dddd, D MMMM Y') }}</span>
      </h2>
      @if ($pertemuanHariIni->isEmpty())
        @include('partials.kosong', [
          'ikon' => 'calendar',
          'judul' => 'Tidak Ada Jadwal Pertemuan Hari Ini',
          'teks' => 'Tidak ada guru yang membuka pertemuan pada tanggal hari ini untuk kelas ini.'
        ])
      @else
        <div class="tabel-wrap">
          <table>
            <thead>
              <tr>
                <th>Mata Pelajaran</th>
                <th>Materi / Judul Pertemuan</th>
                <th>Guru Pengampu</th>
                <th class="angka">Kehadiran</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($pertemuanHariIni as $p)
                @php($hadirCount = \App\Models\Presensi::where('pertemuan_id', $p->id)->where('status', 'hadir')->count())
                <tr>
                  <td class="tebal">{{ $p->ruang->mapel->nama }}</td>
                  <td>{{ $p->judul }}</td>
                  <td>{{ $p->ruang->guru?->name ?? '-' }}</td>
                  <td class="angka">
                    <span class="lencana hijau">
                      {!! icon('check-square', 12) !!} {{ $hadirCount }} / {{ $siswa->count() }} Hadir
                    </span>
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
    {{-- Card: Catatan Pembinaan Terbaru --}}
    <div class="kartu">
      <h2>
        {!! icon('edit', 18) !!}Catatan Pembinaan
        <a href="{{ route('wali.catatan', ['kelas' => $kelas->id]) }}" class="kanan" style="font-size:13px">Semua &rarr;</a>
      </h2>
      @forelse ($catatanTerbaru as $c)
        <div class="pt" style="margin-bottom:12px;padding:12px;border:1px solid var(--garis);border-radius:10px">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
            <span class="lencana {{ $c->jenis === 'prestasi' ? 'hijau' : ($c->jenis === 'pelanggaran' ? 'merah' : 'biru') }}">
              {{ \App\Models\CatatanWali::JENIS[$c->jenis] ?? $c->jenis }}
            </span>
            <small class="kecil-redup">{{ $c->tanggal->format('d/m/Y') }}</small>
          </div>
          <div style="font-weight:600;font-size:14px;margin-bottom:4px">
            <a href="{{ route('wali.siswa', [$c->siswa, 'kelas' => $kelas->id]) }}">{{ $c->siswa->name }}</a>
          </div>
          <p style="font-size:13px;color:var(--redup);margin:0;line-height:1.4">
            {{ \Illuminate\Support\Str::limit($c->catatan, 100) }}
          </p>
          @if ($c->tindak_lanjut)
            <div style="font-size:12px;margin-top:6px;color:var(--tinta);font-style:italic">
              Tindak lanjut: {{ \Illuminate\Support\Str::limit($c->tindak_lanjut, 80) }}
            </div>
          @endif
        </div>
      @empty
        @include('partials.kosong', [
          'ikon' => 'edit',
          'judul' => 'Belum Ada Catatan',
          'teks' => 'Gunakan tab Catatan Pembinaan untuk mencatat konseling, prestasi, atau panggilan orang tua.'
        ])
      @endforelse
      <div class="mt" style="text-align:center">
        <a href="{{ route('wali.catatan', ['kelas' => $kelas->id]) }}" class="btn kecil" style="width:100%">
          {!! icon('plus', 14) !!}Tambah Catatan Siswa
        </a>
      </div>
    </div>

    {{-- Card: Daftar Mata Pelajaran --}}
    <div class="kartu mt">
      <h2>{!! icon('book', 18) !!}Mata Pelajaran di Kelas Ini</h2>
      <div style="display:flex;flex-direction:column;gap:8px">
        @forelse ($ruang as $r)
          <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--garis)">
            <div>
              <b style="font-size:13.5px">{{ $r->mapel->nama }}</b>
              <div class="kecil-redup">{{ $r->guru?->name ?? 'Guru belum ditentukan' }}</div>
            </div>
            <span class="lencana">KKM {{ $r->kkm }}</span>
          </div>
        @empty
          <div class="kecil-redup">Belum ada mata pelajaran yang dibuat untuk kelas ini.</div>
        @endforelse
      </div>
    </div>
  </div>
</div>
@endsection
