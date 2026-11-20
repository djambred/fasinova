{{-- Paginasi situs publik bertema UEU. Dipanggil lewat $paginator->links('site.pagination'); $elements disediakan Laravel. --}}
@if ($paginator->hasPages())
<nav class="fx-pager" role="navigation" aria-label="Navigasi halaman">
    <span class="fx-pager-info">Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }}</span>
    <span class="fx-pager-links">
        @if ($paginator->onFirstPage())
            <span class="fx-pg off" aria-disabled="true">‹ Sebelumnya</span>
        @else
            <a class="fx-pg" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Sebelumnya</a>
        @endif
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="fx-pg gap">…</span>
            @else
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="fx-pg on" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="fx-pg num" href="{{ $url }}" aria-label="Halaman {{ $page }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach
        @if ($paginator->hasMorePages())
            <a class="fx-pg" href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya ›</a>
        @else
            <span class="fx-pg off" aria-disabled="true">Berikutnya ›</span>
        @endif
    </span>
</nav>
@endif
