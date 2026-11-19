@extends('site.layout')
@section('title', $i->title.' — FASINOVA')
@section('description', \Illuminate\Support\Str::limit(strip_tags($i->abstract ?: $i->problem), 160))
@section('image', ! empty(($i->materials ?? [])['poster']) ? url(\Illuminate\Support\Facades\Storage::disk('public')->url($i->materials['poster'])) : url('/og-image.png'))
@section('content')
@php
    $m = $i->materials ?? [];
    $video = ! empty($m['video']) ? \Illuminate\Support\Facades\Storage::disk('public')->url($m['video']) : null;
    $demo = $i->demoUrl();
    $poster = ! empty($m['poster']) ? \Illuminate\Support\Facades\Storage::disk('public')->url($m['poster']) : null;
    // Link YouTube (watch / youtu.be / embed) -> URL embed; selain itu tampil sebagai tautan.
    $yt = null;
    if ($i->video_url && preg_match('~(?:youtube\.com/(?:watch\?v=|embed/)|youtu\.be/)([\w-]{11})~', $i->video_url, $mm)) $yt = 'https://www.youtube-nocookie.com/embed/'.$mm[1];
@endphp
<a href="/" class="text-sm text-brand-600">← Semua karya</a>
<h1 class="text-3xl font-bold mt-2">{{ $i->title }}</h1>
<p class="text-sm text-slate-500">{{ $i->track->name }}@if($i->formTypeLabel()) · {{ $i->formTypeLabel() }}@endif · {{ $i->academic_period }}</p>
<div class="grid md:grid-cols-3 gap-8 mt-6">
  <div class="md:col-span-2 space-y-6">
    @if($yt)<iframe class="w-full aspect-video rounded-lg" src="{{ $yt }}" title="Video demo" allowfullscreen loading="lazy"></iframe>
    @elseif($video)<video controls class="w-full rounded-lg bg-black" src="{{ $video }}"></video>
    @elseif($i->video_url)<a class="text-brand-600 underline" href="{{ $i->video_url }}">Tonton video</a>
    @elseif($poster)<img src="{{ $poster }}" alt="Poster {{ $i->title }}" class="w-full rounded-lg border">@endif
    @if($i->abstract)<section><h2 class="font-semibold">Abstrak</h2><p class="whitespace-pre-line">{{ $i->abstract }}</p></section>@endif
    <section><h2 class="font-semibold">Masalah</h2><p>{{ $i->problem }}</p></section>
    <section><h2 class="font-semibold">Solusi</h2><p>{{ $i->solution }}</p></section>
    @if($i->resultCard)
    <section class="bg-white border rounded-lg p-5"><h2 class="font-semibold mb-2">Kartu hasil</h2>
        @foreach($i->track->result_card_schema ?? [] as $f)
            @if($v = $i->resultCard->data[$f['key']] ?? null)
                <h3 class="text-xs uppercase text-slate-500 mt-3">{{ $f['label'] }}</h3><p class="whitespace-pre-line">{{ $v }}</p>
            @endif
        @endforeach
    </section>@endif
  </div>
  <aside class="space-y-6">
    @if($demo)<a href="{{ $demo }}" rel="noopener" target="_blank" class="block text-center bg-green-600 text-white rounded-lg py-3 font-semibold">Coba demo</a>@endif
    @if($poster)<a href="{{ $poster }}" target="_blank" rel="noopener"><img src="{{ $poster }}" alt="Poster" class="w-full rounded-lg border"></a>@endif
    @if($i->journal_url)<p class="text-sm bg-white border rounded-lg p-3"><span class="font-semibold">Publikasi jurnal:</span> <a class="text-brand-700 underline break-all" href="{{ $i->journal_url }}" target="_blank" rel="noopener noreferrer">{{ $i->journal_url }}</a></p>@endif
    @if($i->hki)<p class="text-sm bg-white border rounded-lg p-3"><span class="font-semibold">HKI:</span> {{ $i->hki }}</p>@endif
    <div class="bg-white border rounded-lg p-4 text-sm"><h2 class="font-semibold">Tim</h2>
        <p>{{ $i->owner->name }} (ketua)</p>
        @foreach($i->members ?? [] as $mem)<p>{{ $mem['name'] }}</p>@endforeach
        <p class="mt-2 text-slate-500">Pembimbing: {{ $i->supervisor?->name }}</p></div>
    <form method="post" action="/karya/{{ $i->slug }}/kolaborasi" class="bg-white border rounded-lg p-4 space-y-2 text-sm">
        @csrf <h2 class="font-semibold">Tertarik berkolaborasi?</h2>
        @if(session('ok'))<p class="text-green-700">{{ session('ok') }}</p>@endif
        @foreach($errors->all() as $e)<p class="text-red-600">{{ $e }}</p>@endforeach
        <input name="name" placeholder="Nama" value="{{ old('name') }}" class="border rounded w-full px-2 py-1" required>
        <input name="email" type="email" placeholder="Email" value="{{ old('email') }}" class="border rounded w-full px-2 py-1" required>
        <input name="organization" placeholder="Organisasi" value="{{ old('organization') }}" class="border rounded w-full px-2 py-1">
        <textarea name="message" placeholder="Pesan" class="border rounded w-full px-2 py-1" required>{{ old('message') }}</textarea>
        <label class="flex gap-2"><input type="checkbox" name="pdp" value="1" required> Saya setuju data saya diproses sesuai UU PDP No. 27/2022 untuk menindaklanjuti pesan ini.</label>
        @if(config('fasinova.turnstile_site_key'))
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
            <div class="cf-turnstile" data-sitekey="{{ config('fasinova.turnstile_site_key') }}"></div>
        @endif
        <button class="bg-brand-600 text-white rounded px-4 py-2">Kirim</button>
    </form>
  </aside>
</div>
@endsection
