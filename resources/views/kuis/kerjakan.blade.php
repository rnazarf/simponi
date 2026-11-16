@extends('layouts.app')
@section('title', $kuis->judul)
@section('content')
<div class="kepala">
  <div>
    <div class="remah">{{ $ruang->mapel->nama }} · {{ $ruang->kelas->nama }}</div>
    <h1><span class="sorot">{{ $kuis->judul }}</span></h1>
    @if ($kuis->anti_curang)
      <div style="margin-top:6px;display:flex;align-items:center;gap:8px;font-size:13px;flex-wrap:wrap">
        <span class="lencana biru" style="font-weight:600">🛡️ Anti-Curang Aktif</span>
        <span id="badge-curang" class="lencana {{ ($p->jumlah_pelanggaran ?? 0) > 0 ? 'merah' : 'hijau' }}">
          <span id="label-curang">{{ $p->jumlah_pelanggaran ?? 0 }} Pelanggaran</span>
        </span>
        @if ($kuis->maks_pelanggaran > 0)
          <span class="kecil-redup">Batas toleransi: {{ $kuis->maks_pelanggaran }}x</span>
        @endif
      </div>
    @endif
  </div>
  <div style="text-align:right"><div class="kecil-redup">Sisa waktu</div><div class="timer" id="timer">--:--</div><div class="simpan-status" id="status">Jawaban tersimpan otomatis</div></div>
</div>

@if ($kuis->anti_curang)
  {{-- Modal Peringatan Pelanggaran --}}
  <div id="modal-peringatan-curang" class="anti-curang-overlay" style="display:none">
    <div class="anti-curang-box">
      <div style="font-size:40px;margin-bottom:8px">⚠️</div>
      <h3 style="margin:0 0 8px;color:var(--merah);font-size:18px">Peringatan Aktivitas Mencurigakan!</h3>
      <p id="pesan-curang" style="margin:0 0 14px;font-size:14px;color:var(--teks-2)">Anda terdeteksi beralih dari tab atau jendela pengerjaan kuis.</p>
      
      <div style="background:var(--kertas-2);border-radius:var(--r-sm);padding:12px 14px;margin-bottom:16px;font-size:13px;text-align:left">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
          <span>Total Pelanggaran Anda:</span>
          <b id="teks-total-pelanggaran" style="color:var(--merah);font-size:14px">1 kali</b>
        </div>
        <div id="teks-sisa-toleransi" style="color:var(--redup);font-size:12.5px"></div>
        <div style="margin-top:8px;font-size:12px;color:var(--redup);border-top:1px dashed var(--garis);padding-top:6px">
          Catatan waktu dan durasi aktivitas meninggalkan tab telah terekam secara otomatis ke pengawas guru.
        </div>
      </div>

      <button type="button" class="btn" id="btn-tutup-curang" style="width:100%">{!! icon('check', 16) !!}Saya Mengerti &amp; Lanjutkan Kuis</button>
    </div>
  </div>

  {{-- Toast Notifikasi Pelanggaran Ringan --}}
  <div id="toast-curang" class="anti-curang-toast" style="display:none">
    <span style="font-size:16px">⚠️</span>
    <span id="toast-curang-teks">Peringatan: Aktivitas mencurigakan terekam</span>
  </div>
@endif

<div class="grid g-utama">
  <div class="kartu {{ $kuis->anti_curang ? 'user-select-none' : '' }}">
    @foreach ($daftar as $i => $s)
      @php($jwb = $p->jawaban[$s->id] ?? null)
      <div class="soal-kartu {{ $i === 0 ? 'aktif' : '' }}" data-no="{{ $i }}" data-id="{{ $s->id }}">
        <div class="remah">Soal {{ $i + 1 }} dari {{ $daftar->count() }} · {{ $s->jenis === 'pg' ? 'Pilihan ganda' : 'Esai' }}</div>
        <div class="soal-tanya {{ $kuis->anti_curang ? 'user-select-none' : '' }}">{{ $s->pertanyaan }}</div>
        @if ($s->jenis === 'pg')
          @foreach ($p->urutan_soal['opsi'][$s->id] ?? array_keys($s->opsi) as $j => $h)
            <label class="opsi {{ $kuis->anti_curang ? 'user-select-none' : '' }}"><input type="radio" name="s{{ $s->id }}" value="{{ $h }}" @checked($jwb === $h)>
              <span class="huruf">{{ chr(65 + $j) }}</span><span>{{ $s->opsi[$h] }}</span></label>
          @endforeach
        @else
          <textarea name="s{{ $s->id }}" class="besar" placeholder="Tulis jawabanmu...">{{ $jwb }}</textarea>
        @endif
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:20px;gap:8px;flex-wrap:wrap">
          <button type="button" class="btn garis" data-ke="{{ $i - 1 }}" @disabled($i === 0)>{!! icon('back', 16) !!}Sebelumnya</button>

          @php($isRagu = !empty($p->ragu[$s->id]))
          <label class="btn-ragu {{ $isRagu ? 'aktif' : '' }}" title="Tandai ragu-ragu untuk diperiksa kembali nanti">
            <input type="checkbox" class="cek-ragu" data-id="{{ $s->id }}" data-no="{{ $i }}" @checked($isRagu)>
            <span class="ikon-ragu">⚠️</span>
            <span>Ragu-ragu</span>
          </label>

          @if ($i < $daftar->count() - 1)
            <button type="button" class="btn" data-ke="{{ $i + 1 }}">Berikutnya{!! icon('arrow', 16) !!}</button>
          @else
            <span style="display:inline-block;width:95px"></span>
          @endif
        </div>
      </div>
    @endforeach
  </div>
  <div class="kartu">
    <h2>{!! icon('grid', 18) !!}Nomor soal</h2>
    <div class="soal-nav" id="nav">
      @foreach ($daftar as $i => $s)
        @php($jwb = $p->jawaban[$s->id] ?? null)
        @php($isRagu = !empty($p->ragu[$s->id]))
        @php($sudahJawab = $jwb !== null && $jwb !== '')
        <button type="button" data-ke="{{ $i }}" data-id="{{ $s->id }}"
          class="{{ $i === 0 ? 'aktif' : '' }} {{ $sudahJawab ? 'terjawab' : '' }} {{ $isRagu ? 'ragu' : '' }}"
          title="Soal nomor {{ $i + 1 }}{{ $isRagu ? ' (Ragu-ragu)' : ($sudahJawab ? ' (Terjawab)' : ' (Belum dijawab)') }}">
          {{ $i + 1 }}
        </button>
      @endforeach
    </div>

    {{-- Legenda & Rangkuman Status Soal --}}
    <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:8px;margin-top:14px;padding-top:12px;border-top:1px dashed var(--garis);font-size:12px;text-align:center">
      <div style="background:var(--hijau-lembut);padding:6px 4px;border-radius:var(--r-sm);border:1px solid var(--hijau-border)">
        <div style="font-weight:700;color:var(--hijau-tua);font-size:14px" id="hitung-terjawab">0</div>
        <div class="kecil-redup" style="color:var(--hijau-tua);font-size:11.5px">Dijawab</div>
      </div>
      <div style="background:var(--oranye-lembut);padding:6px 4px;border-radius:var(--r-sm);border:1px solid var(--oranye-border)">
        <div style="font-weight:700;color:var(--oranye-tua);font-size:14px" id="hitung-ragu">0</div>
        <div class="kecil-redup" style="color:var(--oranye-tua);font-size:11.5px">Ragu-ragu</div>
      </div>
      <div style="background:var(--kertas-2);padding:6px 4px;border-radius:var(--r-sm);border:1px solid var(--garis-2)">
        <div style="font-weight:700;color:var(--redup);font-size:14px" id="hitung-belum">{{ $daftar->count() }}</div>
        <div class="kecil-redup" style="font-size:11.5px">Belum</div>
      </div>
    </div>

    <form method="post" action="{{ route('kuis.kumpul', [$ruang, $kuis]) }}" id="form-kumpul" class="mt">
      @csrf<button class="btn stabilo blok" id="tombol-kumpul">{!! icon('check', 16) !!}Kumpulkan jawaban</button></form>
  </div>
