<script>
(function () {
    var html = document.documentElement;
    function show() {
        html.classList.remove('dugsi-pending');
    }
    if (document.readyState === 'complete') {
        show();
    } else {
        window.addEventListener('load', show);
    }
    setTimeout(show, 2000);
})();
</script>
