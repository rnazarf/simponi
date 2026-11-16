@extends('layouts.app')
@section('title', 'Catatan Pembinaan · ' . $kelas->nama)
@section('content')
@include('partials.wali-kepala')

<div class="kepala mt">
  <div>
    <h1><span class="sorot">Buku Catatan Pembinaan &amp; Konseling</span></h1>
    <p>Rekam jejak pembinaan disiplin, bimbingan, konseling, dan komunikasi orang tua siswa kelas {{ $kelas->nama }}.</p>
  </div>
</div>

@include('partials.flash')

<div class="grid g-utama">
  <div>
    <div class="kartu">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px">
        <h2>{!! icon('clipboard', 18) !!}Riwayat Catatan ({{ $daftarCatatan->total() }})</h2>
        <form method="get" class="inline-form" style="margin:0">
          <input type="hidden" name="kelas" value="{{ $kelas->id }}">
          <div class="field">
            <select name="siswa_id" onchange="this.form.submit()">
              <option value="">-- Semua Siswa --</option>
              @foreach ($siswa as $s)
                <option value="{{ $s->id }}" @selected(request('siswa_id') == $s->id)>{{ $s->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="field">
            <select name="jenis" onchange="this.form.submit()">
              <option value="">-- Semua Jenis --</option>
              @foreach (\App\Models\CatatanWali::JENIS as $kunci => $label)
                <option value="{{ $kunci }}" @selected(request('jenis') == $kunci)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
        </form>
      </div>

      @forelse ($daftarCatatan as $c)
        <div class="pt" style="border:1px solid var(--garis);padding:14px;border-radius:10px;margin-bottom:14px">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px">
            <div>
              <span class="lencana {{ $c->jenis === 'prestasi' ? 'hijau' : ($c->jenis === 'pelanggaran' ? 'merah' : ($c->jenis === 'komunikasi_ortu' ? 'kuning' : 'biru')) }}">
                {{ \App\Models\CatatanWali::JENIS[$c->jenis] ?? $c->jenis }}
              </span>
              <a href="{{ route('wali.siswa', [$c->siswa, 'kelas' => $kelas->id]) }}" class="tebal" style="margin-left:8px;font-size:15px">
                {{ $c->siswa->name }}
              </a>
              <span class="kecil-redup" style="margin-left:4px">NISN {{ $c->siswa->username }}</span>
            </div>
            <div style="display:flex;align-items:center;gap:6px">
              <span class="kecil-redup">{{ $c->tanggal->locale('id')->isoFormat('D MMM Y') }}</span>
              <form method="post" action="{{ route('wali.catatan.hapus', [$c, 'kelas' => $kelas->id]) }}" onsubmit="return confirm('Hapus catatan ini?')" style="margin:0">
                @csrf @method('delete')
                <button type="submit" class="ikon-btn" title="Hapus catatan" style="color:var(--merah)">
                  {!! icon('trash', 14) !!}
                </button>
              </form>
            </div>
          </div>

          <div style="margin-top:10px;font-size:14px;line-height:1.5;color:var(--teks)">
            {!! nl2br(e($c->catatan)) !!}
          </div>

          @if ($c->tindak_lanjut)
            <div style="margin-top:10px;background:var(--garis-lemah);padding:8px 12px;border-radius:6px;font-size:13px;border-left:3px solid var(--tinta)">
              <b>Tindak Lanjut / Solusi:</b> {{ $c->tindak_lanjut }}
            </div>
          @endif

          <div class="kecil-redup mt" style="font-size:11.5px">
            Dicatat oleh: <b>{{ $c->guru->name }}</b> &middot; {{ $c->created_at->diffForHumans() }}
          </div>
        </div>
      @empty
        @include('partials.kosong', [
          'ikon' => 'edit',
          'judul' => 'Belum Ada Catatan',
          'teks' => 'Gunakan formulir di sebelah kanan untuk menambahkan catatan pembinaan siswa.'
        ])
      @endforelse

      {{ $daftarCatatan->links() }}
    </div>
  </div>

  <div>
    <form method="post" action="{{ route('wali.catatan.simpan', ['kelas' => $kelas->id]) }}" class="kartu">
      @csrf
      <h2>{!! icon('plus', 18) !!}Tambah Catatan Baru</h2>
      <p class="kecil-redup">Catatan ini bersifat rahasia dan hanya dapat diakses oleh Wali Kelas dan Pihak Sekolah/Admin.</p>

      <div class="field">
        <label>Pilih Siswa</label>
        <select name="siswa_id" required>
          <option value="">-- Pilih Siswa --</option>
          @foreach ($siswa as $s)
            <option value="{{ $s->id }}" @selected(old('siswa_id') == $s->id)>{{ $s->name }} ({{ $s->username }})</option>
          @endforeach
        </select>
      </div>

      <div class="field">
        <label>Jenis Catatan</label>
        <select name="jenis" required>
          @foreach (\App\Models\CatatanWali::JENIS as $kunci => $label)
            <option value="{{ $kunci }}" @selected(old('jenis') == $kunci)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div class="field">
        <label>Tanggal Kejadian / Pembinaan</label>
        <input type="date" name="tanggal" value="{{ old('tanggal', date('Y-m-d')) }}" required>
      </div>

      <div class="field">
        <label>Uraian Masalah / Kejadian / Catatan</label>
        <textarea name="catatan" rows="4" placeholder="Jelaskan secara ringkas mengenai permasalahan, prestasi, atau kronologi yang terjadi..." required>{{ old('catatan') }}</textarea>
      </div>

      <div class="field">
        <label>Rencana Tindak Lanjut / Solusi (Opsional)</label>
        <textarea name="tindak_lanjut" rows="2" placeholder="Misal: Panggilan orang tua pada hari Kamis, bimbingan konseling mandiri, dsb...">{{ old('tindak_lanjut') }}</textarea>
      </div>

      <button class="btn" style="width:100%">{!! icon('check', 16) !!}Simpan Catatan</button>
    </form>
  </div>
</div>
@endsection