</div>
@push('script')
<script>
(function () {
  var batas = new Date(@json($p->batas_at->toIso8601String())).getTime();
  var selisihJam = Date.now() - @json(now()->getTimestampMs()); // koreksi jam perangkat siswa
  var urlJawab = @json(route('kuis.jawab', [$ruang, $kuis]));
  var token = document.querySelector('meta[name=csrf-token]').content;
  var kartu = document.querySelectorAll('.soal-kartu'), nav = document.querySelectorAll('#nav button');
  var status = document.getElementById('status'), form = document.getElementById('form-kumpul');
  var antre = {}, timerKetik = {};

  function ke(n) {
    if (n < 0 || n >= kartu.length) return;
    kartu.forEach(function (k, i) { k.classList.toggle('aktif', i === n); });
    nav.forEach(function (b, i) { b.classList.toggle('aktif', i === n); });
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-ke]');
    if (b) ke(parseInt(b.dataset.ke, 10));
  });

  function hitung() {
    var countTerjawab = 0;
    var countRagu = 0;
    kartu.forEach(function (k, i) {
      var r = k.querySelector('input[type="radio"]:checked');
      var t = k.querySelector('textarea');
      var isi = r || (t && t.value.trim() !== '');

      var cekRagu = k.querySelector('.cek-ragu');
      var isRagu = cekRagu && cekRagu.checked;

      nav[i].classList.toggle('terjawab', !!isi);
      nav[i].classList.toggle('ragu', !!isRagu);

      if (isi) countTerjawab++;
      if (isRagu) countRagu++;
    });

    var totalSoal = kartu.length;
    var countBelum = Math.max(0, totalSoal - countTerjawab);

    var elTerjawab = document.getElementById('hitung-terjawab');
    var elRagu = document.getElementById('hitung-ragu');
    var elBelum = document.getElementById('hitung-belum');

    if (elTerjawab) elTerjawab.textContent = countTerjawab;
    if (elRagu) elRagu.textContent = countRagu;
    if (elBelum) elBelum.textContent = countBelum;

    return { terjawab: countTerjawab, ragu: countRagu, belum: countBelum };
  }

  function kirim(id, nilai) {
    status.textContent = 'Menyimpan...';
    fetch(urlJawab, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
      body: JSON.stringify({ soal_id: id, jawaban: nilai })
    }).then(function (r) {
      if (r.status === 409) { form.submit(); return null; }
      return r.json();
    }).then(function (d) {
      if (!d) return;
      delete antre[id];
      status.textContent = d.ok ? 'Tersimpan ' + d.tersimpan : 'Gagal menyimpan';
    }).catch(function () {
      antre[id] = nilai;
      status.textContent = 'Koneksi terputus, akan dicoba lagi...';
    });
  }

  function kirimRagu(id, isRagu) {
    status.textContent = 'Menyimpan status ragu...';
    fetch(urlJawab, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
      body: JSON.stringify({ soal_id: id, ragu: isRagu })
    }).then(function (r) {
      if (r.status === 409) { form.submit(); return null; }
      return r.json();
    }).then(function (d) {
      if (!d) return;
      status.textContent = d.ok ? 'Tersimpan ' + d.tersimpan : 'Gagal menyimpan status';
    }).catch(function () {
      status.textContent = 'Koneksi terputus, status ragu akan dicoba lagi...';
    });
  }

  // Coba kirim ulang jawaban yang gagal (sinyal hilang)
  setInterval(function () { Object.keys(antre).forEach(function (id) { kirim(parseInt(id, 10), antre[id]); }); }, 8000);

  kartu.forEach(function (k) {
    var id = parseInt(k.dataset.id, 10);
    k.addEventListener('change', function (e) {
      if (e.target.type === 'radio') {
        hitung();
        kirim(id, e.target.value);
      }
    });
    k.addEventListener('input', function (e) {
      if (e.target.tagName !== 'TEXTAREA') return;
      hitung();
      clearTimeout(timerKetik[id]);
      timerKetik[id] = setTimeout(function () { kirim(id, e.target.value); }, 900);
    });

    // Checkbox ragu-ragu
    var cekRagu = k.querySelector('.cek-ragu');
    if (cekRagu) {
      cekRagu.addEventListener('change', function () {
        var label = cekRagu.closest('.btn-ragu');
        if (label) label.classList.toggle('aktif', cekRagu.checked);
        hitung();
        kirimRagu(id, cekRagu.checked);
      });
    }
  });

  form.addEventListener('submit', function (e) {
    if (form.dataset.paksa) return;
    var st = hitung();
    var pesan = [];
    if (st.belum > 0) pesan.push(st.belum + ' soal belum dijawab');
    if (st.ragu > 0) pesan.push(st.ragu + ' soal masih ditandai ragu-ragu');

    var teksKonfirmasi = pesan.length
      ? 'Perhatian:\nMasih ada ' + pesan.join(' dan ') + '.\n\nTetap kumpulkan jawaban sekarang?'
      : 'Semua soal telah terjawab. Kumpulkan jawaban sekarang?';

    if (!confirm(teksKonfirmasi)) e.preventDefault();
  });

  var el = document.getElementById('timer');
  function detak() {
    var s = Math.max(0, Math.floor((batas - (Date.now() - selisihJam)) / 1000));
    var j = Math.floor(s / 3600), m = Math.floor(s % 3600 / 60), d = s % 60;
    el.textContent = (j ? j + ':' : '') + String(m).padStart(2, '0') + ':' + String(d).padStart(2, '0');
    el.classList.toggle('kritis', s <= 60);
    if (s === 0) { form.dataset.paksa = 1; status.textContent = 'Waktu habis, mengumpulkan...'; form.submit(); return; }
    setTimeout(detak, 1000);
  }
  hitung(); detak();

  // ============================================================
  // Sistem Anti-Curang & Monitoring Integritas Kuis
  // ============================================================
  var isAntiCurang = @json($kuis->anti_curang);
  var maksPelanggaran = @json((int) $kuis->maks_pelanggaran);
  var urlPelanggaran = @json(route('kuis.pelanggaran', [$ruang, $kuis]));
  var modalCurang = document.getElementById('modal-peringatan-curang');
  var toastCurang = document.getElementById('toast-curang');
  var teksToast = document.getElementById('toast-curang-teks');
  var btnTutupCurang = document.getElementById('btn-tutup-curang');
  var pesanCurang = document.getElementById('pesan-curang');
  var angkaPelanggaran = document.getElementById('teks-total-pelanggaran');
  var sisaToleransi = document.getElementById('teks-sisa-toleransi');
  var badgeCurang = document.getElementById('badge-curang');
  var labelCurang = document.getElementById('label-curang');

  var timerToast = null;
  function tampilkanToast(teks) {
    if (!toastCurang) return;
    teksToast.textContent = teks;
    toastCurang.style.display = 'flex';
    clearTimeout(timerToast);
    timerToast = setTimeout(function () {
      toastCurang.style.display = 'none';
    }, 4000);
  }

  function catatPelanggaran(jenis, keterangan, durasi) {
    if (!isAntiCurang) return;

    fetch(urlPelanggaran, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': token
      },
      body: JSON.stringify({
        jenis: jenis,
        keterangan: keterangan,
        durasi_detik: durasi || null
      })
    })
    .then(function (r) {
      if (r.status === 409) {
        form.dataset.paksa = 1;
        form.submit();
        return null;
      }
      return r.json();
    })
    .then(function (d) {
      if (!d || !d.ok) return;

      // Update badge di header
      if (badgeCurang && labelCurang) {
        labelCurang.textContent = d.total + ' Pelanggaran';
        badgeCurang.className = 'lencana merah';
      }

      // Jika batas maksimal tercapai -> otomatis kumpulkan
      if (d.force_submit) {
        alert(d.pesan || 'Batas toleransi pelanggaran tercapai. Kuis Anda otomatis dikumpulkan!');
        form.dataset.paksa = 1;
        form.submit();
        return;
      }

      // Tampilkan modal peringatan untuk beralih tab / blur
      if (modalCurang && (jenis === 'tab_switch' || jenis === 'blur' || jenis === 'fullscreen_exit')) {
        if (pesanCurang) pesanCurang.textContent = keterangan;
        if (angkaPelanggaran) angkaPelanggaran.textContent = d.total + ' kali';
        if (sisaToleransi) {
          if (d.maks > 0) {
            var sisa = Math.max(0, d.maks - d.total);
            sisaToleransi.textContent = 'Peringatan: Tersisa ' + sisa + ' kali lagi sebelum kuis otomatis dikumpulkan secara paksa.';
          } else {
            sisaToleransi.textContent = 'Kuis ini mencatat seluruh aktivitas mencurigakan ke laporan pengawas.';
          }
        }
        modalCurang.style.display = 'flex';
      } else {
        // Tampilkan toast untuk copy/cut/inspect
        tampilkanToast(keterangan + ' (Pelanggaran ke-' + d.total + ')');
      }
    })
    .catch(function (e) {
      console.warn('Gagal mencatat pelanggaran:', e);
    });
  }

  if (btnTutupCurang && modalCurang) {
    btnTutupCurang.addEventListener('click', function () {
      modalCurang.style.display = 'none';
    });
  }

  if (isAntiCurang) {
    // 1. Deteksi Tab Switch / Blur
    var waktuKeluar = null;
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) {
        waktuKeluar = Date.now();
      } else if (waktuKeluar) {
        var durasi = Math.max(1, Math.round((Date.now() - waktuKeluar) / 1000));
        waktuKeluar = null;
        catatPelanggaran('tab_switch', 'Beralih tab / membuka aplikasi lain selama ' + durasi + ' detik', durasi);
      }
    });

    window.addEventListener('blur', function () {
      if (!waktuKeluar && !document.hidden) {
        waktuKeluar = Date.now();
      }
    });

    window.addEventListener('focus', function () {
      if (waktuKeluar && !document.hidden) {
        var durasi = Math.max(1, Math.round((Date.now() - waktuKeluar) / 1000));
        waktuKeluar = null;
        catatPelanggaran('tab_switch', 'Kehilangan fokus jendela / membuka aplikasi lain selama ' + durasi + ' detik', durasi);
      }
    });

    // 2. Blokir dan Rekam Copy / Cut
    document.addEventListener('copy', function (e) {
      var targetTag = e.target ? e.target.tagName : '';
      if (targetTag !== 'TEXTAREA' && targetTag !== 'INPUT') {
        e.preventDefault();
        catatPelanggaran('copy', 'Mencoba menyalin (copy) teks pertanyaan kuis');
      }
    });

    document.addEventListener('cut', function (e) {
      var targetTag = e.target ? e.target.tagName : '';
      if (targetTag !== 'TEXTAREA' && targetTag !== 'INPUT') {
        e.preventDefault();
        catatPelanggaran('copy', 'Mencoba memotong (cut) teks pertanyaan kuis');
      }
    });

    // 3. Blokir dan Rekam Klik Kanan (Context Menu)
    document.addEventListener('contextmenu', function (e) {
      e.preventDefault();
      catatPelanggaran('inspect', 'Mencoba membuka menu klik kanan (context menu)');
    });

    // 4. Blokir Shortcuts (F12, Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+U)
    document.addEventListener('keydown', function (e) {
      var k = e.key;
      if (
        k === 'F12' ||
        (e.ctrlKey && e.shiftKey && (k === 'I' || k === 'i' || k === 'J' || k === 'j' || k === 'C' || k === 'c')) ||
        (e.ctrlKey && (k === 'U' || k === 'u'))
      ) {
        e.preventDefault();
        catatPelanggaran('shortcut', 'Mencoba membuka Developer Tools / Shortcut (' + k + ')');
      }
    });
  }
})();
</script>
@endpush
@endsection
