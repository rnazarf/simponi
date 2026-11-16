<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('kode') · {{ setting('nama_sekolah') }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v=4">
</head>
<body style="background:var(--kertas)">
<div style="min-height:100vh;display:grid;place-items:center;padding:32px;text-align:center">
  <div style="max-width:440px">
    <div style="font-size:80px;font-weight:800;letter-spacing:-0.04em;line-height:1;background:linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent">@yield('kode')</div>
    <h1 style="margin:16px 0 8px;font-size:24px"><span class="sorot">@yield('judul')</span></h1>
    <p style="color:var(--redup);font-size:15px;margin-bottom:24px">@yield('pesan')</p>
    <a class="btn" href="{{ url('/') }}">{!! icon('arrow', 16) !!}Kembali ke Beranda</a>
  </div>
</div>
</body>
</html>
