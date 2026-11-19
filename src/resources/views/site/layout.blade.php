<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'FASINOVA — Fasilkom Innovation Showcase')</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-slate-50 text-slate-800">
<header class="bg-white border-b"><div class="max-w-6xl mx-auto px-4 py-4 flex justify-between items-center">
    <a href="/" class="text-xl font-bold text-brand-700">FASINOVA</a>
    <nav class="flex items-center gap-4 text-sm text-slate-500">
        <span class="hidden sm:inline">Fasilkom Innovation Showcase</span>
        @auth <a href="{{ auth()->user()->homeUrl() }}" class="text-brand-700 font-medium">Dasbor</a>
        @else <a href="{{ route('login') }}" class="bg-brand-600 text-white rounded px-3 py-1.5">Masuk</a> @endauth
    </nav></div></header>
<main class="max-w-6xl mx-auto px-4 py-8">@yield('content')</main>
<footer class="text-center text-xs text-slate-400 py-8">© Fakultas Ilmu Komputer</footer>
</body></html>
