@extends('site.layout')
@section('title', 'Daftar Mahasiswa — FASINOVA')
@section('description', 'Daftar akun mahasiswa FASINOVA untuk mengunggah karya tugas akhir Fakultas Ilmu Komputer Universitas Esa Unggul.')
@section('hero')
<section class="fx-hero sm">
    <span class="fx-dot d3"></span><span class="fx-dot d4"></span>
    <div class="fx-wrap" style="text-align:center">
        <span class="fx-kicker">Khusus mahasiswa Fasilkom</span>
        <h1>Daftar akun FASINOVA</h1>
    </div>
</section>
@endsection
@section('content')
<div class="fx-auth wide">
    <form method="post" action="{{ route('register') }}" class="fx-panel top fx-form" novalidate>
        @csrf
        <h2>Buat akun mahasiswa</h2>
        @if($errors->any())<div class="fx-err">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
        <div class="fx-grid2">
            <label class="fx-field">Nama lengkap
                <input name="name" value="{{ old('name') }}" autocomplete="name" required maxlength="100" autofocus>
            </label>
            <label class="fx-field">NIM
                <input name="nim" value="{{ old('nim') }}" inputmode="numeric" required maxlength="20">
            </label>
        </div>
        <label class="fx-field">Email kampus
            <input name="email" type="email" value="{{ old('email') }}" autocomplete="email" required
                   placeholder="{{ $domains ? 'nama@'.$domains[0] : 'nama@kampus.ac.id' }}">
        </label>
        <label class="fx-field">Program studi
            <select name="study_program_id" required>
                <option value="">Pilih program studi</option>
                @foreach($prodis as $id => $nama)<option value="{{ $id }}" @selected(old('study_program_id') == $id)>{{ $nama }}</option>@endforeach
            </select>
        </label>
        <div class="fx-grid2">
            <label class="fx-field">Kata sandi
                <input name="password" type="password" autocomplete="new-password" required minlength="8">
            </label>
            <label class="fx-field">Ulangi kata sandi
                <input name="password_confirmation" type="password" autocomplete="new-password" required minlength="8">
            </label>
        </div>
        <p class="fx-hint">Minimal 8 karakter, berisi huruf dan angka.</p>
        <label class="fx-check"><input type="checkbox" name="pernyataan" value="1" @checked(old('pernyataan')) required> Data di atas benar dan milik saya sendiri. Akun palsu atau atas nama orang lain akan dihapus admin.</label>
        @if(config('fasinova.turnstile_site_key'))
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
            <div class="cf-turnstile" data-sitekey="{{ config('fasinova.turnstile_site_key') }}"></div>
        @endif
        <button>Daftar</button>
    </form>
    <p class="fx-back">Sudah punya akun? <a href="{{ route('login') }}" class="fx-link-plain">Masuk</a> · Dosen &amp; PIC: akun dibuatkan admin.</p>
</div>
@endsection
