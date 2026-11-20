@extends('site.layout')
@section('title', 'Dosen Pembimbing — FASINOVA')
@section('description', 'Dosen pembimbing Fakultas Ilmu Komputer Universitas Esa Unggul beserta karya tugas akhir tim bimbingannya.')
@section('hero')
<section class="fx-hero sm">
    <span class="fx-dot d3"></span><span class="fx-dot d4"></span>
    <div class="fx-wrap">
        <span class="fx-kicker">Fakultas Ilmu Komputer</span>
        <h1>Dosen Pembimbing</h1>
        <p class="fx-sub">Kenali dosen di balik karya mahasiswa Fasilkom, beserta karya tim yang mereka bimbing.</p>
    </div>
</section>
@endsection
@section('content')
<div class="fx-lect-grid">
    @forelse($lecturers as $u)
        @include('site._lecturer-card', ['u' => $u])
    @empty
        <div class="fx-empty">Belum ada data dosen.</div>
    @endforelse
</div>
{{ $lecturers->links('site.pagination') }}
@endsection
