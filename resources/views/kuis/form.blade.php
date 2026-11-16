@extends('layouts.app')
@section('title', ($kuis->exists ? 'Ubah' : 'Buat') . ' kuis')
@section('content')
@include('partials.ruang-kepala')
@php($sudahDikerjakan = $sudahDikerjakan ?? false)
@php($ubahSoal = $ubahSoal ?? null)
<div class="grid g-utama">
  <div>
    @if ($kuis->exists)
      <div class="kartu rapat">
        <h2>{!! icon('quiz', 18) !!}Soal <span class="kanan kecil-redup">{{ $kuis->soal->count() }} soal · total bobot {{ $kuis->soal->sum('bobot') }}</span></h2>
        @if ($sudahDikerjakan)<p class="flash error" style="margin:12px 20px 0">Sudah ada siswa yang mengerjakan. Mengubah kunci/bobot akan menghitung ulang nilai mereka.</p>@endif
        <div style="padding:6px 20px 16px">
          @forelse ($kuis->soal as $s)
            <div style="padding:14px 0;border-bottom:1px dashed var(--garis-2)">
              <div style="display:flex;gap:10px;align-items:flex-start">
                <b style="color:var(--tinta)">{{ $s->urutan }}.</b>
                <div style="flex:1;min-width:0">
                  <div style="white-space:pre-line">{{ $s->pertanyaan }}</div>
                  @if ($s->jenis === 'pg')
                    <div style="display:grid;gap:4px;margin-top:8px;font-size:14px">
                      @foreach ($s->opsi as $h => $o)<div style="{{ $h === $s->kunci ? 'color:var(--hijau);font-weight:700' : 'color:var(--redup)' }}">{{ $h }}. {{ $o }} @if ($h === $s->kunci){!! icon('check', 14) !!}@endif</div>@endforeach
                    </div>
                  @endif
                  <div style="margin-top:6px"><span class="lencana {{ $s->jenis === 'pg' ? 'biru' : 'kuning' }}">{{ $s->jenis === 'pg' ? 'Pilihan ganda' : 'Esai' }}</span> <span class="lencana">Bobot {{ $s->bobot }}</span></div>
                </div>
                <a class="ikon-btn" href="?soal={{ $s->id }}#form-soal" title="Ubah">{!! icon('edit', 16) !!}</a>
                @include('partials.hapus', ['url' => route('kuis.soal.hapus', [$ruang, $kuis, $s]), 'pesan' => 'Hapus soal nomor ' . $s->urutan . '?'])
              </div>
            </div>
          @empty
            @include('partials.kosong', ['ikon' => 'quiz', 'judul' => 'Belum ada soal', 'teks' => 'Tambah satu per satu atau impor sekaligus dari teks.'])
          @endforelse
        </div>
      </div>

      {{-- ================================================================ --}}
      {{-- Generator Soal AI (Google Gemini)                                --}}
      {{-- ================================================================ --}}
      @php($geminiTersedia = \App\Models\Pengaturan::geminiTersedia())
      @php($geminiModel = \App\Models\Pengaturan::geminiModel())
      @php($jenjangSekolah = setting('jenjang'))
      @php($defaultOpsiCount = in_array($jenjangSekolah, ['SMA', 'SMK']) ? 5 : 4)

      <div class="kartu kartu-ai" id="kartu-ai">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px">
          <h2 style="margin:0;display:flex;align-items:center;gap:8px">
            {!! icon('sparkles', 20) !!}Buat Soal Otomatis dengan AI
            <span class="ai-header-tag"><span class="ai-sparkle">✨</span> Google Gemini</span>
          </h2>
          <span class="lencana {{ $geminiTersedia ? 'hijau' : 'kuning' }}" id="ai-status-badge">
            {{ $geminiModel }} · {{ $geminiTersedia ? 'Siap' : 'Key Belum Disetel' }}
          </span>
        </div>

        <p class="kecil-redup" style="margin-bottom:14px">
          Ketik topik/materi atau tempel rangkuman pelajaran. AI akan menyusun butir soal, opsi pengecoh, kunci jawaban benar, dan pembahasan secara otomatis.
        </p>

        <div id="ai-key-warning" class="flash" style="background:var(--oranye-lembut);border-color:var(--oranye-border);color:var(--oranye-tua);margin-bottom:14px;padding:10px 14px;border-radius:var(--r-sm);{{ $geminiTersedia ? 'display:none;' : '' }}">
          <div style="display:flex;align-items:flex-start;gap:8px">
            {!! icon('alert', 16) !!}
            <div style="flex:1;font-size:13px">
              <b>Google Gemini API Key belum terkonfigurasi di sistem.</b>
              @if (auth()->user()->role === 'admin')
                Anda dapat menyetelnya secara global di <a href="{{ route('admin.sekolah') }}" style="color:var(--tinta);font-weight:600;text-decoration:underline">Pengaturan Sekolah</a> atau file <code>.env</code>.
              @else
                Admin dapat menyetelnya di Pengaturan Sekolah, atau Anda dapat menggunakan <b>API Key pribadi</b> gratis di bawah ini.
              @endif
              <div style="margin-top:6px">
                <a href="javascript:void(0)" id="toggle-form-kunci" style="text-decoration:underline;color:inherit;font-weight:600">Gunakan API Key Pribadi untuk sesi ini &rarr;</a>
              </div>
            </div>
          </div>
          <div id="form-kunci-pribadi" style="display:none;margin-top:10px;padding-top:10px;border-top:1px dashed var(--oranye-border)">
            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">API Key Google Gemini Pribadi (Tersimpan di browser):</label>
            <div style="display:flex;gap:6px">
              <input type="password" id="input-personal-key" placeholder="AIzaSy..." style="flex:1;font-size:13px;padding:6px 10px">
              <button type="button" class="btn kecil" id="btn-simpan-personal-key">Gunakan</button>
            </div>
            <small class="kecil-redup" style="display:block;margin-top:4px">Dapatkan API Key gratis di <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener" style="text-decoration:underline">Google AI Studio</a>.</small>
          </div>
        </div>

        <div class="field">
          <label style="display:flex;justify-content:space-between;align-items:center">
            <span>Materi / Topik / Rangkuman Pembelajaran <span class="wajib">*</span></span>
            <span class="kecil-redup" id="btn-contoh-prompt" style="cursor:pointer;color:var(--tinta);text-decoration:underline;font-size:12.5px">Contoh prompt</span>
          </label>
          <textarea id="ai-prompt" style="min-height:95px" placeholder="Tuliskan topik atau instruksi. Contoh:
