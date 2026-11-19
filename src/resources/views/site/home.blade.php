@extends('site.layout')
@section('content')
<section class="text-center py-10">
    <h1 class="text-3xl md:text-4xl font-bold">Karya terbaik mahasiswa Fasilkom</h1>
    <p class="mt-2 text-slate-600">Jelajahi, tonton, dan coba demo langsung.</p>
    <form class="mt-6 flex flex-wrap gap-2 justify-center" method="get">
        <input name="q" value="{{ request('q') }}" placeholder="Cari karya…" class="border rounded px-3 py-2 w-64">
        <select name="track" class="border rounded px-2"><option value="">Semua peminatan</option>
            @foreach($tracks as $t)<option value="{{ $t->id }}" @selected(request('track') == $t->id)>{{ $t->name }}</option>@endforeach</select>
        <select name="prodi" class="border rounded px-2"><option value="">Semua prodi</option>
            @foreach($prodis as $p)<option @selected(request('prodi') == $p)>{{ $p }}</option>@endforeach</select>
        <select name="tahun" class="border rounded px-2"><option value="">Semua tahun</option>
            @foreach($tahuns as $p)<option @selected(request('tahun') == $p)>{{ $p }}</option>@endforeach</select>
        <button class="bg-brand-600 text-white rounded px-4">Cari</button>
    </form>
</section>
<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
@forelse($items as $i)
    <a href="/karya/{{ $i->slug }}" class="block bg-white rounded-lg border p-5 hover:shadow">
        <span class="text-xs rounded bg-brand-50 text-brand-700 px-2 py-0.5">{{ $i->track->name }}</span>
        <h2 class="mt-2 font-semibold">{{ $i->title }}</h2>
        <p class="mt-1 text-sm text-slate-600 line-clamp-3">{{ $i->problem }}</p>
        @if($i->demoUrl())<span class="mt-3 inline-block text-xs text-green-700">● Demo online</span>@endif
    </a>
@empty <p class="col-span-full text-center text-slate-500">Belum ada karya terbit.</p>
@endforelse
</div>
<div class="mt-8">{{ $items->links() }}</div>
@endsection
