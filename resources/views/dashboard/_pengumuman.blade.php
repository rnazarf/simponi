<div class="kartu">
  <h2>{!! icon('megaphone', 18) !!}Pengumuman <a class="kanan" href="{{ route('pengumuman') }}">Semua</a></h2>
  @forelse ($pengumuman as $p)
    <div style="padding:12px 0;border-bottom:1px solid var(--garis)">
      <div class="tebal" style="display:flex;gap:6px;align-items:center">@if ($p->disematkan){!! icon('pin', 14) !!}@endif{{ $p->judul }}</div>
      <div class="kecil-redup" style="margin-top:2px">{{ $p->user->name }} · {{ $p->created_at->locale('id')->diffForHumans() }}</div>
      <div style="font-size:13.5px;margin-top:5px;color:var(--teks-2);line-height:1.5">{{ \Illuminate\Support\Str::limit($p->isi, 140) }}</div>
    </div>
  @empty
    <p class="kecil-redup">Belum ada pengumuman.</p>
  @endforelse
</div>
