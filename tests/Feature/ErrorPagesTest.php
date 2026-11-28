<?php

declare(strict_types=1);

use App\Http\ErrorPage;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Support\SessionKey;
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
    // Frozen: a second ticking between the two visits would make the wait 59.
    $this->freezeSecond();

    $this->get('/test/limited')->assertOk();
    $this->get('/test/limited')
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', '60')
        ->assertHeader('X-RateLimit-Limit', '1')
        ->assertHeader('X-RateLimit-Remaining', '0')
        ->assertHeader('X-RateLimit-Reset', (string) now()->addSeconds(60)->getTimestamp())
        ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', 429));
});

it('keeps a response a limiter built on purpose, with its own words', function (): void {
    RateLimiter::for('test-own-words', fn (): Limit => Limit::perMinute(1)->response(
        fn (): Illuminate\Http\Response => response('Recibimos demasiadas solicitudes.', 429),
    ));
    Route::middleware(['web', 'throttle:test-own-words'])->get('/test/own-limit', fn () => 'ok');

    $this->get('/test/own-limit')->assertOk();
    $this->get('/test/own-limit')
        ->assertTooManyRequests()
        ->assertSee('Recibimos demasiadas solicitudes.')
        ->assertDontSee('data-page', escape: false);
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

// E-12: whoever opens their own link (portal, page of the people, time clock) is spoken to as «usted», on a page and in a toast.
it('speaks «usted» on the error page of every page a worker opens with a link', function (string $name): void {
    Route::middleware('web')->get('/test/worker-gone', fn () => abort(404))->name($name);

    $this->get('/test/worker-gone')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('error')->where('survey', true));
})->with([
    'portal.vacations.show', 'workers.show', 'attendance.device.kiosk.show', 'portal.team.show', 'survey.show',
    'engagement.survey.show', 'complaints.public.create', 'complaints.follow-up.show', 'worker.enter',
]);

it('says which error page is of the time clock screen, for it to ask RH for a new link (hallazgo 24)', function (string $name, bool $screen): void {
    Route::middleware('web')->get('/test/screen-gone', fn () => abort(404))->name($name);

    $this->get('/test/screen-gone')->assertNotFound()->assertInertia(fn (Assert $page) => $page->where('screen', $screen));
})->with([['attendance.device.kiosk.show', true], ['attendance.device.kiosk.activate', true], ['attendance.device.punch.show', false], ['portal.home', false]]);

it('answers a throttled send from the portal with a toast in «usted»', function (): void {
    Route::middleware(['web', 'throttle:1,1'])->post('/test/portal-send', fn () => 'ok')->name('portal.test.send');
    $headers = ['X-Inertia' => 'true', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request())];

    $this->post('/test/portal-send', [], $headers)->assertOk();
    $this->post('/test/portal-send', [], $headers)
        ->assertRedirect()
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'error', 'message' => 'Demasiados intentos. Espere un minuto y vuelva a intentarlo.']]);
});

it('shows its page even when what every page shares cannot be read (the database is down)', function (): void {
    Route::middleware('web')->get('/test/database-down', function (): never {
        Inertia::share('inCourse', fn () => throw new RuntimeException('SQLSTATE[08006] connection refused'));

        abort(500);
    });

    $this->get('/test/database-down')
        ->assertStatus(500)
        ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', 500)
            ->where('auth.user', null)
            ->where('tenant', null)
            ->where('inCourse', null)
            ->where('demo', false)
            ->where('requestId', fn (?string $id): bool => is_string($id) && Str::isUuid($id)));
});

it('gives a server error the id of the request, the one in X-Request-Id, so a person can quote it', function (): void {
    Route::middleware('web')->get('/test/failed-id', fn () => abort(503));

    $response = $this->get('/test/failed-id')->assertServiceUnavailable();

    $response->assertInertia(fn (Assert $page) => $page->where('requestId', $response->headers->get('X-Request-Id')));
});

it('gives no request id to an error the person caused: two equal pages must stay equal', function (int $status): void {
    Route::middleware('web')->get('/test/own-error', fn () => abort($status));

    $this->get('/test/own-error')->assertStatus($status)
        ->assertInertia(fn (Assert $page) => $page->where('requestId', null));
})->with([403, 404, 419, 429]);
