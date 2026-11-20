@extends('site.layout')
@section('title', $i->title.' — FASINOVA')
@section('description', \Illuminate\Support\Str::limit(strip_tags($i->abstract ?: $i->problem), 160))
@section('image', ! empty(($i->materials ?? [])['poster']) ? url(\Illuminate\Support\Facades\Storage::disk('public')->url($i->materials['poster'])) : url('/og-image.png'))
@php
    $m = $i->materials ?? [];
    $video = ! empty($m['video']) ? \Illuminate\Support\Facades\Storage::disk('public')->url($m['video']) : null;
    $demo = $i->demoUrl();
    $poster = ! empty($m['poster']) ? \Illuminate\Support\Facades\Storage::disk('public')->url($m['poster']) : null;
    // Link YouTube (watch / youtu.be / embed) -> URL embed; selain itu tampil sebagai tautan.
    $yt = null;
    if ($i->video_url && preg_match('~(?:youtube\.com/(?:watch\?v=|embed/)|youtu\.be/)([\w-]{11})~', $i->video_url, $mm)) $yt = 'https://www.youtube-nocookie.com/embed/'.$mm[1];
    $hasVideo = $yt || $video || $i->video_url;   // tanpa video, poster tampil di kolom utama saja (tidak dobel)
    $card = collect($i->track->result_card_schema ?? [])->map(fn ($f) => ['label' => $f['label'], 'value' => $i->resultCard?->data[$f['key']] ?? null])->filter(fn ($f) => filled($f['value']));
@endphp
@section('hero')
<section class="fx-hero sm">
    <span class="fx-dot d3"></span><span class="fx-dot d4"></span>
    <div class="fx-wrap">
        <a href="/" class="fx-crumb">← Semua karya</a>
        <h1>{{ $i->title }}</h1>
        <div class="fx-meta">
            <span>{{ $i->track->name }}</span>
            @if($i->formTypeLabel())<span>{{ $i->formTypeLabel() }}</span>@endif
            @if($i->academic_period)<span>{{ $i->academic_period }}</span>@endif
            @if($demo)<span class="hl">Demo online</span>@endif
        </div>
    </div>
</section>
@endsection

@section('content')
<div class="fx-detail">
  <div class="fx-col">
    @if($yt)<div class="fx-panel fx-media"><iframe src="{{ $yt }}" title="Video demo" allowfullscreen loading="lazy"></iframe></div>
    @elseif($video)<div class="fx-panel fx-media"><video controls src="{{ $video }}"></video></div>
    @elseif($i->video_url)<div class="fx-panel"><a class="fx-link" href="{{ $i->video_url }}">Tonton video</a></div>
    @elseif($poster)<div class="fx-panel fx-media"><img src="{{ $poster }}" alt="Poster {{ $i->title }}"></div>@endif

    <div class="fx-panel top">
        @if($i->abstract)<h2>Abstrak</h2><p class="fx-pre">{{ $i->abstract }}</p>@endif
        @if($i->problem)<h2 @if($i->abstract) style="margin-top:22px" @endif>Masalah</h2><p>{{ $i->problem }}</p>@endif
        @if($i->solution)<h2 style="margin-top:22px">Solusi</h2><p>{{ $i->solution }}</p>@endif
        @if(! $i->abstract && ! $i->problem && ! $i->solution)<p>Deskripsi karya belum diisi.</p>@endif
    </div>

    @if($card->isNotEmpty())
    <div class="fx-panel"><h2>Kartu hasil</h2>
        @foreach($card as $f)<h3>{{ $f['label'] }}</h3><p class="fx-pre">{{ $f['value'] }}</p>@endforeach
    </div>@endif
  </div>

  <aside class="fx-col">
    @if($demo)<a href="{{ $demo }}" rel="noopener" target="_blank" class="fx-demo"><b></b>Coba demo</a>@endif
    @if($poster && $hasVideo)<a href="{{ $poster }}" target="_blank" rel="noopener" class="fx-poster"><img src="{{ $poster }}" alt="Poster"></a>@endif
    @if($i->journal_url || $i->hki)
    <div class="fx-panel">
        @if($i->journal_url)<h2>Publikasi jurnal</h2><p><a class="fx-link" href="{{ $i->journal_url }}" target="_blank" rel="noopener noreferrer">{{ $i->journal_url }}</a></p>@endif
        @if($i->hki)<h2 @if($i->journal_url) style="margin-top:18px" @endif>HKI</h2><p>{{ $i->hki }}</p>@endif
    </div>@endif
    <div class="fx-panel fx-team"><h2>Tim</h2>
        <p>{{ $i->owner->name }} <small>Ketua</small></p>
        @foreach($i->members ?? [] as $mem)<p>{{ $mem['name'] }}</p>@endforeach
        @if($i->supervisor)<h3>Pembimbing</h3><p>{{ $i->supervisor->name }}</p>@endif
    </div>
    <form method="post" action="/karya/{{ $i->slug }}/kolaborasi" class="fx-panel top fx-form">
        @csrf <h2>Tertarik berkolaborasi?</h2>
        @if(session('ok'))<p class="fx-ok">{{ session('ok') }}</p>@endif
        @foreach($errors->all() as $e)<p class="fx-err">{{ $e }}</p>@endforeach
        <input name="name" placeholder="Nama" value="{{ old('name') }}" aria-label="Nama" required>
        <input name="email" type="email" placeholder="Email" value="{{ old('email') }}" aria-label="Email" required>
        <input name="organization" placeholder="Organisasi (opsional)" value="{{ old('organization') }}" aria-label="Organisasi">
        <textarea name="message" placeholder="Pesan" aria-label="Pesan" required>{{ old('message') }}</textarea>
        <label class="fx-check"><input type="checkbox" name="pdp" value="1" required> Saya setuju data saya diproses sesuai UU PDP No. 27/2022 untuk menindaklanjuti pesan ini.</label>
        @if(config('fasinova.turnstile_site_key'))
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
            <div class="cf-turnstile" data-sitekey="{{ config('fasinova.turnstile_site_key') }}"></div>
        @endif
        <button>Kirim</button>
    </form>
  </aside>
</div>
@endsection
