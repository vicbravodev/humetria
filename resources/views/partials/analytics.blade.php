{{-- PostHog's configuration. Data, not code: written by Blade, never by React (Node does not run it), and read by resources/js/lib/analytics.ts. Included only when App\Http\Analytics says so. --}}
<script type="application/json" id="analytics-config">{!! App\Http\Analytics::configuration(request(), $token) !!}</script>
