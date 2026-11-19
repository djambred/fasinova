<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'FASINOVA — Fasilkom Innovation Showcase')</title>
    @php
        $mTitle = trim($__env->yieldContent('title')) ?: 'FASINOVA — Fasilkom Innovation Showcase';
        $mDesc = trim($__env->yieldContent('description')) ?: 'Etalase karya tugas akhir mahasiswa Fasilkom: inovasi, demo, dan peluang kolaborasi.';
        $mImg = trim($__env->yieldContent('image')) ?: url('/og-image.png');
    @endphp
    <meta name="description" content="{{ $mDesc }}"><link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="website"><meta property="og:site_name" content="FASINOVA"><meta property="og:locale" content="id_ID">
    <meta property="og:title" content="{{ $mTitle }}"><meta property="og:description" content="{{ $mDesc }}">
    <meta property="og:url" content="{{ url()->current() }}"><meta property="og:image" content="{{ $mImg }}">
    <meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="{{ $mTitle }}"><meta name="twitter:description" content="{{ $mDesc }}"><meta name="twitter:image" content="{{ $mImg }}">
    <link rel="icon" type="image/svg+xml" href="/icon.svg"><link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png"><link rel="apple-touch-icon" href="/apple-touch-icon.png">
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
