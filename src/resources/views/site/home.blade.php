@extends('site.layout')
@section('hero')
<section class="fx-hero">
    <span class="fx-dot d1"></span><span class="fx-dot d2"></span><span class="fx-dot d3"></span><span class="fx-dot d4"></span>
    <div class="fx-wrap">
        <div>
            <span class="fx-kicker">Showcase Karya Mahasiswa {{ $tahuns->sortDesc()->first() }}</span>
            <h1>Karya terbaik <em>mahasiswa Fasilkom</em> Universitas Esa Unggul</h1>
            <p class="fx-sub">Jelajahi, tonton, dan coba langsung demo proyek inovatif dari peminatan {{ $tracks->pluck('name')->map(fn ($n) => preg_replace('/\s*\(.*\)$/', '', $n))->join(', ', ', dan ') }}.</p>
        </div>
        <div class="fx-stats">
            <div class="fx-stat"><strong>{{ $total }}</strong><span>Karya</span></div>
            <div class="fx-stat"><strong>{{ $tracks->count() }}</strong><span>Peminatan</span></div>
            <div class="fx-stat hl"><strong>{{ $live }}</strong><span>Demo online</span></div>
        </div>
    </div>
</section>
@endsection

@section('content')
    <form class="fx-search" method="get">
        <input name="q" value="{{ request('q') }}" placeholder="Cari karya, teknologi, atau tim…" aria-label="Cari karya">
        <select name="track" aria-label="Peminatan">
            <option value="">Semua peminatan</option>
            @foreach($tracks as $t)
                <option value="{{ $t->id }}" @selected(request('track') == $t->id)>{{ $t->name }}</option>
            @endforeach
        </select>
        <select name="prodi" aria-label="Prodi">
            <option value="">Semua prodi</option>
            @foreach($prodis as $p)<option @selected(request('prodi') == $p)>{{ $p }}</option>@endforeach
        </select>
        <select name="tahun" aria-label="Tahun">
            <option value="">Semua tahun</option>
            @foreach($tahuns as $th)<option @selected(request('tahun') == $th)>{{ $th }}</option>@endforeach
        </select>
        <button>Cari</button>
    </form>

    <div class="fx-chips">
        <a href="{{ request()->fullUrlWithQuery(['track' => null, 'page' => null]) }}" class="fx-chip {{ request('track') ? '' : 'on' }}">Semua</a>
        @foreach($tracks as $t)
            <a href="{{ request()->fullUrlWithQuery(['track' => $t->id, 'page' => null]) }}" class="fx-chip {{ request('track') == $t->id ? 'on' : '' }}">{{ $t->name }}</a>
        @endforeach
    </div>

    <div class="fx-sec-head">
        <h2>Karya Mahasiswa</h2>
        <span>{{ $items->total() }} karya</span>
    </div>

    <div class="fx-grid">
        @forelse($items as $k)
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
                    <p class="fx-desc">{{ \Illuminate\Support\Str::limit(strip_tags($k->problem ?: $k->abstract), 200) }}</p>
                    <span class="fx-more">Lihat karya <em>→</em></span>
                </div>
            </a>
        @empty
            <div class="fx-empty">Belum ada karya yang cocok dengan pencarian Anda.</div>
        @endforelse
    </div>

    @if($items->hasPages())<div style="padding-bottom:60px">{{ $items->links() }}</div>@endif
@endsection
