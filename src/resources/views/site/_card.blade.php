{{-- Kartu karya: dipakai beranda & profil dosen. --}}
@php $poster = ($k->materials ?? [])['poster'] ?? null; @endphp
<a href="/karya/{{ $k->slug }}" class="fx-card" data-track="{{ $k->track->code }}">
    <div class="fx-cover">
        @if($poster)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($poster) }}" alt="{{ $k->title }}" loading="lazy">
        @else
            <span class="fx-init" aria-hidden="true">{{ \Illuminate\Support\Str::of($k->title)->explode(' ')->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('') }}</span>
        @endif
        @if($k->demoUrl())<span class="fx-live"><b></b>Demo online</span>@endif
    </div>
    <div class="fx-body">
        <span class="fx-tag">{{ $k->track->name }}</span>
        <h3>{{ $k->title }}</h3>
        @if($k->owner)<span class="fx-teamline">Tim: {{ $k->owner->name }}@if($n = count($k->members ?? [])) + {{ $n }} anggota @endif</span>@endif
        <p class="fx-desc">{{ \Illuminate\Support\Str::limit(strip_tags($k->problem ?: $k->abstract), 200) }}</p>
        <span class="fx-more">Lihat karya <em>→</em></span>
    </div>
</a>
