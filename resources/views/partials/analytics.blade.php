{{-- Google Analytics 4. Written by Blade, never by React: Node does not run it. Included only when App\Http\Analytics says so. --}}
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $id }}" nonce="{{ Vite::cspNonce() }}"></script>
<script nonce="{{ Vite::cspNonce() }}">
    window.dataLayer = window.dataLayer || [];
    function gtag() { dataLayer.push(arguments); }
    gtag('consent', 'default', {!! App\Http\Analytics::consentDefault() !!});
    gtag('js', new Date());
    gtag('config', @js($id), {!! App\Http\Analytics::configuration(request()) !!});
</script>