- Buatkan 5 soal Pilihan Ganda tentang Sistem Peredaran Darah Manusia untuk kelas 11 SMA, fokus pada studi kasus dan HOTS.
- Atau tempelkan (paste) rangkuman teks materi dari modul Anda di sini agar soal 100% bersumber dari teks tersebut."></textarea>
        </div>

        <div class="baris-form">
          <div class="field" style="flex:1;min-width:110px">
            <label>Jumlah Soal</label>
            <select id="ai-jumlah">
              <option value="3">3 Soal</option>
              <option value="5" selected>5 Soal</option>
              <option value="10">10 Soal</option>
              <option value="15">15 Soal</option>
              <option value="20">20 Soal</option>
            </select>
          </div>
          <div class="field" style="flex:1.4;min-width:140px">
            <label>Bentuk Soal</label>
            <select id="ai-jenis">
              <option value="pg" selected>Pilihan Ganda (PG)</option>
              <option value="esai">Esai / Uraian</option>
              <option value="campuran">Campuran (PG + Esai)</option>
            </select>
          </div>
          <div class="field" style="flex:1.4;min-width:140px">
            <label>Tingkat Kesulitan</label>
            <select id="ai-tingkat">
              <option value="sedang" selected>Sedang (MOTS - Bawaan)</option>
              <option value="mudah">Mudah (LOTS)</option>
              <option value="sulit">Sulit / Analisis (HOTS)</option>
              <option value="campuran">Bervariasi (Mudah-HOTS)</option>
            </select>
          </div>
          <div class="field" style="flex:1.2;min-width:130px" id="wrap-opsi-count">
            <label>Pilihan Opsi</label>
            <select id="ai-opsi-count">
              <option value="5" @selected($defaultOpsiCount === 5)>5 Opsi (A - E)</option>
              <option value="4" @selected($defaultOpsiCount === 4)>4 Opsi (A - D)</option>
            </select>
          </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:14px;align-items:center;flex-wrap:wrap">
          <button type="button" id="btn-generate-ai" class="btn btn-ai-magic">
            {!! icon('sparkles', 16) !!} Generate Soal dengan AI
          </button>
          <span class="kecil-redup" style="font-size:13px">Diproses langsung oleh model {{ $geminiModel }}.</span>
        </div>

        {{-- Loading State --}}
        <div id="ai-loading-box" class="ai-loading-box" style="display:none;">
          <div class="ai-spinner"></div>
          <div style="font-weight:700;color:var(--teks);font-size:15px" id="ai-loading-title">Menghubungi Google Gemini AI...</div>
          <div class="kecil-redup" style="margin-top:4px" id="ai-loading-desc">Menganalisis materi, menyusun butir soal, opsi pengecoh, dan kunci jawaban...</div>
        </div>

        {{-- Error State --}}
        <div id="ai-error-box" class="flash error" style="display:none;margin-top:16px">
          <div style="display:flex;align-items:flex-start;gap:8px">
            {!! icon('alert', 16) !!}
            <div style="flex:1">
              <span id="ai-error-msg">Terjadi kesalahan.</span>
            </div>
          </div>
        </div>

        {{-- Pratinjau Soal Hasil Generate --}}
        <div id="ai-preview-box" class="ai-preview-wrap" style="display:none;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px">
            <div>
              <h3 style="margin:0;display:flex;align-items:center;gap:6px;font-size:16px">
                {!! icon('quiz', 18) !!}Pratinjau Hasil Soal AI
                <span class="lencana hijau" id="ai-preview-count">0 soal</span>
              </h3>
              <p class="kecil-redup" style="margin:2px 0 0;font-size:12.5px">Centang soal yang disetujui, edit teks bila diperlukan, lalu simpan ke kuis.</p>
            </div>
            <div style="display:flex;gap:8px;align-items:center">
              <label class="cek" style="font-size:13px"><input type="checkbox" id="ai-pilih-semua" checked> Pilih Semua</label>
              <button type="button" id="btn-simpan-ai-top" class="btn btn-ai-magic kecil">{!! icon('check', 14) !!} Masukkan ke Kuis</button>
            </div>
          </div>

          <div id="ai-preview-list"></div>

          <div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;padding-top:14px;border-top:1px dashed var(--garis);flex-wrap:wrap;gap:10px">
            <button type="button" id="btn-batal-preview" class="btn garis">Tutup Pratinjau</button>
            <button type="button" id="btn-simpan-ai" class="btn btn-ai-magic">
              {!! icon('check', 16) !!} Masukkan <span id="ai-selected-count">0</span> Soal Terpilih ke Kuis
            </button>
          </div>
        </div>
      </div>

      <form method="post" action="{{ route('kuis.soal', [$ruang, $kuis]) }}" class="kartu" id="form-soal">
        @csrf
        <h2>{!! icon($ubahSoal ? 'edit' : 'plus', 18) !!}{{ $ubahSoal ? 'Ubah soal nomor ' . $ubahSoal->urutan : 'Tambah soal' }}</h2>
        @if ($ubahSoal)<input type="hidden" name="soal_id" value="{{ $ubahSoal->id }}">@endif
        @php($jenis = old('jenis', $ubahSoal->jenis ?? 'pg'))
        <div class="baris-form">
          <div class="field"><label>Jenis</label><select name="jenis" id="jenis"><option value="pg" @selected($jenis === 'pg')>Pilihan ganda (dinilai otomatis)</option><option value="esai" @selected($jenis === 'esai')>Esai (dikoreksi guru)</option></select></div>
          <div class="field" style="max-width:120px"><label>Bobot</label><input type="number" name="bobot" min="1" max="100" value="{{ old('bobot', $ubahSoal->bobot ?? 1) }}" required></div>
        </div>
        <div class="field"><label>Pertanyaan</label><textarea name="pertanyaan" required style="min-height:90px">{{ old('pertanyaan', $ubahSoal->pertanyaan ?? '') }}</textarea></div>
        <div id="area-opsi">
          @foreach (['A', 'B', 'C', 'D', 'E'] as $h)
            <div class="inline-form" style="margin-bottom:8px">
              <label class="cek" style="width:58px"><input type="radio" name="kunci" value="{{ $h }}" @checked(old('kunci', $ubahSoal->kunci ?? '') === $h)> {{ $h }}</label>
              <div class="field" style="flex:1"><input type="text" name="opsi[{{ $h }}]" value="{{ old('opsi.' . $h, $ubahSoal->opsi[$h] ?? '') }}" placeholder="Opsi {{ $h }}{{ $h === 'E' ? ' (opsional)' : '' }}"></div>
            </div>
          @endforeach
          <small class="kecil-redup">Pilih bulatan di kiri sebagai kunci jawaban.</small>
        </div>
        <div style="display:flex;gap:8px;margin-top:12px">
          <button class="btn">{!! icon('check', 16) !!}{{ $ubahSoal ? 'Simpan perubahan' : 'Tambah soal' }}</button>
          @if ($ubahSoal)<a class="btn garis" href="{{ route('kuis.edit', [$ruang, $kuis]) }}">Batal</a>@endif
        </div>
      </form>

      <form method="post" action="{{ route('kuis.impor', [$ruang, $kuis]) }}" class="kartu">
        @csrf
        <h2>{!! icon('upload', 18) !!}Impor soal dari teks</h2>
        <p class="kecil-redup">Tempel soal dari Word/Google Docs dengan format di bawah. Soal tanpa opsi dianggap esai.</p>
        <textarea name="teks" class="besar" placeholder="1. Ibu kota provinsi Jawa Tengah adalah ...
