{{--
    The tags of the head that PHP prints when Node did not render the page (it failed, or the visitor is signed in).
    Same tags, same data-inertia keys as resources/js/components/landing/seo-head.tsx, from the same `seo` prop:
    when the app starts, the client replaces or keeps each one by its key, so nothing is doubled. The golden file
    tests/Ssr/fixtures/seo-tags.json keeps the two in step.
--}}
@php($seo = $page['props']['seo'])
<title data-inertia="">{{ App\Http\DocumentTitle::for($seo['title'], $seo['siteName']) }}</title>
<meta data-inertia="description" name="description" content="{{ $seo['description'] }}">
<link data-inertia="canonical" rel="canonical" href="{{ $seo['canonical'] }}">
<meta data-inertia="og-url" property="og:url" content="{{ $seo['canonical'] }}">
<meta data-inertia="og-type" property="og:type" content="website">
<meta data-inertia="og-site-name" property="og:site_name" content="{{ $seo['siteName'] }}">
<meta data-inertia="og-title" property="og:title" content="{{ $seo['title'] }}">
<meta data-inertia="og-description" property="og:description" content="{{ $seo['description'] }}">
<meta data-inertia="og-locale" property="og:locale" content="{{ $seo['locale'] }}">
<meta data-inertia="og-image" property="og:image" content="{{ $seo['image']['url'] }}">
<meta data-inertia="og-image-width" property="og:image:width" content="{{ $seo['image']['width'] }}">
<meta data-inertia="og-image-height" property="og:image:height" content="{{ $seo['image']['height'] }}">
<meta data-inertia="og-image-alt" property="og:image:alt" content="{{ $seo['image']['alt'] }}">
<meta data-inertia="twitter-card" name="twitter:card" content="summary_large_image">
<meta data-inertia="twitter-title" name="twitter:title" content="{{ $seo['title'] }}">
<meta data-inertia="twitter-description" name="twitter:description" content="{{ $seo['description'] }}">
<meta data-inertia="twitter-image" name="twitter:image" content="{{ $seo['image']['url'] }}">
{{-- A data block, not code: the browser does not run it, and PageSeo escapes `<` and `>` so it cannot close the tag. --}}
<script data-inertia="json-ld" type="application/ld+json" @if ($nonce = Vite::cspNonce()) nonce="{{ $nonce }}" @endif>{!! $seo['jsonLd'] !!}</script>
