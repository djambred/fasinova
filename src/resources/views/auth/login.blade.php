@extends('site.layout')
@section('title', 'Masuk — FASINOVA')
@section('hero')
<section class="fx-hero sm">
    <span class="fx-dot d3"></span><span class="fx-dot d4"></span>
    <div class="fx-wrap" style="text-align:center">
        <span class="fx-kicker">Mahasiswa · Dosen · PIC</span>
        <h1>Masuk ke FASINOVA</h1>
    </div>
</section>
@endsection
@section('content')
<div class="fx-auth">
    <form method="post" action="{{ route('login') }}" class="fx-panel top fx-form">
        @csrf
        <h2>Masuk ke akun Anda</h2>
        @error('email')<p class="fx-err">{{ $message }}</p>@enderror
        <label class="fx-field">Email
            <input name="email" type="email" value="{{ old('email') }}" autocomplete="username" autofocus required>
        </label>
        <label class="fx-field">Kata sandi
            <input name="password" type="password" autocomplete="current-password" required>
        </label>
        <label class="fx-check"><input type="checkbox" name="remember" value="1"> Ingat saya</label>
        <button>Masuk</button>
    </form>
    <p class="fx-back">Mahasiswa belum punya akun? <a href="{{ route('register') }}" class="fx-link-plain">Daftar di sini</a></p>
    <a href="/" class="fx-back">← Kembali ke etalase</a>
</div>
@endsection