A. Surabaya
B. Semarang
C. Bandung
D. Yogyakarta
Kunci: B

2. Jelaskan pengertian fotosintesis!"></textarea>
        <button class="btn garis mt">{!! icon('upload', 16) !!}Impor soal</button>
      </form>
    @endif
  </div>

  <form method="post" action="{{ $kuis->exists ? route('kuis.update', [$ruang, $kuis]) : route('kuis.store', $ruang) }}" class="kartu">
    @csrf @if ($kuis->exists) @method('put') @endif
    <h2>{!! icon('settings', 18) !!}Pengaturan kuis</h2>
    <div class="field"><label>Judul</label><input type="text" name="judul" value="{{ old('judul', $kuis->judul) }}" required maxlength="160" placeholder="Contoh: Ulangan Harian Bab 2"></div>
    <div class="field"><label>Petunjuk</label><textarea name="petunjuk" style="min-height:80px" placeholder="Kerjakan dengan jujur. Waktu berjalan sejak tombol Mulai ditekan.">{{ old('petunjuk', $kuis->petunjuk) }}</textarea></div>
    <div class="field"><label>Dibuka</label><input type="datetime-local" name="mulai" value="{{ old('mulai', $kuis->mulai?->format('Y-m-d\TH:i')) }}" required></div>
    <div class="field"><label>Ditutup</label><input type="datetime-local" name="selesai" value="{{ old('selesai', $kuis->selesai?->format('Y-m-d\TH:i')) }}" required></div>
    <div class="field"><label>Durasi mengerjakan (menit)</label><input type="number" name="durasi_menit" min="5" max="600" value="{{ old('durasi_menit', $kuis->durasi_menit) }}" required><small>Waktu tetap berhenti saat kuis ditutup.</small></div>
    <div class="field"><label class="cek"><input type="checkbox" name="acak_soal" value="1" @checked(old('acak_soal', $kuis->acak_soal))> Acak urutan soal</label></div>
    <div class="field"><label class="cek"><input type="checkbox" name="acak_opsi" value="1" @checked(old('acak_opsi', $kuis->acak_opsi))> Acak urutan opsi jawaban</label></div>
    <div class="field"><label class="cek"><input type="checkbox" name="tampilkan_nilai" value="1" @checked(old('tampilkan_nilai', $kuis->tampilkan_nilai))> Tampilkan nilai ke siswa setelah selesai</label></div>

    <div style="margin:14px 0 10px;padding:12px;border:1px dashed var(--garis-2);border-radius:var(--r-md);background:var(--kertas)">
      <div class="field" style="margin-bottom:8px">
        <label class="cek">
          <input type="checkbox" name="anti_curang" id="cek-anti-curang" value="1" @checked(old('anti_curang', $kuis->anti_curang ?? true))>
          <b style="color:var(--tinta)">🛡️ Aktifkan Sistem Anti-Curang</b>
        </label>
        <small style="display:block;margin-top:2px;color:var(--redup)">Mendeteksi siswa yang beralih tab/aplikasi, memblokir copy-paste, dan mencatat log pelanggaran secara otomatis.</small>
      </div>

      <div class="field" id="wrap-maks-pelanggaran" style="margin:0">
        <label style="font-size:13px">Batas Toleransi Pelanggaran</label>
        <select name="maks_pelanggaran" style="font-size:13.5px">
          <option value="0" @selected(old('maks_pelanggaran', $kuis->maks_pelanggaran ?? 0) == 0)>0 — Pantau &amp; rekam saja (Tanpa auto-submit)</option>
          <option value="1" @selected(old('maks_pelanggaran', $kuis->maks_pelanggaran ?? 0) == 1)>1 kali — Sangat ketat (1x langgar langsung kumpul)</option>
          <option value="2" @selected(old('maks_pelanggaran', $kuis->maks_pelanggaran ?? 0) == 2)>2 kali — Ketat (2x langgar langsung kumpul)</option>
          <option value="3" @selected(old('maks_pelanggaran', $kuis->maks_pelanggaran ?? 0) == 3)>3 kali — Standar Kuis (3x peringatan lalu kumpul)</option>
          <option value="5" @selected(old('maks_pelanggaran', $kuis->maks_pelanggaran ?? 0) == 5)>5 kali — Toleransi Sedang</option>
        </select>
        <small style="display:block;margin-top:2px;color:var(--redup)">Jika siswa melanggar melebihi batas ini, jawaban kuis akan langsung dikumpulkan secara otomatis.</small>
      </div>
    </div>
    @if ($kuis->exists)
      <div class="field catatan" style="border-left-color:var(--stabilo)"><label class="cek"><input type="checkbox" name="terbit" value="1" @checked($kuis->terbit)> Terbitkan untuk siswa</label></div>
    @endif
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <button class="btn">{!! icon('check', 16) !!}{{ $kuis->exists ? 'Simpan' : 'Buat kuis & isi soal' }}</button>
      @if ($kuis->exists)<a class="btn garis" href="{{ route('kuis.show', [$ruang, $kuis]) }}">Lihat hasil</a>@endif
    </div>
  </form>
