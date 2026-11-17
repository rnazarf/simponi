<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk · {{ setting('nama_sekolah') }}</title>
<script>
(function() {
  var t = localStorage.getItem('lms_tema') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  document.documentElement.setAttribute('data-theme', t);
})();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v=4">
</head>
<body>
<div class="login-halaman">
  <section class="login-kiri">
    <div class="merek" style="border:0;padding:0">
      <div class="logo">{!! icon('school', 22) !!}</div>
      <div><b>{{ setting('nama_sekolah') }}</b><small>NPSN {{ setting('npsn') }}</small></div>
    </div>
    <div>
      <div class="tulisan">SIMPONI · Sistem Pembelajaran &amp; Evaluasi Mandiri</div>
      <h1>Belajar terarah, <span>berprestasi</span> bersama guru hebat.</h1>
      <p>Materi, presensi daring &amp; tatap muka, tugas, kuis berbasis AI, dan leger nilai jenjang {{ setting('jenjang') }} terpadu dalam satu portal modern.</p>
      <div class="login-poin">
        <div><span class="ic">{!! icon('video', 18) !!}</span>Materi terstruktur: teks, video, dan lampiran berkas</div>
        <div><span class="ic">{!! icon('check-square', 18) !!}</span>Presensi daring &amp; tatap muka dengan jadwal buka-tutup</div>
        <div><span class="ic">{!! icon('clipboard', 18) !!}</span>Pengumpulan tugas aman disertai umpan balik guru</div>
        <div><span class="ic">{!! icon('quiz', 18) !!}</span>Kuis interaktif AI &amp; sistem anti-curang dengan penilaian instan</div>
      </div>
    </div>
    <small style="color:#64748b;font-size:12px;position:relative;z-index:1">{{ setting('alamat') }}</small>
  </section>
  <section class="login-kanan">
    <div style="position:absolute;top:20px;right:20px">
      <button type="button" class="btn garis kecil" onclick="toggleTema()" title="Ganti Mode Gelap / Terang">
        <span class="tema-ikon-gelap">{!! icon('moon', 16) !!}</span>
        <span class="tema-ikon-terang">{!! icon('sun', 16) !!}</span>
        <span class="tema-label">Mode Gelap</span>
      </button>
    </div>
    <div class="login-kotak">
      <h2><span class="sorot">Selamat Datang</span></h2>
      <p style="color:var(--redup);font-size:13.5px;margin-top:4px">Silakan masuk menggunakan NIP (guru) atau NISN (siswa).</p>
      @include('partials.flash')
      <form method="post" action="{{ url('/login') }}" class="mt">
        @csrf
        <div class="field">
          <label for="username">NIP / NISN / Nama Pengguna</label>
          <input type="text" id="username" name="username" value="{{ old('username') }}" placeholder="Masukkan username atau NISN/NIP" autocomplete="username" required autofocus>
        </div>
        <div class="field">
          <label for="password">Kata Sandi</label>
          <input type="password" id="password" name="password" placeholder="Masukkan kata sandi" autocomplete="current-password" required>
        </div>
        <div class="field"><label class="cek"><input type="checkbox" name="ingat" value="1"> Ingat saya di perangkat ini</label></div>
        <button class="btn blok" style="padding:11px 18px;font-size:14px">{!! icon('arrow', 18) !!}Masuk ke Akun</button>
      </form>
      @if (config('app.env') !== 'production')
      <div class="demo" style="margin-top:20px">
        <b>Akun Demo (Klik baris untuk mengisi otomatis)</b>
        <table>
          <tr onclick="isiAkun('admin', 'admin123')" title="Klik untuk pakai akun Admin">
            <td><strong>Admin</strong></td><td><code>admin</code></td><td><code>admin123</code></td>
          </tr>
          <tr onclick="isiAkun('guru', 'guru123')" title="Klik untuk pakai akun Guru">
            <td><strong>Guru</strong></td><td><code>guru</code></td><td><code>guru123</code></td>
          </tr>
          <tr onclick="isiAkun('siswa', 'siswa123')" title="Klik untuk pakai akun Siswa">
            <td><strong>Siswa</strong></td><td><code>siswa</code></td><td><code>siswa123</code></td>
          </tr>
        </table>
      </div>
      <script>
      function isiAkun(u, p) {
        document.getElementById('username').value = u;
        document.getElementById('password').value = p;
      }
      </script>
      @endif
      <script>
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
      </script>
    </div>
  </section>
</div>
</body>
</html>
