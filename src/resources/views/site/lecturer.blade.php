@extends('site.layout')
@section('title', $u->name.' — Dosen Pembimbing FASINOVA')
@section('description', 'Profil '.$u->name.' dan karya tugas akhir tim bimbingannya di Fakultas Ilmu Komputer Universitas Esa Unggul.')
@php
    $ini = \Illuminate\Support\Str::of($u->name)->explode(' ')->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
@endphp
@section('hero')
<section class="fx-hero sm">
    <span class="fx-dot d3"></span><span class="fx-dot d4"></span>
    <div class="fx-wrap">
        <a href="/pembimbing" class="fx-crumb">← Semua dosen</a>
        <div class="fx-profile">
            <span class="fx-avatar lg" aria-hidden="true">{{ $ini }}</span>
            <div>
                <h1>{{ $u->name }}</h1>
                <div class="fx-meta">
                    @if($u->jabatan())<span class="hl">{{ $u->jabatan() }}</span>@endif
                    @if($u->program)<span>{{ $u->program->name }}</span>@endif
                    @foreach($u->tracksAsPic as $t)<span>PIC {{ $t->name }}</span>@endforeach
                    <span>{{ $u->karya_count }} karya terbit</span>
                    <span>{{ $u->mahasiswa_count }} mahasiswa</span>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
@section('content')
<div class="fx-sec-head" id="karya" style="margin-top:56px">
    <h2>Karya tim bimbingan</h2>
    <span>{{ $items->total() }} karya</span>
</div>
<div class="fx-grid">
    @forelse($items as $k)
        @include('site._card', ['k' => $k])
    @empty
        <div class="fx-empty">Belum ada karya bimbingan yang terbit.</div>
    @endforelse
</div>
{{ $items->links('site.pagination') }}
@endsection
