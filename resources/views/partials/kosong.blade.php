<div class="kosong">
  <div class="ikon">{!! icon($ikon ?? 'book', 24) !!}</div>
  <b>{{ $judul }}</b>
  @isset($teks)<div style="font-size:13.5px;max-width:380px;margin:4px auto 0;color:var(--redup)">{{ $teks }}</div>@endisset
</div>
