@props(['items'])

<nav class="flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
    @foreach ($items as $i => $item)
        @if ($i > 0)
            <span class="text-slate-300">›</span>
        @endif
        @if (! empty($item['url']))
            <a href="{{ $item['url'] }}" class="hover:text-dugsi-primary hover:underline">{{ $item['label'] }}</a>
        @else
            <span class="font-medium text-slate-700">{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
