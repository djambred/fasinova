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
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="{{ asset('css/fasinova.css') }}?v={{ filemtime(public_path('css/fasinova.css')) }}">
</head>
<body class="fx">
<div class="fx-top">
    <div class="fx-wrap">
        <span>Fakultas Ilmu Komputer · Universitas Esa Unggul</span>
        <span class="fx-hide"><a href="https://fasilkom.esaunggul.ac.id" target="_blank" rel="noopener">fasilkom.esaunggul.ac.id</a></span>
    </div>
</div>
<header class="fx-head">
    <div class="fx-wrap">
        <a href="/" class="fx-brand">
            <img src="{{ asset('img/logo-ueu.png') }}" alt="Universitas Esa Unggul" width="135" height="44">
            <span class="fx-sep"></span>
            <span><strong>FASI<span>NOVA</span></strong><small>Fasilkom Innovation Showcase</small></span>
        </a>
        <nav class="fx-nav">
            <a href="/" class="fx-hide {{ request()->is('/') ? 'on' : '' }}">Beranda</a>
            <a href="/pembimbing" class="{{ request()->is('pembimbing*') ? 'on' : '' }}">Dosen</a>
            @auth <a href="{{ auth()->user()->homeUrl() }}" class="fx-btn">Dasbor</a>
            @else @unless(request()->routeIs('login'))<a href="{{ route('login') }}" class="fx-btn">Masuk</a>@endunless @endauth
        </nav>
    </div>
</header>
@yield('hero')
<main class="fx-wrap">@yield('content')</main>
<footer class="fx-foot">
    <div class="fx-wrap">
        <span><strong>FASINOVA</strong> · Fasilkom Innovation Showcase</span>
        <span>© {{ date('Y') }} Fakultas Ilmu Komputer, Universitas Esa Unggul</span>
    </div>
</footer>
</body></html>
