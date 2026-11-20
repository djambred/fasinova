<x-filament-widgets::widget>
    @php
        $jam = now()->hour;
        $salam = $jam < 11 ? 'Selamat pagi' : ($jam < 15 ? 'Selamat siang' : ($jam < 18 ? 'Selamat sore' : 'Selamat malam'));
    @endphp

    <div class="fx-welcome">
        <h2>{{ $salam }}, {{ $nama }}</h2>
        <p>{{ $intro }}</p>
        @if($todo > 0)
            <a href="{{ $todoUrl }}" class="fx-todo">Perlu tindakan Anda <b>{{ $todo }}</b> →</a>
        @else
            <span class="fx-todo">Semua beres, tidak ada yang menunggu ✓</span>
        @endif
    </div>

    <div class="fx-flow">
        @foreach($steps as $n => [$judul, $v, $ket, $url, $mine])
            <a href="{{ $url }}" class="fx-step {{ $mine && $v > 0 ? 'hot' : '' }}">
                <small><i>{{ $n + 1 }}</i>{{ $judul }}</small>
                <strong>{{ $v }}</strong>
                <span>{{ $ket }}</span>
            </a>
        @endforeach
    </div>
</x-filament-widgets::widget>
