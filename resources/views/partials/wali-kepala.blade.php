@php($u = auth()->user())
<div class="ruang-kepala">
  <div class="isi">
    <span class="pita" style="background: var(--tinta)"></span>
    <div style="flex:1;min-width:220px">
      <div class="remah">
        <a href="{{ route('home') }}">Beranda</a> / 
        <span>Kelas Perwalian</span>
      </div>
      <h1 style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        {{ $kelas->nama }}
        <span class="lencana biru" style="font-size:12px;vertical-align:middle">Tingkat {{ $kelas->tingkat }}</span>
        @if ($kelas->jurusan)
          <span class="lencana" style="font-size:12px;vertical-align:middle">{{ $kelas->jurusan->kode }}</span>
        @endif
      </h1>
      <div class="meta">
        <span>{!! icon('user', 15) !!}<b>Wali:</b> {{ $kelas->wali?->name ?? 'Belum ditentukan' }}</span>
        <span>{!! icon('users', 15) !!}<b>{{ $kelas->siswa()->where('aktif', true)->count() }}</b> Siswa Aktif</span>
        <span>{!! icon('book', 15) !!}<b>{{ $kelas->ruang()->where('tahun_ajaran_id', \App\Models\TahunAjaran::aktifId())->count() }}</b> Mata Pelajaran</span>
      </div>
    </div>
    @if ($kelasList->count() > 1)
      <div class="aksi">
        <form method="get" class="inline-form" style="margin:0">
          <label class="kecil-redup" style="margin-right:6px">Ganti Kelas:</label>
          <select name="kelas" onchange="this.form.submit()" style="font-weight:600;padding:6px 10px">
            @foreach ($kelasList as $k)
              <option value="{{ $k->id }}" @selected($kelas->id === $k->id)>{{ $k->nama }}</option>
            @endforeach
          </select>
        </form>
      </div>
    @endif
  </div>
  <nav class="tab">
    <a href="{{ route('wali.index', ['kelas' => $kelas->id]) }}" class="{{ request()->routeIs('wali.index') ? 'on' : '' }}">
      {!! icon('home', 16) !!}Ringkasan
    </a>
    <a href="{{ route('wali.presensi', ['kelas' => $kelas->id]) }}" class="{{ request()->routeIs('wali.presensi') ? 'on' : '' }}">
      {!! icon('check-square', 16) !!}Presensi Lintas Mapel
    </a>
    <a href="{{ route('wali.tugas', ['kelas' => $kelas->id]) }}" class="{{ request()->routeIs('wali.tugas') ? 'on' : '' }}">
      {!! icon('clipboard', 16) !!}Monitoring Tugas &amp; Kuis
    </a>
    <a href="{{ route('wali.nilai', ['kelas' => $kelas->id]) }}" class="{{ request()->routeIs('wali.nilai') ? 'on' : '' }}">
      {!! icon('award', 16) !!}Leger Nilai
    </a>
    <a href="{{ route('wali.catatan', ['kelas' => $kelas->id]) }}" class="{{ request()->routeIs('wali.catatan') ? 'on' : '' }}">
      {!! icon('edit', 16) !!}Catatan Pembinaan
    </a>
  </nav>
</div>
