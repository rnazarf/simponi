<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title') · {{ setting('nama_sekolah') }}</title>
<script>
(function() {
  var t = localStorage.getItem('lms_tema') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  document.documentElement.setAttribute('data-theme', t);
})();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v=3">
@stack('head')
</head>
<body>
@php($u = auth()->user())
<div class="atas-hp">
  <button type="button" onclick="document.body.classList.toggle('menu-buka')" aria-label="Buka menu">{!! icon('menu', 24) !!}</button>
  <b>{{ setting('nama_sekolah') }}</b>
  <button type="button" onclick="toggleTema()" aria-label="Ganti tema" class="ikon-hp-tema" title="Ganti Mode Gelap / Terang">
    <span class="tema-ikon-gelap">{!! icon('moon', 20) !!}</span>
    <span class="tema-ikon-terang">{!! icon('sun', 20) !!}</span>
  </button>
</div>
<div class="app">
  <aside class="sisi" onclick="if(event.target.closest('a'))document.body.classList.remove('menu-buka')">
    <div class="merek">
      <div class="logo">{!! icon('school', 22) !!}</div>
      <div><b>{{ setting('nama_sekolah') }}</b><small>LMS PJJ · {{ \App\Models\TahunAjaran::aktif()?->label() ?? 'TA belum diatur' }}</small></div>
    </div>
    <a href="{{ route('home') }}" class="nav {{ request()->routeIs('home') ? 'on' : '' }}">{!! icon('home') !!}Beranda</a>
    <a href="{{ route('ruang') }}" class="nav {{ request()->routeIs('ruang*', 'pertemuan.*', 'tugas.*', 'kuis.*') ? 'on' : '' }}">{!! icon('book') !!}{{ $u->role === 'admin' ? 'Semua Ruang Belajar' : 'Ruang Belajar' }}</a>
    <a href="{{ route('jadwal') }}" class="nav {{ request()->routeIs('jadwal') ? 'on' : '' }}">{!! icon('calendar') !!}Jadwal</a>
    <a href="{{ route('pengumuman') }}" class="nav {{ request()->routeIs('pengumuman') ? 'on' : '' }}">{!! icon('megaphone') !!}Pengumuman</a>
    @if ($u->role === 'admin')
      <div class="judul-grup">Data sekolah</div>
      <a href="{{ route('admin.pengguna', 'siswa') }}" class="nav {{ request()->is('admin/pengguna/siswa*') ? 'on' : '' }}">{!! icon('users') !!}Siswa</a>
      <a href="{{ route('admin.pengguna', 'guru') }}" class="nav {{ request()->is('admin/pengguna/guru*') ? 'on' : '' }}">{!! icon('user') !!}Guru</a>
      <a href="{{ route('admin.kelas') }}" class="nav {{ request()->routeIs('admin.kelas') ? 'on' : '' }}">{!! icon('grid') !!}Kelas</a>
      <a href="{{ route('admin.mapel') }}" class="nav {{ request()->routeIs('admin.mapel') ? 'on' : '' }}">{!! icon('layers') !!}Mata Pelajaran</a>
      @if (in_array(setting('jenjang'), ['SMA', 'SMK']))
        <a href="{{ route('admin.jurusan') }}" class="nav {{ request()->routeIs('admin.jurusan') ? 'on' : '' }}">{!! icon('flag') !!}Jurusan</a>
      @endif
      <a href="{{ route('admin.ruang') }}" class="nav {{ request()->routeIs('admin.ruang') ? 'on' : '' }}">{!! icon('clipboard') !!}Pembagian Mengajar</a>
      <a href="{{ route('admin.penjadwalan') }}" class="nav {{ request()->routeIs('admin.penjadwalan*') ? 'on' : '' }}">{!! icon('clock') !!}Penjadwalan</a>
      <div class="judul-grup">Laporan</div>
      <a href="{{ route('admin.rekap.presensi') }}" class="nav {{ request()->routeIs('admin.rekap.presensi') ? 'on' : '' }}">{!! icon('check-square') !!}Rekap Presensi</a>
      <a href="{{ route('admin.rekap.nilai') }}" class="nav {{ request()->routeIs('admin.rekap.nilai') ? 'on' : '' }}">{!! icon('award') !!}Leger Nilai</a>
      <a href="{{ route('admin.rekap.keaktifan') }}" class="nav {{ request()->routeIs('admin.rekap.keaktifan') ? 'on' : '' }}">{!! icon('chart') !!}Keaktifan Siswa</a>
      <a href="{{ route('admin.sekolah') }}" class="nav {{ request()->routeIs('admin.sekolah') ? 'on' : '' }}">{!! icon('settings') !!}Pengaturan</a>
    @endif
    <div class="bawah">
      <div class="tema-toggle-wrap">
        <button type="button" class="tema-btn" onclick="toggleTema()" title="Ganti Mode Gelap / Terang">
          <span class="tema-ikon-gelap">{!! icon('moon', 16) !!}</span>
          <span class="tema-ikon-terang">{!! icon('sun', 16) !!}</span>
          <span class="tema-label">Mode Gelap</span>
        </button>
      </div>
      <a href="{{ route('profil') }}" class="akun" style="text-decoration:none">
        <span class="avatar">{{ $u->inisial() }}</span>
        <span style="min-width:0"><b class="potong">{{ $u->name }}</b><small>{{ \App\Models\User::ROLES[$u->role] }}{{ $u->kelas ? ' · ' . $u->kelas->nama : '' }}</small></span>
      </a>
      <form method="post" action="{{ route('logout') }}">@csrf<button class="btn garis kecil">{!! icon('logout', 16) !!}Keluar</button></form>
    </div>
  </aside>
  <main class="utama">
    @include('partials.flash')
    @yield('content')
  </main>
</div>
<script>
// Fungsi Dark Mode
function toggleTema() {
  var cur = document.documentElement.getAttribute('data-theme') || 'light';
  var target = cur === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', target);
  localStorage.setItem('lms_tema', target);
  perbaruiLabelTema();
}
function perbaruiLabelTema() {
  var cur = document.documentElement.getAttribute('data-theme') || 'light';
  var labels = document.querySelectorAll('.tema-label');
  labels.forEach(function(l) { l.textContent = cur === 'dark' ? 'Mode Terang' : 'Mode Gelap'; });
}
document.addEventListener('DOMContentLoaded', perbaruiLabelTema);

// Konfirmasi untuk tombol hapus / aksi penting
document.addEventListener('submit', function (e) {
  var f = e.target;
  if (f.dataset.konfirmasi && !confirm(f.dataset.konfirmasi)) e.preventDefault();
});
</script>
@stack('script')
</body>
</html>
