@extends('layouts.app')
@section('title', 'Pengaturan sekolah')
@section('content')
<div class="kepala"><div><h1><span class="sorot">Pengaturan sekolah</span></h1><p>Profil sekolah, jenjang, dan tahun ajaran.</p></div></div>
<div class="grid g2">
  <form method="post" class="kartu">
    @csrf
    <h2>{!! icon('school', 18) !!}Profil sekolah</h2>
    <div class="field"><label>Nama sekolah</label><input type="text" name="nama_sekolah" value="{{ old('nama_sekolah', setting('nama_sekolah')) }}" required></div>
    <div class="baris-form">
      <div class="field"><label>Jenjang</label><select name="jenjang">@foreach (['SD', 'SMP', 'SMA', 'SMK'] as $j)<option @selected(setting('jenjang') === $j)>{{ $j }}</option>@endforeach</select></div>
      <div class="field"><label>NPSN</label><input type="text" name="npsn" value="{{ old('npsn', setting('npsn')) }}"></div>
      <div class="field"><label>KKM bawaan</label><input type="number" name="kkm" min="0" max="100" value="{{ old('kkm', setting('kkm')) }}" required></div>
    </div>
    <div class="field"><label>Alamat</label><input type="text" name="alamat" value="{{ old('alamat', setting('alamat')) }}"></div>
    <div class="field"><label>Kepala sekolah</label><input type="text" name="kepala_sekolah" value="{{ old('kepala_sekolah', setting('kepala_sekolah')) }}"></div>
    <div class="field">
      <label>Presensi Daring Mandiri Siswa</label>
      <select name="presensi_daring">
        <option value="1" @selected(setting('presensi_daring') !== '0')>Aktif (ON) — Siswa bisa presensi mandiri online ("Saya hadir")</option>
        <option value="0" @selected(setting('presensi_daring') === '0')>Nonaktif (OFF) — Tatap Muka / Offline (Presensi dicatat guru di kelas)</option>
      </select>
      <small>Jika diatur Nonaktif (OFF), siswa tidak dapat presensi mandiri. Mode ini cocok saat sekolah menjalankan pembelajaran tatap muka/offline.</small>
    </div>

    <div style="margin-top:20px;padding-top:16px;border-top:1px dashed var(--garis)">
      <h3 style="display:flex;align-items:center;gap:8px;font-size:16px;margin:0 0 10px;color:var(--aksen)">
        <span style="font-size:18px">✨</span> Integrasi Google Gemini AI
      </h3>
      <p class="kecil-redup" style="margin-bottom:12px">Digunakan untuk generator soal kuis otomatis berdasarkan prompt guru dan materi pembelajaran.</p>
      
      <div class="field">
        <label>Google Gemini API Key</label>
        <div style="display:flex;gap:8px">
          <input type="password" name="gemini_api_key" id="input_gemini_api_key" value="{{ old('gemini_api_key', \App\Models\Pengaturan::geminiApiKey()) }}" placeholder="AIzaSy..." style="flex:1">
          <button type="button" class="btn garis kecil" onclick="const i = document.getElementById('input_gemini_api_key'); i.type = i.type === 'password' ? 'text' : 'password'; this.textContent = i.type === 'password' ? 'Lihat' : 'Sembunyikan';">Lihat</button>
        </div>
        <small>Dapatkan API Key gratis di <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">Google AI Studio (aistudio.google.com)</a>. Kunci juga bisa disetel di file <code>.env</code> (GEMINI_API_KEY).</small>
      </div>

      <div class="field">
        <label>Model Gemini Bawaan</label>
        @php($modelDipilih = old('gemini_model', \App\Models\Pengaturan::geminiModel()))
        <select name="gemini_model">
          <option value="gemini-2.5-flash" @selected($modelDipilih === 'gemini-2.5-flash')>gemini-2.5-flash (Direkomendasikan — Generasi Terbaru, Cepat &amp; Akurat)</option>
          <option value="gemini-2.5-pro" @selected($modelDipilih === 'gemini-2.5-pro')>gemini-2.5-pro (Penalaran Kompleks / Soal Analisis Tinggi)</option>
          <option value="gemini-flash-latest" @selected($modelDipilih === 'gemini-flash-latest')>gemini-flash-latest (Model Flash Otomatis Terbaru)</option>
        </select>
      </div>
    </div>

    <p class="kecil-redup">Jenjang menentukan pilihan tingkat kelas (SD 1–6, SMP 7–9, SMA/SMK 10–12). Menu Jurusan muncul untuk SMA dan SMK.</p>
    <button class="btn">{!! icon('check', 16) !!}Simpan</button>
  </form>
  <div>
    <div class="kartu rapat">
      <h2>{!! icon('calendar', 18) !!}Tahun ajaran</h2>
      <div class="tabel-wrap mt"><table>
        <thead><tr><th>Tahun ajaran</th><th>Semester</th><th class="angka">Ruang</th><th></th></tr></thead>
        <tbody>
          @foreach ($tahun as $t)
            <tr><td class="tebal">{{ $t->nama }}</td><td>{{ $t->semester }}</td><td class="angka">{{ $t->ruang_count }}</td>
              <td class="angka">@if ($t->aktif)<span class="lencana hijau">Aktif</span>@else
                <form method="post" action="{{ route('admin.tahun.aktif', $t) }}" data-konfirmasi="Aktifkan {{ $t->label() }}? Guru & siswa akan melihat ruang belajar semester ini.">@csrf<button class="btn kecil garis">Aktifkan</button></form>@endif</td></tr>
          @endforeach
        </tbody>
      </table></div>
    </div>
    <form method="post" action="{{ route('admin.tahun.store') }}" class="kartu">
      @csrf
      <h2>{!! icon('plus', 18) !!}Tambah semester</h2>
      <div class="baris-form">
        <div class="field"><label>Tahun ajaran</label><input type="text" name="nama" placeholder="2027/2028" pattern="\d{4}/\d{4}" required></div>
        <div class="field"><label>Semester</label><select name="semester"><option>Ganjil</option><option>Genap</option></select></div>
      </div>
      <div class="field"><label class="cek"><input type="checkbox" name="salin" value="1" checked> Salin pembagian mengajar, struktur jam, beban ajar &amp; jadwal dari semester aktif</label></div>
      <button class="btn garis">{!! icon('plus', 16) !!}Tambah</button>
    </form>
  </div>
</div>
@endsection
