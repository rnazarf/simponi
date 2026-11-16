<nav class="tab" style="margin-bottom:18px">
  <a href="{{ route('admin.penjadwalan') }}" class="{{ request()->routeIs('admin.penjadwalan', 'admin.penjadwalan.lihat') ? 'on' : '' }}">{!! icon('calendar', 16) !!}Jadwal Otomatis</a>
  <a href="{{ route('admin.penjadwalan.jam') }}" class="{{ request()->routeIs('admin.penjadwalan.jam*') ? 'on' : '' }}">{!! icon('clock', 16) !!}Struktur Jam</a>
  <a href="{{ route('admin.penjadwalan.beban') }}" class="{{ request()->routeIs('admin.penjadwalan.beban*') ? 'on' : '' }}">{!! icon('clipboard', 16) !!}Beban Ajar</a>
  <a href="{{ route('admin.penjadwalan.ketersediaan') }}" class="{{ request()->routeIs('admin.penjadwalan.ketersediaan*') ? 'on' : '' }}">{!! icon('user', 16) !!}Ketersediaan Guru</a>
</nav>
