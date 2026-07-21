{{--
    Prevent flash of unstyled HTML (FOUC) on slow hosts.
    Page stays on a solid background until stylesheets finish, then appears fully styled.
--}}
@php
    $antiFoucBg = $antiFoucBg ?? '#f1f5f9';
@endphp
<style>
    html { background: {{ $antiFoucBg }}; }
    html.dugsi-pending body { visibility: hidden !important; }
</style>
<script>
    document.documentElement.classList.add('dugsi-pending');
</script>
