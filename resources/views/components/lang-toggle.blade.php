@props(['route', 'lang'])

<div {{ $attributes->merge(['class' => 'dugsi-lang-toggle']) }}>
    <a href="{{ route($route, array_merge(request()->query(), ['lang' => 'so'])) }}"
        class="dugsi-lang-btn {{ $lang === 'so' ? 'is-active' : '' }}">SO</a>
    <a href="{{ route($route, array_merge(request()->query(), ['lang' => 'en'])) }}"
        class="dugsi-lang-btn {{ $lang === 'en' ? 'is-active' : '' }}">EN</a>
</div>
