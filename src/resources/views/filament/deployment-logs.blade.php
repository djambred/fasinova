<pre class="text-xs overflow-auto max-h-[60vh] p-3 rounded bg-gray-950 text-gray-100 whitespace-pre-wrap">@forelse($logs as $l)[{{ $l->created_at }}] {{ $l->step }}{{ $l->stream === 'err' ? ' !' : '' }}: {{ $l->line }}
@empty Belum ada log.
@endforelse</pre>
