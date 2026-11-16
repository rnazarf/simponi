@if (session('ok'))
  <div class="flash ok"><span class="ic">{!! icon('check', 18) !!}</span><div>{{ session('ok') }}</div></div>
@endif
@if (session('error'))
  <div class="flash error"><span class="ic">{!! icon('alert', 18) !!}</span><div>{{ session('error') }}</div></div>
@endif
@if (isset($errors) && $errors->any())
  <div class="flash error"><span class="ic">{!! icon('alert', 18) !!}</span><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif
