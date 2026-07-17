@props(['tabs' => [], 'active' => null])

{{--
  $tabs: list of ['key' => string, 'label' => string, 'href' => string]
--}}
<div {{ $attributes->merge(['class' => 'inline-flex flex-wrap gap-1 rounded-lg bg-slate-100 p-1']) }}>
    @foreach ($tabs as $tab)
        @php $isActive = ($active ?? '') === ($tab['key'] ?? ''); @endphp
        <a href="{{ $tab['href'] }}"
            class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors {{ $isActive ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
