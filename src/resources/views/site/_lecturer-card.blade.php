{{-- Kartu dosen: avatar inisial, jabatan & prodi, peminatan yang dibina, jumlah karya terbit bimbingan. --}}
@php
    $ini = \Illuminate\Support\Str::of($u->name)->explode(' ')->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
    $peran = collect([$u->jabatan(), $u->program?->name])->filter()->join(' · ');
@endphp
<a href="{{ $u->profileUrl() }}" class="fx-lect">
    <span class="fx-avatar" aria-hidden="true">{{ $ini }}</span>
    <span class="fx-lect-body">
        <strong>{{ $u->name }}</strong>
        <small>{{ $peran ?: 'Dosen Fakultas Ilmu Komputer' }}</small>
        @if($u->tracksAsPic->isNotEmpty())
            <span class="fx-lect-tags">@foreach($u->tracksAsPic as $t)<em>{{ $t->name }}</em>@endforeach</span>
        @endif
        <span class="fx-lect-stats"><b>{{ $u->karya_count }}</b> karya terbit · <b>{{ $u->mahasiswa_count }}</b> mahasiswa</span>
    </span>
</a>
