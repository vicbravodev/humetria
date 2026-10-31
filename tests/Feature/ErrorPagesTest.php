<?php

declare(strict_types=1);

use App\Http\ErrorPage;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ErrorPage::class);

beforeEach(function (): void {
    config(['app.debug' => false]);
});

it('answers an unknown address with the app own 404 page, still a 404', function (): void {
    $this->get('/no-such-page')
        ->assertNotFound()
        // Said outright: with nosniff, a page without its type would show as plain text.
        ->assertHeader('Content-Type', 'text/html; charset=utf-8')
        ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', 404)
            // No route ran its middleware: the page still gets what every page shares.
            ->where('auth.user', null)
            ->where('tenant', null));
});

it('shows its own page for the errors a person can meet, each with its status', function (int $status): void {
    Route::middleware('web')->get('/test/abort', fn () => abort($status));

    $this->get('/test/abort')
        ->assertStatus($status)
        ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', $status)->where('survey', false));
})->with([403, 404, 419, 429, 500, 503]);

it('keeps saying when to try again on its page for too many attempts', function (): void {
    Route::middleware(['web', 'throttle:1,1'])->get('/test/limited', fn () => 'ok');

    $this->get('/test/limited')->assertOk();
    $this->get('/test/limited')
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', '60')
        ->assertHeader('X-RateLimit-Limit', '1')
        ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', 429));
});

it('tells the error page it speaks to someone answering the survey, in "usted"', function (): void {
    foreach (range(1, 30) as $attempt) {
        $this->get(route('survey.show', 'the-token'))->assertOk();
    }

    $this->get(route('survey.show', 'the-token'))
        ->assertTooManyRequests()
        ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', 429)->where('survey', true));
});

it('answers an Inertia visit with the page too, so it replaces the screen instead of a modal', function (): void {
    Route::middleware('web')->get('/test/forbidden', fn () => abort(403));

    $this->get('/test/forbidden', ['X-Inertia' => 'true', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request())])
        ->assertForbidden()
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'error')
        ->assertJsonPath('props.status', 403);
});

it('keeps what the signed-in page shares, so the 403 can say which role the person has', function (): void {
    Route::middleware('web')->get('/test/forbidden', fn () => abort(403));

    $this->actingAs(User::factory()->create(['name' => 'Rita RH']))
        ->get('/test/forbidden')
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('error')->where('auth.user.name', 'Rita RH'));
});

it('leaves a server error to the debug page while debugging', function (): void {
    config(['app.debug' => true]);
    Route::middleware('web')->get('/test/boom', fn () => throw new RuntimeException('boom'));

    $response = $this->get('/test/boom');

    $response->assertStatus(500);
    expect((string) $response->getContent())->not->toContain('"component":"error"')
        ->and((string) $response->getContent())->toContain('RuntimeException');
});

it('still shows a 404 page while debugging: it gives nothing away', function (): void {
    config(['app.debug' => true]);

    $this->get('/no-such-page')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', 404));
});

it('leaves JSON requests and the other statuses as they were', function (): void {
    Route::middleware('web')->get('/test/abort/{status}', fn (int $status) => abort($status));

    $this->getJson('/test/abort/404')->assertNotFound()->assertJsonStructure(['message']);
    $this->get('/test/abort/400')->assertStatus(400)->assertDontSee('data-page', escape: false);
});