</div>
@if ($kuis->exists)
  <div class="mt">@include('partials.hapus', ['url' => route('kuis.destroy', [$ruang, $kuis]), 'pesan' => 'Hapus kuis ini beserta semua jawaban siswa?', 'kelas' => 'btn bahaya kecil', 'label' => 'Hapus kuis'])</div>
@endif
@push('script')
<script>
(function () {
  var j = document.getElementById('jenis'), a = document.getElementById('area-opsi');
  if (j && a) {
    function atur() { a.style.display = j.value === 'pg' ? '' : 'none'; }
    j.addEventListener('change', atur); atur();
  }

  // ==========================================
  // AI Quiz Generator Script (Google Gemini)
  // ==========================================
  var btnGen = document.getElementById('btn-generate-ai');
  if (!btnGen) return;

  var promptEl = document.getElementById('ai-prompt');
  var jumlahEl = document.getElementById('ai-jumlah');
  var jenisEl = document.getElementById('ai-jenis');
  var tingkatEl = document.getElementById('ai-tingkat');
  var opsiCountEl = document.getElementById('ai-opsi-count');
  var wrapOpsi = document.getElementById('wrap-opsi-count');

  var loadingBox = document.getElementById('ai-loading-box');
  var errorBox = document.getElementById('ai-error-box');
  var errorMsg = document.getElementById('ai-error-msg');
  var previewBox = document.getElementById('ai-preview-box');
  var previewList = document.getElementById('ai-preview-list');
  var previewCount = document.getElementById('ai-preview-count');
  var selectedCount = document.getElementById('ai-selected-count');
  var selectAllCheck = document.getElementById('ai-pilih-semua');
  var btnSimpan = document.getElementById('btn-simpan-ai');
  var btnSimpanTop = document.getElementById('btn-simpan-ai-top');
  var btnBatal = document.getElementById('btn-batal-preview');

  // Personal Key handling
  var inputPersonalKey = document.getElementById('input-personal-key');
  var btnSimpanKey = document.getElementById('btn-simpan-personal-key');
  var toggleFormKunci = document.getElementById('toggle-form-kunci');
  var formKunci = document.getElementById('form-kunci-pribadi');
  var keyWarning = document.getElementById('ai-key-warning');
  var statusBadge = document.getElementById('ai-status-badge');

  var savedPersonalKey = localStorage.getItem('gemini_personal_key') || '';
  if (savedPersonalKey && inputPersonalKey) {
    inputPersonalKey.value = savedPersonalKey;
    if (keyWarning && !{{ $geminiTersedia ? 'true' : 'false' }}) {
      keyWarning.style.display = 'none';
      if (statusBadge) {
        statusBadge.className = 'lencana hijau';
        statusBadge.textContent = 'Key Pribadi Aktif';
      }
    }
  }

  if (toggleFormKunci && formKunci) {
    toggleFormKunci.addEventListener('click', function () {
      formKunci.style.display = formKunci.style.display === 'none' ? 'block' : 'none';
    });
  }

  if (btnSimpanKey && inputPersonalKey) {
    btnSimpanKey.addEventListener('click', function () {
      var val = inputPersonalKey.value.trim();
      if (!val) {
        localStorage.removeItem('gemini_personal_key');
        alert('API Key pribadi dihapus.');
      } else {
        localStorage.setItem('gemini_personal_key', val);
        alert('API Key pribadi disimpan di browser Anda.');
        if (keyWarning && !{{ $geminiTersedia ? 'true' : 'false' }}) {
          keyWarning.style.display = 'none';
        }
        if (statusBadge) {
          statusBadge.className = 'lencana hijau';
          statusBadge.textContent = 'Key Pribadi Aktif';
        }
      }
    });
  }

  // Tampilkan/sembunyikan pilihan opsi huruf berdasarkan jenis soal
  if (jenisEl && wrapOpsi) {
    jenisEl.addEventListener('change', function () {
      wrapOpsi.style.display = jenisEl.value === 'esai' ? 'none' : '';
    });
  }

  // Tombol contoh prompt
  var btnContoh = document.getElementById('btn-contoh-prompt');
  if (btnContoh && promptEl) {
    btnContoh.addEventListener('click', function () {
      var mapel = '{{ addslashes($ruang->mapel?->nama ?? "Pelajaran") }}';
      var kelas = '{{ addslashes($ruang->kelas?->nama ?? "Kelas") }}';
      promptEl.value = 'Buatkan 5 butir soal untuk mata pelajaran ' + mapel + ' (' + kelas + ').\n' +
        'Fokus pada materi utama bab saat ini, sertakan soal berbasis analisis / studi kasus sehari-hari (HOTS).\n' +
        'Pastikan opsi pilihan ganda masuk akal dan kunci jawaban tepat.';
      promptEl.focus();
    });
  }

  // Simpan data soal di memori JavaScript
  var soalItems = [];

  // Generate Soal Action
  btnGen.addEventListener('click', function () {
    var promptVal = promptEl.value.trim();
    if (!promptVal) {
      alert('Silakan tulis materi atau instruksi soal terlebih dahulu.');
      promptEl.focus();
      return;
    }

    // Reset UI
    errorBox.style.display = 'none';
    previewBox.style.display = 'none';
    loadingBox.style.display = 'flex';
    btnGen.disabled = true;

    var personalKey = localStorage.getItem('gemini_personal_key') || '';

    var payload = {
      prompt: promptVal,
      jumlah: parseInt(jumlahEl.value, 10) || 5,
      jenis: jenisEl.value,
      tingkat: tingkatEl.value,
      opsi_count: parseInt(opsiCountEl.value, 10) || 5,
      api_key: personalKey,
      _token: '{{ csrf_token() }}'
    };

    fetch('{{ route("kuis.ai.generate", [$ruang, $kuis]) }}', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}'
      },
      body: JSON.stringify(payload)
    })
    .then(function (res) {
      return res.json().then(function (data) {
        return { ok: res.ok, status: res.status, data: data };
      });
    })
    .then(function (res) {
      loadingBox.style.display = 'none';
      btnGen.disabled = false;

      if (!res.ok || !res.data.ok) {
        var pesan = (res.data && res.data.pesan) ? res.data.pesan : 'Terjadi kegagalan saat generate soal.';
        errorMsg.textContent = pesan;
        errorBox.style.display = 'block';
        return;
      }

      soalItems = res.data.soal || [];
      if (!soalItems.length) {
        errorMsg.textContent = 'Tidak ada soal yang dihasilkan. Silakan coba prompt lain.';
        errorBox.style.display = 'block';
        return;
      }

      renderPreview(soalItems);
    })
    .catch(function (err) {
      loadingBox.style.display = 'none';
      btnGen.disabled = false;
      errorMsg.textContent = 'Gagal menghubungi server: ' + err.message;
      errorBox.style.display = 'block';
    });
  });

  // Render Pratinjau Soal
  function renderPreview(items) {
    previewList.innerHTML = '';
    previewCount.textContent = items.length + ' soal';

    items.forEach(function (s, idx) {
      var card = document.createElement('div');
      card.className = 'ai-soal-item';
      card.dataset.index = idx;

      var isPg = s.jenis === 'pg';
      var hurufOpsi = isPg && s.opsi ? Object.keys(s.opsi) : [];

      var html = '';
      html += '<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:8px">';
      html += '  <div style="display:flex;align-items:center;gap:8px">';
      html += '    <label class="cek" style="margin:0"><input type="checkbox" class="ai-item-check" data-idx="' + idx + '" checked> <b>#' + (idx + 1) + '</b></label>';
      html += '    <span class="lencana ' + (isPg ? 'biru' : 'kuning') + '">' + (isPg ? 'Pilihan Ganda' : 'Esai') + '</span>';
      html += '  </div>';
      html += '  <div style="display:flex;align-items:center;gap:6px">';
      html += '    <span class="kecil-redup">Bobot:</span>';
      html += '    <input type="number" class="ai-item-bobot" data-idx="' + idx + '" value="' + (s.bobot || (isPg ? 1 : 5)) + '" min="1" max="100" style="width:65px;padding:3px 6px;font-size:13px">';
      html += '  </div>';
      html += '</div>';

      // Text pertanyaan (bisa diedit guru)
      html += '<div class="field" style="margin-bottom:10px">';
      html += '  <textarea class="ai-item-tanya" data-idx="' + idx + '" style="min-height:60px;font-size:14px;line-height:1.4">' + escapeHtml(s.pertanyaan) + '</textarea>';
      html += '</div>';

      // Opsi untuk PG
      if (isPg && hurufOpsi.length) {
        html += '<div class="ai-opsi-list">';
        hurufOpsi.forEach(function (h) {
          var isKunci = (s.kunci && s.kunci.toUpperCase() === h);
          var textVal = s.opsi[h] || '';
          html += '<div class="ai-opsi-item ' + (isKunci ? 'kunci-benar' : '') + '" id="wrap-opsi-' + idx + '-' + h + '">';
          html += '  <label class="cek" style="width:50px;margin:0" title="Pilih sebagai kunci jawaban">';
          html += '    <input type="radio" name="kunci_' + idx + '" value="' + h + '" ' + (isKunci ? 'checked' : '') + ' class="ai-radio-kunci" data-idx="' + idx + '"> ' + h;
          html += '  </label>';
          html += '  <input type="text" class="ai-item-opsi" data-idx="' + idx + '" data-huruf="' + h + '" value="' + escapeHtml(textVal) + '" style="flex:1;padding:5px 8px;font-size:13px">';
          html += '</div>';
        });
        html += '</div>';
      }

      // Pembahasan
      if (s.pembahasan) {
        html += '<div class="ai-pembahasan">';
        html += '  <b>Pembahasan:</b> ' + escapeHtml(s.pembahasan);
        html += '</div>';
      }

      card.innerHTML = html;
      previewList.appendChild(card);
    });

    previewBox.style.display = 'block';
    updateSelectedCount();
    previewBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    attachPreviewEvents();
  }

  function attachPreviewEvents() {
    // Checkbox toggle unselected styling
    document.querySelectorAll('.ai-item-check').forEach(function (chk) {
      chk.addEventListener('change', function () {
        var card = chk.closest('.ai-soal-item');
        if (chk.checked) card.classList.remove('unselected');
        else card.classList.add('unselected');
        updateSelectedCount();
      });
    });

    // Radio kunci change: update highlight
    document.querySelectorAll('.ai-radio-kunci').forEach(function (rad) {
      rad.addEventListener('change', function () {
        var idx = rad.dataset.idx;
        var card = rad.closest('.ai-soal-item');
        card.querySelectorAll('.ai-opsi-item').forEach(function (el) {
          el.classList.remove('kunci-benar');
        });
        var wrap = document.getElementById('wrap-opsi-' + idx + '-' + rad.value);
        if (wrap) wrap.classList.add('kunci-benar');
      });
    });
  }

  function updateSelectedCount() {
    var checked = document.querySelectorAll('.ai-item-check:checked');
    var count = checked.length;
    if (selectedCount) selectedCount.textContent = count;
    var total = document.querySelectorAll('.ai-item-check').length;
    if (selectAllCheck) selectAllCheck.checked = (count === total && total > 0);
  }

  // Toggle select all
  if (selectAllCheck) {
    selectAllCheck.addEventListener('change', function () {
      var isCheck = selectAllCheck.checked;
      document.querySelectorAll('.ai-item-check').forEach(function (chk) {
        chk.checked = isCheck;
        var card = chk.closest('.ai-soal-item');
        if (isCheck) card.classList.remove('unselected');
        else card.classList.add('unselected');
      });
      updateSelectedCount();
    });
  }

  // Tutup preview
  if (btnBatal) {
    btnBatal.addEventListener('click', function () {
      previewBox.style.display = 'none';
    });
  }

  // Simpan ke Kuis Handler
  function prosesSimpanKeKuis() {
    var checkedBoxes = document.querySelectorAll('.ai-item-check:checked');
    if (!checkedBoxes.length) {
      alert('Pilih minimal 1 soal untuk dimasukkan ke dalam kuis.');
      return;
    }

    var payloadSoal = [];
    checkedBoxes.forEach(function (chk) {
      var idx = chk.dataset.idx;
      var card = chk.closest('.ai-soal-item');
      var tanyaEl = card.querySelector('.ai-item-tanya');
      var bobotEl = card.querySelector('.ai-item-bobot');
      var original = soalItems[idx] || {};

      var isPg = original.jenis === 'pg';
      var opsiData = {};
      var kunciVal = null;

      if (isPg) {
        var checkedRadio = card.querySelector('.ai-radio-kunci:checked');
        kunciVal = checkedRadio ? checkedRadio.value : (original.kunci || 'A');

        card.querySelectorAll('.ai-item-opsi').forEach(function (opInp) {
          var h = opInp.dataset.huruf;
          var v = opInp.value.trim();
          if (v) opsiData[h] = v;
        });
      }

      payloadSoal.push({
        jenis: original.jenis || 'pg',
        pertanyaan: tanyaEl ? tanyaEl.value.trim() : original.pertanyaan,
        bobot: parseInt(bobotEl ? bobotEl.value : (original.bobot || 1), 10) || 1,
        opsi: isPg ? opsiData : null,
        kunci: isPg ? kunciVal : null
      });
    });

    // Disable button & animate
    btnSimpan.disabled = true;
    if (btnSimpanTop) btnSimpanTop.disabled = true;
    btnSimpan.textContent = 'Menyimpan soal ke kuis...';

    fetch('{{ route("kuis.ai.simpan", [$ruang, $kuis]) }}', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}'
      },
      body: JSON.stringify({
        soal: payloadSoal,
        _token: '{{ csrf_token() }}'
      })
    })
    .then(function (res) {
      return res.json().then(function (data) {
        return { ok: res.ok, data: data };
      });
    })
    .then(function (res) {
      if (!res.ok || !res.data.ok) {
        alert(res.data.pesan || 'Gagal menyimpan soal ke kuis.');
        btnSimpan.disabled = false;
        if (btnSimpanTop) btnSimpanTop.disabled = false;
        btnSimpan.textContent = 'Masukkan Soal Terpilih ke Kuis';
        return;
      }

      // Berhasil disimpan, reload halaman kuis agar langsung muncul di daftar soal
      window.location.reload();
    })
    .catch(function (err) {
      alert('Terjadi kesalahan koneksi: ' + err.message);
      btnSimpan.disabled = false;
      if (btnSimpanTop) btnSimpanTop.disabled = false;
      btnSimpan.textContent = 'Masukkan Soal Terpilih ke Kuis';
    });
  }

  btnSimpan.addEventListener('click', prosesSimpanKeKuis);
  if (btnSimpanTop) btnSimpanTop.addEventListener('click', prosesSimpanKeKuis);

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
})();
</script>
@endpush
@endsection
