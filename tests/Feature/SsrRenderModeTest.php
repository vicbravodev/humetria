<?php

declare(strict_types=1);

use App\Http\Middleware\AnnounceRenderMode;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

mutates(AnnounceRenderMode::class);

beforeEach(function (): void {
    config(['inertia.ssr.enabled' => true, 'inertia.ssr.ensure_bundle_exists' => false]);
});

function nodeRendersFine(): void
{
    Http::fake(['http://127.0.0.1:13714/render' => Http::response(['head' => ['<title>x</title>'], 'body' => '<div>rendered</div>'])]);
}

it('says the page was rendered in Node', function (string $url): void {
    nodeRendersFine();

    $this->get($url)->assertOk()->assertHeader('X-Rendered-By', 'ssr');
})->with(['/', '/privacy-notice']);

it('says the browser renders it when Node answers with an error', function (): void {
    Http::fake(['http://127.0.0.1:13714/render' => Http::response(['error' => 'boom', 'type' => 'render'], 500)]);

    $this->get('/')->assertOk()->assertHeader('X-Rendered-By', 'csr');
});

it('says the browser renders it when Node is down', function (): void {
    Http::fake(['http://127.0.0.1:13714/render' => fn () => throw new ConnectionException('refused')]);

    $this->get('/')->assertOk()->assertHeader('X-Rendered-By', 'csr');
});

it('says the browser renders it while the switch is off', function (): void {
    config(['inertia.ssr.enabled' => false]);

    $this->get('/')->assertOk()->assertHeader('X-Rendered-By', 'csr');
});

it('says the browser renders it for someone signed in, even with Node rendering fine', function (): void {
    nodeRendersFine();

    $this->actingAs(User::factory()->create())->get('/')->assertOk()->assertHeader('X-Rendered-By', 'csr');
});

it('does not say anything on a route that is not on the list', function (string $url): void {
    nodeRendersFine();

    $this->get('/')->assertHeader('X-Rendered-By', 'ssr');
    $this->get($url)->assertHeaderMissing('X-Rendered-By');
})->with(['/admin/login', '/no-such-page']);
